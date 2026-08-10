#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Rename/move all project CSS into assets/css with section-prefixed names,
then rewrite references across the codebase.
"""
from __future__ import annotations

import json
import re
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
CSS_DIR = ROOT / "assets" / "css"
MOBILE_CSS_DIR = ROOT / "mobile" / "assets" / "css"
REPORT = ROOT / "tools" / "reports" / "css-rename-map.json"

# old relative path (from repo root) -> new filename under assets/css/
RENAME_MAP: dict[str, str] = {
    # site / global
    "assets/css/global.css": "site-global.css",
    "assets/css/bootstrap-utils.css": "site-bootstrap-utils.css",
    "assets/css/components.css": "site-components.css",
    "assets/css/responsive.css": "site-responsive.css",
    "assets/css/modal.css": "site-modal.css",

    # layout
    "assets/css/header.css": "layout-header.css",
    "assets/css/sidebar.css": "layout-sidebar.css",
    "assets/css/footer-bc.css": "layout-footer.css",
    "assets/css/footer-bc-icons.css": "layout-footer-icons.css",

    # auth
    "assets/css/login.css": "auth-login.css",
    "assets/css/login-modal.css": "auth-login-modal.css",
    "assets/css/register.css": "auth-register.css",
    "assets/css/register-modal.css": "auth-register-modal.css",
    "assets/css/auth-sliders.css": "auth-sliders.css",
    "assets/css/reset-password.css": "auth-reset-password.css",

    # home
    "assets/css/home.css": "home.css",
    "assets/css/jackpot.css": "home-jackpot.css",
    "assets/css/winners.css": "home-winners.css",
    "assets/css/slider.css": "home-slider.css",
    "assets/css/slider-mobile-bc.css": "home-slider-mobile.css",

    # casino
    "assets/css/slots.css": "casino-slots.css",
    "assets/css/bc-cm622-slots.css": "casino-slots-cm622.css",
    "assets/css/bc-cm622-bgaming.css": "casino-bgaming-cm622.css",
    "assets/css/bgaming-lobby-motion.css": "casino-bgaming-motion.css",
    "assets/css/livecasino.css": "casino-live.css",
    "assets/css/bc-cm622-livecasino.css": "casino-live-cm622.css",

    # profile
    "assets/css/profile.css": "profile.css",
    "assets/css/bc-cm622-profile.css": "profile-cm622.css",
    "assets/css/bc-cm622-profile-fix.css": "profile-cm622-fix.css",

    # promotions
    "assets/css/promosyonlar.css": "promotions.css",
    "assets/css/bonus-detail-modal.css": "promotions-bonus-modal.css",

    # pages
    "assets/css/beni-ara.css": "page-beni-ara.css",
    "assets/css/maintenance.css": "page-maintenance.css",

    # mobile (already in assets/css)
    "assets/css/mobile_bottom.css": "mobile-bottom.css",
    "assets/css/mobile-right-sheet.css": "mobile-right-sheet.css",
    "assets/css/mobile-smart-panel.css": "mobile-smart-panel.css",
    "assets/css/bc-mobile.css": "mobile-bc.css",
    "assets/css/bc-mobile-custom.css": "mobile-bc-custom.css",
    "assets/css/bc-mobile-header-original.css": "mobile-bc-header.css",
    "assets/css/bc-mobile-index.css": "mobile-bc-index.css",

    # vendor / misc
    "assets/css/daterangepicker.css": "vendor-daterangepicker.css",
    "assets/css/swiper-bundle.min.css": "vendor-swiper.css",
    "assets/sports-icon.css": "sports-icon.css",

    # mobile/assets/css → assets/css (section-prefixed)
    "mobile/assets/css/base.css": "mobile-base.css",
    "mobile/assets/css/header.css": "mobile-header.css",
    "mobile/assets/css/menu.css": "mobile-menu.css",
    "mobile/assets/css/home.css": "mobile-home.css",
    "mobile/assets/css/home-widgets.css": "mobile-home-widgets.css",
    "mobile/assets/css/livecasino.css": "mobile-live.css",
    "mobile/assets/css/slots.css": "mobile-slots.css",
    "mobile/assets/css/bottom-bar.css": "mobile-bottom-bar.css",
    "mobile/assets/css/footer.css": "mobile-footer.css",
    "mobile/assets/css/auth-modals.css": "mobile-auth-modals.css",
    "mobile/assets/css/profile-panel.css": "mobile-profile-panel.css",
    "mobile/assets/css/mobile-right-sheet.css": "mobile-right-sheet-extra.css",
    "mobile/assets/css/beni-ara.css": "mobile-beni-ara.css",
}

TEXT_EXTS = {
    ".php", ".js", ".mjs", ".cjs", ".html", ".htm", ".phtml",
    ".css", ".md", ".json", ".txt", ".yml", ".yaml", ".xml",
    ".svg", ".vue", ".ts", ".tsx", ".jsx",
}

SKIP_DIRS = {
    ".git", "node_modules", "vendor", ".venv", "__pycache__",
    "storage", "logs", "cache", "_shots", "tools/css_quarantine",
    "tools/reports", "_ref",
}


def should_skip(path: Path) -> bool:
    rel = path.as_posix()
    parts = set(path.parts)
    if parts & {".git", "node_modules", "vendor", ".venv", "__pycache__", "storage", "logs", "_shots", "_ref"}:
        return True
    if "tools/css_quarantine" in rel.replace("\\", "/") or "tools/reports" in rel.replace("\\", "/"):
        return True
    return False


def main() -> int:
    CSS_DIR.mkdir(parents=True, exist_ok=True)

    # Validate uniqueness of targets
    targets = list(RENAME_MAP.values())
    if len(targets) != len(set(targets)):
        dup = [t for t in targets if targets.count(t) > 1]
        raise SystemExit(f"Duplicate target names: {sorted(set(dup))}")

    moved: list[dict] = []
    missing: list[str] = []

    # Phase 1: move/rename files to temp names first to avoid clobber
    # (e.g. home.css -> home.css no-op; profile.css -> profile.css no-op)
    staging: dict[str, Path] = {}
    for old_rel, new_name in RENAME_MAP.items():
        src = ROOT / old_rel
        if not src.is_file():
            missing.append(old_rel)
            continue
        dst = CSS_DIR / new_name
        if src.resolve() == dst.resolve():
            moved.append({"from": old_rel, "to": f"assets/css/{new_name}", "action": "keep"})
            continue
        # stage via temp to avoid overwrite collisions during batch
        tmp = CSS_DIR / f".__rename_tmp__{new_name}"
        shutil.copy2(src, tmp)
        staging[old_rel] = tmp
        moved.append({"from": old_rel, "to": f"assets/css/{new_name}", "action": "copy-stage"})

    for old_rel, tmp in staging.items():
        new_name = RENAME_MAP[old_rel]
        dst = CSS_DIR / new_name
        if dst.exists():
            dst.unlink()
        tmp.rename(dst)

    # Delete old sources that are not the same as destination
    for old_rel, new_name in RENAME_MAP.items():
        src = ROOT / old_rel
        dst = CSS_DIR / new_name
        if not src.is_file():
            continue
        if src.resolve() == dst.resolve():
            continue
        src.unlink()
        for row in moved:
            if row["from"] == old_rel:
                row["action"] = "moved"

    # Phase 2: rewrite references with path-safe patterns (avoid basename collisions)
    # Map old basename -> new basename only for explicit path contexts.
    old_to_new_base = {}
    for old_rel, new_name in RENAME_MAP.items():
        old_base = Path(old_rel).name
        if old_base != new_name:
            old_to_new_base[old_base] = new_name

    full_path_repls: list[tuple[str, str]] = []
    for old_rel, new_name in RENAME_MAP.items():
        new_rel = f"assets/css/{new_name}"
        if old_rel == new_rel:
            continue
        full_path_repls.append((old_rel, new_rel))
        full_path_repls.append(("/" + old_rel, "/" + new_rel))

    full_path_repls.sort(key=lambda x: -len(x[0]))

    # Basename replacements only when preceded by css/ or assets/css/ or quotes+path
    def rewrite_basenames(text: str) -> str:
        for old_base, new_base in sorted(old_to_new_base.items(), key=lambda x: -len(x[0])):
            # /assets/css/old OR assets/css/old OR /mobile/assets/css/old (already rewritten)
            patterns = [
                (rf"(assets/css/){re.escape(old_base)}", rf"\1{new_base}"),
                (rf"(/assets/css/){re.escape(old_base)}", rf"\1{new_base}"),
            ]
            for pat, repl in patterns:
                text = re.sub(pat, repl, text)
            # quoted bare filename used in PHP builders: 'header.css'
            text = re.sub(
                rf"(['\"])({re.escape(old_base)})(['\"])",
                rf"\1{new_base}\3",
                text,
            )
        return text

    extra_repls = [
        ("BASE_PATH . '/mobile/assets/css'", "BASE_PATH . '/assets/css'"),
        ('BASE_PATH . "/mobile/assets/css"', 'BASE_PATH . "/assets/css"'),
        ("'/mobile/assets/css'", "'/assets/css'"),
        ('"/mobile/assets/css"', '"/assets/css"'),
        ("/mobile/assets/css/", "/assets/css/"),
        ("mobile/assets/css/", "assets/css/"),
        ("/assets/sports-icon.css", "/assets/css/sports-icon.css"),
        ("'assets/sports-icon.css'", "'assets/css/sports-icon.css'"),
        ('"assets/sports-icon.css"', '"assets/css/sports-icon.css"'),
    ]

    files_changed = 0
    for path in ROOT.rglob("*"):
        if not path.is_file():
            continue
        try:
            rel = path.relative_to(ROOT)
        except ValueError:
            continue
        if should_skip(rel):
            continue
        if path.suffix.lower() not in TEXT_EXTS:
            continue
        if path.name in {"rename_css_sections.py"}:
            continue
        try:
            text = path.read_text(encoding="utf-8")
        except Exception:
            try:
                text = path.read_text(encoding="cp1254")
            except Exception:
                continue
        original = text
        for old, new in full_path_repls:
            if old in text:
                text = text.replace(old, new)
        for old, new in extra_repls:
            if old in text:
                text = text.replace(old, new)
        text = rewrite_basenames(text)
        if text != original:
            path.write_text(text, encoding="utf-8")
            files_changed += 1

    # Leave a pointer in mobile/assets/css
    MOBILE_CSS_DIR.mkdir(parents=True, exist_ok=True)
    readme = MOBILE_CSS_DIR / "README.md"
    readme.write_text(
        "# Moved\n\nMobile CSS files were consolidated into `/assets/css` "
        "with `mobile-*.css` section prefixes.\n",
        encoding="utf-8",
    )

    report = {
        "moved": moved,
        "missing": missing,
        "files_changed": files_changed,
        "map": {k: f"assets/css/{v}" for k, v in RENAME_MAP.items()},
    }
    REPORT.parent.mkdir(parents=True, exist_ok=True)
    REPORT.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")

    print(f"Moved/kept: {len(moved)}")
    print(f"Missing sources: {len(missing)}")
    for m in missing:
        print(f"  MISSING {m}")
    print(f"Text files updated: {files_changed}")
    print(f"Report: {REPORT.relative_to(ROOT).as_posix()}")

    # Sanity: list assets/css
    names = sorted(p.name for p in CSS_DIR.glob("*.css"))
    print(f"assets/css count: {len(names)}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
