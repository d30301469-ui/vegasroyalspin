#!/usr/bin/env python3
"""
VegasRoyalSpin — Advanced Mobile Design Analyzer & Auto-Fixer
================================================================
Analyzes JS, PHP, HTML, CSS files for mobile design issues,
accessibility problems, code duplication, and structural errors.
Generates a detailed report and can auto-fix many issues.

Usage:
    python tools/mobile_design_analyzer.py              # Analyze only
    python tools/mobile_design_analyzer.py --fix        # Analyze + auto-fix
    python tools/mobile_design_analyzer.py --json       # JSON output
    python tools/mobile_design_analyzer.py --scope mobile  # Mobile-only
"""

import os
import re
import sys
import json
import hashlib
import argparse
import subprocess
from pathlib import Path
from collections import defaultdict, Counter
from dataclasses import dataclass, field
from typing import Optional
from datetime import datetime
from concurrent.futures import ThreadPoolExecutor, as_completed

# ── Configuration ──────────────────────────────────────────────────────────

PROJECT_ROOT = Path(__file__).resolve().parent.parent

# Directories to scan
SCAN_DIRS = {
    "mobile": PROJECT_ROOT / "mobile",
    "views": PROJECT_ROOT / "views",
    "assets_js": PROJECT_ROOT / "assets" / "js",
    "assets_css": PROJECT_ROOT / "assets" / "css",
    "pages": PROJECT_ROOT / "pages",
    "controllers": PROJECT_ROOT / "controllers",
    "public": PROJECT_ROOT / "public",
}

# File extensions per category
EXTENSIONS = {
    "html": [".php", ".html"],
    "css": [".css"],
    "js": [".js"],
}

# Files/dirs to exclude
EXCLUDE_PATTERNS = [
    "vendor/", "node_modules/", ".git/", "storage/", "logs/",
    "*.min.js", "*.min.css", "swiper-bundle*", "bootstrap*",
    "*.map", "admin/", "archive/", "tools/",
]

# ── Data Classes ───────────────────────────────────────────────────────────

@dataclass
class Issue:
    """A single detected issue."""
    severity: str          # critical, high, medium, low, info
    category: str          # html, css, js, php, accessibility, performance, mobile
    code: str              # short code like "DUPLICATE_ID"
    message: str           # human-readable description
    file: str              # relative file path
    line: int = 0          # line number (0 = file-level)
    snippet: str = ""      # relevant code snippet
    fixable: bool = False  # can be auto-fixed?
    fix_description: str = ""  # what the auto-fix does

@dataclass
class ScanResult:
    """Results from scanning the entire project."""
    issues: list = field(default_factory=list)
    stats: dict = field(default_factory=dict)
    fixed: int = 0
    timestamp: str = ""

# ── Helper Utilities ───────────────────────────────────────────────────────

def should_exclude(file_path: Path) -> bool:
    """Check if file matches any exclusion pattern."""
    rel = str(file_path.relative_to(PROJECT_ROOT)).replace("\\", "/")
    for pattern in EXCLUDE_PATTERNS:
        if pattern.endswith("/") and pattern[:-1] in rel.split("/"):
            return True
        if pattern.startswith("*.") and rel.endswith(pattern[1:]):
            return True
        if pattern in rel:
            return True
    return False

def collect_files(scope: str = "all") -> list[Path]:
    """Collect all scannable files."""
    files = []
    dirs_to_scan = {}
    if scope == "mobile":
        dirs_to_scan = {"mobile": SCAN_DIRS["mobile"]}
    else:
        dirs_to_scan = SCAN_DIRS

    for name, directory in dirs_to_scan.items():
        if not directory.exists():
            continue
        for ext in [".php", ".html", ".css", ".js"]:
            for f in directory.rglob(f"*{ext}"):
                if not should_exclude(f):
                    files.append(f)

    # Also scan specific files outside main dirs
    extra = [
        PROJECT_ROOT / "index.php",
        PROJECT_ROOT / "router.php",
        PROJECT_ROOT / "service-worker.js",
    ]
    for f in extra:
        if f.exists() and not should_exclude(f):
            files.append(f)

    return sorted(set(files))

def read_file_safe(file_path: Path) -> Optional[str]:
    """Read file with encoding fallbacks."""
    for enc in ["utf-8", "latin-1", "cp1254"]:
        try:
            return file_path.read_text(encoding=enc)
        except (UnicodeDecodeError, UnicodeError):
            continue
    return None

def get_lines(content: str) -> list[str]:
    """Split content into lines."""
    return content.split("\n")

def find_line_number(content: str, pattern: str) -> int:
    """Find line number of first occurrence of pattern."""
    for i, line in enumerate(content.split("\n"), 1):
        if pattern in line:
            return i
    return 0

# ── Analyzers ──────────────────────────────────────────────────────────────

class HTMLAnalyzer:
    """Analyze HTML/PHP templates for mobile and accessibility issues."""

    def analyze(self, file_path: Path, content: str) -> list[Issue]:
        issues = []
        rel = str(file_path.relative_to(PROJECT_ROOT)).replace("\\", "/")
        lines = get_lines(content)

        # 1. Missing viewport meta (only in actual head/layout files, not partials)
        is_head_file = (
            file_path.name in ["head.php", "head_full.php"]
            or "layouts/head" in rel
        )
        is_partial = "/partials/" in rel or file_path.name.startswith("bc-root-") or file_path.name.startswith("layout-after-")
        
        if is_head_file and not is_partial:
            if '<meta name="viewport"' not in content:
                issues.append(Issue(
                    severity="critical", category="html", code="MISSING_VIEWPORT",
                    message="No viewport meta tag found in head/layout file — mobile rendering will break",
                    file=rel, fixable=True,
                    fix_description='Add <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">'
                ))

        # 2. Duplicate IDs (skip PHP-generated dynamic IDs with <?= patterns)
        ids_found = defaultdict(list)
        for i, line in enumerate(lines, 1):
            for match in re.finditer(r'\bid\s*=\s*["\']([^"\']+)["\']', line):
                id_val = match.group(1)
                # Skip PHP dynamic IDs and purely numeric short IDs that may be data values
                if '<?=' in id_val or '<?php' in id_val:
                    continue
                # Skip very short numeric IDs (likely data-index values, not HTML IDs)
                if id_val.isdigit() and len(id_val) <= 2:
                    continue
                ids_found[id_val].append((i, line.strip()[:120]))

        for id_val, occurrences in ids_found.items():
            if len(occurrences) > 1:
                issues.append(Issue(
                    severity="high", category="html", code="DUPLICATE_ID",
                    message=f'Duplicate ID "{id_val}" found {len(occurrences)} times',
                    file=rel, line=occurrences[0][0],
                    snippet=f"Lines: {', '.join(str(o[0]) for o in occurrences)}",
                    fixable=True,
                    fix_description=f'Rename duplicate IDs to unique values (e.g., "{id_val}-2")'
                ))

        # 3. Inline styles
        inline_style_count = 0
        for i, line in enumerate(lines, 1):
            if re.search(r'\bstyle\s*=\s*["\']', line):
                inline_style_count += 1
        if inline_style_count > 3:
            issues.append(Issue(
                severity="low", category="html", code="INLINE_STYLES",
                message=f"{inline_style_count} inline style attributes found — extract to CSS classes",
                file=rel, fixable=False
            ))

        # 4. Missing alt attributes on images
        img_tags = re.findall(r'<img\s[^>]*>', content, re.IGNORECASE)
        imgs_without_alt = [t for t in img_tags if 'alt=' not in t.lower()]
        if imgs_without_alt:
            issues.append(Issue(
                severity="medium", category="accessibility", code="MISSING_ALT",
                message=f"{len(imgs_without_alt)} <img> tags missing alt attribute",
                file=rel, fixable=False
            ))

        # 5. Non-semantic clickable elements (div/span with onclick, no role)
        for i, line in enumerate(lines, 1):
            if re.search(r'<(div|span)\s[^>]*\bonclick\s*=', line, re.IGNORECASE):
                if 'role="button"' not in line and 'role="link"' not in line:
                    issues.append(Issue(
                        severity="medium", category="accessibility", code="NON_SEMANTIC_CLICK",
                        message=f"Non-semantic clickable element without ARIA role at line {i}",
                        file=rel, line=i, snippet=line.strip()[:120],
                        fixable=True,
                        fix_description='Add role="button" and tabindex="0" to the element'
                    ))

        # 6. Missing lang attribute on html tag
        if file_path.name in ["head.php", "header.php"] or "layout" in rel:
            if '<html' in content and 'lang=' not in content.split('<html')[1].split('>')[0] if '<html' in content else False:
                pass  # Check separately
        if content.strip().startswith('<!DOCTYPE') or content.strip().startswith('<html'):
            html_open = re.search(r'<html([^>]*)>', content)
            if html_open and 'lang=' not in html_open.group(1):
                issues.append(Issue(
                    severity="low", category="accessibility", code="MISSING_LANG",
                    message="<html> tag missing lang attribute",
                    file=rel, fixable=True,
                    fix_description='Add lang="tr" to <html> tag'
                ))

        # 7. Hardcoded pixel widths that break mobile
        for i, line in enumerate(lines, 1):
            # Find style="width: NNNpx" where NNN > 360
            for m in re.finditer(r'(?:style\s*=\s*["\'][^"\']*width\s*:\s*(\d+)px|width\s*:\s*(\d+)px)', line):
                px_val = int(m.group(1) or m.group(2))
                if px_val > 360:
                    issues.append(Issue(
                        severity="low", category="mobile", code="HARDCODED_WIDTH_PX",
                        message=f"Hardcoded width {px_val}px may overflow on mobile (max 360-414px viewport)",
                        file=rel, line=i, snippet=line.strip()[:120],
                        fixable=False
                    ))

        return issues

class CSSAnalyzer:
    """Analyze CSS files for mobile responsiveness and duplication."""

    def analyze(self, file_path: Path, content: str) -> list[Issue]:
        issues = []
        rel = str(file_path.relative_to(PROJECT_ROOT)).replace("\\", "/")

        # 1. !important abuse
        important_count = content.count("!important")
        if important_count > 10:
            issues.append(Issue(
                severity="medium", category="css", code="IMPORTANT_ABUSE",
                message=f"{important_count} !important declarations — indicates specificity wars",
                file=rel, fixable=False
            ))

        # 2. Missing mobile media queries in main CSS
        if "global.css" in rel or "base.css" in rel:
            if "@media" not in content:
                issues.append(Issue(
                    severity="high", category="css", code="NO_MEDIA_QUERIES",
                    message="No @media queries found in base/global CSS — mobile responsiveness likely broken",
                    file=rel, fixable=False
                ))

        # 3. Hardcoded viewport widths
        for m in re.finditer(r'(?:max-)?width\s*:\s*(\d+)px', content):
            px_val = int(m.group(1))
            if px_val > 414:
                pass  # OK for desktop
            elif px_val in [320, 360, 375, 390, 393, 412, 414]:
                pass  # Common mobile breakpoints

        # 4. Duplicate selectors within same file
        selectors = re.findall(r'([^{]+)\{', content)
        selector_bodies = defaultdict(list)
        for sel in selectors:
            sel_clean = sel.strip()
            if sel_clean and not sel_clean.startswith("@"):
                selector_bodies[sel_clean].append(sel_clean)

        dup_count = sum(1 for v in selector_bodies.values() if len(v) > 1)
        if dup_count > 2:
            issues.append(Issue(
                severity="low", category="css", code="DUPLICATE_SELECTORS",
                message=f"{dup_count} duplicate CSS selectors in this file",
                file=rel, fixable=False
            ))

        # 5. Missing touch-action or -webkit-overflow-scrolling (only for actual scroll containers)
        has_overflow_scroll = bool(re.search(r'overflow\s*:\s*(?:scroll|auto)', content))
        if has_overflow_scroll and "-webkit-overflow-scrolling" not in content:
            issues.append(Issue(
                severity="medium", category="css", code="MISSING_MOMENTUM_SCROLL",
                message="Scrollable elements missing -webkit-overflow-scrolling: touch for iOS smooth scrolling",
                file=rel, fixable=True,
                fix_description="Add -webkit-overflow-scrolling: touch to scrollable containers"
            ))

        # 6. Font-size in px instead of rem (accessibility)
        px_fonts = re.findall(r'font-size\s*:\s*(\d+)px', content)
        if len(px_fonts) > 20:
            issues.append(Issue(
                severity="low", category="accessibility", code="PX_FONT_SIZE",
                message=f"{len(px_fonts)} font-size declarations use px instead of rem — harms text scaling",
                file=rel, fixable=False
            ))

        # 7. Fixed position without z-index management
        fixed_elements = re.findall(r'position\s*:\s*fixed', content)
        z_indexes = re.findall(r'z-index\s*:\s*(\d+)', content)
        if len(fixed_elements) > 5 and len(z_indexes) < len(fixed_elements):
            issues.append(Issue(
                severity="medium", category="mobile", code="FIXED_NO_ZINDEX",
                message=f"{len(fixed_elements)} fixed-position elements — potential stacking context issues on mobile",
                file=rel, fixable=False
            ))

        return issues

class JSAnalyzer:
    """Analyze JavaScript files for mobile issues and code quality."""

    def analyze(self, file_path: Path, content: str) -> list[Issue]:
        issues = []
        rel = str(file_path.relative_to(PROJECT_ROOT)).replace("\\", "/")
        lines = get_lines(content)

        # 1. console.log left in production code
        console_logs = [i for i, line in enumerate(lines, 1) if "console.log" in line]
        if len(console_logs) > 5:
            issues.append(Issue(
                severity="low", category="js", code="CONSOLE_LOG",
                message=f"{len(console_logs)} console.log calls — remove before production",
                file=rel, fixable=False
            ))

        # 2. Hardcoded API URLs (should use config)
        hardcoded_urls = re.findall(r'["\']https?://[^"\']*api[^"\']*["\']', content)
        if hardcoded_urls:
            issues.append(Issue(
                severity="medium", category="js", code="HARDCODED_API_URL",
                message=f"{len(hardcoded_urls)} hardcoded API URLs — use config/constants instead",
                file=rel, fixable=False
            ))

        # 3. Duplicate event listeners (same selector, same event)
        listeners = re.findall(r'(?:addEventListener|\.on|\.bind)\s*\(\s*["\']([^"\']+)["\']', content)
        listener_counts = Counter(listeners)
        for event, count in listener_counts.items():
            if count > 10:
                issues.append(Issue(
                    severity="low", category="js", code="MANY_LISTENERS",
                    message=f'"{event}" event bound {count} times — potential memory/performance issue on mobile',
                    file=rel, fixable=False
                ))

        # 4. Missing touch event handlers on mobile scripts
        if "mobile" in rel.lower():
            has_touch = "touchstart" in content or "touchend" in content or "touchmove" in content
            has_click = "click" in content
            if has_click and not has_touch:
                issues.append(Issue(
                    severity="medium", category="mobile", code="NO_TOUCH_EVENTS",
                    message="Mobile JS uses click but no touch events — may have 300ms delay on iOS",
                    file=rel, fixable=False
                ))

        # 5. Large inline scripts in PHP files
        if file_path.suffix == ".php":
            script_blocks = re.findall(r'<script[^>]*>(.*?)</script>', content, re.DOTALL | re.IGNORECASE)
            for i, block in enumerate(script_blocks):
                if len(block) > 2000:
                    line_num = find_line_number(content, block[:50])
                    issues.append(Issue(
                        severity="medium", category="js", code="LARGE_INLINE_SCRIPT",
                        message=f"Large inline script ({len(block)} chars) — extract to external .js file for caching",
                        file=rel, line=line_num,
                        fixable=False
                    ))

        # 6. setTimeout/setInterval without cleanup
        timers = re.findall(r'(setTimeout|setInterval)\s*\(', content)
        clears = re.findall(r'(clearTimeout|clearInterval)\s*\(', content)
        if len(timers) > len(clears):
            issues.append(Issue(
                severity="low", category="js", code="TIMER_NO_CLEANUP",
                message=f"{len(timers)} timers created but only {len(clears)} cleared — potential memory leaks",
                file=rel, fixable=False
            ))

        # 7. Mobile-specific: missing passive touch listeners
        if "touchstart" in content or "touchmove" in content:
            if "{passive:" not in content:
                issues.append(Issue(
                    severity="medium", category="mobile", code="NO_PASSIVE_TOUCH",
                    message="Touch events specified without {passive: true} — may cause scroll jank on mobile",
                    file=rel, fixable=True,
                    fix_description="Add {passive: true} option to touch event listeners"
                ))

        return issues

class PHPAnalyzer:
    """Analyze PHP files for mobile template issues."""

    def analyze(self, file_path: Path, content: str) -> list[Issue]:
        issues = []
        rel = str(file_path.relative_to(PROJECT_ROOT)).replace("\\", "/")

        # 1. Mojibake/encoding issues (only when file has actual encoding problems)
        # Read raw bytes and check for common UTF-8 double-encoding patterns
        try:
            raw_bytes = file_path.read_bytes()
            # Check if file contains bytes that look like UTF-8 mojibake
            # e.g., 'ü' (C3 BC) double-encoded becomes 'Ã¼' (C3 83 C2 BC)
            mojibake_count = 0
            for pattern_bytes, desc in [
                (b'\xc3\x83\xc2\x87', 'Ç'),   # Ã‡
                (b'\xc3\x83\xc2\xbc', 'ü'),   # Ã¼
                (b'\xc3\x83\xc2\xb6', 'ö'),   # Ã¶
                (b'\xc3\x83\xc5\xb8', 'ß'),   # ÃŸ
                (b'\xc3\x84\xc2\xb0', 'İ'),   # Ä°
                (b'\xc3\x85\xc5\xb8', 'ş'),   # ÅŸ
                (b'\xc3\x84\xc5\xb8', 'ğ'),   # ÄŸ
            ]:
                count = raw_bytes.count(pattern_bytes)
                if count > 0:
                    mojibake_count += count
                    issues.append(Issue(
                        severity="high", category="php", code="MOJIBAKE",
                        message=f"{count} double-encoded UTF-8 characters ({desc}) — database/API encoding problem",
                        file=rel, fixable=True,
                        fix_description=f'Apply charset conversion: mb_convert_encoding($str, "UTF-8", "ISO-8859-1")'
                    ))
        except Exception:
            pass

        # 2. Hardcoded fallback values in mobile context
        if "mobile" in rel.lower():
            hardcoded_fallbacks = re.findall(r"defaults?\s*(?:to|=)\s*['\"]([^'\"]+)['\"]", content, re.IGNORECASE)
            if hardcoded_fallbacks:
                issues.append(Issue(
                    severity="low", category="php", code="HARDCODED_FALLBACK",
                    message=f"Hardcoded fallback values: {', '.join(hardcoded_fallbacks[:5])}",
                    file=rel, fixable=False
                ))

        # 3. Missing mobile check in view files
        if "views" in rel or "pages" in rel:
            if "isMobile" not in content and "MOBILE_PATH" not in content:
                if file_path.suffix == ".php" and "layout" not in rel:
                    # Not necessarily an issue for all files, but flag for pages
                    pass

        # 4. include/require with relative paths (fragile)
        relative_includes = re.findall(
            r'(?:include|require|include_once|require_once)\s*\(?\s*["\']\.\.\/',
            content
        )
        if len(relative_includes) > 5:
            issues.append(Issue(
                severity="low", category="php", code="RELATIVE_INCLUDES",
                message=f"{len(relative_includes)} relative-path includes — fragile, use defined constants",
                file=rel, fixable=False
            ))

        # 5. CDN dependencies without local fallback
        cdn_urls = re.findall(r'(https?://(?:cdn|unpkg|cdnjs)[^"\'\s]+)', content)
        if cdn_urls:
            has_fallback = "fallback" in content.lower() or "window." in content and "typeof" in content
            if not has_fallback:
                issues.append(Issue(
                    severity="medium", category="php", code="CDN_NO_FALLBACK",
                    message=f"CDN resource loaded without local fallback: {cdn_urls[0][:80]}",
                    file=rel, fixable=False
                ))

        return issues

class CrossFileAnalyzer:
    """Analyze issues that span multiple files."""

    def __init__(self, all_files: list[Path]):
        self.all_files = all_files

    def analyze(self) -> list[Issue]:
        issues = []

        # 1. Find duplicate JS logic (mobile_bottom.js vs navigation.js)
        mobile_bottom = None
        navigation_js = None
        for f in self.all_files:
            if f.name == "mobile_bottom.js":
                mobile_bottom = f
            if f.name == "navigation.js" and "mobile" in str(f).lower():
                navigation_js = f

        if mobile_bottom and navigation_js:
            mb_content = read_file_safe(mobile_bottom)
            nav_content = read_file_safe(navigation_js)
            if mb_content and nav_content:
                # Check for overlapping menu toggle logic
                mb_has_toggle = "menu" in mb_content.lower() and "toggle" in mb_content.lower()
                nav_has_toggle = "menu" in nav_content.lower() and "toggle" in nav_content.lower()
                if mb_has_toggle and nav_has_toggle:
                    issues.append(Issue(
                        severity="high", category="js", code="DUPLICATE_MENU_LOGIC",
                        message="mobile_bottom.js AND navigation.js both handle mobile menu toggles — duplicate logic, potential conflicts",
                        file=f"mobile/assets/js/navigation.js + assets/js/mobile_bottom.js",
                        fixable=True,
                        fix_description="Consolidate menu logic into one file, remove the other's menu handling"
                    ))

        # 2. Check for two mobile-right-sheet.css files
        mrs_files = [f for f in self.all_files if "mobile-right-sheet" in f.name]
        if len(mrs_files) > 1:
            issues.append(Issue(
                severity="medium", category="css", code="DUPLICATE_CSS_FILE",
                message=f"mobile-right-sheet.css exists in {len(mrs_files)} locations — potential style conflicts",
                file=", ".join(str(f.relative_to(PROJECT_ROOT)).replace("\\", "/") for f in mrs_files),
                fixable=True,
                fix_description="Merge into one file, delete the duplicate"
            ))

        # 3. Check head.php sync between mobile and desktop
        desktop_head = PROJECT_ROOT / "views" / "layouts" / "head.php"
        mobile_head = PROJECT_ROOT / "mobile" / "views" / "layouts" / "head.php"
        if desktop_head.exists() and mobile_head.exists():
            d_content = read_file_safe(desktop_head)
            m_content = read_file_safe(mobile_head)
            if d_content and m_content:
                # Extract CSS file references
                d_css = set(re.findall(r'["\']([^"\']+\.css[^"\']*)["\']', d_content))
                m_css = set(re.findall(r'["\']([^"\']+\.css[^"\']*)["\']', m_content))
                only_desktop = d_css - m_css
                only_mobile = m_css - d_css
                if len(only_desktop) > 3 or len(only_mobile) > 3:
                    issues.append(Issue(
                        severity="medium", category="php", code="HEAD_OUT_OF_SYNC",
                        message=f"Desktop and mobile head.php load different CSS sets. Desktop-only: {len(only_desktop)}, Mobile-only: {len(only_mobile)}",
                        file="views/layouts/head.php vs mobile/views/layouts/head.php",
                        fixable=False
                    ))

        # 4. Check livecasino.php renders wrong view
        livecasino_mobile = PROJECT_ROOT / "mobile" / "views" / "pages" / "livecasino.php"
        if livecasino_mobile.exists():
            lc_content = read_file_safe(livecasino_mobile)
            if lc_content and "pages/slot.php" in lc_content and "livecasino" not in lc_content.lower().replace("pages/slot.php", ""):
                issues.append(Issue(
                    severity="critical", category="php", code="WRONG_VIEW_RENDERED",
                    message="mobile/views/pages/livecasino.php includes slot.php — should render live casino, not slots!",
                    file="mobile/views/pages/livecasino.php",
                    fixable=True,
                    fix_description="Change include to render live casino content instead of slot.php"
                ))

        # 5. Check for .conflict_fix_backup files
        for f in self.all_files:
            if "conflict" in f.name.lower() or "backup" in f.name.lower():
                issues.append(Issue(
                    severity="low", category="js", code="CONFLICT_BACKUP",
                    message=f"Merge conflict backup file still present: {f.name}",
                    file=str(f.relative_to(PROJECT_ROOT)).replace("\\", "/"),
                    fixable=True,
                    fix_description="Remove the backup file after verifying the merge"
                ))

        return issues

# ── Main Scanner ───────────────────────────────────────────────────────────

class MobileDesignScanner:
    """Orchestrates all analyzers across the project."""

    def __init__(self, scope: str = "all"):
        self.scope = scope
        self.html_analyzer = HTMLAnalyzer()
        self.css_analyzer = CSSAnalyzer()
        self.js_analyzer = JSAnalyzer()
        self.php_analyzer = PHPAnalyzer()
        self.all_issues: list[Issue] = []

    def scan(self) -> ScanResult:
        """Run full scan and return results."""
        files = collect_files(self.scope)
        print(f"\n{'='*70}")
        print(f"  VegasRoyalSpin Mobile Design Analyzer")
        print(f"  Scanning {len(files)} files in scope: {self.scope}")
        print(f"{'='*70}\n")

        # Categorize files
        html_files = [f for f in files if f.suffix in [".php", ".html"]]
        css_files = [f for f in files if f.suffix == ".css"]
        js_files = [f for f in files if f.suffix == ".js"]

        stats = {
            "total_files": len(files),
            "html_php": len(html_files),
            "css": len(css_files),
            "js": len(js_files),
        }

        # Scan in parallel
        with ThreadPoolExecutor(max_workers=8) as executor:
            futures = {}

            for f in html_files:
                futures[executor.submit(self._scan_html_php, f)] = f
            for f in css_files:
                futures[executor.submit(self._scan_css, f)] = f
            for f in js_files:
                futures[executor.submit(self._scan_js, f)] = f

            for future in as_completed(futures):
                f = futures[future]
                try:
                    issues = future.result()
                    self.all_issues.extend(issues)
                    if issues:
                        print(f"  📄 {f.relative_to(PROJECT_ROOT)} → {len(issues)} issue(s)")
                except Exception as e:
                    print(f"  ⚠️  Error scanning {f.relative_to(PROJECT_ROOT)}: {e}")

        # Cross-file analysis
        print(f"\n  🔍 Running cross-file analysis...")
        cross = CrossFileAnalyzer(files)
        cross_issues = cross.analyze()
        self.all_issues.extend(cross_issues)

        # Sort by severity
        severity_order = {"critical": 0, "high": 1, "medium": 2, "low": 3, "info": 4}
        self.all_issues.sort(key=lambda i: (severity_order.get(i.severity, 99), i.category, i.file))

        # Stats
        stats["total_issues"] = len(self.all_issues)
        stats["by_severity"] = dict(Counter(i.severity for i in self.all_issues))
        stats["by_category"] = dict(Counter(i.category for i in self.all_issues))
        stats["fixable"] = sum(1 for i in self.all_issues if i.fixable)

        return ScanResult(issues=self.all_issues, stats=stats, timestamp=datetime.now().isoformat())

    def _scan_html_php(self, f: Path) -> list[Issue]:
        content = read_file_safe(f)
        if content is None:
            return []
        issues = []
        issues.extend(self.html_analyzer.analyze(f, content))
        issues.extend(self.php_analyzer.analyze(f, content))
        # Also check for inline scripts
        issues.extend(self.js_analyzer.analyze(f, content))
        return issues

    def _scan_css(self, f: Path) -> list[Issue]:
        content = read_file_safe(f)
        if content is None:
            return []
        return self.css_analyzer.analyze(f, content)

    def _scan_js(self, f: Path) -> list[Issue]:
        content = read_file_safe(f)
        if content is None:
            return []
        return self.js_analyzer.analyze(f, content)

# ── Auto-Fixer ─────────────────────────────────────────────────────────────

class AutoFixer:
    """Applies auto-fixes for fixable issues."""

    def __init__(self, issues: list[Issue], dry_run: bool = False):
        self.issues = [i for i in issues if i.fixable]
        self.dry_run = dry_run
        self.fixed_count = 0
        self.failed: list[str] = []

    def apply_all(self) -> int:
        """Apply all fixable issues."""
        if not self.issues:
            print("  ✅ No fixable issues found.")
            return 0

        print(f"\n  🔧 Auto-fixing {len(self.issues)} issues...")

        for issue in self.issues:
            try:
                self._fix_issue(issue)
            except Exception as e:
                self.failed.append(f"{issue.file}: {issue.code} — {e}")

        print(f"\n  ✅ Fixed: {self.fixed_count}, Failed: {len(self.failed)}")
        for fail in self.failed:
            print(f"     ⚠️  {fail}")

        return self.fixed_count

    def _fix_issue(self, issue: Issue):
        """Fix a single issue based on its code."""
        file_path = PROJECT_ROOT / issue.file
        if not file_path.exists():
            self.failed.append(f"{issue.file}: file not found")
            return

        content = read_file_safe(file_path)
        if content is None:
            self.failed.append(f"{issue.file}: cannot read")
            return

        original = content

        if issue.code == "DUPLICATE_ID":
            content = self._fix_duplicate_ids(content, issue)

        elif issue.code == "NON_SEMANTIC_CLICK" and issue.snippet:
            content = self._fix_non_semantic_click(content, issue.snippet)

        elif issue.code == "MISSING_LANG":
            content = content.replace("<html>", '<html lang="tr">')
            if "<html " in content and 'lang=' not in content.split("<html ")[1].split(">")[0]:
                content = re.sub(r'<html\b(?!.*lang=)', '<html lang="tr"', content, count=1)

        elif issue.code == "MISSING_VIEWPORT":
            viewport_meta = '<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">'
            if "<head>" in content:
                content = content.replace("<head>", f"<head>\n    {viewport_meta}")
            elif "<?php" in content:
                # Insert after charset or title
                if "<title>" in content:
                    content = content.replace("<title>", f"{viewport_meta}\n    <title>")
                else:
                    content = content.replace("<?php", f"<?php\n// Added viewport meta\n// {viewport_meta}\n")

        elif issue.code == "MOJIBAKE":
            content = self._fix_mojibake(content, file_path)

        elif issue.code == "MISSING_MOMENTUM_SCROLL":
            content = self._fix_momentum_scroll(content)

        elif issue.code == "NO_PASSIVE_TOUCH":
            content = self._fix_passive_touch(content)

        elif issue.code == "CONFLICT_BACKUP":
            if self.dry_run:
                print(f"     [DRY RUN] Would delete: {file_path}")
            else:
                file_path.unlink()
                print(f"     🗑️  Deleted: {issue.file}")

        elif issue.code == "DUPLICATE_MENU_LOGIC":
            # Complex fix — just report for now
            print(f"     ⚠️  Manual fix needed for: {issue.code} — see report")

        else:
            self.failed.append(f"{issue.file}: unknown fix code {issue.code}")
            return

        if content != original:
            if self.dry_run:
                print(f"     [DRY RUN] Would fix: {issue.file} ({issue.code})")
            else:
                file_path.write_text(content, encoding="utf-8")
                print(f"     🔧 Fixed: {issue.file} ({issue.code})")
            self.fixed_count += 1

    def _fix_duplicate_ids(self, content: str, issue: Issue) -> str:
        """Rename duplicate IDs by appending -2, -3, etc."""
        # Extract the duplicate ID from the message
        m = re.search(r'Duplicate ID "([^"]+)"', issue.message)
        if not m:
            return content
        dup_id = m.group(1)

        count = 0
        def replace_id(match):
            nonlocal count
            count += 1
            if count == 1:
                return match.group(0)  # Keep first occurrence
            return match.group(0).replace(f'"{dup_id}"', f'"{dup_id}-{count}"').replace(f"'{dup_id}'", f"'{dup_id}-{count}'")

        return re.sub(rf'\bid\s*=\s*["\']{re.escape(dup_id)}["\']', replace_id, content)

    def _fix_non_semantic_click(self, content: str, snippet: str) -> str:
        """Add role and tabindex to clickable divs/spans."""
        # Find the exact line and add attributes
        if snippet in content:
            fixed = snippet.replace(">", ' role="button" tabindex="0">', 1)
            content = content.replace(snippet, fixed, 1)
        return content

    def _fix_mojibake(self, content: str, file_path: Path) -> str:
        """Fix mojibake characters using raw bytes replacement."""
        try:
            raw = file_path.read_bytes()
            replacements = [
                (b'\xc3\x83\xc2\x87', b'\xc3\x87'),   # Ã‡ → Ç
                (b'\xc3\x83\xc2\xa7', b'\xc3\xa7'),   # Ã§ → ç
                (b'\xc3\x83\xc2\xbc', b'\xc3\xbc'),   # Ã¼ → ü
                (b'\xc3\x83\xc2\x9c', b'\xc3\x9c'),   # Ãœ → Ü
                (b'\xc3\x83\xc2\xb6', b'\xc3\xb6'),   # Ã¶ → ö
                (b'\xc3\x83\xc2\x96', b'\xc3\x96'),   # Ã– → Ö
                (b'\xc3\x84\xc2\xb0', b'\xc4\xb0'),   # Ä° → İ
                (b'\xc3\x84\xc2\xb1', b'\xc4\xb1'),   # Ä± → ı
                (b'\xc3\x85\xc5\xb8', b'\xc5\x9f'),   # ÅŸ → ş
                (b'\xc3\x85\xc5\xbe', b'\xc5\x9e'),   # Åž → Ş
                (b'\xc3\x84\xc5\xb8', b'\xc4\x9f'),   # ÄŸ → ğ
                (b'\xc3\x84\xc2\x9e', b'\xc4\x9e'),   # Ä → Ğ
            ]
            changed = False
            for old, new in replacements:
                if old in raw:
                    raw = raw.replace(old, new)
                    changed = True
            if changed:
                return raw.decode('utf-8')
        except Exception:
            pass
        return content

    def _fix_momentum_scroll(self, content: str) -> str:
        """Add -webkit-overflow-scrolling to scrollable elements."""
        lines = content.split("\n")
        fixed_lines = []
        for line in lines:
            if re.search(r'overflow\s*:\s*(?:scroll|auto)', line) and "-webkit-overflow-scrolling" not in line:
                # Replace the overflow line with overflow + momentum
                indent = line[:len(line) - len(line.lstrip())]
                fixed_lines.append(line)
                fixed_lines.append(f"{indent}-webkit-overflow-scrolling: touch;")
            else:
                fixed_lines.append(line)
        return "\n".join(fixed_lines)

    def _fix_passive_touch(self, content: str) -> str:
        """Add {passive: true} to touch event listeners."""
        # Simple pattern: addEventListener('touchstart', fn) → addEventListener('touchstart', fn, {passive: true})
        content = re.sub(
            r"(addEventListener\s*\(\s*['\"]touch(?:start|move)['\"]\s*,\s*[^,)]+)(\))",
            r"\1, {passive: true}\2",
            content
        )
        return content

# ── Report Generator ───────────────────────────────────────────────────────

def generate_report(result: ScanResult) -> str:
    """Generate a detailed markdown report."""
    lines = []
    lines.append("# VegasRoyalSpin — Mobile Design Analysis Report")
    lines.append(f"**Generated:** {result.timestamp}")
    lines.append("")
    lines.append("## Summary")
    lines.append("")
    lines.append(f"| Metric | Value |")
    lines.append(f"|--------|-------|")
    lines.append(f"| Total files scanned | {result.stats['total_files']} |")
    lines.append(f"| HTML/PHP files | {result.stats['html_php']} |")
    lines.append(f"| CSS files | {result.stats['css']} |")
    lines.append(f"| JS files | {result.stats['js']} |")
    lines.append(f"| **Total issues** | **{result.stats['total_issues']}** |")
    lines.append(f"| Fixable issues | {result.stats['fixable']} |")
    lines.append("")

    lines.append("### By Severity")
    for sev in ["critical", "high", "medium", "low", "info"]:
        count = result.stats["by_severity"].get(sev, 0)
        if count > 0:
            emoji = {"critical": "🔴", "high": "🟠", "medium": "🟡", "low": "🔵", "info": "⚪"}
            lines.append(f"- {emoji.get(sev, '•')} **{sev.upper()}**: {count}")

    lines.append("")
    lines.append("### By Category")
    for cat, count in sorted(result.stats["by_category"].items()):
        lines.append(f"- **{cat}**: {count}")

    lines.append("")
    lines.append("## All Issues")
    lines.append("")

    for issue in result.issues:
        emoji = {"critical": "🔴", "high": "🟠", "medium": "🟡", "low": "🔵", "info": "⚪"}
        lines.append(f"### {emoji.get(issue.severity, '•')} [{issue.severity.upper()}] {issue.code}")
        lines.append(f"**File:** `{issue.file}`")
        if issue.line:
            lines.append(f"**Line:** {issue.line}")
        lines.append(f"**Category:** {issue.category}")
        lines.append(f"**Message:** {issue.message}")
        if issue.snippet:
            lines.append(f"```\n{issue.snippet}\n```")
        if issue.fixable:
            lines.append(f"🔧 **Auto-fix:** {issue.fix_description}")
        lines.append("")

    # Recommendations
    lines.append("## Recommendations")
    lines.append("")

    critical_count = result.stats["by_severity"].get("critical", 0)
    high_count = result.stats["by_severity"].get("high", 0)

    if critical_count > 0:
        lines.append("### 🚨 Critical — Fix Immediately")
        for issue in result.issues:
            if issue.severity == "critical":
                lines.append(f"- **{issue.code}** in `{issue.file}`: {issue.message}")

    lines.append("")
    lines.append("### 📱 Mobile-Specific Recommendations")
    lines.append("")
    lines.append("1. **Consolidate duplicate menu logic** — `mobile_bottom.js` and `navigation.js` both handle menus")
    lines.append("2. **Add passive touch listeners** — improves scroll performance on iOS/Android")
    lines.append("3. **Add `-webkit-overflow-scrolling: touch`** — enables momentum scrolling on iOS")
    lines.append("4. **Ensure viewport meta is present** — critical for proper mobile rendering")
    lines.append("5. **Extract large inline scripts** — improves caching and reduces page weight")
    lines.append("")
    lines.append("### ♿ Accessibility Recommendations")
    lines.append("")
    lines.append("1. Add `alt` attributes to all `<img>` tags")
    lines.append("2. Add `role=\"button\"` and `tabindex=\"0\"` to clickable divs/spans")
    lines.append("3. Use `rem` instead of `px` for font sizes (respects user text scaling)")
    lines.append("4. Add `lang=\"tr\"` to `<html>` tag")
    lines.append("")
    lines.append("### ⚡ Performance Recommendations")
    lines.append("")
    lines.append("1. Add local fallbacks for CDN resources (Toastr, Toastify)")
    lines.append("2. Clean up `console.log` statements before production")
    lines.append("3. Clean up timer references (setTimeout/setInterval)")
    lines.append("4. Remove merge conflict backup files")

    return "\n".join(lines)


def print_quick_summary(result: ScanResult):
    """Print a quick summary to console."""
    print(f"\n{'='*70}")
    print(f"  SCAN COMPLETE")
    print(f"{'='*70}")
    print(f"  Files scanned:  {result.stats['total_files']}")
    print(f"  Issues found:   {result.stats['total_issues']}")
    print(f"  Fixable:        {result.stats['fixable']}")
    print(f"")
    for sev in ["critical", "high", "medium", "low", "info"]:
        count = result.stats["by_severity"].get(sev, 0)
        if count > 0:
            print(f"  {sev.upper():8s}: {count}")
    print(f"{'='*70}\n")

# ── CLI Entry Point ────────────────────────────────────────────────────────

def main():
    parser = argparse.ArgumentParser(
        description="VegasRoyalSpin — Advanced Mobile Design Analyzer & Auto-Fixer"
    )
    parser.add_argument("--fix", action="store_true", help="Auto-fix fixable issues")
    parser.add_argument("--dry-run", action="store_true", help="Show what would be fixed without applying")
    parser.add_argument("--json", action="store_true", help="Output results as JSON")
    parser.add_argument("--scope", choices=["all", "mobile"], default="all",
                        help="Scan scope: all (default) or mobile only")
    parser.add_argument("--output", type=str, help="Save report to file")
    parser.add_argument("--open-report", action="store_true", help="Open report in browser after scan")

    args = parser.parse_args()

    # Run scan
    scanner = MobileDesignScanner(scope=args.scope)
    result = scanner.scan()

    # Apply fixes
    if args.fix or args.dry_run:
        fixer = AutoFixer(result.issues, dry_run=args.dry_run)
        result.fixed = fixer.apply_all()

    # Output
    if args.json:
        output = {
            "timestamp": result.timestamp,
            "stats": result.stats,
            "fixed": result.fixed,
            "issues": [
                {
                    "severity": i.severity,
                    "category": i.category,
                    "code": i.code,
                    "message": i.message,
                    "file": i.file,
                    "line": i.line,
                    "fixable": i.fixable,
                }
                for i in result.issues
            ]
        }
        if args.output:
            Path(args.output).write_text(json.dumps(output, indent=2, ensure_ascii=False), encoding="utf-8")
            print(f"\n  📄 JSON report saved to: {args.output}")
        else:
            print(json.dumps(output, indent=2, ensure_ascii=False))
    else:
        report = generate_report(result)
        print_quick_summary(result)

        if args.output:
            Path(args.output).write_text(report, encoding="utf-8")
            print(f"  📄 Report saved to: {args.output}")

        # Print critical/high issues
        critical_high = [i for i in result.issues if i.severity in ("critical", "high")]
        if critical_high:
            print(f"\n  🚨 CRITICAL/HIGH ISSUES ({len(critical_high)}):")
            for issue in critical_high:
                print(f"     [{issue.severity.upper()}] {issue.code}: {issue.message}")
                print(f"     📁 {issue.file}")
                if issue.fixable:
                    print(f"     🔧 Auto-fix available: {issue.fix_description}")
                print()

    # Summary line
    if result.stats["total_issues"] == 0:
        print("  🎉 No issues found! Your mobile design looks clean.")
    else:
        print(f"  📊 Found {result.stats['total_issues']} issues ({result.stats['fixable']} fixable).")
        if not args.fix and result.stats["fixable"] > 0:
            print(f"     Run with --fix to auto-fix {result.stats['fixable']} issues.")

    return 0 if result.stats["by_severity"].get("critical", 0) == 0 else 1

if __name__ == "__main__":
    sys.exit(main())
