import os
import re
import sys
from pathlib import Path
from typing import Iterable, List, Set, Tuple, Dict


SOURCE_EXTENSIONS = {
    ".html", ".htm",
    ".php", ".phtml",
    ".js", ".jsx",
    ".ts", ".tsx",
    ".vue",
    ".twig", ".blade.php",
    ".ejs",
}

IGNORE_DIRS = {
    "node_modules",
    ".git",
    ".idea",
    ".vscode",
    "dist",
    "build",
    "__pycache__",
}


# --- Regex'leri önceden derleyerek hız kazanalım ---
CSS_CLASS_PATTERN = re.compile(r"\.([A-Za-z0-9_-]+)")

ATTR_CLASS_PATTERN = re.compile(
    r"""class(?:Name)?\s*=\s*["']([^"']+)["']""",
    re.IGNORECASE,
)

CLASSLIST_CALL_PATTERN = re.compile(
    r"classList\.(?:add|remove|toggle)\s*\(([^)]*)\)"
)

QUOTED_STRING_PATTERN = re.compile(r"""["']([^"']+)["']""")

QS_PATTERN = re.compile(
    r"querySelector(?:All)?\s*\(\s*['\"]([^'\"]+)['\"]\s*\)"
)

CLASS_IN_SELECTOR_PATTERN = re.compile(r"\.([A-Za-z0-9_-]+)")

VALID_CLASS_TOKEN = re.compile(r"^[A-Za-z0-9_-]+$")

# Selector pozisyonlarından class çekmek için:
# ilk karakter harf veya _ olmalı (rakamla başlayanlar CSS değeri kalıntısı)
CSS_SELECTOR_CLASS_PATTERN = re.compile(r"\.([A-Za-z_][A-Za-z0-9_-]*)")


def walk_project_files(root: Path) -> Tuple[List[Path], List[Path]]:
    """
    Projeyi bir kez dolaş, iki liste döndür:
    - css_files: .css uzantılı dosyalar
    - source_files: class kullanımını arayacağımız diğer dosyalar
    """
    css_files: List[Path] = []
    source_files: List[Path] = []

    root_str = str(root)

    for dirpath, dirnames, filenames in os.walk(root_str):
        # IGNORE_DIRS içerisindeki klasörleri buda
        dirnames[:] = [d for d in dirnames if d not in IGNORE_DIRS]

        for filename in filenames:
            full_path = Path(dirpath) / filename
            name_lower = filename.lower()
            suffix = full_path.suffix.lower()

            if suffix == ".css":
                css_files.append(full_path)
                continue

            # SOURCE_EXTENSIONS kontrolü (.blade.php gibi çoklu uzantı desteği)
            if suffix in SOURCE_EXTENSIONS or any(
                name_lower.endswith(ext) for ext in SOURCE_EXTENSIONS
            ):
                source_files.append(full_path)

    return css_files, source_files


def extract_css_classes(css_text: str) -> Set[str]:
    """
    CSS dosyasından sadece SELECTOR pozisyonlarındaki class isimlerini çeker.

    Property değerlerindeki ondalık sayılar  (0.5, 0.25rem),
    URL'lerdeki uzantılar (.css, .woff),
    alan adı parçaları (.com, .org)
    ve yorum satırları görmezden gelinir.
    """
    text = re.sub(r"/\*.*?\*/", "", css_text, flags=re.DOTALL)
    text = re.sub(r"url\s*\([^)]*\)", "url()", text)
    text = re.sub(r'"[^"]*"', '""', text)
    text = re.sub(r"'[^']*'", "''", text)

    classes: Set[str] = set()

    # Her '{' öncesindeki metin potansiyel bir selector.
    # split('{') ile parçala; her parçanın son '}' den sonrası selector kısmı.
    segments = text.split("{")

    for seg in segments[:-1]:
        last_close = seg.rfind("}")
        selector = seg[last_close + 1 :] if last_close >= 0 else seg
        selector = selector.strip()

        # @font-face, @keyframes, @media satırları selector değil — atla
        # (ama @media içindeki gerçek selector'lar sonraki segment'lerde yakalanır)
        if selector.startswith("@"):
            continue

        for m in CSS_SELECTOR_CLASS_PATTERN.finditer(selector):
            classes.add(m.group(1))

    return classes


def extract_used_classes_in_source(text: str) -> Set[str]:
    """
    HTML/JS/TS/PHP vs. metninden kullanılan class isimlerini çeker.
    - class / className attribute'leri
    - classList.add/remove/toggle(...)
    - querySelector('.foo'), querySelectorAll('.bar')
    """
    used: Set[str] = set()

    # 1) class / className attribute'leri
    for match in ATTR_CLASS_PATTERN.finditer(text):
        value = match.group(1)
        for token in value.split():
            if VALID_CLASS_TOKEN.match(token):
                used.add(token)

    # 2) classList.* çağrıları
    for match in CLASSLIST_CALL_PATTERN.finditer(text):
        args = match.group(1)
        for q in QUOTED_STRING_PATTERN.finditer(args):
            token = q.group(1)
            for part in token.split():
                if VALID_CLASS_TOKEN.match(part):
                    used.add(part)

    # 3) querySelector / querySelectorAll
    for match in QS_PATTERN.finditer(text):
        selector = match.group(1)
        for cls in CLASS_IN_SELECTOR_PATTERN.findall(selector):
            if VALID_CLASS_TOKEN.match(cls):
                used.add(cls)

    return used


def build_global_used_classes(source_files: Iterable[Path]) -> Set[str]:
    """Kaynak dosyalardan global kullanılan class set'ini üretir."""
    global_used: Set[str] = set()

    for path in source_files:
        try:
            text = path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            # okunamayan dosyaları sessizce atla
            continue

        used_here = extract_used_classes_in_source(text)
        if used_here:
            global_used.update(used_here)

    return global_used


def analyze_css_files(
    css_files: Iterable[Path], global_used_classes: Set[str], root: Path
) -> List[Dict[str, object]]:
    """Her CSS dosyası için kullanılma/kullanılmama istatistiklerini hesaplar."""
    results: List[Dict[str, object]] = []

    for path in css_files:
        try:
            css_text = path.read_text(encoding="utf-8", errors="ignore")
        except Exception:
            continue

        css_classes = extract_css_classes(css_text)
        if not css_classes:
            continue

        used = {c for c in css_classes if c in global_used_classes}
        unused = css_classes - used

        total = len(css_classes)
        used_count = len(used)
        unused_count = len(unused)

        used_percent = (used_count / total) * 100 if total else 0.0
        unused_percent = (unused_count / total) * 100 if total else 0.0

        results.append(
            {
                "path": path,
                "rel_path": path.relative_to(root),
                "total": total,
                "classes": sorted(css_classes),
                "unused_classes": sorted(unused),
                "used": used_count,
                "unused": unused_count,
                "used_percent": used_percent,
                "unused_percent": unused_percent,
            }
        )

    return results


def remove_simple_unused_rules(css_text: str, unused_classes: Set[str]) -> str:
    """
    Kullanılmayan class'lara ait CSS kural bloklarını güvenli şekilde kaldırır.

    Bir selector'daki TÜM class referansları unused kümesindeyse
    ilgili kural bloğu (selector + { … }) tamamen silinir.

    @font-face, @keyframes gibi @-rule blokları silinmez.
    @media gibi sarmalayıcı blokların içindeki kurallar ayrı ayrı kontrol edilir.
    Virgüllü grup selector'lerde tüm alt-selector'lar kontrol edilir;
    hepsi silinebilir durumdaysa blok silinir, aksi halde dokunulmaz.
    """
    if not unused_classes:
        return css_text

    # Yorumları ve string literal'leri boşluğa çevir (pozisyon korunsun)
    clean = re.sub(
        r"/\*.*?\*/", lambda m: " " * len(m.group()), css_text, flags=re.DOTALL
    )
    clean = re.sub(r"url\s*\([^)]*\)", lambda m: " " * len(m.group()), clean)
    clean = re.sub(r'"[^"]*"', lambda m: " " * len(m.group()), clean)
    clean = re.sub(r"'[^']*'", lambda m: " " * len(m.group()), clean)

    removals: List[Tuple[int, int]] = []
    i = 0
    length = len(clean)
    selector_start = 0

    while i < length:
        ch = clean[i]

        if ch == "{":
            selector = clean[selector_start:i].strip()

            if selector.startswith("@"):
                # @media vb. sarmalayıcı: içeri gir, iç kuralları ayrı işle
                i += 1
                selector_start = i
                continue

            sel_classes = set(CSS_SELECTOR_CLASS_PATTERN.findall(selector))

            if sel_classes and sel_classes.issubset(unused_classes):
                # Eşleşen kapanış ayracını bul
                depth = 1
                j = i + 1
                while j < length and depth > 0:
                    if clean[j] == "{":
                        depth += 1
                    elif clean[j] == "}":
                        depth -= 1
                    j += 1
                # Bloktan sonraki boş satırı da temizle
                while j < length and clean[j] in ("\n", "\r"):
                    j += 1
                removals.append((selector_start, j))
                i = j
                selector_start = j
                continue

            # Blok korunacak: kapanış ayracına atla
            depth = 1
            i += 1
            while i < length and depth > 0:
                if clean[i] == "{":
                    depth += 1
                elif clean[i] == "}":
                    depth -= 1
                i += 1
            selector_start = i
            continue

        elif ch == "}":
            # @-rule sarmalayıcı kapanışı
            selector_start = i + 1

        i += 1

    if not removals:
        return css_text

    parts: List[str] = []
    prev_end = 0
    for start, end in sorted(removals):
        parts.append(css_text[prev_end:start])
        prev_end = end
    parts.append(css_text[prev_end:])

    return "".join(parts)


def delete_unused_classes(results: List[Dict[str, object]]) -> None:
    """
    Analiz sonuçlarına göre, kullanıcı onayı alındıktan sonra
    CSS dosyalarındaki kullanılmayan basit class bloklarını siler.
    """
    total_deleted_blocks = 0

    for r in results:
        path = r["path"]  # type: ignore[index]
        unused_classes = set(r.get("unused_classes") or [])  # type: ignore[index]

        if not unused_classes:
            continue

        try:
            css_text = path.read_text(encoding="utf-8", errors="ignore")  # type: ignore[attr-defined]
        except Exception:
            continue

        new_css_text = remove_simple_unused_rules(css_text, unused_classes)

        if new_css_text != css_text:
            try:
                path.write_text(new_css_text, encoding="utf-8")  # type: ignore[attr-defined]
                # Tam olarak kaç blok silindiğini takip etmek maliyetli;
                # en azından bir şeylerin değiştiğini sayalım.
                total_deleted_blocks += 1
                print(f"Kullanılmayan basit class blokları silindi: {path}")
            except Exception as e:  # pragma: no cover - IO hatası için basit log
                print(f"Dosya yazılırken hata oluştu: {path} -> {e}")

    if total_deleted_blocks == 0:
        print("Silinecek uygun basit kullanılmayan class bloğu bulunamadı.")
    else:
        print(f"Toplam {total_deleted_blocks} CSS dosyasında düzenleme yapıldı.")


def print_report(results: List[Dict[str, object]], root: Path) -> None:
    """Detaylı dosya bazlı rapor + genel özet yazdırır."""
    if not results:
        print("Hiç CSS dosyasında class bulunamadı.")
        return

    total_css_classes = 0
    total_used = 0
    total_unused = 0

    for r in sorted(results, key=lambda x: str(x["rel_path"])):  # type: ignore[index]
        total_css_classes += int(r["total"])  # type: ignore[index]
        total_used += int(r["used"])  # type: ignore[index]
        total_unused += int(r["unused"])  # type: ignore[index]

        print(f"Dosya: {r['rel_path']}")  # type: ignore[index]
        print(f"  Toplam class     : {r['total']}")  # type: ignore[index]
        print(
            f"  Kullanılan       : {r['used']} ({r['used_percent']:.2f}%)"  # type: ignore[index]
        )
        print(
            f"  Kullanılmayan    : {r['unused']} ({r['unused_percent']:.2f}%)"  # type: ignore[index]
        )
        # Kullanılmayan class isimlerinin detay listesi
        unused_classes = r.get("unused_classes") or []  # type: ignore[index]
        if unused_classes:
            joined = ", ".join(unused_classes)
            print(f"  Kullanılmayan class isimleri: {joined}")
        print("-" * 60)

    print()
    print("GENEL ÖZET")
    print(f"  Toplam CSS class sayısı : {total_css_classes}")
    print(f"  Toplam kullanılan       : {total_used}")
    print(f"  Toplam kullanılmayan    : {total_unused}")
    if total_css_classes:
        used_ratio = total_used / total_css_classes * 100
        unused_ratio = total_unused / total_css_classes * 100
        print(f"  Kullanım oranı          : {used_ratio:.2f}%")
        print(f"  Kullanılmama oranı      : {unused_ratio:.2f}%")


def main() -> None:
    # Argüman verilirse onu kök dizin kabul et, yoksa script'in bulunduğu klasör (scripts/ ise bir üst = proje kökü)
    if len(sys.argv) > 1:
        root = Path(sys.argv[1]).resolve()
    else:
        root = Path(__file__).resolve().parent
        if root.name == "scripts":
            root = root.parent

    if not root.exists() or not root.is_dir():
        print(f"Kök klasör bulunamadı: {root}")
        sys.exit(1)

    print(f"Proje kökü: {root}")

    css_files, source_files = walk_project_files(root)

    print(
        f"Bulunan CSS dosyaları: {len(css_files)}, "
        f"tarancak kaynak dosya: {len(source_files)}"
    )
    print("Global kullanılan class'lar taranıyor (HTML/PHP/JS/TS vs.)...")
    global_used = build_global_used_classes(source_files)
    print(f"Bulunan farklı kullanılan class sayısı (global): {len(global_used)}")
    print()

    print("CSS dosyaları analiz ediliyor...")
    results = analyze_css_files(css_files, global_used, root)

    print_report(results, root)

    # Kullanılmayan class'ları silme için interaktif mod
    total_unused = sum(int(r["unused"]) for r in results)
    if total_unused > 0:
        while True:
            answer = input(
                f"\nToplam {total_unused} kullanılmayan CSS class bulundu. "
                "Bunların basit olanlarını CSS dosyalarından silmek ister misiniz? (y/n): "
            ).strip().lower()

            if answer in {"y", "n"}:
                break
            print("Lütfen sadece 'y' veya 'n' giriniz.")

        if answer == "y":
            delete_unused_classes(results)
        else:
            print("Kullanılmayan class'lar silinmedi.")
    else:
        print("Kullanılmayan CSS class bulunamadı, silme işlemi gerekmedi.")


if __name__ == "__main__":
    main()

