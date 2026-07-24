#!/usr/bin/env python3
"""
VegasRoyalSpin Advanced Auto-Fix Tool
Automatically fixes safe HTML/CSS/JS issues detected by system_analyzer.py.

Usage: python tools/auto_fix.py [--dry-run] [--backup]
  --dry-run  Preview fixes without writing
  --backup   Create .bak backups before modifying
"""

import os
import re
import sys
import json
import shutil
import difflib
from pathlib import Path
from datetime import datetime
from collections import defaultdict

PROJECT_ROOT = Path(__file__).resolve().parent.parent
LATEST_REPORT = None

# Find latest report
reports_dir = PROJECT_ROOT / 'tools' / 'reports'
if reports_dir.exists():
    json_reports = sorted(reports_dir.glob('scan_report_*.json'), reverse=True)
    if json_reports:
        LATEST_REPORT = json_reports[0]

BACKUP_DIR = PROJECT_ROOT / 'tools' / 'backups'
FIX_LOG_PATH = PROJECT_ROOT / 'tools' / 'auto_fix_log.json'

# ── CLI ────────────────────────────────────────────────────────────
DRY_RUN = '--dry-run' in sys.argv
DO_BACKUP = '--backup' in sys.argv

# ── Stats ──────────────────────────────────────────────────────────
stats = {
    'files_modified': 0,
    'fixes_applied': 0,
    'fixes_skipped': 0,
    'by_category': defaultdict(int),
    'errors': [],
}
fix_log: list[dict] = []

# ── Utils ──────────────────────────────────────────────────────────

def rel(path: Path) -> str:
    try:
        return str(path.relative_to(PROJECT_ROOT)).replace('\\', '/')
    except ValueError:
        return str(path)

def read_file(path: Path) -> str | None:
    for enc in ('utf-8', 'latin-1', 'cp1254', 'iso-8859-9'):
        try:
            with open(path, 'r', encoding=enc) as f:
                return f.read()
        except (UnicodeDecodeError, Exception):
            continue
    return None

def write_file(path: Path, content: str):
    if DRY_RUN:
        return
    if DO_BACKUP:
        backup_path = BACKUP_DIR / (path.name + '.bak.' + datetime.now().strftime('%Y%m%d%H%M%S'))
        backup_path.parent.mkdir(parents=True, exist_ok=True)
        shutil.copy2(path, backup_path)
    with open(path, 'w', encoding='utf-8') as f:
        f.write(content)

def log_fix(file_path: Path, line: int, category: str, description: str):
    stats['fixes_applied'] += 1
    stats['by_category'][category] += 1
    fix_log.append({
        'file': rel(file_path), 'line': line,
        'category': category, 'description': description,
    })

def skip_fix(reason: str):
    stats['fixes_skipped'] += 1


# ╔══════════════════════════════════════════════════════════════════╗
# ║                     FIX FUNCTIONS                               ║
# ╚══════════════════════════════════════════════════════════════════╝

def fix_img_missing_alt(file_path: Path, content: str) -> tuple[str, int]:
    """Add alt="" to <img> tags missing alt attribute. Skips PHP expressions."""
    fixes = 0
    # Match img tags that don't have alt= anywhere in the tag (including inside PHP)
    # But be careful: <?= ... ?> can contain 'alt=' as part of PHP expression
    # Strategy: extract all img tags, check each one individually
    
    img_re = re.compile(r'<img\b([^>]*?)(/?)>', re.IGNORECASE)
    
    def check_and_fix(m):
        nonlocal fixes
        full = m.group(0)
        attrs = m.group(1)
        self_close = m.group(2)
        
        # Already has alt? Skip
        if re.search(r'\balt\s*=', attrs, re.IGNORECASE):
            return full
        
        # Don't add alt="" if the img tag contains PHP - too risky
        if '<?' in full:
            return full
            
        fixes += 1
        return f'<img{attrs} alt=""{self_close}>'
    
    new_content = img_re.sub(check_and_fix, content)
    if fixes:
        log_fix(file_path, 0, 'html-a11y', f'Added alt="" to {fixes} <img> tags')
    return new_content, fixes


def fix_target_blank_noopener(file_path: Path, content: str) -> tuple[str, int]:
    """Add rel="noopener noreferrer" to target="_blank" links missing it."""
    fixes = 0

    def replacer(m):
        nonlocal fixes
        full_tag = m.group(0)
        # Check if rel="noopener" already exists elsewhere in the tag
        if 'noopener' in full_tag.lower():
            return full_tag
        fixes += 1
        # Insert rel before the closing > or before class/href end
        return full_tag.replace('target="_blank"', 'target="_blank" rel="noopener noreferrer"')

    # Match <a ... target="_blank" ... > (single line)
    pattern = re.compile(r'<a\b[^>]*target="_blank"[^>]*>', re.IGNORECASE)
    new_content = pattern.sub(replacer, content)

    if fixes:
        log_fix(file_path, 0, 'html-security', f'Added rel="noopener noreferrer" to {fixes} links')
    return new_content, fixes


def fix_missing_lang(file_path: Path, content: str) -> tuple[str, int]:
    """Add lang="tr" to <html> tag missing lang."""
    fixes = 0
    pattern = re.compile(r'<html\b(?![\s\S]*?\blang\s*=)([^>]*?)>', re.IGNORECASE)

    if pattern.search(content) and 'lang=' not in (re.search(r'<html\b([^>]*?)>', content, re.I).group(1) if re.search(r'<html\b', content, re.I) else ''):
        def replacer(m):
            nonlocal fixes
            fixes += 1
            return f'<html lang="tr"{m.group(1)}>'

        new_content = pattern.sub(replacer, content)
        if fixes:
            log_fix(file_path, 0, 'html-a11y', 'Added lang="tr" to <html>')
        return new_content, fixes
    return content, 0


def fix_missing_charset(file_path: Path, content: str) -> tuple[str, int]:
    """Add <meta charset="UTF-8"> in <head> if missing."""
    if '<meta charset=' in content.lower():
        return content, 0
    if '<head' not in content.lower():
        return content, 0

    fixes = 0
    pattern = re.compile(r'(<head\b[^>]*?>)', re.IGNORECASE)

    def replacer(m):
        nonlocal fixes
        fixes += 1
        return m.group(1) + '\n<meta charset="UTF-8">'

    new_content = pattern.sub(replacer, content, count=1)
    if fixes:
        log_fix(file_path, 0, 'html-meta', 'Added <meta charset="UTF-8">')
    return new_content, fixes


def fix_missing_viewport(file_path: Path, content: str) -> tuple[str, int]:
    """Add viewport meta tag if missing."""
    if 'viewport' in content.lower():
        return content, 0
    if '<head' not in content.lower():
        return content, 0

    fixes = 0
    # Insert after <meta charset> or <head>
    if '<meta charset=' in content.lower():
        pattern = re.compile(r'(<meta\s+charset=[^>]+>)', re.IGNORECASE)
        def replacer(m):
            nonlocal fixes
            fixes += 1
            return m.group(1) + '\n<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        new_content = pattern.sub(replacer, content, count=1)
    else:
        pattern = re.compile(r'(<head\b[^>]*?>)', re.IGNORECASE)
        def replacer(m):
            nonlocal fixes
            fixes += 1
            return m.group(1) + '\n<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        new_content = pattern.sub(replacer, content, count=1)

    if fixes:
        log_fix(file_path, 0, 'html-meta', 'Added viewport meta tag')
    return new_content, fixes


def fix_empty_css_rulesets(file_path: Path, content: str) -> tuple[str, int]:
    """Remove empty CSS rulesets like selector { }."""
    fixes = 0

    def replacer(m):
        nonlocal fixes
        fixes += 1
        return ''

    # Remove single-line empty rulesets
    pattern = re.compile(r'[^{}]+\{\s*\}\s*', re.MULTILINE)
    new_content = pattern.sub(replacer, content)

    # Also remove multi-line empty rulesets
    pattern2 = re.compile(r'[^{}]+\{\s*\n\s*\}', re.MULTILINE)
    new_content = pattern2.sub(replacer, new_content)

    if fixes:
        log_fix(file_path, 0, 'css-empty', f'Removed {fixes} empty CSS rulesets')
    return new_content, fixes


def fix_console_log(file_path: Path, content: str) -> tuple[str, int]:
    """Comment out console.log/console.debug calls, preserving surrounding if-guards."""
    fixes = 0
    lines = content.split('\n')
    new_lines = []

    for i, line in enumerate(lines):
        stripped = line.strip()
        if stripped.startswith('//') or stripped.startswith('/*') or stripped.startswith('*'):
            new_lines.append(line)
            continue

        # Only fix standalone console.log lines (not inside complex if conditions)
        m = re.search(r'\bconsole\.(log|debug|dir|info)\b', line)
        if m and 'console.error' not in line and 'console.warn' not in line:
            # Check if this line is JUST a console.log call (with optional if guard on same line)
            if re.match(r'^\s*console\.(log|debug|dir|info)\s*\(', line):
                indent = line[:len(line) - len(line.lstrip())]
                new_lines.append(f'{indent}// [DEV] {line.lstrip()}')
                fixes += 1
                continue
            # If the line has an if guard like: if (console && console.log) console.log(...)
            elif re.match(r'^\s*if\s*\(.*console.*\)\s*console\.(log|debug|dir|info)', line):
                indent = line[:len(line) - len(line.lstrip())]
                # Only comment the console call part, keep the if guard
                fixed = re.sub(
                    r'(if\s*\(.*?\))\s*(console\.(?:log|debug|dir|info)\s*\(.*?\))\s*;?\s*$',
                    r'\1 { /* [DEV] \2 */ }',
                    line
                )
                new_lines.append(fixed)
                fixes += 1
                continue

        new_lines.append(line)

    if fixes:
        log_fix(file_path, 0, 'js-console', f'Commented out {fixes} console.log calls')
    return '\n'.join(new_lines), fixes


def fix_missing_use_strict(file_path: Path, content: str) -> tuple[str, int]:
    """Add 'use strict'; to JS files missing it."""
    if not content.strip():
        return content, 0
    if "'use strict'" in content[:2000] or '"use strict"' in content[:2000]:
        return content, 0

    fixes = 0
    # Add to top of file (after optional comment block)
    first_line = content.strip().split('\n')[0] if content.strip() else ''

    if first_line.startswith('/**') or first_line.startswith('/*'):
        # Find end of comment block
        end_idx = content.find('*/')
        if end_idx > 0:
            new_content = content[:end_idx+2] + "\n'use strict';\n" + content[end_idx+2:].lstrip()
        else:
            new_content = "'use strict';\n" + content
    elif first_line.startswith('//') or first_line.startswith('#'):
        new_content = "'use strict';\n" + content
    elif content.startswith('(function'):
        # IIFE - add inside
        new_content = content.replace('(function', "(function () {\n    'use strict';", 1)
    else:
        new_content = "'use strict';\n" + content

    fixes = 1
    log_fix(file_path, 0, 'js-strict', "Added 'use strict'")
    return new_content, fixes


def fix_javascript_void(file_path: Path, content: str) -> tuple[str, int]:
    """Replace href="javascript:void(0)" with href="#" (cosmetic)."""
    fixes = 0
    pattern = re.compile(r'href="javascript:void\(0\)"', re.IGNORECASE)

    def replacer(m):
        nonlocal fixes
        fixes += 1
        return 'href="#"'

    new_content = pattern.sub(replacer, content)
    if fixes:
        log_fix(file_path, 0, 'html-link', f'Replaced {fixes} href="javascript:void(0)" with href="#"')
    return new_content, fixes


def fix_input_no_type(file_path: Path, content: str) -> tuple[str, int]:
    """Add type="text" to <input> tags without type attribute."""
    fixes = 0
    pattern = re.compile(r'<input\b(?![^>]*\btype\s*=)([^>]*?)(/?)>', re.IGNORECASE)

    def replacer(m):
        nonlocal fixes
        fixes += 1
        attrs = m.group(1)
        self_close = m.group(2)
        return f'<input type="text"{attrs}{self_close}>'

    new_content = pattern.sub(replacer, content)
    if fixes:
        log_fix(file_path, 0, 'html-form', f'Added type="text" to {fixes} <input> tags')
    return new_content, fixes


def fix_xss_unescaped_echo(file_path: Path, content: str) -> tuple[str, int]:
    """
    Auto-wrap simple unescaped PHP echo expressions with htmlspecialchars.
    VERY conservative: only wraps simple scalar variable references.
    Skips variables that likely contain HTML ($media, $html, $body, $content, etc.)
    Skips lines with include/require.
    """
    fixes = 0
    lines = content.split('\n')
    new_lines = []

    # Known safe escaping functions (don't double-wrap)
    safe_funcs = [
        'htmlspecialchars', 'htmlentities', 'strip_tags',
        'h(', 'text(', 'e(', 'esc_html(', 'esc_attr(',
        '(int)', '(float)',
        'json_encode', 'urlencode', 'rawurlencode',
        'number_format', 'ucfirst', 'strtoupper', 'strtolower',
        'basename', 'trim', 'date(', 'gmdate(',
        'defined(', 'constant(', 'empty(',
        'isset(', 'is_array(', 'is_numeric(',
        'count(', 'sizeof(',
        'money(', 'dateInputValue(', 'dateTimeInputValue(',
        'badgeClass(', 'fieldLabel(', 'fieldId(',
        'parseEnumOptions(', 'jsonRows(', 'jsonRootType(',
        'val(', 'hsc(',
    ]

    # Variable names that likely contain HTML (do NOT escape)
    html_var_pattern = re.compile(
        r'\$(media|html|body|content|rendered|output|markup|template|snippet|inner|outer|'
        r'htmlContent|htmlBody|emailBody|emailHtml|rawHtml|htmlOutput|'
        r'cardHtml|rowHtml|itemHtml|listHtml|tableHtml)',
        re.IGNORECASE
    )

    for i, line in enumerate(lines):
        # Skip lines with include/require
        if re.search(r'\b(include|require)\b', line):
            new_lines.append(line)
            continue

        # Find all <?= ... ?> in the line
        matches = list(re.finditer(r'<\?=\s*(.+?)\s*\?>', line))
        if not matches:
            new_lines.append(line)
            continue

        modified_line = line
        for m in reversed(matches):
            expr = m.group(1).strip()

            # Skip if already has a safe function
            if any(fn in expr for fn in safe_funcs):
                continue

            # Skip if expression contains HTML-like variable names
            if html_var_pattern.search(expr):
                continue

            # Skip if expression is too complex
            if '(' in expr or '?' in expr or ':' in expr:
                continue

            # Skip if it's just a number/constant/string
            if re.match(r'^[\d.\-\'"]+$', expr):
                continue

            # Skip if it's a PHP keyword/constant
            if expr.lower() in ['true', 'false', 'null']:
                continue

            # Only auto-fix simple variable references (no function calls, no concat, no HTML vars)
            if re.match(r'^\$[a-zA-Z_][\w]*(\[\s*[\'"]?\w+[\'"]?\s*\])*$', expr):
                varname = expr.split('[')[0].lstrip('$')
                # Skip known HTML variables
                if re.match(r'^(media|html|body|content|rendered|output|markup|template)$', varname, re.I):
                    continue
                full = m.group(0)
                new_expr = f"<?= htmlspecialchars({expr}, ENT_QUOTES, 'UTF-8') ?>"
                modified_line = modified_line.replace(full, new_expr, 1)
                fixes += 1
            elif re.match(r'^\(string\)\s*\$[a-zA-Z_]', expr):
                inner = re.sub(r'^\(string\)\s*', '', expr)
                varname = inner.lstrip('$')
                if re.match(r'^(media|html|body|content|rendered|output)$', varname, re.I):
                    continue
                full = m.group(0)
                new_expr = f"<?= htmlspecialchars({inner}, ENT_QUOTES, 'UTF-8') ?>"
                modified_line = modified_line.replace(full, new_expr, 1)
                fixes += 1

        new_lines.append(modified_line)

    if fixes:
        log_fix(file_path, 0, 'html-xss', f'Wrapped {fixes} unescaped PHP echoes with htmlspecialchars()')
    return '\n'.join(new_lines), fixes


def fix_deprecated_attrs(file_path: Path, content: str) -> tuple[str, int]:
    """Convert deprecated HTML attributes to inline styles (gentle approach: just flag, don't change)."""
    # This is a risky fix - skip auto-fix, just report
    return content, 0


# ╔══════════════════════════════════════════════════════════════════╗
# ║                      ORCHESTRATOR                               ║
# ╚══════════════════════════════════════════════════════════════════╝

def process_file(file_path: Path, categories: set) -> int:
    """Process a single file applying relevant fixes."""
    content = read_file(file_path)
    if not content:
        return 0

    original = content
    ext = file_path.suffix.lower()
    is_php = ext in {'.php', '.phtml'}
    is_js = ext == '.js'
    is_css = ext == '.css'
    total_fixes = 0

    # Apply fixes based on file type and enabled categories
    fixes_to_apply = []

    if is_php or ext in {'.html', '.htm'}:
        if 'html-a11y' in categories:
            fixes_to_apply.append(('img-missing-alt', fix_img_missing_alt))
            fixes_to_apply.append(('missing-lang', fix_missing_lang))
        if 'html-meta' in categories:
            fixes_to_apply.append(('missing-charset', fix_missing_charset))
            fixes_to_apply.append(('missing-viewport', fix_missing_viewport))
        if 'html-xss' in categories:
            fixes_to_apply.append(('xss-echo', fix_xss_unescaped_echo))
        # These are too risky for auto-fix:
        # html-security: target_blank (already done manually)
        # html-link: javascript:void (cosmetic, may break JS)
        # html-form: input_no_type (may conflict with PHP-generated inputs)

    if is_js:
        if 'js-console' in categories:
            fixes_to_apply.append(('console-log', fix_console_log))
        if 'js-strict' in categories:
            fixes_to_apply.append(('missing-strict', fix_missing_use_strict))

    if is_css:
        if 'css-empty' in categories:
            fixes_to_apply.append(('empty-css', fix_empty_css_rulesets))

    for fix_name, fix_fn in fixes_to_apply:
        try:
            content, count = fix_fn(file_path, content)
            total_fixes += count
        except Exception as e:
            stats['errors'].append(f'{rel(file_path)} @ {fix_name}: {e}')

    if content != original and not DRY_RUN:
        write_file(file_path, content)
        stats['files_modified'] += 1

    return total_fixes


def get_files_from_report() -> dict:
    """Parse the latest analyzer report and group fixable issues by file."""
    if not LATEST_REPORT or not LATEST_REPORT.exists():
        print("No analyzer report found. Run system_analyzer.py first.")
        return {}

    with open(LATEST_REPORT, 'r', encoding='utf-8') as f:
        report = json.load(f)

    by_file: dict[str, set] = defaultdict(set)
    for issue in report.get('issues', []):
        if issue.get('fixable'):
            by_file[issue['file']].add(issue['category'])

    return dict(by_file)


def scan_and_fix_all():
    """Main entry point - scan all files and apply fixes."""
    print("=" * 70)
    print("  VegasRoyalSpin Advanced Auto-Fix Tool")
    print(f"  Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    if DRY_RUN:
        print("  MODE: DRY RUN (no files will be modified)")
    if DO_BACKUP:
        print("  MODE: BACKUP (creating .bak files)")
    print("=" * 70)

    # Get files to fix from report
    file_categories = get_files_from_report()
    if not file_categories:
        print("\nNo fixable issues found in report. Looking for issues directly...")
        # Fallback: scan all PHP/JS/CSS files
        return direct_scan_and_fix()

    print(f"\nLoaded {len(file_categories)} files to fix from report.")

    # Group by category for summary
    cat_count = defaultdict(int)
    for cats in file_categories.values():
        for c in cats:
            cat_count[c] += 1

    print("\nFixable issues by category:")
    for cat, cnt in sorted(cat_count.items(), key=lambda x: -x[1]):
        print(f"  {cat}: {cnt} files")

    total_fixes = 0
    processed = 0
    files_list = sorted(file_categories.keys())

    print(f"\nProcessing {len(files_list)} files...\n")

    for fname in files_list:
        fpath = PROJECT_ROOT / fname
        if not fpath.exists():
            continue
        cats = file_categories[fname]
        fixes = process_file(fpath, cats)
        total_fixes += fixes
        processed += 1
        if processed % 50 == 0:
            print(f"  [{processed}/{len(files_list)}] {total_fixes} fixes so far...")

    print(f"  [{processed}/{len(files_list)}] Complete! {total_fixes} total fixes applied.")

    # Summary
    print("\n" + "=" * 70)
    print("  AUTO-FIX RESULTS")
    print("=" * 70)
    print(f"\n  Files modified: {stats['files_modified']}")
    print(f"  Fixes applied:  {stats['fixes_applied']}")
    print(f"  Fixes skipped:  {stats['fixes_skipped']}")

    print(f"\n  By category:")
    for cat, cnt in sorted(stats['by_category'].items(), key=lambda x: -x[1]):
        print(f"    {cat}: {cnt}")

    if stats['errors']:
        print(f"\n  Errors ({len(stats['errors'])}):")
        for e in stats['errors'][:10]:
            print(f"    {e}")

    # Save fix log
    fix_log_data = {
        'timestamp': datetime.now().isoformat(),
        'stats': dict(stats),
        'fixes': fix_log,
    }
    with open(FIX_LOG_PATH, 'w', encoding='utf-8') as f:
        json.dump(fix_log_data, f, indent=2, ensure_ascii=False)

    print(f"\n  Fix log: {rel(FIX_LOG_PATH)}")
    print("=" * 70)


def direct_scan_and_fix():
    """Fallback: scan all files directly and fix issues."""
    print("\nPerforming direct scan...")

    HTML_EXT = {'.php', '.html', '.htm', '.phtml'}
    JS_EXT = {'.js'}
    CSS_EXT = {'.css'}

    EXCLUDE_DIRS = {'vendor', 'node_modules', '.git', '.venv', 'storage', 'logs',
                    'archive', 'deploy', 'tools', 'scripts', 'cert.gcb.cw',
                    'database', 'admin/vendor', 'admin/storage', 'bin'}
    EXCLUDE_FILES = {'swiper-bundle.min.js', 'swiper-bundle.min.css', 'runtime.js',
                     'vendors.js', '2026.js', 'admin-ui.js', 'vendor-chartjs.js',
                     'vendor-fullcalendar.js', 'style.css', 'bc-mobile-index.css',
                     'bc-mobile-header-original.css', 'bc-mobile-custom.css',
                     'bc-mobile-maltabet.css'}

    total_fixes = 0
    processed = 0
    all_files = []

    for root_dir in [PROJECT_ROOT, PROJECT_ROOT / 'views', PROJECT_ROOT / 'pages',
                      PROJECT_ROOT / 'mobile', PROJECT_ROOT / 'assets', PROJECT_ROOT / 'api',
                      PROJECT_ROOT / 'admin']:
        if not root_dir.exists():
            continue
        for f in root_dir.rglob('*'):
            if not f.is_file():
                continue
            rel_path = str(f.relative_to(PROJECT_ROOT)).replace('\\', '/')
            if any(ex in rel_path.split('/') for ex in EXCLUDE_DIRS):
                continue
            if f.name in EXCLUDE_FILES:
                continue
            ext = f.suffix.lower()
            if ext in HTML_EXT | JS_EXT | CSS_EXT:
                all_files.append(f)

    all_files = list(set(all_files))
    print(f"Found {len(all_files)} files to scan.")

    all_cats = {'html-a11y', 'html-security', 'html-meta', 'html-link',
                'html-form', 'html-xss', 'js-console', 'js-strict', 'css-empty'}

    for f in sorted(all_files):
        fixes = process_file(f, all_cats)
        total_fixes += fixes
        processed += 1
        if processed % 50 == 0:
            print(f"  [{processed}/{len(all_files)}] {total_fixes} fixes...")

    print(f"\n  Complete! {total_fixes} fixes applied.")
    print(f"  Files modified: {stats['files_modified']}")


if __name__ == '__main__':
    scan_and_fix_all()
