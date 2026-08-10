#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Repo-wide orphan / leftover / test / backup detector.

Default: dry-run. --apply moves matches into tools/repo_quarantine/<stamp>/.

Conservative: does NOT touch .env, vendor, node_modules, live runtime assets
that are referenced, or ALWAYS_KEEP paths.
"""

from __future__ import annotations

import argparse
import json
import os
import re
import shutil
import sys
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
REPORT_DIR = ROOT / "tools" / "reports"
QUARANTINE = ROOT / "tools" / "repo_quarantine"

SKIP_DIRS = {
    ".git", "node_modules", "vendor", ".venv", "__pycache__",
    "storage", "logs", "cache", "uploads", "upload",
    "Crashpad", ".cursor", ".github",
}

# Never quarantine these even if pattern matches
ALWAYS_KEEP_PREFIXES = (
    "assets/css/",
    "assets/js/",  # already cleaned by unused_js; only bak handled separately
    "mobile/assets/css/",
    "mobile/assets/js/",
    "admin/app/",
    "admin/database/migrations/",
    "database/migrations/",
    "views/",
    "pages/",
    "services/",
    "controllers/",
    "config/",
    "ENV.example",
    "admin/ENV.example",
    ".htaccess",
    "composer.json",
    "composer.lock",
    "package.json",
    "package-lock.json",
)

# Explicit always-keep files (basename or rel)
ALWAYS_KEEP_FILES = {
    "affiliate-cron.php",
    "service-worker.js",
    "public/service-worker.js",
    "tools/unused_css_cleaner.py",
    "tools/unused_js_cleaner.py",
    "tools/js_dynamic_safe.py",
    "tools/generate_js_manifests.py",
    "tools/rename_css_sections.py",
    "scripts/affiliate_e2e_probe.php",  # keep unless --aggressive
}

NAME_PATTERNS = [
    (re.compile(r"(^|/)_tmp_", re.I), "tmp_script", "high"),
    (re.compile(r"(^|/)_tmp[./]", re.I), "tmp_script", "high"),
    (re.compile(r"\.corrupt-bak$", re.I), "corrupt_backup", "high"),
    (re.compile(r"\.bak(\.|$)", re.I), "backup", "high"),
    (re.compile(r"\.(old|orig|copy|disabled)$", re.I), "backup", "high"),
    (re.compile(r"(^|/)test[_-].*\.(php|js|py|sh)$", re.I), "test_file", "medium"),
    (re.compile(r"(^|/).*[_-]test\.(php|js|py)$", re.I), "test_file", "medium"),
    (re.compile(r"(^|/)phpunit", re.I), "test_harness", "low"),
    (re.compile(r"(^|/)(dump|scratch|playground|sandbox)[_-]?", re.I), "scratch", "medium"),
    (re.compile(r"(^|/)_ref/", re.I), "reference_dump", "medium"),
    (re.compile(r"(^|/)_shots/", re.I), "browser_shots", "high"),
    (re.compile(r"(^|/)tools/backups/", re.I), "tools_backup", "high"),
    (re.compile(r"(^|/)tools/css_quarantine/", re.I), "already_quarantined", "low"),
    (re.compile(r"(^|/)tools/js_quarantine/", re.I), "already_quarantined", "low"),
    (re.compile(r"(^|/)aff_pull\.tgz$", re.I), "archive", "high"),
    (re.compile(r"(^|/).*\.tgz$", re.I), "archive", "medium"),
    (re.compile(r"(^|/).*e2e.*\.(php|js|py)$", re.I), "e2e_probe", "medium"),
    (re.compile(r"(^|/)scripts/.*probe", re.I), "probe_script", "medium"),
    (re.compile(r"(^|/)storage/.*/_.*\.(html|htm)$", re.I), "log_dump", "high"),
    (re.compile(r"(^|/)admin/storage/.*/_.*\.(html|htm)$", re.I), "log_dump", "high"),
    (re.compile(r"(^|/)tools/(peek|scan|sample|find|extract|verify|check)-", re.I), "one_off_tool", "medium"),
    (re.compile(r"(^|/)tools/(build|rebuild|download)-cm622", re.I), "one_off_tool", "medium"),
    (re.compile(r"(^|/)tools/patch-profile-", re.I), "one_off_tool", "medium"),
]

DIR_CANDIDATES = [
    ("_shots", "browser_shots", "high"),
    ("_ref", "reference_dump", "medium"),
    ("tools/backups", "tools_backup", "high"),
]


@dataclass
class Candidate:
    rel: str
    kind: str
    confidence: str
    size: int
    reason: str
    is_dir: bool = False


@dataclass
class Report:
    generated_at: str
    mode: str
    candidates: list[dict] = field(default_factory=list)
    actions: list[str] = field(default_factory=list)
    skipped: list[str] = field(default_factory=list)
    stats: dict = field(default_factory=dict)


def rel_posix(path: Path) -> str:
    try:
        return path.resolve().relative_to(ROOT).as_posix()
    except ValueError:
        return path.as_posix()


def is_kept(rel: str, aggressive: bool) -> bool:
    if rel in ALWAYS_KEEP_FILES and not aggressive:
        return True
    if rel.startswith("tools/js_manifests/"):
        return True
    if rel.startswith("tools/reports/") and rel.endswith((".md", ".json")):
        # keep latest reports
        if "unused-" in rel or rel.endswith("README.md"):
            return True
    # Don't move active product code by name pattern alone
    for prefix in (
        "assets/js/", "assets/css/", "mobile/assets/", "admin/app/",
        "views/", "pages/", "services/", "controllers/",
    ):
        if rel.startswith(prefix) and not any(
            x in rel for x in (".bak", ".old", ".orig", ".corrupt", "_tmp")
        ):
            return True
    return False


def classify_path(rel: str, is_dir: bool) -> Candidate | None:
    for pat, kind, conf in NAME_PATTERNS:
        if pat.search(rel):
            return Candidate(rel=rel, kind=kind, confidence=conf, size=0, reason=f"pattern:{kind}", is_dir=is_dir)
    return None


def collect(aggressive: bool, include_ref: bool, include_shots: bool) -> list[Candidate]:
    out: list[Candidate] = []
    seen: set[str] = set()

    def add(c: Candidate) -> None:
        if c.rel in seen:
            return
        if is_kept(c.rel, aggressive):
            return
        if c.kind == "reference_dump" and not include_ref and not aggressive:
            return
        if c.kind == "browser_shots" and not include_shots and not aggressive:
            return
        if c.kind == "already_quarantined":
            return  # leave where they are
        if c.kind == "e2e_probe" and not aggressive:
            return
        if c.kind == "probe_script" and not aggressive:
            return
        if c.kind == "one_off_tool" and not aggressive:
            return
        if c.kind == "test_harness":
            return  # don't touch phpunit vendor
        # Keep long-term maintainers even if name matches one_off patterns
        keep_tools = {
            "tools/unused_css_cleaner.py",
            "tools/unused_js_cleaner.py",
            "tools/js_dynamic_safe.py",
            "tools/generate_js_manifests.py",
            "tools/rename_css_sections.py",
            "tools/repo_orphan_cleaner.py",
        }
        if c.rel in keep_tools:
            return
        seen.add(c.rel)
        out.append(c)

    # Explicit dirs
    for drel, kind, conf in DIR_CANDIDATES:
        p = ROOT / drel
        if not p.exists():
            continue
        if kind == "reference_dump" and not include_ref and not aggressive:
            continue
        if kind == "browser_shots" and not include_shots and not aggressive:
            continue
        size = 0
        if p.is_dir():
            for fp in p.rglob("*"):
                if fp.is_file():
                    try:
                        size += fp.stat().st_size
                    except OSError:
                        pass
        add(Candidate(rel=drel, kind=kind, confidence=conf, size=size, reason=f"dir:{kind}", is_dir=p.is_dir()))

    for dirpath, dirnames, filenames in os.walk(ROOT):
        p = Path(dirpath)
        # prune
        dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS and d != "repo_quarantine"]
        rel_dir = rel_posix(p)
        if rel_dir.startswith("tools/repo_quarantine"):
            dirnames[:] = []
            continue
        if rel_dir.startswith("tools/css_quarantine") or rel_dir.startswith("tools/js_quarantine"):
            continue
        if rel_dir.startswith("_shots") or rel_dir.startswith("_ref") or rel_dir.startswith("tools/backups"):
            # already handled as whole dirs
            dirnames[:] = []
            continue
        if "vendor" in p.parts or "node_modules" in p.parts:
            dirnames[:] = []
            continue

        for name in filenames:
            fp = p / name
            rel = rel_posix(fp)
            c = classify_path(rel, False)
            if not c:
                # tools temp php/py
                if rel.startswith("tools/") and (
                    name.startswith("_tmp") or name.endswith(".tmp") or ".tmp." in name
                ):
                    c = Candidate(rel=rel, kind="tmp_script", confidence="high", size=0, reason="tools temp", is_dir=False)
                else:
                    continue
            try:
                c.size = fp.stat().st_size
            except OSError:
                c.size = 0
            add(c)

    # Loose archives / dumps at root
    for fp in ROOT.glob("*"):
        if not fp.is_file():
            continue
        rel = rel_posix(fp)
        if rel.endswith((".tgz", ".tar.gz", ".zip")) and "vendor" not in rel:
            add(Candidate(rel=rel, kind="archive", confidence="high", size=fp.stat().st_size, reason="root archive"))

    return sorted(out, key=lambda c: (0 if c.confidence == "high" else 1, -c.size, c.rel))


def apply_quarantine(cands: list[Candidate], stamp: str, min_conf: str) -> list[str]:
    rank = {"high": 3, "medium": 2, "low": 1}
    need = rank.get(min_conf, 3)
    actions: list[str] = []
    dest_root = QUARANTINE / stamp
    dest_root.mkdir(parents=True, exist_ok=True)

    for c in cands:
        if rank.get(c.confidence, 0) < need:
            continue
        src = ROOT / c.rel
        if not src.exists():
            continue
        safe = c.rel.replace("/", "__").replace("\\", "__")
        dest = dest_root / safe
        if src.is_dir():
            if dest.exists():
                shutil.rmtree(dest)
            shutil.move(str(src), str(dest))
        else:
            dest.parent.mkdir(parents=True, exist_ok=True)
            shutil.move(str(src), str(dest))
        actions.append(f"quarantine {c.rel} -> {rel_posix(dest)} [{c.kind}/{c.confidence}]")
    return actions


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--apply", action="store_true")
    ap.add_argument("--min-confidence", choices=["high", "medium", "low"], default="high")
    ap.add_argument("--aggressive", action="store_true", help="Include e2e probes, _ref")
    ap.add_argument("--include-ref", action="store_true")
    ap.add_argument("--include-shots", action="store_true", default=True)
    ap.add_argument("--no-shots", action="store_true")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args()

    include_shots = not args.no_shots
    cands = collect(args.aggressive, args.include_ref or args.aggressive, include_shots)

    # Filter by confidence for display of "will apply"
    rank = {"high": 3, "medium": 2, "low": 1}
    need = rank[args.min_confidence]
    actionable = [c for c in cands if rank[c.confidence] >= need]

    report = Report(
        generated_at=datetime.now(timezone.utc).isoformat(),
        mode="apply" if args.apply else "dry-run",
        candidates=[asdict(c) for c in cands],
        stats={
            "total": len(cands),
            "actionable": len(actionable),
            "bytes": sum(c.size for c in actionable),
            "by_kind": {},
        },
    )
    by_kind: dict[str, int] = {}
    for c in actionable:
        by_kind[c.kind] = by_kind.get(c.kind, 0) + 1
    report.stats["by_kind"] = by_kind

    stamp = datetime.now(timezone.utc).strftime("%Y%m%d_%H%M%S")
    if args.apply:
        report.actions = apply_quarantine(actionable, stamp, args.min_confidence)
        report.mode = f"apply:{stamp}"

    REPORT_DIR.mkdir(parents=True, exist_ok=True)
    (REPORT_DIR / "repo-orphans.md").write_text(
        "# Repo orphan cleanup\n\n"
        + f"- mode: `{report.mode}`\n"
        + f"- actionable: **{len(actionable)}** ({report.stats['bytes']} bytes)\n\n"
        + "## Candidates\n\n"
        + "\n".join(
            f"- `{c.rel}` [{c.kind}/{c.confidence}] {c.size}b — {c.reason}"
            for c in actionable
        )
        + ("\n\n## Actions\n\n" + "\n".join(f"- {a}" for a in report.actions) if report.actions else "")
        + "\n",
        encoding="utf-8",
    )
    (REPORT_DIR / "repo-orphans.json").write_text(
        json.dumps(asdict(report), ensure_ascii=False, indent=2),
        encoding="utf-8",
    )

    if args.json:
        print(json.dumps(asdict(report), ensure_ascii=False, indent=2))
    else:
        print(f"candidates={len(cands)} actionable={len(actionable)} bytes={report.stats['bytes']}")
        print(f"by_kind={by_kind}")
        for c in actionable[:80]:
            print(f"  {c.confidence:6} {c.kind:18} {c.size:10} {c.rel}")
        if len(actionable) > 80:
            print(f"  ... +{len(actionable)-80} more")
        if report.actions:
            print(f"actions={len(report.actions)}")
        else:
            print("Dry-run. Use --apply --min-confidence high")
        print("Report: tools/reports/repo-orphans.md")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
