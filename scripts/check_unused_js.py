#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Projede kullanılmayan JavaScript dosyalarını tespit eden script.

Tüm .js dosyalarını tarar ve HTML/PHP/CSS/JS içinde referans edilmeyenleri raporlar.

Referans türleri:
  - <script src="...">
  - import ... from '...'
  - require('...')
  - import('...')  (dynamic import)
  - createElement('script').src
  - $.getScript()
  - loadJS / loadScript benzeri çağrılar

Kullanım:
    python check_unused_js.py [proje_kök_dizini]
    python check_unused_js.py --json
    python check_unused_js.py --list-all   # Tüm JS dosyalarını listele (kullanılan + kullanılmayan)
    python check_unused_js.py --show-refs  # Her kullanılan dosyanın nerede referans edildiğini göster
"""

import os
import re
import sys
import json
from pathlib import Path
from typing import Dict, List, Optional, Set, Tuple
from urllib.parse import unquote

# Windows konsol UTF-8
if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except Exception:
        pass

IGNORE_DIRS = {
    ".git", "node_modules", ".idea", ".vscode",
    "dist", "build", "__pycache__", "vendor", "logs",
    "cert.gcb.cw",  # Ayrı sertifika projesi
}

SOURCE_EXTENSIONS = {".html", ".htm", ".php", ".phtml", ".js", ".jsx", ".ts", ".tsx", ".css", ".vue"}

# Regex kalıpları - JS referansları
RE_SCRIPT_SRC = re.compile(
    r'<script[^>]+src\s*=\s*["\']([^"\']+\.js[^"\']*)["\']',
    re.IGNORECASE
)
RE_HREF_SRC = re.compile(
    r'(?:href|src)\s*=\s*["\']([^"\']+\.js[^"\']*)["\']',
    re.IGNORECASE
)
RE_SCRIPT_SRC_GENERIC = re.compile(
    r'(?:href|src)\s*=\s*["\']([^"\']+)["\']',
    re.IGNORECASE
)
RE_JS_IMPORT = re.compile(
    r'import\s+.*?from\s+["\']([^"\']+)["\']|import\s+["\']([^"\']+)["\']',
    re.IGNORECASE
)
RE_JS_REQUIRE = re.compile(
    r'require\s*\(\s*["\']([^"\']+)["\']\s*\)',
    re.IGNORECASE
)
RE_JS_DYNAMIC_IMPORT = re.compile(
    r'import\s*\(\s*["\']([^"\']+)["\']\s*\)',
    re.IGNORECASE
)
RE_GET_SCRIPT = re.compile(
    r'\$\.getScript\s*\(\s*["\']([^"\']+)["\']',
    re.IGNORECASE
)
RE_LOAD_SCRIPT = re.compile(
    r'(?:loadScript|loadJS|load_script)\s*\(\s*["\']([^"\']+)["\']',
    re.IGNORECASE
)
RE_CREATE_SCRIPT = re.compile(
    r'(?:\.src|src\s*=\s*)\s*["\']([^"\']+\.js[^"\']*)["\']',
    re.IGNORECASE
)
RE_WORKER = re.compile(
    r'new\s+Worker\s*\(\s*["\']([^"\']+)["\']',
    re.IGNORECASE
)
RE_SCRIPT_TAG_BARE = re.compile(
    r'<script[^>]+src\s*=\s*["\']([^"\']+)["\']',
    re.IGNORECASE
)


def normalize_js_ref(path: str, base_dir: Path, root: Path) -> Set[Path]:
    """Referans string'ini proje içindeki JS dosya Path'lerine çevirir."""
    result: Set[Path] = set()
    path = path.strip()
    if not path or path.startswith(("#", "javascript:", "data:", "http://", "https://", "//")):
        return result

    path = path.split("?")[0].split("#")[0].strip()
    if not path:
        return result

    if "://" in path or path.startswith("//"):
        return result

    if path.startswith("/"):
        path = path[1:]

    path = path.replace("\\", "/")

    # .js uzantısı yoksa ekle
    if not path.lower().endswith(".js"):
        path = path + ".js"

    candidates = [path]
    for cand in candidates:
        full = root / cand
        if full.exists() and full.is_file():
            result.add(full.resolve())

    # Relative path
    if not path.startswith("/") and ".." not in path:
        for parent in [base_dir, root]:
            full = parent / path
            if full.exists() and full.is_file():
                result.add(full.resolve())
    elif not path.startswith("/") and ("/" in path or ".." in path):
        try:
            resolved = (base_dir / path).resolve()
            if resolved.exists() and resolved.is_file():
                result.add(resolved)
        except (OSError, ValueError):
            pass

    return result


def extract_js_references(text: str, file_path: Path, root: Path) -> Set[Path]:
    """Dosya içeriğinden JS referanslarını çıkarır."""
    refs: Set[Path] = set()
    base_dir = file_path.parent

    patterns = [
        (RE_SCRIPT_SRC, 1),
        (RE_HREF_SRC, 1),
        (RE_SCRIPT_SRC_GENERIC, 1),  # .js olmayan path'ler için
        (RE_JS_IMPORT, 1), (RE_JS_IMPORT, 2),
        (RE_JS_REQUIRE, 1),
        (RE_JS_DYNAMIC_IMPORT, 1),
        (RE_GET_SCRIPT, 1),
        (RE_LOAD_SCRIPT, 1),
        (RE_CREATE_SCRIPT, 1),
        (RE_WORKER, 1),
        (RE_SCRIPT_TAG_BARE, 1),
    ]

    for regex, group_idx in patterns:
        for m in regex.finditer(text):
            g = m.group(group_idx) if group_idx <= len(m.groups()) else None
            if g and (".js" in g.lower() or regex == RE_SCRIPT_SRC_GENERIC or regex == RE_SCRIPT_TAG_BARE):
                for p in normalize_js_ref(g, base_dir, root):
                    refs.add(p)

    return refs


def collect_js_files(root: Path) -> List[Path]:
    """Projedeki tüm .js dosyalarını toplar."""
    js_files: List[Path] = []
    root_str = str(root)

    for dirpath, dirnames, filenames in os.walk(root_str):
        dirnames[:] = [d for d in dirnames if d not in IGNORE_DIRS]
        for filename in filenames:
            if filename.lower().endswith(".js"):
                full = Path(dirpath) / filename
                try:
                    full.relative_to(root)
                except ValueError:
                    continue
                js_files.append(full.resolve())

    return sorted(js_files, key=lambda p: str(p))


def collect_source_files(root: Path) -> List[Path]:
    """Referans aranacak kaynak dosyaları toplar."""
    sources: List[Path] = []
    root_str = str(root)

    for dirpath, dirnames, filenames in os.walk(root_str):
        dirnames[:] = [d for d in dirnames if d not in IGNORE_DIRS]
        for filename in filenames:
            full = Path(dirpath) / filename
            try:
                full.relative_to(root)
            except ValueError:
                continue
            suffix = full.suffix.lower()
            if suffix in SOURCE_EXTENSIONS:
                sources.append(full.resolve())

    return sources


def find_used_js(source_files: List[Path], root: Path, ref_map: Optional[Dict[Path, List[Path]]] = None) -> Set[Path]:
    """Tüm kaynaklardan referans edilen JS dosyalarını bulur."""
    used: Set[Path] = set()
    if ref_map is not None:
        ref_map.clear()

    for path in source_files:
        try:
            text = path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            continue
        refs = extract_js_references(text, path, root)
        used.update(refs)
        if ref_map is not None:
            for r in refs:
                ref_map.setdefault(r, []).append(path)

    return used


def main() -> None:
    path_args = [a for a in sys.argv[1:] if not a.startswith("--")]
    output_json = "--json" in sys.argv
    list_all = "--list-all" in sys.argv
    show_refs = "--show-refs" in sys.argv

    _script_dir = Path(__file__).resolve().parent
    root = Path(path_args[0]).resolve() if path_args else (_script_dir.parent if _script_dir.name == "scripts" else _script_dir)

    if not root.exists() or not root.is_dir():
        print(f"Kök klasör bulunamadı: {root}", file=sys.stderr)
        sys.exit(1)

    print(f"Proje kökü: {root}", file=sys.stderr)
    print("JavaScript dosyaları taranıyor...", file=sys.stderr)

    js_files = collect_js_files(root)
    source_files = collect_source_files(root)
    ref_map: Dict[Path, List[Path]] = {}
    used = find_used_js(source_files, root, ref_map)

    js_set = set(js_files)
    unused = sorted(js_set - used, key=lambda p: str(p))
    used_js = sorted(js_set & used, key=lambda p: str(p))

    if output_json:
        out = {
            "root": str(root),
            "total_js_files": len(js_files),
            "used_count": len(used_js),
            "unused_count": len(unused),
            "used": [str(p.relative_to(root)) for p in used_js],
            "unused": [str(p.relative_to(root)) for p in unused],
        }
        print(json.dumps(out, indent=2, ensure_ascii=False))
        return

    print("\n" + "=" * 70)
    print("JAVASCRIPT DOSYALARI RAPORU")
    print("=" * 70)
    print(f"\nToplam JS dosyası: {len(js_files)}")
    print(f"Kullanılan: {len(used_js)}")
    print(f"Kullanılmayan: {len(unused)}")

    if list_all:
        print("\n--- KULLANILAN JS DOSYALARI ---")
        for p in used_js:
            try:
                rel = p.relative_to(root)
            except ValueError:
                rel = p
            print(f"  ✓ {rel}")

    if show_refs:
        print("\n--- REFERANS KAYNAKLARI ---")
        for p in used_js:
            try:
                rel = p.relative_to(root)
            except ValueError:
                rel = p
            sources = ref_map.get(p, [])
            parts = []
            for s in sources[:5]:
                try:
                    parts.append(str(s.relative_to(root)))
                except ValueError:
                    parts.append(str(s))
            src_str = ", ".join(parts)
            if len(sources) > 5:
                src_str += f" (+{len(sources)-5} daha)"
            print(f"  {rel}")
            print(f"    ← {src_str}")

    if unused:
        print("\n--- KULLANILMAYAN JS DOSYALARI ---")
        for p in unused:
            try:
                rel = p.relative_to(root)
            except ValueError:
                rel = p
            print(f"  ✗ {rel}")
    else:
        print("\nTüm JavaScript dosyaları en az bir yerde referans ediliyor.")

    print("\n" + "=" * 70)


if __name__ == "__main__":
    main()
