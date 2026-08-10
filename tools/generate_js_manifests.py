#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Generate / refresh tools/js_manifests for dynamic JS files.

Usage:
  python tools/generate_js_manifests.py
  python tools/generate_js_manifests.py --file assets/js/profile.js
  python tools/generate_js_manifests.py --report
"""

from __future__ import annotations

import argparse
import json
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(ROOT / "tools"))

from js_dynamic_safe import (  # noqa: E402
    DEFAULT_DYNAMIC_GLOBS,
    MANIFEST_DIR,
    MANIFEST_INDEX,
    analyze_file,
    ensure_default_index,
    extract_window_entries,
    load_manifest_index,
    parse_annotations,
    policy_for,
    read_text,
)


def write_file_manifest(rel: str) -> dict:
    path = ROOT / rel
    text = read_text(path)
    policy = policy_for(rel, text)
    report = analyze_file(rel, path)
    ann_entries, ann_keep, _ = parse_annotations(text)
    payload = {
        "file": rel,
        "generated_at": datetime.now(timezone.utc).isoformat(),
        "mode": policy.mode,
        "strip_policy": policy.strip_policy,
        "notes": policy.notes
        or "Auto-generated. Window exports + annotations are entries; strip needs runtime coverage.",
        "entries": sorted(set(policy.entries) | set(ann_entries) | extract_window_entries(text)),
        "keep": sorted(set(policy.keep) | set(ann_keep)),
        "stats": {
            "functions": len(report.functions),
            "reachable": len(report.reachable),
            "unreachable_candidates": len(report.unreachable),
            "coverage_hits": len(report.coverage_hits),
        },
        "unreachable_sample": report.unreachable[:40],
        "confidence_note": report.confidence_note,
    }
    safe = rel.replace("/", "__").replace("\\", "__")
    out = MANIFEST_DIR / f"{safe}.manifest.json"
    MANIFEST_DIR.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
    return payload


def refresh_index(rels: list[str]) -> None:
    ensure_default_index()
    data = json.loads(read_text(MANIFEST_INDEX)) if MANIFEST_INDEX.is_file() else {"version": 1, "files": {}}
    files = data.setdefault("files", {})
    for rel in rels:
        path = ROOT / rel
        if not path.is_file():
            continue
        text = read_text(path)
        policy = policy_for(rel, text, load_manifest_index())
        prev = files.get(rel) or {}
        files[rel] = {
            "mode": policy.mode,
            "strip_policy": policy.strip_policy,
            "entries": sorted(set(prev.get("entries") or []) | set(policy.entries)),
            "keep": sorted(set(prev.get("keep") or []) | set(policy.keep)),
            "notes": prev.get("notes")
            or policy.notes
            or "Dynamic surface — symbol strip requires tools/reports/js-runtime-usage.json",
        }
    data["version"] = 1
    data["updated_at"] = datetime.now(timezone.utc).isoformat()
    data["coverage_file"] = "tools/reports/js-runtime-usage.json"
    data["annotation_docs"] = {
        "@entry": "Mark a function as a live entry point",
        "@keep": "Never report/strip this symbol",
        "@dynamic-file": "Treat entire file as dynamic (coverage required to strip)",
    }
    MANIFEST_INDEX.write_text(json.dumps(data, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--file", action="append", default=[], help="Specific relative JS path (repeatable)")
    ap.add_argument("--report", action="store_true", help="Print reachability summary")
    args = ap.parse_args()

    ensure_default_index()
    rels = args.file or list(DEFAULT_DYNAMIC_GLOBS)
    rels = [r.replace("\\", "/") for r in rels if (ROOT / r).is_file()]

    refresh_index(rels)
    for rel in rels:
        payload = write_file_manifest(rel)
        if args.report:
            print(
                f"{rel}: mode={payload['mode']} funcs={payload['stats']['functions']} "
                f"reachable={payload['stats']['reachable']} "
                f"unreachable={payload['stats']['unreachable_candidates']} "
                f"entries={len(payload['entries'])}"
            )
        else:
            print(f"wrote manifest for {rel}")

    print(f"index: {MANIFEST_INDEX.relative_to(ROOT).as_posix()}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
