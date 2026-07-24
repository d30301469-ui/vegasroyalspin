#!/usr/bin/env python3
"""
VegasRoyalSpin Mobile Full Design Scanner & Fixer
===================================================
Comprehensive mobile design analysis:
- JS conflicts (duplicate loads, menu binding, scroll locks)
- CSS issues (z-index stacking, mobile-specific files, redundancy)
- HTML structure (missing ARIA, viewport, orphan overlays)
- Performance (CSS file count, large inline SVGs)
- Layout risks (fixed positioning conflicts, touch-action issues)

Usage:
  python tools/mobile_js_conflict_detector.py          # detect only
  python tools/mobile_js_conflict_detector.py --fix    # detect and fix
  python tools/mobile_js_conflict_detector.py --report # generate JSON report
"""

import os
import re
import sys
import json
import hashlib
import argparse
from pathlib import Path
from collections import defaultdict
from typing import Dict, List, Set, Tuple, Optional

BASE_PATH = Path(r"c:\laragon\www\vegasroyalspin")

# ─── CONFIG ────────────────────────────────────────────────────────
PHP_VIEW_FILES = [
    "mobile/views/layouts/head.php",
    "mobile/views/layouts/bc-root-close.php",
    "mobile/views/layouts/bc-root-open.php",
    "mobile/views/partials/layout-after-header.php",
    "mobile/views/partials/footer.php",
    "mobile/views/partials/bottom-bar.php",
    "mobile/views/partials/bc-navigation.php",
    "mobile/views/partials/mobile-footer-bc.php",
    "mobile/views/pages/home.php",
    "mobile/views/pages/slot.php",
    "mobile/views/pages/bgaming.php",
    "mobile/views/pages/livecasino.php",
    "views/partials/layout-after-header.php",
    "views/pages/slot.php",
    "views/pages/bgaming.php",
    "views/layouts/head.php",
    "views/layouts/profile_modal_head.php",
]

JS_FILES = [
    "assets/js/global.js",
    "assets/js/auth-shared.js",
    "assets/js/header.js",
    "assets/js/footer.js",
    "assets/js/login.js",
    "assets/js/register.js",
    "assets/js/slot.js",
    "assets/js/bgaming.js",
    "assets/js/mobile_bottom.js",
    "assets/js/mobile-right-sheet.js",
    "assets/js/site-settings-hydrate.js",
    "assets/js/header-balance-poll.js",
    "assets/js/session-heartbeat.js",
    "assets/js/game-favorites.js",
    "assets/js/favorites-drawer.js",
    "assets/js/footer-bc.js",
    "assets/js/game-wallet-picker.js",
    "assets/js/member-api-console.js",
    "assets/js/profile.js",
    "assets/js/profile-api.js",
    "assets/js/profile-account.js",
    "assets/js/profile-payments.js",
    "assets/js/profile-history.js",
    "assets/js/profile-bonus.js",
    "assets/js/profile-kyc.js",
    "assets/js/modal-polyfill.js",
    "assets/js/toastify-helper.js",
    "assets/js/pwa-register.js",
    "assets/js/swiper-bundle.min.js",
    "assets/js/jackpot.js",
    "assets/js/winners.js",
    "assets/js/back-to-top.js",
    "mobile/assets/js/navigation.js",
    "mobile/assets/js/mobile-header.js",
    "mobile/assets/js/profile-panel.js",
    "mobile/assets/js/betslip-mobile.js",
]

# ─── DETECTION RULES ───────────────────────────────────────────────
# Known conflict patterns in JS files
CONFLICT_PATTERNS = {
    "duplicate_event_listener": {
        "description": "Multiple addEventListener calls for same element/event that may stack",
        "patterns": [
            (r"addEventListener\s*\(\s*['\"]click['\"]", "click handler"),
            (r"addEventListener\s*\(\s*['\"]touchend['\"]", "touchend handler"),
        ],
        "severity": "medium",
    },
    "body_scroll_lock_race": {
        "description": "Multiple functions manipulating body scroll lock (mobileMenu-locked, body-scroll-locked, mprofile-open)",
        "keywords": [
            "mobileMenu-locked", "body-scroll-locked", "navigation-is-visible",
            "mprofile-open", "overlaySlidingIsVisible", "overlay-sliding-is-visible",
        ],
        "severity": "high",
    },
    "global_namespace_collision": {
        "description": "window-level variables that could collide between scripts",
        "keywords": [
            "window.BetcoAuthShared", "window.__CSRF_TOKEN__",
            "window.__openLoginModal", "window.__openRegisterModal",
            "window.__closeMobileProfilePanel", "window.__syncHeaderStickyTop",
        ],
        "severity": "medium",
    },
    "defer_vs_sync_race": {
        "description": "Same script loaded with defer AND without defer (sync) in different files",
        "severity": "high",
    },
    "menu_toggle_multi_binding": {
        "description": "Multiple scripts binding to menu toggle (#menu-toggle, .tab-nav-item-bc.menu)",
        "keywords": ["menu-toggle", "mobileMenu", "data-mobile-menu-toggle"],
        "severity": "high",
    },
    "game_click_handler_conflict": {
        "description": "Game card click handlers that may intercept or block each other",
        "keywords": [
            "__bgamingHandlePlayIntent", "isBgamingGame",
            "casinoGameItemContent", "game-item", "game-cta",
        ],
        "severity": "high",
    },
    "overlay_panel_zindex_race": {
        "description": "Multiple overlay panels (profile, menu, sidebar, betslip) fighting for z-index/visibility",
        "keywords": [
            "mprofileOverlay", "mprofilePanel", "rightSidebarOverlay",
            "betslipPanelOverlay", "profileModalOverlay", "searchOverlay",
            "mobileMenu-overlay", "appFeedbackDialogOverlay",
        ],
        "severity": "medium",
    },
    "forceClose_competing": {
        "description": "forceCloseCompetingPanels may close panels that another script just opened",
        "keywords": ["forceCloseCompetingPanels", "forceClose"],
        "severity": "high",
    },
}


class JSConflictDetector:
    def __init__(self, base_path: Path, fix_mode: bool = False):
        self.base_path = base_path
        self.fix_mode = fix_mode
        self.issues: List[Dict] = []
        self.script_loads: Dict[str, List[Dict]] = defaultdict(list)
        self.file_cache: Dict[str, str] = {}

    def read_file(self, rel_path: str) -> Optional[str]:
        full_path = self.base_path / rel_path
        if full_path in self.file_cache:
            return self.file_cache[full_path]
        try:
            content = full_path.read_text(encoding="utf-8", errors="replace")
            self.file_cache[full_path] = content
            return content
        except Exception as e:
            self.issues.append({
                "type": "file_read_error",
                "file": rel_path,
                "detail": str(e),
                "severity": "low",
            })
            return None

    def write_file(self, rel_path: str, content: str):
        full_path = self.base_path / rel_path
        backup_path = str(full_path) + ".conflict_fix_backup"
        if self.fix_mode and not os.path.exists(backup_path):
            import shutil
            shutil.copy2(full_path, backup_path)
            print(f"  BACKUP: {rel_path} -> {Path(backup_path).name}")
        full_path.write_text(content, encoding="utf-8")

    # ── 1. Detect duplicate script loads ──────────────────────────
    def detect_duplicate_scripts(self):
        """Find scripts loaded multiple times across PHP view files."""
        script_pattern = re.compile(
            r'<script\s+(?:defer\s+)?src\s*=\s*["\']([^"\']+\.js(?:\?[^"\']*)?)["\']',
            re.IGNORECASE,
        )

        all_loads: Dict[str, List[Tuple[str, str, bool]]] = defaultdict(list)
        # script_basename -> [(file, full_src, is_defer)]

        for pf in PHP_VIEW_FILES:
            content = self.read_file(pf)
            if not content:
                continue
            for m in script_pattern.finditer(content):
                src = m.group(1)
                basename = re.sub(r'\?.*$', '', src).split('/')[-1]
                is_defer = 'defer' in m.group(0).lower()
                all_loads[basename].append((pf, src, is_defer))

        # Check for duplicates
        for basename, loads in all_loads.items():
            if len(loads) > 1:
                defer_modes = set(l[2] for l in loads)
                files = [l[0] for l in loads]
                severity = "high" if len(defer_modes) > 1 else "medium"

                issue = {
                    "type": "duplicate_script_load",
                    "script": basename,
                    "files": files,
                    "count": len(loads),
                    "defer_modes": list(defer_modes),
                    "severity": severity,
                    "detail": f"'{basename}' loaded {len(loads)} times in {len(set(files))} files"
                }
                if len(defer_modes) > 1:
                    issue["detail"] += " (MIXED defer/sync — high risk!)"
                self.issues.append(issue)

    # ── 2. Detect script loading in both mobile and desktop layout ──
    def detect_cross_layout_duplicates(self):
        """Check if mobile layout-after-header loads same scripts as desktop's."""
        mobile_lah = self.read_file("mobile/views/partials/layout-after-header.php")
        desktop_lah = self.read_file("views/partials/layout-after-header.php")
        mobile_head = self.read_file("mobile/views/layouts/head.php")

        script_re = re.compile(
            r'<script\s+(?:defer\s+)?src\s*=\s*["\']([^"\']+\.js(?:\?[^"\']*)?)["\']',
            re.IGNORECASE,
        )

        def extract_basenames(content: Optional[str]) -> Set[str]:
            if not content:
                return set()
            return {re.sub(r'\?.*$', '', m.group(1)).split('/')[-1]
                    for m in script_re.finditer(content)}

        mobile_scripts = extract_basenames(mobile_lah)
        head_scripts = extract_basenames(mobile_head)
        desktop_scripts = extract_basenames(desktop_lah)

        # Scripts loaded in BOTH mobile head (defer) AND mobile layout-after-header (sync)
        head_vs_lah = head_scripts & mobile_scripts
        for s in head_vs_lah:
            self.issues.append({
                "type": "defer_vs_sync_conflict",
                "script": s,
                "files": ["mobile/views/layouts/head.php", "mobile/views/partials/layout-after-header.php"],
                "severity": "high",
                "detail": f"'{s}' loaded with defer in head.php and sync in layout-after-header.php — possible double execution"
            })

        # Mobile layout loads scripts that desktop also loads (desktop delegates to mobile)
        # Informational only — the desktop file includes mobile file on mobile detection
        overlap = mobile_scripts & desktop_scripts
        if overlap:
            self.issues.append({
                "type": "desktop_mobile_overlap_info",
                "scripts": sorted(overlap),
                "severity": "info",
                "detail": f"These scripts appear in both desktop and mobile layout-after-header. Desktop delegates to mobile via isMobile() check — OK unless isMobile() fails."
            })

    # ── 3. Scan JS files for conflict patterns ────────────────────
    def scan_js_conflicts(self):
        """Scan JavaScript files for known conflict patterns."""
        for js_file in JS_FILES:
            content = self.read_file(js_file)
            if not content:
                continue

            # Check scroll lock patterns
            for kw in CONFLICT_PATTERNS["body_scroll_lock_race"]["keywords"]:
                count = content.count(kw)
                if count > 0:
                    # Find other files also using same keyword
                    for other_js in JS_FILES:
                        if other_js == js_file:
                            continue
                        other_content = self.read_file(other_js)
                        if other_content and kw in other_content:
                            self.issues.append({
                                "type": "scroll_lock_collision",
                                "keyword": kw,
                                "file_a": js_file,
                                "file_b": other_js,
                                "severity": "high",
                                "detail": f"Both '{Path(js_file).name}' and '{Path(other_js).name}' manipulate '{kw}' — scroll lock race risk"
                            })

            # Check menu toggle
            for kw in CONFLICT_PATTERNS["menu_toggle_multi_binding"]["keywords"]:
                if kw in content:
                    for other_js in JS_FILES:
                        if other_js == js_file:
                            continue
                        other_content = self.read_file(other_js)
                        if other_content and kw in other_content:
                            self.issues.append({
                                "type": "menu_toggle_multi_bind",
                                "keyword": kw,
                                "file_a": js_file,
                                "file_b": other_js,
                                "severity": "high",
                                "detail": f"'{kw}' referenced in both '{Path(js_file).name}' and '{Path(other_js).name}' — double menu binding"
                            })

            # Check global namespace
            for kw in CONFLICT_PATTERNS["global_namespace_collision"]["keywords"]:
                count = content.count(kw)
                if count > 0:
                    for other_js in JS_FILES:
                        if other_js == js_file:
                            continue
                        other_content = self.read_file(other_js)
                        if other_content and kw in other_content:
                            self.issues.append({
                                "type": "global_namespace_shared",
                                "keyword": kw,
                                "file_a": js_file,
                                "file_b": other_js,
                                "severity": "info",
                                "detail": f"'{kw}' used in both '{Path(js_file).name}' and '{Path(other_js).name}' — shared global (may be intentional)"
                            })

            # Check game click handlers
            for kw in CONFLICT_PATTERNS["game_click_handler_conflict"]["keywords"]:
                if kw in content:
                    for other_js in JS_FILES:
                        if other_js == js_file:
                            continue
                        other_content = self.read_file(other_js)
                        if other_content and kw in other_content:
                            self.issues.append({
                                "type": "game_handler_collision",
                                "keyword": kw,
                                "file_a": js_file,
                                "file_b": other_js,
                                "severity": "high",
                                "detail": f"Game handler '{kw}' in both '{Path(js_file).name}' and '{Path(other_js).name}'"
                            })

    # ── 4. Detect IIFE isolation gaps ─────────────────────────────
    def detect_iife_isolation(self):
        """Check if JS files properly wrap code in IIFE to avoid global leaks."""
        for js_file in JS_FILES:
            content = self.read_file(js_file)
            if not content or len(content.strip()) < 10:
                continue

            stripped = content.strip()
            is_iife = (
                stripped.startswith("(function") or
                stripped.startswith(";(function") or
                stripped.startswith("!function") or
                stripped.startswith('"use strict"') or
                stripped.startswith("'use strict'")
            )

            if not is_iife:
                # Check if it sets window.* directly
                window_assigns = re.findall(r'window\.(\w+)\s*=', content)
                if window_assigns:
                    unique_assigns = list(dict.fromkeys(window_assigns))
                    self.issues.append({
                        "type": "no_iife_global_leak",
                        "file": js_file,
                        "globals": unique_assigns[:5],
                        "severity": "low",
                        "detail": f"'{Path(js_file).name}' not wrapped in IIFE — sets window.{', window.'.join(unique_assigns[:3])}"
                    })

    # ── 5. Check slot.js / bgaming.js cross-contamination ─────────
    def detect_slot_bgaming_conflict(self):
        """Specific check for slot.js and bgaming.js conflict on bgaming page."""
        slot_js = self.read_file("assets/js/slot.js")
        bgaming_js = self.read_file("assets/js/bgaming.js")
        bgaming_view = self.read_file("views/pages/bgaming.php")
        mobile_bgaming = self.read_file("mobile/views/pages/bgaming.php")

        if slot_js and bgaming_js:
            # slot.js has isBgamingGame() filter
            if "isBgamingGame" in slot_js:
                # Check if bgaming view loads slot.js
                if bgaming_view and "slot.js" in bgaming_view:
                    self.issues.append({
                        "type": "bgaming_loads_slot_js",
                        "severity": "high",
                        "detail": "bgaming.php loads slot.js which contains isBgamingGame() filter — may block non-BGaming games that leak into bgaming page"
                    })
                else:
                    self.issues.append({
                        "type": "bgaming_slot_separation_ok",
                        "severity": "info",
                        "detail": "bgaming.php does NOT load slot.js — good separation. But ensure load-more/filter JS handles this correctly."
                    })

            # Check if bgaming.js has own load-more
            if bgaming_js:
                has_load_more = "load-more" in bgaming_js.lower() or "loadMore" in bgaming_js or "LoadMore" in bgaming_js
                self.issues.append({
                    "type": "bgaming_has_load_more" if has_load_more else "bgaming_missing_load_more",
                    "severity": "info" if has_load_more else "medium",
                    "detail": f"bgaming.js {'has' if has_load_more else 'MISSING'} load-more logic"
                })

        # Check mobile bgaming page structure
        if mobile_bgaming:
            if "slot.js" in mobile_bgaming:
                self.issues.append({
                    "type": "mobile_bgaming_loads_slot_js",
                    "severity": "high",
                    "detail": "mobile/views/pages/bgaming.php loads slot.js — use bgaming.js only on bgaming page"
                })

    # ── 6. Check for inline script conflicts ──────────────────────
    def detect_inline_script_conflicts(self):
        """Find inline <script> blocks that may conflict."""
        for pf in PHP_VIEW_FILES:
            content = self.read_file(pf)
            if not content:
                continue

            # Find inline scripts
            inline_blocks = re.findall(
                r'<script>(?!.*src=)(.*?)</script>',
                content, re.DOTALL | re.IGNORECASE
            )
            inline_blocks += re.findall(
                r'<script\s[^>]*>(?!.*src=)(.*?)</script>',
                content, re.DOTALL | re.IGNORECASE
            )

            for block in inline_blocks:
                block_clean = block.strip()
                if not block_clean:
                    continue

                # Check for DOMContentLoaded handlers
                if "DOMContentLoaded" in block_clean:
                    self.issues.append({
                        "type": "inline_dom_ready_handler",
                        "file": pf,
                        "severity": "low",
                        "detail": f"Inline DOMContentLoaded handler in {pf} — may race with deferred scripts"
                    })

                # Check for window.* assignment
                win_assigns = re.findall(r'window\.(\w+)\s*=', block_clean)
                if win_assigns:
                    unique_assigns = list(dict.fromkeys(win_assigns))
                    self.issues.append({
                        "type": "inline_window_assignment",
                        "file": pf,
                        "vars": unique_assigns,
                        "severity": "medium",
                        "detail": f"Inline script in {pf} sets window.{', window.'.join(unique_assigns[:3])}"
                    })

    # ── 7. Generate comprehensive report ──────────────────────────
    def run_all_checks(self) -> List[Dict]:
        print("=" * 70)
        print("  VegasRoyalSpin Mobile Full Design Scanner")
        print("=" * 70)
        print()

        print("[1/9] Detecting duplicate script loads...")
        self.detect_duplicate_scripts()

        print("[2/9] Detecting cross-layout duplicates...")
        self.detect_cross_layout_duplicates()

        print("[3/9] Scanning JS files for conflict patterns...")
        self.scan_js_conflicts()

        print("[4/9] Checking IIFE isolation...")
        self.detect_iife_isolation()

        print("[5/9] Checking slot.js vs bgaming.js conflict...")
        self.detect_slot_bgaming_conflict()

        print("[6/9] Checking inline script conflicts...")
        self.detect_inline_script_conflicts()

        print("[7/9] Scanning mobile CSS for issues...")
        self.scan_mobile_css()

        print("[8/9] Checking mobile HTML structure...")
        self.check_mobile_html_structure()

        print("[9/9] Checking mobile layout/performance...")
        self.check_mobile_performance()

        print()
        return self.issues

    # ── 8. Mobile CSS checks ──────────────────────────────────────
    CSS_FILES_CHECK = [
        "mobile/assets/css/base.css",
        "mobile/assets/css/header.css",
        "mobile/assets/css/menu.css",
        "mobile/assets/css/home.css",
        "mobile/assets/css/home-widgets.css",
        "mobile/assets/css/slots.css",
        "mobile/assets/css/bottom-bar.css",
        "mobile/assets/css/footer.css",
        "mobile/assets/css/auth-modals.css",
        "mobile/assets/css/profile-panel.css",
    ]

    def scan_mobile_css(self):
        """Check mobile CSS files for issues."""
        # Check if each mobile CSS file exists
        for css_file in self.CSS_FILES_CHECK:
            content = self.read_file(css_file)
            if not content:
                self.issues.append({
                    "type": "missing_mobile_css",
                    "file": css_file,
                    "severity": "high",
                    "detail": f"Mobile CSS file '{css_file}' is missing"
                })
                continue

            # Check for z-index values that could conflict
            z_indices = re.findall(r'z-index\s*:\s*(\d+)', content, re.IGNORECASE)
            if z_indices:
                high_z = [int(z) for z in z_indices if int(z) > 99990]
                if high_z:
                    self.issues.append({
                        "type": "high_z_index_risk",
                        "file": css_file,
                        "values": sorted(set(high_z)),
                        "severity": "low",
                        "detail": f"High z-index values in {Path(css_file).name}: {sorted(set(high_z))} — may overlap with other overlays"
                    })

            # Check for !important abuse
            important_count = len(re.findall(r'!important', content, re.IGNORECASE))
            if important_count > 10:
                self.issues.append({
                    "type": "css_important_abuse",
                    "file": css_file,
                    "count": important_count,
                    "severity": "medium",
                    "detail": f"{important_count} !important declarations in {Path(css_file).name} — may cause specificity wars"
                })

            # Check for position:fixed without z-index
            fixed_no_z = re.findall(
                r'position\s*:\s*fixed[^}]*?(?=\})',
                content, re.IGNORECASE | re.DOTALL
            )
            for block in fixed_no_z:
                if 'z-index' not in block.lower():
                    self.issues.append({
                        "type": "fixed_no_zindex",
                        "file": css_file,
                        "severity": "low",
                        "detail": f"position:fixed without z-index in {Path(css_file).name} — stacking order not defined"
                    })
                    break  # one per file is enough

            # Check for overflow:hidden on body (scroll prevention)
            if 'body' in content and 'overflow' in content and 'hidden' in content:
                self.issues.append({
                    "type": "body_overflow_hidden",
                    "file": css_file,
                    "severity": "medium",
                    "detail": f"body overflow:hidden found in {Path(css_file).name} — may block scrolling"
                })

    # ── 9. Mobile HTML structure checks ──────────────────────────
    def check_mobile_html_structure(self):
        """Check mobile PHP view files for structural issues."""
        mobile_head = self.read_file("mobile/views/layouts/head.php")
        mobile_footer = self.read_file("mobile/views/partials/footer.php")

        if mobile_head:
            # Check viewport meta
            if 'viewport' not in mobile_head.lower():
                self.issues.append({
                    "type": "missing_viewport_meta",
                    "file": "mobile/views/layouts/head.php",
                    "severity": "high",
                    "detail": "Viewport meta tag missing from mobile head"
                })

            # Check base href
            if '<base href="/">' not in mobile_head and '<base href=' not in mobile_head:
                self.issues.append({
                    "type": "missing_base_href",
                    "file": "mobile/views/layouts/head.php",
                    "severity": "medium",
                    "detail": "Base href tag may be missing from mobile head"
                })

            # Check theme-color
            if 'theme-color' not in mobile_head:
                self.issues.append({
                    "type": "missing_theme_color",
                    "file": "mobile/views/layouts/head.php",
                    "severity": "low",
                    "detail": "theme-color meta tag missing"
                })

        # Check bottom bar file
        bottom_bar = self.read_file("mobile/views/partials/bottom-bar.php")
        if bottom_bar:
            # Check for aria attributes on navigation
            if 'aria-label' not in bottom_bar and 'aria-label' not in (self.read_file("mobile/views/partials/bc-navigation.php") or ""):
                self.issues.append({
                    "type": "missing_aria_nav",
                    "file": "mobile/views/partials/bottom-bar.php",
                    "severity": "low",
                    "detail": "Bottom navigation bar missing aria-label"
                })

            # Check mobileMenu has aria-hidden
            # mobileMenu has aria-hidden in bottom-bar.php (included by bc-navigation.php)
            mobile_menu = self.read_file("mobile/views/partials/bottom-bar.php")
            if mobile_menu and 'aria-hidden' not in mobile_menu:
                self.issues.append({
                    "type": "missing_aria_hidden_menu",
                    "file": "mobile/views/partials/bc-navigation.php",
                    "severity": "low",
                    "detail": "Mobile menu missing aria-hidden attribute"
                })

        # Check for orphan overlay IDs (defined in HTML but not referenced in JS)
        overlay_ids_in_html = set()
        for pf in PHP_VIEW_FILES:
            content = self.read_file(pf)
            if not content:
                continue
            for overlay_id in [
                "mprofileOverlay", "mprofilePanel", "rightSidebarOverlay",
                "betslipPanelOverlay", "profileModalOverlay", "searchOverlay",
                "appFeedbackDialogOverlay", "bonus-detail-modal-overlay",
                "mobileMenu-overlay",
            ]:
                if f'id="{overlay_id}"' in content or f"id='{overlay_id}'" in content:
                    overlay_ids_in_html.add(overlay_id)

        overlay_ids_in_js = set()
        for js_file in JS_FILES:
            content = self.read_file(js_file)
            if not content:
                continue
            for overlay_id in overlay_ids_in_html:
                if overlay_id in content:
                    overlay_ids_in_js.add(overlay_id)

        orphan_overlays = overlay_ids_in_html - overlay_ids_in_js
        if orphan_overlays:
            self.issues.append({
                "type": "orphan_overlay_elements",
                "overlays": sorted(orphan_overlays),
                "severity": "low",
                "detail": f"Overlay elements defined in HTML but not referenced in any JS: {', '.join(sorted(orphan_overlays))}"
            })

    # ── 10. Mobile performance checks ────────────────────────────
    def check_mobile_performance(self):
        """Check mobile performance issues."""
        mobile_head = self.read_file("mobile/views/layouts/head.php")
        mobile_lah = self.read_file("mobile/views/partials/layout-after-header.php")

        # Count total CSS files loaded on mobile
        css_count = 0
        for pf in ["mobile/views/layouts/head.php", "mobile/views/partials/layout-after-header.php"]:
            content = self.read_file(pf)
            if not content:
                continue
            css_links = re.findall(r'<link[^>]*stylesheet[^>]*>', content, re.IGNORECASE)
            css_count += len(css_links)

        if css_count > 30:
            self.issues.append({
                "type": "too_many_css_files",
                "count": css_count,
                "severity": "medium",
                "detail": f"{css_count} CSS files loaded on mobile — consider bundling"
            })

        # Count JS files loaded
        js_count = 0
        for pf in ["mobile/views/layouts/head.php", "mobile/views/partials/layout-after-header.php"]:
            content = self.read_file(pf)
            if not content:
                continue
            js_links = re.findall(r'<script\s[^>]*src\s*=\s*["\'][^"\']+\.js', content, re.IGNORECASE)
            js_count += len(js_links)

        if js_count > 20:
            self.issues.append({
                "type": "too_many_js_files",
                "count": js_count,
                "severity": "medium",
                "detail": f"{js_count} JS files loaded on mobile — consider bundling/deferring"
            })

        # Check for large inline SVGs in slot.php (heavy page)
        slot_view = self.read_file("views/pages/slot.php")
        if slot_view:
            svg_blocks = re.findall(r'<svg[^>]*>.*?</svg>', slot_view, re.DOTALL | re.IGNORECASE)
            large_svgs = [s for s in svg_blocks if len(s) > 3000]
            if large_svgs:
                self.issues.append({
                    "type": "large_inline_svgs",
                    "count": len(large_svgs),
                    "total_chars": sum(len(s) for s in large_svgs),
                    "severity": "medium",
                    "detail": f"{len(large_svgs)} large inline SVGs in slot.php (total {sum(len(s) for s in large_svgs)} chars) — move to external SVG sprite"
                })

        # Check for CDN-loaded resources (blocking)
        cdn_count = 0
        for pf in ["mobile/views/layouts/head.php", "mobile/views/partials/layout-after-header.php"]:
            content = self.read_file(pf)
            if not content:
                continue
            cdn_links = re.findall(r'(?:src|href)\s*=\s*["\']https?://(?:cdn|unpkg|cdnjs)[^"\']+', content, re.IGNORECASE)
            cdn_count += len(cdn_links)

        if cdn_count > 0:
            self.issues.append({
                "type": "cdn_dependencies",
                "count": cdn_count,
                "severity": "info",
                "detail": f"{cdn_count} CDN-hosted resources — ensure local fallback exists"
            })

    def print_report(self):
        """Print a formatted report of all issues."""
        if not self.issues:
            print("*** No JS conflicts detected!")
            return

        by_severity = defaultdict(list)
        for issue in self.issues:
            by_severity[issue.get("severity", "info")].append(issue)

        print(f"\n*** SUMMARY: {len(self.issues)} potential issues found")
        print(f"   [HIGH] High:   {len(by_severity['high'])}")
        print(f"   [MED]  Medium: {len(by_severity['medium'])}")
        print(f"   [LOW]  Low:    {len(by_severity['low'])}")
        print(f"   [INFO] Info:   {len(by_severity['info'])}")
        print()

        for severity in ["high", "medium", "low", "info"]:
            issues = by_severity[severity]
            if not issues:
                continue

            icon = {"high": "[HIGH]", "medium": "[MED] ", "low": "[LOW] ", "info": "[INFO]"}[severity]
            print(f"{icon} {severity.upper()} SEVERITY ({len(issues)} issues)")
            print("-" * 60)

            for i, issue in enumerate(issues, 1):
                detail = issue.get("detail", "")
                files = issue.get("files", [issue.get("file", "")])
                file_str = ", ".join(
                    str(Path(f).name) if '/' in str(f) else str(f)
                    for f in (files if isinstance(files, list) else [files])
                    if f
                )
                print(f"  {i:2d}. [{issue['type']}] {detail}")
                if file_str:
                    print(f"      Files: {file_str}")
            print()

    def export_json_report(self, output_path: str):
        """Export issues as JSON."""
        report = {
            "meta": {
                "total_issues": len(self.issues),
                "by_severity": {},
            },
            "issues": self.issues,
        }
        for issue in self.issues:
            sev = issue.get("severity", "info")
            report["meta"]["by_severity"][sev] = report["meta"]["by_severity"].get(sev, 0) + 1

        with open(output_path, "w", encoding="utf-8") as f:
            json.dump(report, f, indent=2, ensure_ascii=False)
        print(f"JSON report saved to: {output_path}")

    # ── FIXES ─────────────────────────────────────────────────────
    def apply_fixes(self):
        """Apply automatic fixes for detected issues."""
        if not self.fix_mode:
            print("WARNING: Fix mode not enabled. Run with --fix to apply fixes.")
            return

        print("\n*** Applying fixes...")
        fixes_applied = 0

        # Fix 1: Remove duplicate script loads from mobile head.php (defer versions
        # that are also loaded sync in layout-after-header.php)
        fixes_applied += self._fix_head_duplicate_scripts()

        # Fix 2: Add guard in navigation.js to prevent double-initialization
        fixes_applied += self._fix_navigation_double_init()

        # Fix 3: Add missing load-more logic if bgaming.js lacks it
        fixes_applied += self._fix_bgaming_load_more()

        # Fix 4: Ensure mobile bgaming page doesn't load slot.js
        fixes_applied += self._fix_mobile_bgaming_isolation()

        # Fix 5: Add scroll lock recovery to mobile-header.js
        fixes_applied += self._fix_scroll_lock_recovery()

        print(f"\n*** {fixes_applied} fixes applied.")

    def _fix_head_duplicate_scripts(self) -> int:
        """Remove scripts from head.php that are already loaded sync in layout-after-header.php."""
        head_path = "mobile/views/layouts/head.php"
        content = self.read_file(head_path)
        if not content:
            return 0

        # Scripts loaded with defer in head that are also loaded sync in layout-after-header
        duplicates_to_remove = [
            "mobile-right-sheet.js",
            "footer.js",
        ]

        fixed = 0
        for script_name in duplicates_to_remove:
            pattern = re.compile(
                r'\s*<script\s+defer\s+src\s*=\s*["\'][^"\']*' + re.escape(script_name) + r'[^"\']*["\']\s*>\s*</script>\s*\n?',
                re.IGNORECASE,
            )
            if pattern.search(content):
                content = pattern.sub('\n', content)
                fixed += 1
                print(f"  OK Removed duplicate deferred '{script_name}' from head.php")

        if fixed:
            self.write_file(head_path, content)
        return fixed

    def _fix_navigation_double_init(self) -> int:
        """Add guard flag to prevent navigation.js from initializing twice."""
        nav_path = "mobile/assets/js/navigation.js"
        content = self.read_file(nav_path)
        if not content:
            return 0

        if "MOBILE_NAV_INITIALIZED" in content:
            print("  INFO navigation.js already has init guard")
            return 0

        # Add init guard
        guard = """
// GUARD: prevent double initialization
if (window.__MOBILE_NAV_INITIALIZED__) { return; }
window.__MOBILE_NAV_INITIALIZED__ = true;
"""
        # Insert after 'use strict'
        if "'use strict';" in content:
            content = content.replace("'use strict';", "'use strict';\n" + guard, 1)
        else:
            content = guard + "\n" + content

        self.write_file(nav_path, content)
        print("  OK Added double-init guard to navigation.js")
        return 1

    def _fix_bgaming_load_more(self) -> int:
        """Ensure bgaming.js has its own load-more logic."""
        bgaming_path = "assets/js/bgaming.js"
        content = self.read_file(bgaming_path)
        if not content:
            return 0

        has_load_more = "loadMore" in content or "load-more" in content.lower()
        if not has_load_more:
            # Check if bgaming view has load-more-sentinel
            bgaming_view = self.read_file("views/pages/bgaming.php")
            if bgaming_view and "load-more-sentinel" in bgaming_view:
                print("  WARN BGaming page has load-more-sentinel but bgaming.js has no load-more logic!")
                print("  INFO This is a KNOWN INTENTIONAL DESIGN - bgaming.js uses scroll-based loading, not slot.js load-more.")
                print("  INFO No automatic fix applied. Verify bgaming.js has scroll observer.")
            return 0

        print("  INFO bgaming.js already has load-more logic")
        return 0

    def _fix_mobile_bgaming_isolation(self) -> int:
        """Ensure mobile bgaming page does not load slot.js."""
        mobile_bgaming_path = "mobile/views/pages/bgaming.php"
        content = self.read_file(mobile_bgaming_path)
        if not content:
            return 0

        if "slot.js" in content:
            print("  INFO mobile bgaming page includes desktop bgaming.php")
            print("  INFO Checking if desktop bgaming.php loads slot.js...")

            desktop_bgaming = self.read_file("views/pages/bgaming.php")
            if desktop_bgaming and "slot.js" not in desktop_bgaming:
                print("  OK Desktop bgaming.php does NOT load slot.js - clean separation")
            else:
                print("  WARN Desktop bgaming.php loads slot.js - needs fix")
                if self.fix_mode and desktop_bgaming:
                    # Remove slot.js from bgaming.php if present
                    desktop_bgaming = re.sub(
                        r'\s*<script\s+src\s*=\s*["\'][^"\']*slot\.js[^"\']*["\']\s*>\s*</script>\s*',
                        '\n',
                        desktop_bgaming,
                    )
                    self.write_file("views/pages/bgaming.php", desktop_bgaming)
                    print("  OK Removed slot.js from views/pages/bgaming.php")
                    return 1
            return 0

        print("  OK Mobile bgaming page does not load slot.js directly")
        return 0

    def _fix_scroll_lock_recovery(self) -> int:
        """Add a global scroll lock recovery mechanism to prevent stuck locks."""
        mobile_header_path = "mobile/assets/js/mobile-header.js"
        content = self.read_file(mobile_header_path)
        if not content:
            return 0

        if "recoverAllScrollLocks" in content:
            print("  INFO Scroll lock recovery already present")
            return 0

        # Add a recovery function at the end of mobile-header.js
        recovery_code = """
// SCROLL LOCK RECOVERY: Emergency cleanup if body gets stuck locked
(function() {
    var RECOVERY_INTERVAL = 3000; // check every 3s
    var lastRecoveryCheck = 0;

    function isBodyStuckLocked() {
        var body = document.body;
        var menuOpen = document.getElementById('mobileMenu');
        var menuIsOpen = menuOpen && menuOpen.classList.contains('is-open');
        var profileOpen = document.getElementById('mprofilePanel');
        var profileIsOpen = profileOpen && profileOpen.classList.contains('is-open');
        var hasLock = body.classList.contains('mobileMenu-locked') || body.classList.contains('body-scroll-locked');
        // If lock is present but neither menu nor profile is open, body is stuck
        return hasLock && !menuIsOpen && !profileIsOpen;
    }

    function recoverScrollLock() {
        var body = document.body;
        body.classList.remove('mobileMenu-locked', 'body-scroll-locked', 'navigation-is-visible', 'mprofile-open', 'overlay-sliding-is-visible', 'overlaySlidingIsVisible');
        body.style.position = '';
        body.style.top = '';
        body.style.left = '';
        body.style.right = '';
        body.style.width = '';
        body.style.overflow = '';
        body.style.touchAction = '';
        body.style.paddingRight = '';
    }

    setInterval(function() {
        if (isBodyStuckLocked()) {
            console.warn('[ScrollLockRecovery] Detected stuck scroll lock — recovering');
            recoverScrollLock();
        }
    }, RECOVERY_INTERVAL);
})();
"""
        content = content.rstrip() + "\n" + recovery_code + "\n"
        self.write_file(mobile_header_path, content)
        print("  OK Added scroll lock recovery to mobile-header.js")
        return 1


def main():
    parser = argparse.ArgumentParser(
        description="VegasRoyalSpin Mobile JS Conflict Detector & Fixer"
    )
    parser.add_argument("--fix", action="store_true", help="Apply automatic fixes")
    parser.add_argument("--report", action="store_true", help="Generate JSON report")
    parser.add_argument(
        "--output", type=str, default="mobile_js_conflict_report.json",
        help="Output path for JSON report"
    )
    parser.add_argument(
        "--base-path", type=str, default=str(BASE_PATH),
        help="Base path of the project"
    )
    args = parser.parse_args()

    base_path = Path(args.base_path)
    if not base_path.exists():
        print(f"❌ Base path does not exist: {base_path}")
        sys.exit(1)

    detector = JSConflictDetector(base_path, fix_mode=args.fix)
    detector.run_all_checks()
    detector.print_report()

    if args.report:
        detector.export_json_report(args.output)

    if args.fix:
        detector.apply_fixes()

    # Return non-zero if high-severity issues found
    high_issues = [i for i in detector.issues if i.get("severity") == "high"]
    if high_issues:
        print(f"\nWARNING: {len(high_issues)} high-severity issues need attention!")
        sys.exit(1 if not args.fix else 0)

    sys.exit(0)


if __name__ == "__main__":
    main()
