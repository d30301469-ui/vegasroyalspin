#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Projede hiç referans edilmeyen (kullanılmayan) dosyaları tespit eden script.

Uzantı ve skip yok: her dosya hem referans aranacak kaynak hem de izlenecek
dosya olarak taranır; hiçbir dosya türü atlanmaz.

Kullanım:
    python scripts/check_unused_files.py [proje_kök_dizini]
    python scripts/check_unused_files.py --json
    python scripts/check_unused_files.py --exclude-ext webp

Not: Dinamik olarak yüklenen dosyalar (API'den gelen resimler vb.) tespit edilemez.
"""

import os
import re
import sys
import json
from pathlib import Path
from typing import Dict, List, Optional, Set

# Windows konsol UTF-8
if sys.platform == "win32":
    try:
        sys.stdout.reconfigure(encoding="utf-8")
        sys.stderr.reconfigure(encoding="utf-8")
    except Exception:
        pass


# Tarama dışı bırakılacak klasörler
IGNORE_DIRS = {
    ".git",
    "node_modules",
    ".idea",
    ".vscode",
    "dist",
    "build",
    "__pycache__",
    "vendor",
    "logs",
    "cert.gcb.cw",
    "drakon",
}

# Klasör adı eşleşmesi için küçük harf seti (Windows'ta os.walk farklı case dönebilir)
_IGNORE_DIRS_LOWER = {d.lower() for d in IGNORE_DIRS}

# Bu klasörlerdeki dosyalar "kullanılmıyor" sayılmaz (kullanıcı yüklemeleri, cache vb.)
IGNORE_TRACKABLE_PREFIXES = (
    "profile/uploads/",   # KYC, profil resimleri vb.
    "drakon/",           # drakon dizini (oyun görselleri vb.)
)

# Bu dosya adları raporda "kullanılmayan" olarak gösterilmez
IGNORE_FILENAMES = {".htaccess"}

# Doğrudan erişilebilir giriş noktaları (URL ile çağrılabilir)
ENTRY_POINT_FILES = {
    "index.php",
    "login.php",
    "register.php",
    "router.php",
    "slot.php",
    "livecasino.php",
    "promotions.php",
    "sportsbook.php",
    "pcsport.php",
    "mobile_bottom.php",
    "test.php",
    "database.php",
    "config.php",
}

# Regex kalıpları
RE_HREF_SRC = re.compile(
    r'(?:href|src)\s*=\s*["\']([^"\']+)["\']',
    re.IGNORECASE
)
RE_URL_CSS = re.compile(
    r'url\s*\(\s*["\']?([^"\')\s]+)["\']?\s*\)',
    re.IGNORECASE
)
RE_IMPORT_CSS = re.compile(
    r'@import\s+(?:url\s*\(\s*)?["\']?([^"\')\s;]+)["\']?\s*\)?',
    re.IGNORECASE
)
RE_PHP_INCLUDE = re.compile(
    r'(?:include|require|include_once|require_once)\s*(?:\(?\s*)?["\']([^"\']+)["\']',
    re.IGNORECASE
)
RE_PHP_INCLUDE_DIR = re.compile(
    r'(?:include|require|include_once|require_once)\s*(?:\(?\s*)?__DIR__\s*\.\s*["\']([^"\']+)["\']',
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
# PHP: glob('path/*.ext') — dizin kısmı kullanılıyor sayılır
RE_PHP_GLOB_PATTERN = re.compile(
    r'glob\s*\(\s*["\']([^"\']+)["\']',
    re.IGNORECASE
)
# PHP: $degisken = 'assets/images/ligler' gibi atama (glob ile kullanılmak üzere)
RE_PHP_VAR_ASSIGN_PATH = re.compile(
    r'\$(\w+)\s*=\s*["\']([^"\']+)["\']',
    re.IGNORECASE
)
# PHP: glob($degisken . '/*.ext') — atanan dizin kullanılıyor
RE_PHP_GLOB_VAR = re.compile(
    r'glob\s*\(\s*\$(\w+)\s*\.',
    re.IGNORECASE
)


def _path_key(p: Path) -> str:
    """Aynı dosyayı tek bir anahtarda toplar (Windows/Linux, büyük/küçük harf, slash)."""
    try:
        s = str(p.resolve())
    except (OSError, ValueError):
        s = str(p)
    return s.replace("\\", "/").lower()


def _glob_dir_from_pattern(pattern: str) -> Optional[str]:
    """
    glob('path/*.ext') veya path/*.{png,webp} gibi ifadeden dizin yolunu döndürür.
    Örn: 'assets/images/ligler/*.{png,jpg,webp}' -> 'assets/images/ligler'
    """
    pattern = pattern.strip().replace("\\", "/")
    if "/*" in pattern:
        return pattern.split("/*")[0].rstrip("/")
    if pattern.endswith("/"):
        return pattern.rstrip("/")
    return None


def _expand_glob_dirs_to_files(root: Path, dir_paths: Set[str]) -> Set[Path]:
    """Verdiğiniz dizin yollarındaki tüm dosyaları döndürür (tüm uzantılar)."""
    result: Set[Path] = set()
    for dir_path in dir_paths:
        dir_path = dir_path.strip().replace("\\", "/").lstrip("/")
        full_dir = root / dir_path
        try:
            if not full_dir.is_dir():
                continue
            for f in full_dir.iterdir():
                if f.is_file():
                    result.add(f.resolve())
        except (OSError, ValueError):
            pass
    return result


def normalize_path(path: str, base_dir: Path, root: Path) -> Set[Path]:
    """
    Bir referans string'ini proje içindeki olası Path'lere çevirir.
    Birden fazla eşleşme olabilir (örn. /assets/x ve assets/x).
    """
    result: Set[Path] = set()
    path = path.strip()
    if not path or path.startswith(("#", "javascript:", "mailto:", "tel:", "data:")):
        return result

    # Query string ve fragment kaldır
    path = path.split("?")[0].split("#")[0].strip()
    if not path:
        return result

    # CDN / harici URL'leri atla
    if "://" in path or path.startswith("//"):
        return result

    # Başındaki / kaldır (proje köküne göre)
    if path.startswith("/"):
        path = path[1:]

    path = path.replace("\\", "/")
    full = root / path
    if full.exists() and full.is_file():
        result.add(full.resolve())
    if not full.suffix and (root / (path + ".php")).exists():
        result.add((root / (path + ".php")).resolve())

    # base_dir'e göre relative path çözümle
    if not path.startswith("/") and ".." not in path and "/" not in path:
        # Sadece dosya adı - base_dir'de ara
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


def extract_references(text: str, file_path: Path, root: Path) -> Set[Path]:
    """Bir dosya içeriğinden referans edilen dosyaları çıkarır."""
    refs: Set[Path] = set()
    base_dir = file_path.parent

    # href, src
    for m in RE_HREF_SRC.finditer(text):
        for p in normalize_path(m.group(1), base_dir, root):
            refs.add(p)

    # url() - CSS
    for m in RE_URL_CSS.finditer(text):
        for p in normalize_path(m.group(1), base_dir, root):
            refs.add(p)

    # @import - CSS
    for m in RE_IMPORT_CSS.finditer(text):
        for p in normalize_path(m.group(1), base_dir, root):
            refs.add(p)

    # PHP include/require
    for m in RE_PHP_INCLUDE.finditer(text):
        inc_path = m.group(1)
        for p in normalize_path(inc_path, base_dir, root):
            refs.add(p)
        # __DIR__ . '/path' formatı
        try:
            resolved = (base_dir / inc_path).resolve()
            if resolved.exists() and resolved.is_file():
                refs.add(resolved)
        except (OSError, ValueError):
            pass

    for m in RE_PHP_INCLUDE_DIR.finditer(text):
        inc_path = m.group(1).replace("\\", "/").lstrip("/")
        try:
            resolved = (base_dir / inc_path).resolve()
            if resolved.exists() and resolved.is_file():
                refs.add(resolved)
        except (OSError, ValueError):
            pass

    # JS import/require
    for m in RE_JS_IMPORT.finditer(text):
        for g in m.groups():
            if g:
                for p in normalize_path(g, base_dir, root):
                    refs.add(p)
                break

    for m in RE_JS_REQUIRE.finditer(text):
        for p in normalize_path(m.group(1), base_dir, root):
            refs.add(p)

    for m in RE_JS_DYNAMIC_IMPORT.finditer(text):
        for p in normalize_path(m.group(1), base_dir, root):
            refs.add(p)

    # PHP: glob() ile dinamik yüklenen dizinler (örn. assets/images/ligler/*.webp)
    if file_path.suffix.lower() in (".php", ".phtml"):
        glob_dirs: Set[str] = set()
        # $var = 'path/to/dir' atamalarını topla (dizin gibi görünenler)
        var_to_path: Dict[str, str] = {}
        for m in RE_PHP_VAR_ASSIGN_PATH.finditer(text):
            var_name, path = m.group(1), m.group(2).strip().replace("\\", "/")
            if "*" not in path and "/" in path and not path.startswith(("http", "//")):
                var_to_path[var_name] = path
        # glob('path/*.ext') — doğrudan pattern
        for m in RE_PHP_GLOB_PATTERN.finditer(text):
            pattern = m.group(1)
            dir_path = _glob_dir_from_pattern(pattern)
            if dir_path:
                glob_dirs.add(dir_path)
        # glob($var . '/*...') — değişkene atanmış dizin
        for m in RE_PHP_GLOB_VAR.finditer(text):
            var_name = m.group(1)
            if var_name in var_to_path:
                glob_dirs.add(var_to_path[var_name])
        refs.update(_expand_glob_dirs_to_files(root, glob_dirs))

    return refs


def collect_project_files(root: Path) -> List[Path]:
    """Projedeki tüm dosyaları toplar (kaynak ve izlenecek tek liste)."""
    files: List[Path] = []
    for dirpath, dirnames, filenames in os.walk(str(root)):
        dirnames[:] = [d for d in dirnames if d.lower() not in _IGNORE_DIRS_LOWER]
        for filename in filenames:
            full = Path(dirpath) / filename
            try:
                full.relative_to(root)
            except ValueError:
                continue
            files.append(full.resolve())
    return files


def find_used_files(files: List[Path], root: Path) -> Set[Path]:
    """Tüm dosyalardan referans edilen dosyaları toplar."""
    used: Set[Path] = set()
    for path in files:
        try:
            text = path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            continue

        refs = extract_references(text, path, root)
        used.update(refs)

    return used


def get_entry_points(root: Path) -> Set[Path]:
    """Doğrudan erişilebilir giriş noktalarını döner."""
    entry: Set[Path] = set()
    for name in ENTRY_POINT_FILES:
        p = root / name
        if p.exists() and p.is_file():
            entry.add(p.resolve())

    # Router'dan özel route'lar
    router_specials = [
        root / "api" / "drakon.php",
        root / "api" / "drakon_game.php",
        root / "gold_api" / "api.php",
        root / "tbs2" / "api.php",
        root / "signup_tracker.php",
    ]
    for p in router_specials:
        if p.exists() and p.is_file():
            entry.add(p.resolve())

    # Kök dizindeki tüm .php dosyaları potansiyel entry point
    for f in root.iterdir():
        if f.is_file() and f.suffix.lower() == ".php":
            entry.add(f.resolve())

    return entry


def main() -> None:
    exclude_exts: Set[str] = set()
    path_args: List[str] = []
    i = 1  # sys.argv[0] = script adı
    while i < len(sys.argv):
        a = sys.argv[i]
        if a == "--json":
            pass
        elif a == "--exclude-ext" and i + 1 < len(sys.argv):
            exclude_exts.add("." + sys.argv[i + 1].lstrip(".").lower())
            i += 1
        elif not a.startswith("--"):
            path_args.append(a)
        i += 1

    output_json = "--json" in sys.argv
    if path_args:
        root = Path(path_args[0]).resolve()
    else:
        root = Path(__file__).resolve().parent
        if root.name == "scripts":
            root = root.parent

    if not root.exists() or not root.is_dir():
        print(f"Kök klasör bulunamadı: {root}", file=sys.stderr)
        sys.exit(1)

    print(f"Proje kökü: {root}", file=sys.stderr)
    print("Dosyalar taranıyor...", file=sys.stderr)

    files = collect_project_files(root)
    print(f"  Dosya sayısı: {len(files)}", file=sys.stderr)

    used = find_used_files(files, root)
    used.update(get_entry_points(root))
    used_keys = {_path_key(p) for p in used}

    unused_raw = [p for p in files if _path_key(p) not in used_keys]

    def should_skip(p: Path) -> bool:
        if p.name.lower() in IGNORE_FILENAMES:
            return True
        try:
            rel = str(p.relative_to(root)).replace("\\", "/")
            if any(rel.startswith(prefix) for prefix in IGNORE_TRACKABLE_PREFIXES):
                return True
            if exclude_exts and p.suffix.lower() in exclude_exts:
                return True
        except ValueError:
            pass
        return False

    unused = sorted((p for p in unused_raw if not should_skip(p)), key=lambda p: str(p))

    # Rapor
    if output_json:
        out = {
            "root": str(root),
            "total_files": len(files),
            "total_used": len(files) - len(unused),
            "total_unused": len(unused),
            "unused_files": [str(p.relative_to(root)) for p in unused],
        }
        print(json.dumps(out, indent=2, ensure_ascii=False))
    else:
        print("\n" + "=" * 70)
        print("KULLANILMAYAN DOSYALAR")
        print("=" * 70)

        if not unused:
            print("\nTüm izlenen dosyalar en az bir yerde referans ediliyor.")
            return

        # Uzantıya göre grupla
        by_ext: Dict[str, List[Path]] = {}
        for p in unused:
            ext = p.suffix.lower() or "(uzantısız)"
            by_ext.setdefault(ext, []).append(p)

        for ext in sorted(by_ext.keys()):
            files = by_ext[ext]
            print(f"\n--- {ext} ({len(files)} dosya) ---")
            for p in files:
                try:
                    rel = p.relative_to(root)
                except ValueError:
                    rel = p
                print(f"  {rel}")

        print("\n" + "=" * 70)
        print(f"ÖZET: Toplam {len(unused)} kullanılmayan dosya tespit edildi.")
        print("=" * 70)


if __name__ == "__main__":
    main()
