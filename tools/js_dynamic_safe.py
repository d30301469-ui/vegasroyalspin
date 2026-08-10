#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Dynamic-safe JS analysis helpers (long-term unused-code foundation).

Why this exists
---------------
Files like assets/js/profile.js are large IIFEs with:
  - window.* public API exports
  - string / data-attr driven dispatch
  - nested function declarations

Regex "top-level unused function" scanners produce false positives there.
This module instead:

  1) Classifies files via tools/js_manifests/dynamic-files.json
  2) Discovers entry points (window exports, @entry/@keep annotations,
     DOMContentLoaded, optional runtime coverage)
  3) Builds a conservative intra-file reachability graph
  4) Only reports unreachable symbols; never auto-strips dynamic files
     unless coverage evidence is provided

Annotation convention (anywhere in a JS file):
  // @entry openVegaPanel
  // @keep  helperUsedByPhpInline
  // @dynamic-file
"""

from __future__ import annotations

import json
import re
from collections import defaultdict, deque
from dataclasses import dataclass, field
from pathlib import Path
from typing import Iterable

ROOT = Path(__file__).resolve().parent.parent
MANIFEST_DIR = ROOT / "tools" / "js_manifests"
MANIFEST_INDEX = MANIFEST_DIR / "dynamic-files.json"
COVERAGE_PATH = ROOT / "tools" / "reports" / "js-runtime-usage.json"

ANNOT_ENTRY = re.compile(r"(?://|/\*|#)\s*@entry\s+([A-Za-z_$][\w$]*(?:\s*,\s*[A-Za-z_$][\w$]*)*)", re.I)
ANNOT_KEEP = re.compile(r"(?://|/\*|#)\s*@keep\s+([A-Za-z_$][\w$]*(?:\s*,\s*[A-Za-z_$][\w$]*)*)", re.I)
ANNOT_DYNAMIC = re.compile(r"(?://|/\*|#)\s*@dynamic-file\b", re.I)

# window.foo = function ... | window.foo = bar | window.foo = async function
WINDOW_ASSIGN = re.compile(
    r"window\.([A-Za-z_$][\w$]*)\s*=\s*(?:async\s+)?(function\b|([A-Za-z_$][\w$]*))",
)
WINDOW_ASSIGN_ANY = re.compile(r"window\.([A-Za-z_$][\w$]*)\s*=")

FN_DECL = re.compile(
    r"(?m)^(?P<indent>[ \t]*)(?:async\s+)?function\s+(?P<name>[A-Za-z_$][\w$]*)\s*\("
)

IDENT = re.compile(r"\b([A-Za-z_$][\w$]{2,})\b")

DEFAULT_DYNAMIC_GLOBS = [
    "assets/js/profile.js",
    "assets/js/header.js",
    "assets/js/home.js",
    "assets/js/slot.js",
    "assets/js/bgaming.js",
    "assets/js/login.js",
    "assets/js/register.js",
    "mobile/assets/js/navigation.js",
    "mobile/assets/js/mobile-header.js",
    "mobile/assets/js/profile-panel.js",
    "admin/admin-ui.js",
    "admin/2026.js",
    "admin/vendors.js",
    "admin/runtime.js",
]


@dataclass
class FilePolicy:
    rel: str
    mode: str = "static"  # static | hybrid | dynamic
    strip_policy: str = "high"  # high | never | coverage_required
    entries: list[str] = field(default_factory=list)
    keep: list[str] = field(default_factory=list)
    notes: str = ""


@dataclass
class ReachReport:
    rel: str
    mode: str
    entries: list[str]
    keep: list[str]
    functions: list[str]
    reachable: list[str]
    unreachable: list[str]
    coverage_hits: list[str]
    confidence_note: str = ""


def read_text(path: Path) -> str:
    for enc in ("utf-8", "utf-8-sig", "cp1254", "latin-1"):
        try:
            return path.read_text(encoding=enc)
        except UnicodeDecodeError:
            continue
        except OSError:
            return ""
    return path.read_text(encoding="utf-8", errors="replace")


def split_names(blob: str) -> list[str]:
    return [n.strip() for n in re.split(r"[,\s]+", blob) if n.strip()]


def parse_annotations(text: str) -> tuple[set[str], set[str], bool]:
    entries: set[str] = set()
    keep: set[str] = set()
    dynamic = bool(ANNOT_DYNAMIC.search(text))
    for m in ANNOT_ENTRY.finditer(text):
        entries.update(split_names(m.group(1)))
    for m in ANNOT_KEEP.finditer(text):
        keep.update(split_names(m.group(1)))
    return entries, keep, dynamic


def extract_window_entries(text: str) -> set[str]:
    """Public API candidates: window.X = function|identifier."""
    out: set[str] = set()
    for m in WINDOW_ASSIGN.finditer(text):
        name = m.group(1)
        rhs = m.group(2)
        alias = m.group(3)
        if rhs == "function" or (alias and alias not in {"true", "false", "null", "undefined"}):
            # Prefer the alias as entry if assigning an existing function name
            if alias and alias not in {"function"}:
                out.add(alias)
            out.add(name)
    # Also treat function-valued exports without capturing junk flags:
    # window.foo = function(...)  already covered.
    # window.__openProfileModalUrl = openProfileModalUrl → entry openProfileModalUrl
    return out


def extract_functions(text: str) -> dict[str, tuple[int, int]]:
    """name -> (start, end) for function declarations (any indent)."""
    results: dict[str, tuple[int, int]] = {}
    for m in FN_DECL.finditer(text):
        name = m.group("name")
        start = m.start()
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
                if ch in ("'", '"', "`"):
                    in_str = ch
                elif ch == "{":
                    depth += 1
                elif ch == "}":
                    depth -= 1
                    if depth == 0:
                        results[name] = (start, i + 1)
                        break
            i += 1
    return results


def build_local_refs(text: str, fn_names: set[str]) -> dict[str, set[str]]:
    """
    For each function body, which other known function names are referenced.
    Conservative: any identifier match counts (may over-mark used = good).
    """
    spans = extract_functions(text)
    graph: dict[str, set[str]] = {n: set() for n in fn_names}
    for name, (a, b) in spans.items():
        body = text[a:b]
        for other in fn_names:
            if other == name:
                continue
            if re.search(rf"\b{re.escape(other)}\b", body):
                graph[name].add(other)
    # Also global text refs outside any function (boot / IIFE root)
    root_chunks = []
    last = 0
    ordered = sorted(spans.values(), key=lambda s: s[0])
    for a, b in ordered:
        if last < a:
            root_chunks.append(text[last:a])
        last = max(last, b)
    if last < len(text):
        root_chunks.append(text[last:])
    root = "\n".join(root_chunks)
    root_hits = {n for n in fn_names if re.search(rf"\b{re.escape(n)}\b", root)}
    graph["__root__"] = root_hits
    return graph


def reachable(entries: Iterable[str], graph: dict[str, set[str]]) -> set[str]:
    start = set(entries) | (graph.get("__root__", set()))
    seen: set[str] = set()
    q: deque[str] = deque(sorted(start))
    while q:
        n = q.popleft()
        if n in seen:
            continue
        if n == "__root__":
            for c in graph.get("__root__", set()):
                if c not in seen:
                    q.append(c)
            continue
        seen.add(n)
        for c in graph.get(n, set()):
            if c not in seen:
                q.append(c)
    return seen


def load_manifest_index() -> dict[str, FilePolicy]:
    policies: dict[str, FilePolicy] = {}
    if MANIFEST_INDEX.is_file():
        data = json.loads(read_text(MANIFEST_INDEX))
        files = data.get("files") or {}
        for rel, meta in files.items():
            policies[rel.replace("\\", "/")] = FilePolicy(
                rel=rel.replace("\\", "/"),
                mode=str(meta.get("mode") or "dynamic"),
                strip_policy=str(meta.get("strip_policy") or "coverage_required"),
                entries=list(meta.get("entries") or []),
                keep=list(meta.get("keep") or []),
                notes=str(meta.get("notes") or ""),
            )
    # Per-file detailed manifests
    if MANIFEST_DIR.is_dir():
        for fp in MANIFEST_DIR.glob("*.manifest.json"):
            data = json.loads(read_text(fp))
            rel = str(data.get("file") or "").replace("\\", "/")
            if not rel:
                continue
            base = policies.get(rel, FilePolicy(rel=rel))
            base.mode = str(data.get("mode") or base.mode)
            base.strip_policy = str(data.get("strip_policy") or base.strip_policy)
            base.entries = sorted(set(base.entries) | set(data.get("entries") or []))
            base.keep = sorted(set(base.keep) | set(data.get("keep") or []))
            base.notes = str(data.get("notes") or base.notes)
            policies[rel] = base
    return policies


def load_runtime_coverage() -> dict[str, set[str]]:
    """
    Expected shape:
      { "generated_at": "...", "files": { "assets/js/profile.js": ["openVegaPanel", ...] } }
    """
    if not COVERAGE_PATH.is_file():
        return {}
    try:
        data = json.loads(read_text(COVERAGE_PATH))
    except json.JSONDecodeError:
        return {}
    out: dict[str, set[str]] = {}
    for rel, names in (data.get("files") or {}).items():
        out[str(rel).replace("\\", "/")] = set(names or [])
    return out


def extract_boot_entries(text: str, fn_names: set[str]) -> set[str]:
    """Functions invoked from DOMContentLoaded / immediate boot windows."""
    out: set[str] = set()
    for m in re.finditer(r"DOMContentLoaded[\s\S]{0,4000}", text):
        chunk = m.group(0)
        for name in fn_names:
            if re.search(rf"\b{re.escape(name)}\s*\(", chunk):
                out.add(name)
    # document.ready / jQuery ready
    for m in re.finditer(r"(?:\$|jQuery)\s*\(\s*function\s*\([^)]*\)\s*\{[\s\S]{0,4000}", text):
        chunk = m.group(0)
        for name in fn_names:
            if re.search(rf"\b{re.escape(name)}\s*\(", chunk):
                out.add(name)
    return out


def policy_for(rel: str, text: str, policies: dict[str, FilePolicy] | None = None) -> FilePolicy:
    rel = rel.replace("\\", "/")
    policies = policies if policies is not None else load_manifest_index()
    ann_entries, ann_keep, ann_dynamic = parse_annotations(text)
    base = policies.get(rel) or FilePolicy(
        rel=rel,
        mode="dynamic" if (ann_dynamic or rel in DEFAULT_DYNAMIC_GLOBS) else "static",
        strip_policy="coverage_required" if (ann_dynamic or rel in DEFAULT_DYNAMIC_GLOBS) else "high",
    )
    win_entries = extract_window_entries(text)
    spans = extract_functions(text)
    boot_entries = extract_boot_entries(text, set(spans))
    base.entries = sorted(set(base.entries) | ann_entries | win_entries | boot_entries)
    base.keep = sorted(set(base.keep) | ann_keep)
    if ann_dynamic:
        base.mode = "dynamic"
        if base.strip_policy == "high":
            base.strip_policy = "coverage_required"
    return base


def analyze_file(rel: str, path: Path | None = None) -> ReachReport:
    rel = rel.replace("\\", "/")
    path = path or (ROOT / rel)
    text = read_text(path)
    policy = policy_for(rel, text)
    spans = extract_functions(text)
    fn_names = set(spans)
    graph = build_local_refs(text, fn_names)
    entries = set(policy.entries) | set(policy.keep)
    cov = load_runtime_coverage().get(rel, set())
    entries |= cov
    # Re-include boot entries in case policy was cached without them
    entries |= extract_boot_entries(text, fn_names)
    reached = reachable(entries, graph)
    reached |= set(policy.keep)
    unreachable = sorted(fn_names - reached)
    note = (
        f"mode={policy.mode} strip_policy={policy.strip_policy}; "
        f"entries={len(entries)} funcs={len(fn_names)} reachable={len(reached & fn_names)} "
        f"unreachable={len(unreachable)} coverage={len(cov)}"
    )
    return ReachReport(
        rel=rel,
        mode=policy.mode,
        entries=sorted(entries),
        keep=list(policy.keep),
        functions=sorted(fn_names),
        reachable=sorted(reached & fn_names),
        unreachable=unreachable,
        coverage_hits=sorted(cov),
        confidence_note=note,
    )


def may_strip_symbol(rel: str, policy: FilePolicy, has_coverage: bool) -> bool:
    if policy.mode == "dynamic" and policy.strip_policy == "never":
        return False
    if policy.strip_policy == "coverage_required" and not has_coverage:
        return False
    if policy.mode == "dynamic" and policy.strip_policy == "coverage_required" and has_coverage:
        return True
    if policy.mode in {"static", "hybrid"} and policy.strip_policy == "high":
        return True
    return False


def ensure_default_index() -> None:
    MANIFEST_DIR.mkdir(parents=True, exist_ok=True)
    if MANIFEST_INDEX.is_file():
        return
    files = {}
    for rel in DEFAULT_DYNAMIC_GLOBS:
        files[rel] = {
            "mode": "dynamic",
            "strip_policy": "coverage_required",
            "entries": [],
            "keep": [],
            "notes": "Auto-seeded dynamic surface; regenerate with tools/generate_js_manifests.py",
        }
    payload = {
        "version": 1,
        "description": "Long-term dynamic JS policy index. Prevents false-positive symbol stripping.",
        "coverage_file": "tools/reports/js-runtime-usage.json",
        "annotation_docs": {
            "@entry": "Mark a function as a live entry point",
            "@keep": "Never report/strip this symbol",
            "@dynamic-file": "Treat entire file as dynamic (coverage required to strip)",
        },
        "files": files,
    }
    MANIFEST_INDEX.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n", encoding="utf-8")
