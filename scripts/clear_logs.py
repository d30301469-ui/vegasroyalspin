import os
from pathlib import Path


def clear_log_files(root_dir: str) -> None:
    """
    Verilen klasör ve alt klasörlerindeki tüm .log dosyalarının içeriğini temizler.
    Dosyaları silmez, sadece boyutlarını 0 byte'a düşürür.
    """
    root_path = Path(root_dir)

    if not root_path.exists():
        print(f"Geçersiz dizin: {root_dir}")
        return

    for log_file in root_path.rglob("*.log"):
        try:
            # Dosya içeriğini boşalt
            with log_file.open("w", encoding="utf-8"):
                pass
            print(f"Temizlendi: {log_file}")
        except Exception as exc:  # noqa: BLE001
            print(f"Hata ({log_file}): {exc}")


if __name__ == "__main__":
    # Scriptin bulunduğu dizinden itibaren tüm .log dosyalarını temizle (scripts/ içindeyse proje kökü = bir üst)
    project_root = os.path.dirname(os.path.abspath(__file__))
    if os.path.basename(project_root) == "scripts":
        project_root = os.path.dirname(project_root)
    clear_log_files(project_root)

