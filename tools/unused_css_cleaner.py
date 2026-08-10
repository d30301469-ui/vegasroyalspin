#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
VegasRoyalSpin — Unused CSS Detector & Cleaner
==============================================

Tespit eder:
  1) Hiç referans edilmeyen CSS dosyaları
  2) Kullanılmayan / düşük güvenli CSS seçicileri (class / id)
  3) Bak / duplicate / orphan adayları

Varsayılan: dry-run (silmez). Temizlik için --apply.

Örnekler:
  python tools/unused_css_cleaner.py
  python tools/unused_css_cleaner.py --json
  python tools/unused_css_cleaner.py --apply --files-only
  python tools/unused_css_cleaner.py --apply --rules --min-confidence high
  python tools/unused_css_cleaner.py --include-ref --report tools/reports/unused-css.md

Notlar:
  - Dinamik sınıf üretimleri (JS concat, PHP) nedeniyle rule temizliği
    varsayılan olarak kapalıdır; --rules ile açılır.
  - _ref/, vendor/, node_modules/ tarama dışı (opsiyonel --include-ref).
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import re
import shutil
import sys
from collections import defaultdict
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Iterable

# ── Paths ──────────────────────────────────────────────────────────
ROOT = Path(__file__).resolve().parent.parent
REPORT_DIR = ROOT / "tools" / "reports"
QUARANTINE_DIR = ROOT / "tools" / "css_quarantine"

CSS_SCAN_DIRS = [
    ROOT / "assets" / "css",
    ROOT / "mobile" / "assets" / "css",
    ROOT / "assets",  # e.g. sports-icon.css at assets root
]

CODE_EXTS = {
    ".php", ".html", ".htm", ".phtml", ".js", ".mjs", ".cjs",
    ".ts", ".tsx", ".jsx", ".vue", ".twig", ".blade.php",
    ".json", ".md", ".txt", ".yml", ".yaml", ".xml", ".svg",
}

EXCLUDE_DIR_NAMES = {
    ".git", ".svn", ".hg", "node_modules", "vendor", ".venv",
    "__pycache__", "storage", "logs", "cache", "uploads", "upload",
    "database", "bin", ".cursor", ".github",
    "_shots", "Crashpad",
}

# When indexing code references, still skip heavy/non-product trees:
SKIP_REF_DIR_NAMES = {
    "tools", "tools/reports", "tools/css_quarantine",
}

# Always keep these even if string-match misses (critical / dynamically composed)
ALWAYS_KEEP_FILES = {
    "assets/css/site-global.css",
    "assets/css/site-bootstrap-utils.css",
    "assets/css/site-components.css",
    "assets/css/site-responsive.css",
    "assets/css/layout-header.css",
    "assets/css/profile.css",
    "assets/css/profile-cm622.css",
    "assets/css/profile-cm622-fix.css",
    "assets/css/site-modal.css",
    "assets/css/layout-sidebar.css",
    "assets/css/mobile-base.css",
    "assets/css/mobile-header.css",
}

# Selectors / prefixes that are almost always runtime-dynamic
DYNAMIC_CLASS_PREFIXES = (
    "bc-i-", "fa-", "fas ", "far ", "fab ", "swiper-", "toastify",
    "iziToast", "toastr", "is-", "has-", "js-", "data-",
)

PSEUDO_OR_SPECIAL = re.compile(
    r"(:{1,2}[a-zA-Z-]+)|(\[[^\]]+\])|(@[a-zA-Z-]+)",
    re.I,
)

# ── Data models ────────────────────────────────────────────────────
@dataclass
class CssFileInfo:
    rel: str
    path: str
    size: int
    sha1: str
    referenced: bool = False
    reference_hits: list[str] = field(default_factory=list)
    keep_forced: bool = False
    is_backup: bool = False
    duplicate_of: str | None = None


@dataclass
class SelectorHit:
    selector: str
    kind: str  # class | id | other
    token: str
    file: str
    confidence: str  # high | medium | low
    used: bool
    reason: str = ""


@dataclass
class Report:
    generated_at: str
    root: str
    mode: str
    css_files_total: int = 0
    unused_files: list[dict] = field(default_factory=list)
    backup_files: list[dict] = field(default_factory=list)
    duplicate_files: list[dict] = field(default_factory=list)
    unused_selectors: list[dict] = field(default_factory=list)
    kept_files: list[str] = field(default_factory=list)
    actions: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    stats: dict = field(default_factory=dict)


# ── Helpers ────────────────────────────────────────────────────────
def rel_posix(path: Path) -> str:
    try:
        return path.resolve().relative_to(ROOT).as_posix()
    except ValueError:
        return path.as_posix()


def should_skip_dir(path: Path, include_ref: bool) -> bool:
    name = path.name
    if name in EXCLUDE_DIR_NAMES:
        return True
    if name == "tools":
        return True
    if name == "_ref" and not include_ref:
        return True
    if name == "admin" and "vendor" in path.parts:
        return True
    return False


def read_text(path: Path) -> str:
    for enc in ("utf-8", "utf-8-sig", "cp1254", "latin-1"):
        try:
            return path.read_text(encoding=enc)
        except UnicodeDecodeError:
            continue
        except OSError:
            return ""
    return path.read_text(encoding="utf-8", errors="replace")


def sha1_file(path: Path) -> str:
    h = hashlib.sha1()
    with path.open("rb") as fh:
        for chunk in iter(lambda: fh.read(1024 * 256), b""):
            h.update(chunk)
    return h.hexdigest()


def iter_code_files(include_ref: bool) -> Iterable[Path]:
    for dirpath, dirnames, filenames in os.walk(ROOT):
        p = Path(dirpath)
        # prune
        dirnames[:] = [
            d for d in dirnames
            if not should_skip_dir(p / d, include_ref)
        ]
        for name in filenames:
            fp = p / name
            # skip huge / binary-ish
            if fp.suffix.lower() not in CODE_EXTS and fp.suffix.lower() != ".css":
                continue
            # skip quarantine + reports
            rel = rel_posix(fp)
            if rel.startswith("tools/css_quarantine/") or rel.startswith("tools/reports/"):
                continue
            yield fp


def collect_css_files(include_ref: bool, include_admin: bool) -> list[Path]:
    found: list[Path] = []
    seen: set[str] = set()

    candidates: list[Path] = []
    for base in CSS_SCAN_DIRS:
        if not base.is_dir():
            continue
        if base.name == "assets" and (base / "css").is_dir() and base == (ROOT / "assets"):
            for fp in base.glob("*.css"):
                candidates.append(fp)
            continue
        candidates.extend(base.rglob("*.css"))
        # Backup leftovers: foo.css.bak, foo.css.bak-authfix, foo.css.old
        candidates.extend(base.rglob("*.css.bak*"))
        candidates.extend(base.rglob("*.css.old"))
        candidates.extend(base.rglob("*.css.orig"))
        candidates.extend(base.rglob("*.css.copy"))

    if include_admin:
        admin_css = ROOT / "admin"
        if admin_css.is_dir():
            candidates.extend(admin_css.rglob("*.css"))

    if include_ref:
        ref = ROOT / "_ref"
        if ref.is_dir():
            candidates.extend(ref.rglob("*.css"))

    for fp in candidates:
        if not fp.is_file():
            continue
        rel = rel_posix(fp)
        if rel in seen:
            continue
        seen.add(rel)
        found.append(fp)
    return sorted(found, key=lambda x: rel_posix(x))


# ── Reference detection (files) ────────────────────────────────────
REF_PATTERNS = [
    # href="/assets/css/foo.css" or '...'
    re.compile(r"""(?:href|src)\s*=\s*['"]([^'"]+\.css)(?:\?[^'"]*)?['"]""", re.I),
    # @import url(...) / @import "..."
    re.compile(r"""@import\s+(?:url\()?['"]?([^'")\s]+\.css)""", re.I),
    # PHP string literals mentioning css paths
    re.compile(r"""['"]([^'"]*assets/css/[^'"]+\.css)['"]""", re.I),
    re.compile(r"""['"]([^'"]*mobile/assets/css/[^'"]+\.css)['"]""", re.I),
    # basename only in dynamic builders: 'bc-cm622-slots.css'
    re.compile(r"""['"]([a-zA-Z0-9._-]+\.css)['"]"""),
    # versionedAsset('assets/css/...')
    re.compile(r"""versionedAsset\(\s*['"]([^'"]+\.css)['"]""", re.I),
    re.compile(r"""mobileVersionedUrl\(\s*['"]([^'"]+\.css)['"]""", re.I),
]


def normalize_css_ref(raw: str, from_file: Path | None = None) -> list[str]:
    """Return possible relative project paths for a CSS reference."""
    s = raw.strip().replace("\\", "/")
    if s.startswith(("http://", "https://", "//")):
        return []
    s = s.split("?", 1)[0].split("#", 1)[0]
    while s.startswith("./"):
        s = s[2:]
    out: list[str] = []
    if s.startswith("/"):
        s2 = s.lstrip("/")
        out.append(s2)
    else:
        out.append(s)
        if from_file is not None:
            try:
                joined = (from_file.parent / s).resolve()
                out.append(rel_posix(joined))
            except OSError:
                pass
    # basename fallback key
    out.append(Path(s).name)
    # unique preserve order
    seen: set[str] = set()
    uniq: list[str] = []
    for x in out:
        x = x.replace("\\", "/")
        if x and x not in seen:
            seen.add(x)
            uniq.append(x)
    return uniq


def build_reference_index(include_ref: bool) -> dict[str, list[str]]:
    """Map css basename / rel path -> list of referencing files."""
    index: dict[str, list[str]] = defaultdict(list)
    for fp in iter_code_files(include_ref):
        # Don't count a CSS file as referencing itself for "used" via path string in comments only —
        # but @import inside CSS should count.
        text = read_text(fp)
        if not text:
            continue
        rel = rel_posix(fp)
        for pat in REF_PATTERNS:
            for m in pat.finditer(text):
                raw = m.group(1)
                for key in normalize_css_ref(raw, fp if fp.suffix.lower() == ".css" else None):
                    if rel not in index[key]:
                        index[key].append(rel)
    return index


def file_is_referenced(info: CssFileInfo, index: dict[str, list[str]]) -> tuple[bool, list[str]]:
    keys = {info.rel, Path(info.rel).name}
    # also without leading folders variants
    if info.rel.startswith("assets/css/"):
        keys.add(info.rel[len("assets/"):])  # css/foo.css sometimes
    hits: list[str] = []
    for k in keys:
        for ref in index.get(k, []):
            # ignore self-reference only
            if ref == info.rel:
                continue
            if ref not in hits:
                hits.append(ref)
    return (len(hits) > 0, hits[:30])


# ── CSS selector parsing (lightweight) ─────────────────────────────
COMMENT_RE = re.compile(r"/\*.*?\*/", re.S)
STRING_RE = re.compile(r"(\"([^\"\\]|\\.)*\"|'([^'\\]|\\.)*')", re.S)


def strip_css_noise(css: str) -> str:
    css = COMMENT_RE.sub("", css)
    # keep strings as empty to avoid false selector tokens inside urls/content
    css = STRING_RE.sub('""', css)
    return css


def split_top_level_blocks(css: str) -> list[tuple[str, str]]:
    """
    Return list of (prelude, body) for top-level rule blocks.
    Handles simple nesting of braces; not a full CSS parser.
    """
    blocks: list[tuple[str, str]] = []
    i = 0
    n = len(css)
    buf: list[str] = []
    while i < n:
        ch = css[i]
        if ch == "{":
            prelude = "".join(buf).strip()
            depth = 1
            i += 1
            body_chars: list[str] = []
            while i < n and depth:
                c2 = css[i]
                if c2 == "{":
                    depth += 1
                elif c2 == "}":
                    depth -= 1
                    if depth == 0:
                        i += 1
                        break
                if depth:
                    body_chars.append(c2)
                i += 1
            blocks.append((prelude, "".join(body_chars)))
            buf = []
            continue
        buf.append(ch)
        i += 1
    return blocks


CLASS_TOKEN_RE = re.compile(r"\.(-?[_a-zA-Z]+[_a-zA-Z0-9-]*)")
ID_TOKEN_RE = re.compile(r"#(-?[_a-zA-Z]+[_a-zA-Z0-9-]*)")


def extract_selector_tokens(prelude: str) -> list[tuple[str, str, str]]:
    """
    From a rule prelude, extract (kind, token, full_selector_chunk).
    Skips @rules except we still walk nested later.
    """
    prelude = prelude.strip()
    if not prelude or prelude.startswith("@"):
        return []
    out: list[tuple[str, str, str]] = []
    for chunk in prelude.split(","):
        chunk = chunk.strip()
        if not chunk:
            continue
        for m in CLASS_TOKEN_RE.finditer(chunk):
            out.append(("class", m.group(1), chunk))
        for m in ID_TOKEN_RE.finditer(chunk):
            out.append(("id", m.group(1), chunk))
    return out


def confidence_for_token(kind: str, token: str, selector: str) -> str:
    if kind == "other":
        return "low"
    if any(token.startswith(p.rstrip("- ")) or token.startswith(p) for p in DYNAMIC_CLASS_PREFIXES):
        return "low"
    if PSEUDO_OR_SPECIAL.search(selector):
        return "medium"
    if "*" in selector or ">" in selector or "+" in selector or "~" in selector:
        return "medium"
    if len(token) <= 2:
        return "low"
    # utility-ish short tokens
    if re.fullmatch(r"[a-z]{1,3}\d*", token):
        return "low"
    return "high"


# ── Usage corpus (classes / ids in code) ───────────────────────────
def build_usage_corpus(include_ref: bool) -> tuple[set[str], set[str], set[str]]:
    """
    Returns (class_tokens, id_tokens, loose_tokens).
    loose_tokens covers dynamic class fragments without O(n*m) haystack regex.
    """
    classes: set[str] = set()
    ids: set[str] = set()
    loose: set[str] = set()

    class_attr = re.compile(
        r"""(?:class|className)\s*=\s*['"]([^'"]+)['"]""",
        re.I,
    )
    class_list_js = re.compile(
        r"""classList\s*\.\s*(?:add|remove|toggle|contains)\s*\(\s*['"]([^'"]+)['"]""",
        re.I,
    )
    id_attr = re.compile(r"""\bid\s*=\s*['"]([^'"]+)['"]""", re.I)
    by_id = re.compile(r"""getElementById\(\s*['"]([^'"]+)['"]\s*\)""")
    qsa = re.compile(r"""querySelector(?:All)?\(\s*['"]([^'"]+)['"]\s*\)""")
    dashed = re.compile(r"""['"]([a-zA-Z][a-zA-Z0-9_-]{2,80})['"]""")
    ident = re.compile(r"""[a-zA-Z_][a-zA-Z0-9_-]{2,80}""")

    skip_name_substrings = (
        ".min.js", "swiper-bundle", "chart", "fullcalendar", "vendor-",
        "runtime.js", "vendors.js",
    )

    for fp in iter_code_files(include_ref):
        if fp.suffix.lower() == ".css":
            continue
        name_l = fp.name.lower()
        if any(s in name_l for s in skip_name_substrings):
            continue
        try:
            size = fp.stat().st_size
        except OSError:
            continue
        if size > 1_500_000:
            continue
        text = read_text(fp)
        if not text:
            continue
        if len(text) > 1_500_000:
            text = text[:1_500_000]

        for pat in (class_attr, class_list_js):
            for m in pat.finditer(text):
                for tok in re.split(r"\s+", m.group(1).strip()):
                    tok = tok.strip(".#[]")
                    if tok:
                        classes.add(tok)
                        loose.add(tok)

        for m in id_attr.finditer(text):
            ids.add(m.group(1).strip())
            loose.add(m.group(1).strip())
        for m in by_id.finditer(text):
            ids.add(m.group(1).strip())
            loose.add(m.group(1).strip())

        for m in qsa.finditer(text):
            sel = m.group(1)
            for cm in CLASS_TOKEN_RE.finditer(sel):
                classes.add(cm.group(1))
                loose.add(cm.group(1))
            for im in ID_TOKEN_RE.finditer(sel):
                ids.add(im.group(1))
                loose.add(im.group(1))

        if fp.suffix.lower() in {".js", ".php", ".html", ".htm", ".phtml"}:
            for m in dashed.finditer(text):
                tok = m.group(1)
                loose.add(tok)
                if "-" in tok or tok[:1].islower():
                    classes.add(tok)
            if size < 400_000:
                for m in ident.finditer(text):
                    loose.add(m.group(0))

    return classes, ids, loose


def token_used(
    kind: str,
    token: str,
    classes: set[str],
    ids: set[str],
    loose: set[str],
) -> tuple[bool, str]:
    if kind == "class":
        if token in classes:
            return True, "exact-class"
        if token in loose:
            return True, "loose-token"
        return False, "not-found"
    if kind == "id":
        if token in ids:
            return True, "exact-id"
        if token in loose:
            return True, "loose-token"
        return False, "not-found"
    return True, "other-kept"


# ── Rule stripping (optional apply) ────────────────────────────────
def remove_unused_rules_from_css(
    css_text: str,
    unused_tokens: set[tuple[str, str]],
) -> tuple[str, int]:
    """
    Remove top-level style rules whose ALL class/id tokens are in unused_tokens.
    Keeps @rules intact (recurses into plain nested style rules lightly).
    """
    css = css_text
    # Work on stripped comments only for analysis, but rewrite from original carefully:
    # Safer approach: rebuild from blocks of stripped version mapping is hard;
    # instead operate on comment-stripped CSS for rewrite output.
    work = strip_css_noise(css)
    blocks = split_top_level_blocks(work)
    kept_parts: list[str] = []
    removed = 0

    # Preserve @charset / leftover text before first block approximately:
    first_brace = work.find("{")
    prefix = work[:first_brace].rsplit("}", 1)[-1] if first_brace != -1 else work
    # Actually rebuild entirely from blocks:
    rebuilt: list[str] = []
    # leading non-block content
    leading = re.split(r"\{", work, maxsplit=1)[0]
    # leading may contain previous incomplete — use empty and only blocks
    for prelude, body in blocks:
        p = prelude.strip()
        if not p:
            continue
        if p.startswith("@"):
            rebuilt.append(f"{p} {{{body}}}")
            continue
        tokens = extract_selector_tokens(p)
        class_id = [(k, t) for (k, t, _) in tokens if k in ("class", "id")]
        if class_id and all((k, t) in unused_tokens for (k, t) in class_id):
            removed += 1
            continue
        rebuilt.append(f"{p} {{{body}}}")

    new_css = "\n\n".join(rebuilt) + ("\n" if rebuilt else "")
    return new_css, removed


# ── Main analysis ──────────────────────────────────────────────────
def analyze(args: argparse.Namespace) -> Report:
    mode = "apply" if args.apply else "dry-run"
    report = Report(
        generated_at=datetime.now(timezone.utc).isoformat(),
        root=str(ROOT),
        mode=mode,
    )

    css_files = collect_css_files(args.include_ref, args.include_admin)
    report.css_files_total = len(css_files)
    report.warnings.append(
        f"Scanned {len(css_files)} CSS files "
        f"(ref={args.include_ref}, admin={args.include_admin})"
    )

    print(f"[1/4] Building reference index…")
    ref_index = build_reference_index(args.include_ref)

    print(f"[2/4] Classifying CSS files…")
    by_hash: dict[str, list[CssFileInfo]] = defaultdict(list)
    infos: list[CssFileInfo] = []

    for fp in css_files:
        rel = rel_posix(fp)
        digest = sha1_file(fp)
        info = CssFileInfo(
            rel=rel,
            path=str(fp),
            size=fp.stat().st_size,
            sha1=digest,
            keep_forced=rel in ALWAYS_KEEP_FILES,
            is_backup=bool(re.search(r"\.(bak|old|orig|copy)(\.|$)", fp.name, re.I)
                           or ".bak-" in fp.name
                           or fp.name.endswith(".bak")),
        )
        used, hits = file_is_referenced(info, ref_index)
        info.referenced = used or info.keep_forced
        info.reference_hits = hits
        infos.append(info)
        by_hash[digest].append(info)

    # duplicates
    for digest, group in by_hash.items():
        if len(group) < 2:
            continue
        # keep the one that is referenced, else shortest path
        group_sorted = sorted(
            group,
            key=lambda g: (not g.referenced, len(g.rel), g.rel),
        )
        primary = group_sorted[0]
        for other in group_sorted[1:]:
            other.duplicate_of = primary.rel
            report.duplicate_files.append({
                "file": other.rel,
                "duplicate_of": primary.rel,
                "size": other.size,
                "sha1": other.sha1,
                "referenced": other.referenced,
            })

    unused_files = []
    for info in infos:
        if info.is_backup:
            report.backup_files.append(asdict(info))
        if info.referenced:
            report.kept_files.append(info.rel)
            continue
        # unused file candidate
        unused_files.append(info)
        report.unused_files.append({
            "file": info.rel,
            "size": info.size,
            "sha1": info.sha1,
            "is_backup": info.is_backup,
            "duplicate_of": info.duplicate_of,
            "reason": "no-reference" + (";backup" if info.is_backup else ""),
        })

    print(f"[3/4] Building usage corpus for selectors…")
    classes, ids, loose = build_usage_corpus(args.include_ref)
    report.stats = {
        "css_files": len(infos),
        "referenced_files": sum(1 for i in infos if i.referenced),
        "unused_files": len(unused_files),
        "backup_files": len(report.backup_files),
        "duplicate_groups": len({d["sha1"] for d in report.duplicate_files}),
        "class_tokens_in_code": len(classes),
        "id_tokens_in_code": len(ids),
        "loose_tokens_in_code": len(loose),
    }

    unused_selector_rows: list[SelectorHit] = []
    if args.scan_rules:
        print(f"[4/4] Scanning selectors in referenced CSS…")
        for info in infos:
            if not info.referenced:
                continue
            if info.rel.startswith("_ref/"):
                continue
            text = read_text(Path(info.path))
            work = strip_css_noise(text)
            seen_local: set[tuple[str, str]] = set()
            for prelude, _body in split_top_level_blocks(work):
                if prelude.strip().startswith("@"):
                    # dive into @media / @supports bodies
                    if prelude.strip().lower().startswith(("@media", "@supports", "@layer", "@container")):
                        for p2, _b2 in split_top_level_blocks(_body):
                            for kind, token, chunk in extract_selector_tokens(p2):
                                key = (kind, token)
                                if key in seen_local:
                                    continue
                                seen_local.add(key)
                                conf = confidence_for_token(kind, token, chunk)
                                used, reason = token_used(kind, token, classes, ids, loose)
                                if not used:
                                    unused_selector_rows.append(SelectorHit(
                                        selector=chunk, kind=kind, token=token,
                                        file=info.rel, confidence=conf, used=False, reason=reason,
                                    ))
                    continue
                for kind, token, chunk in extract_selector_tokens(prelude):
                    key = (kind, token)
                    if key in seen_local:
                        continue
                    seen_local.add(key)
                    conf = confidence_for_token(kind, token, chunk)
                    used, reason = token_used(kind, token, classes, ids, loose)
                    if not used:
                        unused_selector_rows.append(SelectorHit(
                            selector=chunk, kind=kind, token=token,
                            file=info.rel, confidence=conf, used=False, reason=reason,
                        ))
    else:
        report.warnings.append("Selector scan skipped (pass --scan-rules to enable).")

    # filter by confidence
    conf_rank = {"high": 3, "medium": 2, "low": 1}
    min_rank = conf_rank.get(args.min_confidence, 3)
    filtered = [s for s in unused_selector_rows if conf_rank.get(s.confidence, 0) >= min_rank]
    report.unused_selectors = [asdict(s) for s in filtered]
    report.stats["unused_selectors_reported"] = len(filtered)
    report.stats["unused_selectors_raw"] = len(unused_selector_rows)

    # ── Apply actions ──────────────────────────────────────────────
    if args.apply:
        QUARANTINE_DIR.mkdir(parents=True, exist_ok=True)
        stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        batch = QUARANTINE_DIR / stamp
        batch.mkdir(parents=True, exist_ok=True)

        # files
        if not args.rules_only:
            for info in unused_files:
                if info.keep_forced or info.rel in ALWAYS_KEEP_FILES:
                    continue
                if not args.include_duplicates and info.duplicate_of and info.referenced:
                    continue
                # Safe default: only backups / bit-identical duplicates.
                # Unreferenced unique files need --aggressive.
                if not args.aggressive and not (info.is_backup or info.duplicate_of):
                    report.actions.append(f"SKIP (use --aggressive): {info.rel}")
                    continue
                src = Path(info.path)
                dest = batch / info.rel.replace("/", "__")
                dest.parent.mkdir(parents=True, exist_ok=True)
                if args.hard_delete:
                    src.unlink(missing_ok=True)
                    report.actions.append(f"DELETED {info.rel}")
                else:
                    shutil.move(str(src), str(dest))
                    report.actions.append(f"QUARANTINED {info.rel} -> {rel_posix(dest)}")

        # rules
        if args.rules:
            # group unused high-confidence tokens by file
            by_file: dict[str, set[tuple[str, str]]] = defaultdict(set)
            for row in filtered:
                if row.confidence != "high" and args.min_confidence == "high":
                    continue
                by_file[row.file].add((row.kind, row.token))
            for rel, tokens in by_file.items():
                fp = ROOT / rel
                if not fp.is_file():
                    continue
                original = read_text(fp)
                new_css, removed = remove_unused_rules_from_css(original, tokens)
                if removed <= 0:
                    continue
                backup = batch / (rel.replace("/", "__") + ".before.css")
                backup.write_text(original, encoding="utf-8")
                fp.write_text(new_css, encoding="utf-8")
                report.actions.append(
                    f"STRIPPED {removed} rules from {rel} (backup {rel_posix(backup)})"
                )
    else:
        report.actions.append("dry-run: no files modified")

    return report


def write_reports(report: Report, md_path: Path, json_path: Path) -> None:
    md_path.parent.mkdir(parents=True, exist_ok=True)
    json_path.write_text(
        json.dumps(asdict(report), ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    lines: list[str] = []
    lines.append(f"# Unused CSS Report")
    lines.append("")
    lines.append(f"- Generated: `{report.generated_at}`")
    lines.append(f"- Mode: **{report.mode}**")
    lines.append(f"- CSS files scanned: **{report.css_files_total}**")
    lines.append("")
    lines.append("## Stats")
    lines.append("```json")
    lines.append(json.dumps(report.stats, ensure_ascii=False, indent=2))
    lines.append("```")
    lines.append("")

    lines.append(f"## Unused CSS files ({len(report.unused_files)})")
    if not report.unused_files:
        lines.append("_None_")
    else:
        lines.append("| File | Size | Reason |")
        lines.append("|---|---:|---|")
        for u in sorted(report.unused_files, key=lambda x: -x["size"]):
            lines.append(
                f"| `{u['file']}` | {u['size']} | {u.get('reason','')} |"
            )
    lines.append("")

    lines.append(f"## Backup-named CSS ({len(report.backup_files)})")
    for b in report.backup_files:
        lines.append(f"- `{b['rel']}` ({b['size']} bytes)")
    lines.append("")

    lines.append(f"## Duplicate CSS ({len(report.duplicate_files)})")
    for d in report.duplicate_files:
        lines.append(
            f"- `{d['file']}` == `{d['duplicate_of']}` (sha1 `{d['sha1'][:10]}…`)"
        )
    lines.append("")

    lines.append(f"## Unused selectors ({len(report.unused_selectors)})")
    lines.append("_Showing up to 200 rows_")
    lines.append("")
    lines.append("| Confidence | Kind | Token | File | Selector |")
    lines.append("|---|---|---|---|---|")
    for s in report.unused_selectors[:200]:
        sel = s["selector"].replace("|", "\\|")[:80]
        lines.append(
            f"| {s['confidence']} | {s['kind']} | `{s['token']}` | `{s['file']}` | `{sel}` |"
        )
    if len(report.unused_selectors) > 200:
        lines.append(f"\n… +{len(report.unused_selectors) - 200} more (see JSON)")
    lines.append("")

    lines.append("## Actions")
    for a in report.actions:
        lines.append(f"- {a}")
    lines.append("")
    if report.warnings:
        lines.append("## Warnings")
        for w in report.warnings:
            lines.append(f"- {w}")

    md_path.write_text("\n".join(lines), encoding="utf-8")


def parse_args(argv: list[str] | None = None) -> argparse.Namespace:
    p = argparse.ArgumentParser(
        description="Detect and optionally clean unused CSS files/rules.",
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog=__doc__,
    )
    p.add_argument("--apply", action="store_true",
                   help="Apply cleanup (default quarantines files)")
    p.add_argument("--hard-delete", action="store_true",
                   help="With --apply, permanently delete instead of quarantine")
    p.add_argument("--aggressive", action="store_true",
                   help="Allow removing unreferenced non-backup files")
    p.add_argument("--files-only", action="store_true",
                   help="Only act on files (skip rule stripping even if --rules)")
    p.add_argument("--rules-only", action="store_true",
                   help="Only strip rules; do not move/delete files")
    p.add_argument("--rules", action="store_true",
                   help="Also strip unused high-confidence rules when --apply")
    p.add_argument("--scan-rules", action="store_true", default=True,
                   help="Scan selectors (default on)")
    p.add_argument("--no-scan-rules", action="store_false", dest="scan_rules",
                   help="Skip selector analysis (faster, files only)")
    p.add_argument("--min-confidence", choices=("high", "medium", "low"),
                   default="high", help="Minimum selector confidence to report/strip")
    p.add_argument("--include-ref", action="store_true",
                   help="Include _ref/ casinomilyon CSS in scan")
    p.add_argument("--include-admin", action="store_true",
                   help="Include admin/*.css")
    p.add_argument("--include-duplicates", action="store_true",
                   help="Treat referenced duplicates as removable too")
    p.add_argument("--report", type=str, default="",
                   help="Markdown report path (default tools/reports/unused-css-*.md)")
    p.add_argument("--json", action="store_true",
                   help="Print JSON summary to stdout")
    p.add_argument("--json-out", type=str, default="",
                   help="JSON report path")
    return p.parse_args(argv)


def main(argv: list[str] | None = None) -> int:
    args = parse_args(argv)
    if args.files_only:
        args.rules = False
    if args.hard_delete and not args.apply:
        print("--hard-delete requires --apply", file=sys.stderr)
        return 2

    REPORT_DIR.mkdir(parents=True, exist_ok=True)
    stamp = datetime.now().strftime("%Y%m%d_%H%M%S")
    md_path = Path(args.report) if args.report else (REPORT_DIR / f"unused-css-{stamp}.md")
    if not md_path.is_absolute():
        md_path = ROOT / md_path
    json_path = Path(args.json_out) if args.json_out else (REPORT_DIR / f"unused-css-{stamp}.json")
    if not json_path.is_absolute():
        json_path = ROOT / json_path

    print(f"Root: {ROOT}")
    print(f"Mode: {'APPLY' if args.apply else 'DRY-RUN'}")
    report = analyze(args)
    write_reports(report, md_path, json_path)

    print("")
    print("=== Summary ===")
    print(f"CSS files:          {report.stats.get('css_files')}")
    print(f"Referenced:         {report.stats.get('referenced_files')}")
    print(f"Unused files:       {report.stats.get('unused_files')}")
    print(f"Backup-named:       {report.stats.get('backup_files')}")
    print(f"Unused selectors:   {report.stats.get('unused_selectors_reported')} "
          f"(raw {report.stats.get('unused_selectors_raw')})")
    print(f"Report (md):        {rel_posix(md_path)}")
    print(f"Report (json):      {rel_posix(json_path)}")
    if report.unused_files:
        print("\nTop unused files:")
        for u in sorted(report.unused_files, key=lambda x: -x["size"])[:15]:
            print(f"  - {u['file']} ({u['size']} bytes)")
    if args.apply:
        print(f"\nActions: {len(report.actions)}")
        for a in report.actions[:40]:
            print(f"  {a}")
    else:
        print("\nDry-run only. Examples:")
        print("  # Quarantine backup/duplicate unused CSS:")
        print("  python tools/unused_css_cleaner.py --apply")
        print("  # Also remove other unreferenced files:")
        print("  python tools/unused_css_cleaner.py --apply --aggressive")
        print("  # Strip high-confidence unused rules:")
        print("  python tools/unused_css_cleaner.py --apply --rules --min-confidence high")

    if args.json:
        print(json.dumps(asdict(report), ensure_ascii=False, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
