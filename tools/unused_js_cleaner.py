#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
VegasRoyalSpin — Unused JavaScript Detector & Cleaner
=====================================================

Tespit eder:
  1) Script/loader ile yüklenmeyen JS dosyaları (yorum-only sayılmaz)
  2) .bak / .corrupt-bak / .old artıkları
  3) Bit-identical duplicate JS dosyaları
  4) (opsiyonel) Yüksek güvenli kullanılmayan top-level fonksiyonlar

Varsayılan: dry-run (silmez). Temizlik için --apply.
Silinen dosyalar tools/js_quarantine/<timestamp>/ altına taşınır.

Örnekler:
  python tools/unused_js_cleaner.py
  python tools/unused_js_cleaner.py --json
  python tools/unused_js_cleaner.py --apply --files-only
  python tools/unused_js_cleaner.py --apply --symbols --min-confidence high
  python tools/unused_js_cleaner.py --include-admin --report tools/reports/unused-js.md

Notlar:
  - Dinamik dosyalar (profile.js vb.) tools/js_manifests + @entry/@keep/@dynamic-file
    ve opsiyonel tools/reports/js-runtime-usage.json ile yönetilir.
  - Dinamik dosyalarda symbol strip varsayılan KAPALI (coverage_required).
  - Manifest üret: python tools/generate_js_manifests.py --report
  - Runtime probe: ?js_usage=1 → window.__JS_USAGE_DUMP__()
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

ROOT = Path(__file__).resolve().parent.parent
REPORT_DIR = ROOT / "tools" / "reports"
QUARANTINE_DIR = ROOT / "tools" / "js_quarantine"

JS_SCAN_DIRS = [
    ROOT / "assets" / "js",
    ROOT / "mobile" / "assets" / "js",
]

# Also scan these single files if present
EXTRA_JS_FILES = [
    ROOT / "service-worker.js",
    ROOT / "public" / "service-worker.js",
]

ADMIN_JS_GLOBS = [
    "admin-ui.js",
    "runtime.js",
    "2026.js",
    "vendors.js",
    "chartjs-bridge.js",
    "vendor-chartjs.js",
    "vendor-fullcalendar.js",
]

CODE_EXTS = {
    ".php", ".html", ".htm", ".phtml", ".js", ".mjs", ".cjs",
    ".ts", ".tsx", ".jsx", ".vue", ".twig",
    ".json", ".md", ".txt", ".yml", ".yaml", ".xml",
}

EXCLUDE_DIR_NAMES = {
    ".git", ".svn", ".hg", "node_modules", "vendor", ".venv",
    "__pycache__", "storage", "logs", "cache", "uploads", "upload",
    "database", "bin", ".cursor", ".github",
    "_shots", "Crashpad", "tools",
}

ALWAYS_KEEP_FILES = {
    "assets/js/global.js",
    "assets/js/header.js",
    "assets/js/profile.js",
    "assets/js/auth-shared.js",
    "assets/js/modal-polyfill.js",
    "assets/js/toastify-helper.js",
    "assets/js/js-usage-probe.js",
    "mobile/assets/js/navigation.js",
    "mobile/assets/js/mobile-header.js",
    "mobile/assets/js/profile-panel.js",
    "service-worker.js",
    "public/service-worker.js",
}

# Never strip symbols from these (too dynamic / critical)
SYMBOL_STRIP_DENY = {
    "assets/js/profile.js",
    "assets/js/slot.js",
    "assets/js/bgaming.js",
    "assets/js/header.js",
    "assets/js/home.js",
    "assets/js/login.js",
    "assets/js/register.js",
    "mobile/assets/js/navigation.js",
    "mobile/assets/js/mobile-header.js",
    "mobile/assets/js/profile-panel.js",
    "admin/admin-ui.js",
    "admin/2026.js",
    "admin/vendors.js",
    "admin/runtime.js",
}

MINIFIED_HINT = re.compile(r"(\.min\.js$)|(sourceMappingURL=)", re.I)

# Loader / reference patterns (must look like an actual load, not a prose comment)
LOADER_PATTERNS = [
    # <script ... src=".../file.js?...">
    re.compile(
        r"""<script\b[^>]*\bsrc\s*=\s*['"][^'"]*?(/|assets/|mobile/|admin/)?(?P<path>[^'"]*?\.js)(?:\?[^'"]*)?['"]""",
        re.I,
    ),
    # asset_url('assets/js/x.js') / versionedAsset('...') / mobileVersionedUrl('...')
    re.compile(
        r"""(?:asset_url|versionedAsset|mobileVersionedUrl|mobileAssetVer|\$ver)\s*\(\s*['"](?P<path>[^'"]+\.js)['"]""",
        re.I,
    ),
    # BASE_PATH . '/assets/js/foo.js'  OR  ".../assets/js/foo.js"
    re.compile(
        r"""(?:BASE_PATH|ADMIN_BASE_PATH|__DIR__)\s*\.?\s*(?:\.\s*)?['"](?P<path>/?(?:assets|mobile/assets|admin)/[^'"]+\.js)['"]""",
        re.I,
    ),
    re.compile(
        r"""['"](?P<path>/(?:assets|mobile/assets)/js/[^'"]+\.js)['"]""",
        re.I,
    ),
    re.compile(
        r"""['"](?P<path>(?:assets|mobile/assets)/js/[^'"]+\.js)['"]""",
        re.I,
    ),
    # AdminAuth::url('/admin-ui.js') / install_asset('/runtime.js')
    re.compile(
        r"""(?:AdminAuth::url|install_asset)\s*\(\s*['"](?P<path>/[^'"]+\.js)(?:\?[^'"]*)?['"]""",
        re.I,
    ),
    # import / dynamic import / createElement script.src =
    re.compile(
        r"""(?:import\s*\(|import\s+[^;]*?\s+from\s+|require\s*\()\s*['"](?P<path>[^'"]+\.js)['"]""",
        re.I,
    ),
    re.compile(
        r"""\.src\s*=\s*['"](?P<path>[^'"]+\.js)(?:\?[^'"]*)?['"]""",
        re.I,
    ),
]

COMMENT_LINE = re.compile(r"^\s*(//|#|\*|/\*|\*/)")
PHP_COMMENT = re.compile(r"^\s*(//|#|\*|/\*)")

FN_DECL = re.compile(
    r"""(?:^|\n)(?P<indent>[ \t]*)(?:async\s+)?function\s+(?P<name>[A-Za-z_$][\w$]*)\s*\(""",
)
# window.foo = function / const foo = function / const foo = () =>
ASSIGN_FN = re.compile(
    r"""(?:^|\n)(?P<indent>[ \t]*)(?:window\.)?(?P<name>[A-Za-z_$][\w$]*)\s*=\s*(?:async\s+)?function\b""",
)
CONST_FN = re.compile(
    r"""(?:^|\n)(?P<indent>[ \t]*)(?:const|let|var)\s+(?P<name>[A-Za-z_$][\w$]*)\s*=\s*(?:async\s+)?(?:function\b|\([^)]*\)\s*=>|[A-Za-z_$][\w$]*\s*=>)""",
)

SAFE_SYMBOL_NAME = re.compile(r"^[A-Za-z][A-Za-z0-9_]{3,}$")
DYNAMIC_NAME_HINTS = (
    "init", "handle", "on", "bind", "render", "load", "fetch", "update",
    "toggle", "open", "close", "show", "hide", "click", "submit", "change",
)


@dataclass
class JsFileInfo:
    rel: str
    path: str
    size: int
    sha1: str
    referenced: bool = False
    reference_hits: list[str] = field(default_factory=list)
    keep_forced: bool = False
    is_backup: bool = False
    is_minified: bool = False
    duplicate_of: str | None = None
    load_score: int = 0  # stronger refs only


@dataclass
class SymbolHit:
    name: str
    kind: str
    file: str
    line: int
    confidence: str
    used: bool
    reason: str = ""


@dataclass
class Report:
    generated_at: str
    root: str
    mode: str
    js_files_total: int = 0
    unused_files: list[dict] = field(default_factory=list)
    backup_files: list[dict] = field(default_factory=list)
    duplicate_files: list[dict] = field(default_factory=list)
    unused_symbols: list[dict] = field(default_factory=list)
    kept_files: list[str] = field(default_factory=list)
    actions: list[str] = field(default_factory=list)
    warnings: list[str] = field(default_factory=list)
    stats: dict = field(default_factory=dict)


def rel_posix(path: Path) -> str:
    try:
        return path.resolve().relative_to(ROOT).as_posix()
    except ValueError:
        return path.as_posix()


def should_skip_dir(path: Path, include_ref: bool) -> bool:
    name = path.name
    if name in EXCLUDE_DIR_NAMES:
        return True
    if name == "_ref" and not include_ref:
        return True
    if "vendor" in path.parts:
        return True
    if "js_quarantine" in path.parts or "css_quarantine" in path.parts:
        return True
    if "backups" in path.parts and "tools" in path.parts:
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


def strip_comments_for_match(text: str, ext: str) -> str:
    """Rough comment stripper so prose mentions don't count as loads."""
    # block comments
    text = re.sub(r"/\*.*?\*/", " ", text, flags=re.S)
    out_lines = []
    for line in text.splitlines():
        if ext in {".php", ".js", ".mjs", ".cjs", ".ts", ".tsx", ".jsx"}:
            # keep strings roughly: cut // comments outside strings is hard;
            # remove obvious full-line comments and HTML comments
            if re.match(r"^\s*//", line) or re.match(r"^\s*\*", line):
                continue
            if re.match(r"^\s*#(?!\[)", line) and ext == ".php":
                continue
            # inline // comment: drop trailing if not URL
            if "//" in line and "://" not in line.split("//", 1)[0]:
                # naive: if odd number of quotes before //, keep
                before = line.split("//", 1)[0]
                if before.count('"') % 2 == 0 and before.count("'") % 2 == 0:
                    line = before
        if "<!--" in line:
            line = re.sub(r"<!--.*?-->", " ", line)
        out_lines.append(line)
    return "\n".join(out_lines)


def normalize_js_path(raw: str) -> str:
    p = raw.strip().lstrip("./")
    p = p.replace("\\", "/")
    if p.startswith("/"):
        p = p[1:]
    # AdminAuth::url('/admin-ui.js') → admin/admin-ui.js
    if re.fullmatch(r"[A-Za-z0-9_.-]+\.js", p):
        candidate = ROOT / "admin" / p
        if candidate.is_file():
            return f"admin/{p}"
        for base in ("assets/js", "mobile/assets/js"):
            c2 = ROOT / base / p
            if c2.is_file():
                return f"{base}/{p}"
    if p.startswith("admin/") is False and p in {
        "admin-ui.js", "runtime.js", "2026.js", "vendors.js",
        "chartjs-bridge.js", "vendor-chartjs.js", "vendor-fullcalendar.js",
    }:
        return f"admin/{p}"
    return p


def collect_js_files(include_admin: bool, include_ref: bool) -> list[Path]:
    found: list[Path] = []
    seen: set[str] = set()

    def add(fp: Path) -> None:
        if not fp.is_file():
            return
        key = str(fp.resolve())
        if key in seen:
            return
        seen.add(key)
        found.append(fp)

    for base in JS_SCAN_DIRS:
        if not base.is_dir():
            continue
        for fp in base.glob("*.js"):
            add(fp)
        for fp in base.glob("*.js.*"):
            # profile.js.corrupt-bak etc.
            if any(x in fp.name for x in (".bak", ".old", ".orig", ".corrupt", ".copy")):
                add(fp)

    for fp in EXTRA_JS_FILES:
        add(fp)

    if include_admin:
        admin = ROOT / "admin"
        for name in ADMIN_JS_GLOBS:
            add(admin / name)

    if include_ref:
        ref = ROOT / "_ref"
        if ref.is_dir():
            for fp in ref.rglob("*.js"):
                if should_skip_dir(fp.parent, True):
                    continue
                add(fp)

    return sorted(found, key=lambda p: rel_posix(p))


def iter_code_files(include_ref: bool) -> Iterable[Path]:
    for dirpath, dirnames, filenames in os.walk(ROOT):
        p = Path(dirpath)
        dirnames[:] = [d for d in dirnames if not should_skip_dir(p / d, include_ref)]
        for name in filenames:
            fp = p / name
            ext = fp.suffix.lower()
            if ext not in CODE_EXTS:
                continue
            rel = rel_posix(fp)
            if rel.startswith("tools/js_quarantine/") or rel.startswith("tools/css_quarantine/"):
                continue
            if rel.startswith("tools/reports/"):
                continue
            if fp.stat().st_size > 12_000_000:
                continue
            yield fp


def build_load_index(include_ref: bool) -> dict[str, list[str]]:
    """Map normalized js rel path → list of referencing files (loader-strength)."""
    index: dict[str, list[str]] = defaultdict(list)
    basename_index: dict[str, list[str]] = defaultdict(list)

    for fp in iter_code_files(include_ref):
        rel = rel_posix(fp)
        try:
            raw = read_text(fp)
        except OSError:
            continue
        cleaned = strip_comments_for_match(raw, fp.suffix.lower())
        for pat in LOADER_PATTERNS:
            for m in pat.finditer(cleaned):
                path = normalize_js_path(m.group("path"))
                index[path].append(rel)
                basename_index[Path(path).name].append(rel)

    # Merge basename hits only when unique file exists under scan roots
    return {"paths": index, "basenames": basename_index}  # type: ignore[return-value]


def resolve_references(
    js_files: list[Path],
    load_index: dict,
) -> list[JsFileInfo]:
    path_idx: dict[str, list[str]] = load_index["paths"]
    base_idx: dict[str, list[str]] = load_index["basenames"]
    by_sha: dict[str, list[str]] = defaultdict(list)
    infos: list[JsFileInfo] = []

    # basename uniqueness among product js
    basename_owners: dict[str, list[str]] = defaultdict(list)
    for fp in js_files:
        basename_owners[fp.name].append(rel_posix(fp))

    for fp in js_files:
        rel = rel_posix(fp)
        digest = sha1_file(fp)
        by_sha[digest].append(rel)
        size = fp.stat().st_size
        is_backup = any(
            x in fp.name for x in (".bak", ".old", ".orig", ".corrupt", ".copy")
        )
        text_head = ""
        try:
            with fp.open("rb") as fh:
                text_head = fh.read(8000).decode("utf-8", errors="ignore")
        except OSError:
            pass
        is_min = bool(MINIFIED_HINT.search(fp.name) or MINIFIED_HINT.search(text_head))
        if size > 400_000 and text_head.count("\n") < 30:
            is_min = True

        hits: list[str] = []
        # exact path
        for h in path_idx.get(rel, []):
            if h != rel:
                hits.append(h)
        # leading-slash variants already normalized

        # basename: only if unique owner (avoid mobile_bottom false friends)
        owners = basename_owners.get(fp.name, [])
        if len(owners) == 1:
            for h in base_idx.get(fp.name, []):
                if h != rel and h not in hits:
                    # Ensure the hit isn't only from THIS file's self string in another form
                    hits.append(h)

        # Deduplicate hits
        hits = sorted(set(hits))

        # Filter weak hits: if referencing file only mentions basename inside
        # a comment-stripped blob via prose path without loader — already stripped.
        # Extra: ignore hits from tools/ scripts
        hits = [h for h in hits if not h.startswith("tools/")]

        keep = rel in ALWAYS_KEEP_FILES
        referenced = keep or len(hits) > 0

        infos.append(
            JsFileInfo(
                rel=rel,
                path=str(fp),
                size=size,
                sha1=digest,
                referenced=referenced,
                reference_hits=hits[:12],
                keep_forced=keep,
                is_backup=is_backup,
                is_minified=is_min,
                load_score=len(hits),
            )
        )

    # duplicates
    for digest, rels in by_sha.items():
        if len(rels) < 2:
            continue
        primary = sorted(rels, key=lambda r: (0 if any(i.referenced and i.rel == r for i in infos) else 1, len(r), r))[0]
        for info in infos:
            if info.sha1 == digest and info.rel != primary:
                info.duplicate_of = primary

    return infos


def extract_top_level_functions(text: str) -> list[tuple[str, str, int, int, int]]:
    """
    Return list of (name, kind, start_idx, end_idx, line_no).
    Only top-level (indent 0) function declarations — conservative.
    """
    results: list[tuple[str, str, int, int, int]] = []
    for m in FN_DECL.finditer(text):
        indent = m.group("indent")
        if indent not in ("",):
            # allow only truly top-level (start of line with no indent)
            # but pattern includes indent — skip if indent non-empty
            if indent != "":
                continue
        name = m.group("name")
        start = m.start()
        # find matching brace body end
        brace = text.find("{", m.end() - 1)
        if brace < 0:
            continue
        depth = 0
        i = brace
        in_str = None
        escape = False
        while i < len(text):
            ch = text[i]
            if in_str:
                if escape:
                    escape = False
                elif ch == "\\":
                    escape = True
                elif ch == in_str:
                    in_str = None
            else:
                if ch in ('"', "'", "`"):
                    in_str = ch
                elif ch == "{":
                    depth += 1
                elif ch == "}":
                    depth -= 1
                    if depth == 0:
                        end = i + 1
                        # include trailing semicolon
                        if end < len(text) and text[end:end + 1] == ";":
                            end += 1
                        line = text.count("\n", 0, start) + 1
                        results.append((name, "function", start, end, line))
                        break
            i += 1
    return results


def symbol_usage_count(name: str, corpus: str) -> int:
    return len(re.findall(rf"\b{re.escape(name)}\b", corpus))


def analyze_symbols(
    infos: list[JsFileInfo],
    include_ref: bool,
    min_confidence: str,
    allow_dynamic_report: bool = False,
) -> list[SymbolHit]:
    """
    Symbol unused detection with dynamic-safe policy.

    - static files: classic never-referenced top-level scan
    - dynamic/hybrid files: reachability from window/@entry/@keep/coverage;
      reported only as low/medium unless allow_dynamic_report + coverage
    - stripping of dynamic files is gated in apply_symbol_strip
    """
    sys.path.insert(0, str(ROOT / "tools"))
    try:
        from js_dynamic_safe import (  # type: ignore
            analyze_file as dyn_analyze,
            load_runtime_coverage,
            may_strip_symbol,
            policy_for,
        )
    except Exception as exc:  # pragma: no cover
        print(f"WARN: js_dynamic_safe unavailable ({exc}); falling back to static-only scan")
        dyn_analyze = None
        load_runtime_coverage = lambda: {}  # type: ignore
        may_strip_symbol = None  # type: ignore
        policy_for = None  # type: ignore

    file_texts: dict[str, str] = {}
    for info in infos:
        if info.is_backup or info.is_minified:
            continue
        if not info.rel.endswith(".js"):
            continue
        file_texts[info.rel] = read_text(Path(info.path))

    extra_parts: list[str] = []
    for fp in iter_code_files(include_ref):
        if fp.suffix.lower() not in {".php", ".html", ".htm", ".js"}:
            continue
        rel = rel_posix(fp)
        if rel in file_texts:
            continue
        if rel.startswith("admin/vendor"):
            continue
        try:
            if fp.stat().st_size > 2_000_000:
                continue
        except OSError:
            continue
        extra_parts.append(strip_comments_for_match(read_text(fp), fp.suffix.lower()))
    extra_corpus = "\n".join(extra_parts)

    conf_rank = {"high": 3, "medium": 2, "low": 1}
    min_rank = conf_rank.get(min_confidence, 3)
    coverage = load_runtime_coverage() if callable(load_runtime_coverage) else {}

    hits: list[SymbolHit] = []
    for rel, text in file_texts.items():
        if any(rel.startswith(p) for p in ("admin/",)) and rel not in ALWAYS_KEEP_FILES:
            # admin bundles stay out of auto symbol strip reports unless explicitly static
            pass

        policy = policy_for(rel, text) if callable(policy_for) else None
        mode = policy.mode if policy else ("dynamic" if rel in SYMBOL_STRIP_DENY else "static")

        # ── Dynamic / hybrid: reachability report (no high-confidence false positives) ──
        if mode in {"dynamic", "hybrid"} and dyn_analyze is not None:
            report = dyn_analyze(rel, Path(ROOT / rel))
            has_cov = rel in coverage and len(coverage.get(rel, set())) > 0
            for name in report.unreachable:
                if not SAFE_SYMBOL_NAME.match(name):
                    continue
                # Without coverage, dynamic unreachable is informational only (low)
                if has_cov and name not in coverage.get(rel, set()):
                    confidence = "medium"
                    reason = "unreachable from entries + missing in runtime coverage"
                else:
                    confidence = "low"
                    reason = "unreachable from entries (dynamic file; needs coverage to strip)"
                if not allow_dynamic_report and confidence != "medium":
                    continue
                if conf_rank[confidence] < min_rank and not allow_dynamic_report:
                    continue
                # find line
                m = re.search(rf"(?m)^[ \t]*(?:async\s+)?function\s+{re.escape(name)}\s*\(", text)
                line = text.count("\n", 0, m.start()) + 1 if m else 0
                hits.append(
                    SymbolHit(
                        name=name,
                        kind="function",
                        file=rel,
                        line=line,
                        confidence=confidence,
                        used=False,
                        reason=reason,
                    )
                )
            continue

        # ── Static files: classic scan ──
        if rel in ALWAYS_KEEP_FILES and mode == "dynamic":
            continue

        others = "\n".join(t for r, t in file_texts.items() if r != rel) + "\n" + extra_corpus
        for name, kind, start, end, line in extract_top_level_functions(text):
            if not SAFE_SYMBOL_NAME.match(name):
                continue
            if name.lower().startswith(DYNAMIC_NAME_HINTS):
                conf = "low"
            else:
                conf = "high"

            own = symbol_usage_count(name, text)
            other = symbol_usage_count(name, others)
            if other > 0 or own > 1:
                continue

            if conf == "high" and own == 1 and other == 0:
                confidence = "high"
                reason = "top-level function never referenced"
            elif own == 1 and other == 0:
                confidence = "medium"
                reason = "name unused but dynamic-looking prefix"
            else:
                continue

            if conf_rank[confidence] < min_rank:
                continue

            hits.append(
                SymbolHit(
                    name=name,
                    kind=kind,
                    file=rel,
                    line=line,
                    confidence=confidence,
                    used=False,
                    reason=reason,
                )
            )
    return hits


def quarantine_path(rel: str, stamp: str) -> Path:
    safe = rel.replace("/", "__").replace("\\", "__")
    dest_dir = QUARANTINE_DIR / stamp
    dest_dir.mkdir(parents=True, exist_ok=True)
    return dest_dir / safe


def apply_file_quarantine(infos: list[JsFileInfo], stamp: str, files_only: bool) -> list[str]:
    actions: list[str] = []
    for info in infos:
        should_move = False
        why = ""
        if info.is_backup:
            should_move = True
            why = "backup/corrupt leftover"
        elif not info.referenced and not info.keep_forced:
            should_move = True
            why = "unreferenced by loaders"
        elif (
            not files_only
            and info.duplicate_of
            and not info.referenced
            and not info.keep_forced
        ):
            should_move = True
            why = f"duplicate of {info.duplicate_of}"

        if not should_move:
            continue

        src = Path(info.path)
        if not src.is_file():
            continue
        dest = quarantine_path(info.rel, stamp)
        shutil.move(str(src), str(dest))
        actions.append(f"quarantine {info.rel} -> {rel_posix(dest)} ({why})")
    return actions


def apply_symbol_strip(
    symbols: list[SymbolHit],
    stamp: str,
    min_confidence: str,
    allow_dynamic_strip: bool = False,
) -> list[str]:
    actions: list[str] = []
    conf_rank = {"high": 3, "medium": 2, "low": 1}
    min_rank = conf_rank.get(min_confidence, 3)

    sys.path.insert(0, str(ROOT / "tools"))
    try:
        from js_dynamic_safe import load_runtime_coverage, may_strip_symbol, policy_for  # type: ignore
    except Exception:
        load_runtime_coverage = lambda: {}  # type: ignore
        may_strip_symbol = None  # type: ignore
        policy_for = None  # type: ignore

    coverage = load_runtime_coverage() if callable(load_runtime_coverage) else {}

    by_file: dict[str, list[SymbolHit]] = defaultdict(list)
    for s in symbols:
        if conf_rank.get(s.confidence, 0) < min_rank:
            continue
        by_file[s.file].append(s)

    for rel, syms in by_file.items():
        path = ROOT / rel
        if not path.is_file():
            continue
        text = read_text(path)
        policy = policy_for(rel, text) if callable(policy_for) else None
        has_cov = rel in coverage and len(coverage.get(rel, set())) > 0

        if policy is not None and callable(may_strip_symbol):
            if not may_strip_symbol(rel, policy, has_cov):
                if policy.mode == "dynamic" and not allow_dynamic_strip:
                    actions.append(
                        f"skip-strip {rel} (dynamic; strip_policy={policy.strip_policy}; "
                        f"provide tools/reports/js-runtime-usage.json or --allow-dynamic-strip)"
                    )
                    continue
                if not allow_dynamic_strip:
                    actions.append(f"skip-strip {rel} (policy forbids without coverage)")
                    continue
        elif rel in SYMBOL_STRIP_DENY or rel in ALWAYS_KEEP_FILES:
            if not allow_dynamic_strip:
                actions.append(f"skip-strip {rel} (deny-list protected)")
                continue

        funcs = extract_top_level_functions(text)
        spans = {n: (a, b) for n, _k, a, b, _ln in funcs}
        removals: list[tuple[int, int, str]] = []
        for s in syms:
            if s.confidence not in {"high", "medium"}:
                continue
            if s.name not in spans:
                continue
            # Never strip keep/entries
            if policy and (s.name in policy.keep or s.name in policy.entries):
                continue
            a, b = spans[s.name]
            removals.append((a, b, s.name))
        if not removals:
            continue
        bak = quarantine_path(rel + ".pre-symbol-strip", stamp)
        bak.write_text(text, encoding="utf-8")
        removals.sort(key=lambda t: t[0], reverse=True)
        new_text = text
        for a, b, name in removals:
            new_text = new_text[:a] + new_text[b:]
            actions.append(f"strip function {name}() from {rel}")
        new_text = re.sub(r"\n{3,}", "\n\n", new_text)
        path.write_text(new_text, encoding="utf-8", newline="\n")
    return actions


def render_markdown(report: Report) -> str:
    lines = [
        f"# Unused JS Report",
        f"",
        f"- Generated: `{report.generated_at}`",
        f"- Mode: `{report.mode}`",
        f"- JS files scanned: **{report.js_files_total}**",
        f"- Unused files: **{len(report.unused_files)}**",
        f"- Backup leftovers: **{len(report.backup_files)}**",
        f"- Duplicates: **{len(report.duplicate_files)}**",
        f"- Unused symbols: **{len(report.unused_symbols)}**",
        f"",
        f"## Unused files",
        f"",
    ]
    if not report.unused_files:
        lines.append("_None_")
    else:
        for u in report.unused_files:
            lines.append(f"- `{u['rel']}` ({u['size']} bytes) sha1={u['sha1'][:12]}")
    lines += ["", "## Backup / corrupt leftovers", ""]
    if not report.backup_files:
        lines.append("_None_")
    else:
        for u in report.backup_files:
            lines.append(f"- `{u['rel']}` ({u['size']} bytes)")
    lines += ["", "## Duplicate files", ""]
    if not report.duplicate_files:
        lines.append("_None_")
    else:
        for u in report.duplicate_files:
            lines.append(f"- `{u['rel']}` == `{u['duplicate_of']}`")
    lines += ["", "## Unused symbols (candidates)", ""]
    if not report.unused_symbols:
        lines.append("_None / not scanned_")
    else:
        for s in report.unused_symbols[:200]:
            lines.append(
                f"- `{s['file']}:{s['line']}` `{s['name']}()` "
                f"[{s['confidence']}] {s['reason']}"
            )
    if report.actions:
        lines += ["", "## Actions", ""]
        for a in report.actions:
            lines.append(f"- {a}")
    if report.warnings:
        lines += ["", "## Warnings", ""]
        for w in report.warnings:
            lines.append(f"- {w}")
    lines.append("")
    return "\n".join(lines)


def main(argv: list[str] | None = None) -> int:
    ap = argparse.ArgumentParser(description="Unused JS detector & cleaner")
    ap.add_argument("--apply", action="store_true", help="Quarantine unused files / strip symbols")
    ap.add_argument("--files-only", action="store_true", help="Only act on files (default with --apply)")
    ap.add_argument("--symbols", action="store_true", help="Also analyze/strip unused top-level functions")
    ap.add_argument("--min-confidence", choices=["high", "medium", "low"], default="high")
    ap.add_argument(
        "--report-dynamic",
        action="store_true",
        help="Include dynamic-file unreachable symbols in the report (informational)",
    )
    ap.add_argument(
        "--allow-dynamic-strip",
        action="store_true",
        help="Allow stripping in dynamic files when policy/coverage permits",
    )
    ap.add_argument("--include-admin", action="store_true", help="Include admin/*.js bundles")
    ap.add_argument("--include-ref", action="store_true", help="Include _ref/")
    ap.add_argument("--json", action="store_true", help="Print JSON report")
    ap.add_argument("--report", type=str, default="", help="Write markdown report path")
    args = ap.parse_args(argv)

    stamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    mode = "apply" if args.apply else "dry-run"

    js_files = collect_js_files(args.include_admin, args.include_ref)
    load_index = build_load_index(args.include_ref)
    infos = resolve_references(js_files, load_index)

    unused = [i for i in infos if (not i.referenced) and (not i.is_backup)]
    backups = [i for i in infos if i.is_backup]
    dupes = [i for i in infos if i.duplicate_of]

    symbols: list[SymbolHit] = []
    if args.symbols:
        symbols = analyze_symbols(
            infos,
            args.include_ref,
            args.min_confidence,
            allow_dynamic_report=bool(args.report_dynamic),
        )

    report = Report(
        generated_at=datetime.now(timezone.utc).isoformat(),
        root=str(ROOT),
        mode=mode,
        js_files_total=len(infos),
        unused_files=[asdict(i) for i in unused],
        backup_files=[asdict(i) for i in backups],
        duplicate_files=[asdict(i) for i in dupes],
        unused_symbols=[asdict(s) for s in symbols],
        kept_files=sorted(i.rel for i in infos if i.referenced and not i.is_backup),
        stats={
            "referenced": sum(1 for i in infos if i.referenced and not i.is_backup),
            "unused": len(unused),
            "backups": len(backups),
            "duplicates": len(dupes),
            "symbols_unused": len(symbols),
        },
    )

    if unused:
        report.warnings.append(
            "Unreferenced files are moved only with --apply; verify no dynamic string loaders."
        )
    if args.symbols:
        report.warnings.append(
            "Dynamic files use tools/js_manifests + coverage; regenerate with "
            "python tools/generate_js_manifests.py --report"
        )
        if symbols:
            report.warnings.append(
                "Review unused symbols carefully; dynamic unreachable without coverage is low confidence."
            )

    actions: list[str] = []
    if args.apply:
        # Default to files-only unless --symbols requested without --files-only exclusivity
        files_only = args.files_only or not args.symbols
        if args.files_only or not args.symbols:
            actions += apply_file_quarantine(infos, stamp, files_only=True)
        else:
            actions += apply_file_quarantine(infos, stamp, files_only=False)
        if args.symbols:
            actions += apply_symbol_strip(
                symbols,
                stamp,
                args.min_confidence,
                allow_dynamic_strip=bool(args.allow_dynamic_strip),
            )
        report.actions = actions
        report.mode = f"apply:{stamp}"

    REPORT_DIR.mkdir(parents=True, exist_ok=True)
    report_path = Path(args.report) if args.report else (REPORT_DIR / "unused-js.md")
    if not report_path.is_absolute():
        report_path = ROOT / report_path
    report_path.parent.mkdir(parents=True, exist_ok=True)
    report_path.write_text(render_markdown(report), encoding="utf-8")

    json_path = REPORT_DIR / "unused-js.json"
    json_path.write_text(
        json.dumps(asdict(report), ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    if args.json:
        print(json.dumps(asdict(report), ensure_ascii=False, indent=2))
    else:
        print(f"JS scanned: {report.js_files_total}")
        print(f"Referenced: {report.stats['referenced']}")
        print(f"Unused files: {report.stats['unused']}")
        print(f"Backup leftovers: {report.stats['backups']}")
        print(f"Duplicates: {report.stats['duplicates']}")
        if args.symbols:
            print(f"Unused symbols: {report.stats['symbols_unused']}")
        for i in unused:
            print(f"  UNUSED  {i.rel}")
        for i in backups:
            print(f"  BACKUP  {i.rel}")
        for i in dupes:
            if not i.is_backup and i.referenced:
                print(f"  DUP     {i.rel} == {i.duplicate_of}")
        if args.symbols:
            for s in symbols[:50]:
                print(f"  SYMBOL  {s.file}:{s.line} {s.name}() [{s.confidence}]")
        if actions:
            print(f"Actions: {len(actions)}")
            for a in actions:
                print(f"  {a}")
        print(f"Report: {rel_posix(report_path)}")
        print(f"JSON:   {rel_posix(json_path)}")
        if not args.apply:
            print("Dry-run only. Re-run with --apply --files-only to quarantine.")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
