#!/usr/bin/env python3
"""
VegasRoyalSpin System Analyzer v2
- HTML/PHP template error detection
- JavaScript lint & pattern analysis
- CSS quality & error detection
- Robust against large/complex files

Usage: python tools/system_analyzer.py [--fix]
"""

import os
import re
import sys
import json
import threading
from pathlib import Path
from collections import defaultdict, Counter
from datetime import datetime

# ── Configuration ──────────────────────────────────────────────────
PROJECT_ROOT = Path(__file__).resolve().parent.parent

HTML_EXTENSIONS = {'.php', '.html', '.htm', '.phtml'}
JS_EXTENSIONS   = {'.js'}
CSS_EXTENSIONS  = {'.css'}

EXCLUDE_DIRS = {
    'vendor', 'node_modules', '.git', '.venv', 'storage', 'logs',
    'archive', 'deploy', 'tools', 'scripts', 'cert.gcb.cw',
    'database', 'admin/vendor', 'admin/storage', 'bin',
}
EXCLUDE_FILES = {
    'swiper-bundle.min.js', 'swiper-bundle.min.css',
    'runtime.js', 'vendors.js', '2026.js', 'admin-ui.js',
    'vendor-chartjs.js', 'vendor-fullcalendar.js', 'style.css',
    'bc-mobile-index.css', 'bc-mobile-header-original.css',
    'bc-mobile-custom.css', 'bc-mobile-maltabet.css',
}

OUTPUT_DIR = PROJECT_ROOT / 'tools' / 'reports'
OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

FILE_TIMEOUT = 10

# ── Utility ────────────────────────────────────────────────────────
def should_exclude(file_path: Path) -> bool:
    rel = str(file_path.relative_to(PROJECT_ROOT)).replace('\\', '/')
    for ex in EXCLUDE_DIRS:
        if rel.startswith(ex + '/') or ex in rel.split('/'):
            return True
    return file_path.name in EXCLUDE_FILES

def rel(path: Path) -> str:
    try:
        return str(path.relative_to(PROJECT_ROOT)).replace('\\', '/')
    except ValueError:
        return str(path)

def read_file_safe(path: Path) -> str | None:
    for enc in ('utf-8', 'latin-1', 'cp1254', 'iso-8859-9'):
        try:
            with open(path, 'r', encoding=enc) as fh:
                return fh.read()
        except (UnicodeDecodeError, Exception):
            continue
    return None

# ── Issue collector ────────────────────────────────────────────────
class Collector:
    def __init__(self):
        self.issues: list[dict] = []
        self.stats = defaultdict(int)
        self._lock = threading.Lock()

    def add(self, file_path: Path, line: int, severity: str, category: str,
            message: str, fixable: bool = False, suggestion: str = ''):
        with self._lock:
            self.issues.append({
                'file': rel(file_path), 'line': line, 'severity': severity,
                'category': category, 'message': message,
                'fixable': fixable, 'suggestion': suggestion,
            })
            self.stats[f'{severity}_{category}'] += 1
            self.stats['total'] += 1

collector = Collector()
SKIPPED_FILES: list[str] = []

# ╔══════════════════════════════════════════════════════════════════╗
# ║                    HTML/PHP ANALYSIS                             ║
# ╚══════════════════════════════════════════════════════════════════╝

VOID_TAGS = {'area','base','br','col','embed','hr','img','input','link','meta',
             'param','source','track','wbr'}
PAIRED_TAGS = {'div','span','p','a','section','article','header','footer','nav',
               'main','aside','ul','ol','li','dl','dt','dd','table','thead','tbody',
               'tfoot','tr','th','td','form','fieldset','label','select','option',
               'optgroup','textarea','button','h1','h2','h3','h4','h5','h6',
               'strong','em','b','i','u','s','small','mark','script','style',
               'noscript','iframe','object','video','audio','canvas','figure',
               'figcaption','details','summary','dialog','template','slot',
               'title','head','body','html','blockquote','pre','code','abbr',
               'cite','q','sub','sup','ins','del','caption','colgroup','map'}

DEPRECATED_ATTRS = {
    'align': 'Use CSS instead',
    'bgcolor': 'Use CSS background-color',
    'border': 'Use CSS border (on non-table elements)',
    'cellpadding': 'Use CSS padding',
    'cellspacing': 'Use CSS border-spacing',
    'valign': 'Use CSS vertical-align',
}

def analyze_html_php(file_path: Path, content: str):
    lines = content.split('\n')

    # 1. Missing lang on <html>
    for i, line in enumerate(lines, 1):
        if re.search(r'<html\b(?![\s\S]*?\blang\s*=)', line, re.I):
            collector.add(file_path, i, 'warning', 'html-a11y',
                          '<html> missing lang attribute', True, 'Add lang="tr"')
            break

    # 2. <img> without alt
    for i, line in enumerate(lines, 1):
        for _ in re.finditer(r'<img\b(?![^>]*\balt\s*=)[^>]*>', line, re.I):
            collector.add(file_path, i, 'warning', 'html-a11y',
                          '<img> missing alt attribute', True, 'Add alt="..."')

    # 3. Empty <a> / <button>
    for i, line in enumerate(lines, 1):
        if re.search(r'<a\b[^>]*>\s*</a>', line, re.I):
            collector.add(file_path, i, 'warning', 'html-a11y', 'Empty <a> tag')
        if re.search(r'<button\b[^>]*>\s*</button>', line, re.I):
            collector.add(file_path, i, 'warning', 'html-a11y', 'Empty <button> tag')

    # 4. <form> without action
    for i, line in enumerate(lines, 1):
        if re.search(r'<form\b', line, re.I) and 'action=' not in line:
            collector.add(file_path, i, 'info', 'html-form', '<form> without action attribute')

    # 5. <input> without type
    for i, line in enumerate(lines, 1):
        for _ in re.finditer(r'<input\b(?![^>]*\btype\s*=)[^>]*>', line, re.I):
            collector.add(file_path, i, 'info', 'html-form',
                          '<input> without type', True, 'Add type="text"')

    # 6. Deprecated attributes
    for attr, sug in DEPRECATED_ATTRS.items():
        pat = re.compile(rf'\b{attr}\s*=\s*["\']', re.I)
        for i, line in enumerate(lines, 1):
            if pat.search(line):
                collector.add(file_path, i, 'warning', 'html-deprecated',
                              f'Deprecated "{attr}" attribute — {sug}')

    # 7. Duplicate IDs
    ids = defaultdict(list)
    for i, line in enumerate(lines, 1):
        for m in re.finditer(r'\bid\s*=\s*["\']([^"\']+)["\']', line, re.I):
            ids[m.group(1).lower()].append(i)
    for id_val, occ in ids.items():
        if len(occ) > 1:
            collector.add(file_path, occ[0], 'error', 'html-duplicate-id',
                          f'Duplicate id="{id_val}" ({len(occ)}x at lines {occ})')

    # 8. Tag balance
    open_count = defaultdict(int)
    close_count = defaultdict(int)
    for line in lines:
        for m in re.finditer(r'<(\w+)', line, re.I):
            t = m.group(1).lower()
            if t in PAIRED_TAGS:
                open_count[t] += 1
        for m in re.finditer(r'</(\w+)', line, re.I):
            t = m.group(1).lower()
            if t in PAIRED_TAGS:
                close_count[t] += 1
    for tag in PAIRED_TAGS:
        diff = open_count[tag] - close_count[tag]
        if diff > 0:
            collector.add(file_path, 1, 'error', 'html-unclosed',
                          f'<{tag}> appears {diff}x more than </{tag}> — may be unclosed')
        elif diff < 0:
            collector.add(file_path, 1, 'error', 'html-unclosed',
                          f'</{tag}> appears {-diff}x more than <{tag}> — extra closing tag')

    # 9. Inline event handlers
    for i, line in enumerate(lines, 1):
        if re.search(r'\bon\w+\s*=\s*["\']', line, re.I):
            collector.add(file_path, i, 'info', 'html-inline-js',
                          'Inline event handler — prefer addEventListener')

    # 10. Too many inline styles
    style_count = sum(1 for l in lines if 'style=' in l)
    if style_count > 10:
        collector.add(file_path, 1, 'info', 'html-inline-style',
                      f'{style_count} inline style= attributes — consider CSS classes')

    # 11. Unescaped PHP echo
    SAFE_ECHO_FUNCS = [
        'htmlspecialchars', 'htmlentities', 'strip_tags',
        'h(', 'text(', 'e(', 'esc_html(', 'esc_attr(',
        'int)', '(int)', 'float)', '(float)',
        'json_encode', 'urlencode', 'rawurlencode',
        'number_format', 'ucfirst', 'strtoupper', 'strtolower',
        'basename', 'trim', 'date(', 'gmdate(',
        'money(', 'dateInputValue(', 'dateTimeInputValue(',
        'badgeClass(', 'fieldLabel(', 'fieldId(',
        'parseEnumOptions(', 'jsonRows(', 'jsonRootType(',
        'val(', 'hsc(',
    ]
    for i, line in enumerate(lines, 1):
        for m in re.finditer(r'<\?=\s*(.+?)\s*\?>', line):
            expr = m.group(1).strip()
            # Skip if contains any safe escaping function
            is_safe = any(fn in expr for fn in SAFE_ECHO_FUNCS)
            if not is_safe:
                collector.add(file_path, i, 'warning', 'html-xss',
                              'Short echo without escaping — potential XSS risk', True,
                              'Use <?= htmlspecialchars($var, ENT_QUOTES) ?>')

    # 12. Missing viewport meta
    if '<head' in content.lower() and 'viewport' not in content.lower():
        collector.add(file_path, 1, 'warning', 'html-meta',
                      'Missing viewport meta tag', True,
                      '<meta name="viewport" content="width=device-width, initial-scale=1.0">')

    # 13. Missing charset
    if '<head' in content.lower() and '<meta charset=' not in content.lower() and 'Content-Type' not in content:
        collector.add(file_path, 1, 'warning', 'html-meta',
                      'Missing charset declaration', True, '<meta charset="UTF-8">')

    # 14. href="#" or javascript:void
    for i, line in enumerate(lines, 1):
        if 'href="#"' in line:
            collector.add(file_path, i, 'info', 'html-link',
                          'href="#" — consider <button> or preventDefault')
        if 'href="javascript:void(0)"' in line.lower():
            collector.add(file_path, i, 'info', 'html-link',
                          'href="javascript:void(0)" — use <button> instead')

    # 15. target="_blank" without rel="noopener"
    for i, line in enumerate(lines, 1):
        if 'target="_blank"' in line and 'noopener' not in line:
            collector.add(file_path, i, 'warning', 'html-security',
                          'target="_blank" without rel="noopener" — security risk', True,
                          'Add rel="noopener noreferrer"')


# ╔══════════════════════════════════════════════════════════════════╗
# ║                    JAVASCRIPT ANALYSIS                           ║
# ╚══════════════════════════════════════════════════════════════════╝

def analyze_javascript(file_path: Path, content: str):
    lines = content.split('\n')

    # 1. console.log/debug/dir/info
    for i, line in enumerate(lines, 1):
        s = line.strip()
        if s.startswith('//') or s.startswith('/*') or s.startswith('*'):
            continue
        m = re.search(r'\bconsole\.(log|debug|dir|info)\b', line)
        if m and 'console.error' not in line and 'console.warn' not in line:
            collector.add(file_path, i, 'info', 'js-console',
                          f'console.{m.group(1)}() in production code', True,
                          'Remove or guard with DEV flag')

    # 2. var usage count
    var_count = sum(1 for l in lines if re.search(r'\bvar\s+\w+', l) and not l.strip().startswith('//'))
    if var_count > 0:
        collector.add(file_path, 1, 'info', 'js-es6',
                      f'{var_count} "var" declarations — prefer let/const', True,
                      'Replace var with let or const where possible')

    # 3. Missing 'use strict'
    if content.strip() and "'use strict'" not in content[:1000] and '"use strict"' not in content[:1000]:
        collector.add(file_path, 1, 'info', 'js-strict',
                      'Missing "use strict"', True, 'Add "use strict";')

    # 4. Loose equality
    eq_count = 0
    for line in lines:
        eq_count += len(re.findall(r'(?<![!=<>])=(?!=)(?![=>])', line))
    if eq_count > 5:
        collector.add(file_path, 1, 'warning', 'js-equality',
                      f'{eq_count} loose equality (==) — prefer strict (===)')

    # 5. debugger
    for i, line in enumerate(lines, 1):
        if re.search(r'\bdebugger\b', line) and not line.strip().startswith('//'):
            collector.add(file_path, i, 'error', 'js-debugger',
                          'debugger statement — remove before production', True,
                          'Remove debugger statement')

    # 6. innerHTML count
    inner_count = sum(1 for l in lines if '.innerHTML' in l)
    if inner_count > 5:
        collector.add(file_path, 1, 'warning', 'js-xss',
                      f'{inner_count} .innerHTML usages — verify XSS safety')

    # 7. eval()
    for i, line in enumerate(lines, 1):
        if re.search(r'\beval\s*\(', line) and not line.strip().startswith('//'):
            collector.add(file_path, i, 'error', 'js-eval',
                          'eval() is a critical security risk', True, 'Never use eval()')

    # 8. document.write
    for i, line in enumerate(lines, 1):
        if re.search(r'\bdocument\.write\b', line) and not line.strip().startswith('//'):
            collector.add(file_path, i, 'warning', 'js-deprecated',
                          'document.write() is deprecated')

    # 9. setTimeout with string
    for i, line in enumerate(lines, 1):
        if re.search(r'setTimeout\s*\(\s*["\']', line):
            collector.add(file_path, i, 'warning', 'js-eval-like',
                          'setTimeout with string — use function reference')

    # 10. fetch without .catch
    for i, line in enumerate(lines, 1):
        if re.search(r'\bfetch\s*\(', line) and 'await' not in line:
            chunk = '\n'.join(lines[i-1:min(i+15, len(lines))])
            if '.then(' in chunk and '.catch(' not in chunk:
                collector.add(file_path, i, 'warning', 'js-fetch',
                              'fetch() without .catch() — unhandled rejection possible')

    # 11. Large functions
    func_depth = 0
    func_start = 0
    for i, line in enumerate(lines, 1):
        if re.search(r'\bfunction\s+\w*\s*\(', line):
            func_start = i
            func_depth = 1
        elif func_depth > 0:
            func_depth += line.count('{') - line.count('}')
            if func_depth <= 0:
                length = i - func_start
                if length > 80:
                    collector.add(file_path, func_start, 'info', 'js-complexity',
                                  f'Large function ({length} lines) — consider splitting')
                func_depth = 0

    # 12. TODO/FIXME/HACK
    for i, line in enumerate(lines, 1):
        m = re.search(r'(?:TODO|FIXME|HACK|XXX|WORKAROUND)\b', line, re.I)
        if m and ('//' in line or '/*' in line):
            collector.add(file_path, i, 'info', 'js-todo',
                          f'{m.group()}: {line.strip()[:100]}')

    # 13. Semicolons
    for i, line in enumerate(lines, 1):
        s = line.strip()
        if not s or s.startswith('//') or s.startswith('/*') or s.startswith('*'):
            continue
        if s.endswith(';') or s.endswith('{') or s.endswith('}') or s.endswith(','):
            continue
        if re.match(r'^(if|else|for|while|switch|try|catch|finally|function|class)\b', s):
            continue
        if '=>' in s and not s.endswith(')'):
            continue
        if re.search(r'[=:]\s*\S', s):
            collector.add(file_path, i, 'info', 'js-semicolon',
                          'Line may be missing semicolon (ASI risk)')


# ╔══════════════════════════════════════════════════════════════════╗
# ║                      CSS ANALYSIS                                ║
# ╚══════════════════════════════════════════════════════════════════╝

def analyze_css(file_path: Path, content: str):
    lines = content.split('\n')

    # 1. Empty rulesets
    for i, line in enumerate(lines, 1):
        if re.search(r'\{\s*\}', line):
            collector.add(file_path, i, 'warning', 'css-empty',
                          'Empty CSS ruleset', True, 'Remove or fill empty ruleset')

    # 2. !important count
    imp_count = content.count('!important')
    if imp_count > 5:
        collector.add(file_path, 1, 'warning', 'css-important',
                      f'{imp_count} !important flags — specificity issue')

    # 3. Vendor prefix gaps
    needs_prefix_map = {
        'user-select': '-webkit-user-select',
        'appearance': '-webkit-appearance',
        'backdrop-filter': '-webkit-backdrop-filter',
        'text-size-adjust': '-webkit-text-size-adjust',
    }
    for prop, prefix in needs_prefix_map.items():
        if f'{prop}:' in content and f'{prefix}:' not in content:
            collector.add(file_path, 1, 'info', 'css-prefix',
                          f'"{prop}" may need vendor prefix for Safari')

    # 4. ID selectors count
    id_count = len(re.findall(r'#[a-zA-Z][\w-]*\s*\{', content))
    if id_count > 10:
        collector.add(file_path, 1, 'info', 'css-specificity',
                      f'{id_count} ID selectors — high specificity')

    # 5. Duplicate selectors
    blocks = []
    current_block = ''
    brace_depth = 0
    for line in lines:
        s = line.strip()
        if not s:
            if brace_depth > 0:
                current_block += ' '
            continue
        if s.startswith('/*'):
            continue
        brace_depth += s.count('{') - s.count('}')
        if '{' in s:
            current_block = s
        elif brace_depth > 0:
            current_block += ' ' + s
        if brace_depth == 0 and current_block:
            m = re.match(r'([^{]+)\{', current_block)
            if m:
                sel = re.sub(r'\s+', ' ', m.group(1).strip())
                if sel and not sel.startswith('@'):
                    blocks.append(sel)
            current_block = ''

    dupes = [s for s, c in Counter(blocks).items() if c > 1]
    for sel in dupes[:5]:
        collector.add(file_path, 1, 'warning', 'css-duplicate',
                      f'Duplicate selector: "{sel[:70]}"')

    # 6. CSS var() without fallback
    for i, line in enumerate(lines, 1):
        for m in re.finditer(r'var\((--[\w-]+)\)', line, re.I):
            collector.add(file_path, i, 'info', 'css-var-fallback',
                          f'var({m.group(1)}) without fallback')

    # 7. CSS property typos
    typos = {
        'boder': 'border', 'backgroud': 'background', 'marging': 'margin',
        'pading': 'padding', 'heigth': 'height', 'widht': 'width',
        'dispay': 'display', 'overfow': 'overflow', 'visiblity': 'visibility',
        'text-aling': 'text-align', 'font-weigth': 'font-weight',
        'boder-radius': 'border-radius', 'backround': 'background',
        'backgound': 'background', 'postion': 'position',
    }
    for i, line in enumerate(lines, 1):
        s = line.strip()
        if s.startswith('/*') or ':' not in s:
            continue
        for typo, correct in typos.items():
            if typo in s.lower():
                collector.add(file_path, i, 'error', 'css-typo',
                              f'Typo: "{typo}" → "{correct}"', True,
                              f'Replace {typo} with {correct}')


# ╔══════════════════════════════════════════════════════════════════╗
# ║                   MAIN SCAN ORCHESTRATOR                        ║
# ╚══════════════════════════════════════════════════════════════════╝

def find_all_files():
    html_files, js_files, css_files = [], [], []
    scan_roots = [
        PROJECT_ROOT,
        PROJECT_ROOT / 'views',
        PROJECT_ROOT / 'pages',
        PROJECT_ROOT / 'mobile',
        PROJECT_ROOT / 'assets',
        PROJECT_ROOT / 'api',
        PROJECT_ROOT / 'config',
        PROJECT_ROOT / 'controllers',
        PROJECT_ROOT / 'services',
        PROJECT_ROOT / 'routes',
    ]
    for root in scan_roots:
        if not root.exists():
            continue
        for f in root.rglob('*'):
            if not f.is_file():
                continue
            if should_exclude(f):
                continue
            ext = f.suffix.lower()
            if ext in HTML_EXTENSIONS:
                html_files.append(f)
            elif ext in JS_EXTENSIONS:
                js_files.append(f)
            elif ext in CSS_EXTENSIONS:
                css_files.append(f)
    return list(set(html_files)), list(set(js_files)), list(set(css_files))


def process_with_timeout(fn, file_path: Path, content: str, label: str):
    result = [False]
    def worker():
        try:
            fn(file_path, content)
            result[0] = True
        except Exception as e:
            print(f"  ERROR {label} {file_path.name}: {e}")
    t = threading.Thread(target=worker, daemon=True)
    t.start()
    t.join(timeout=FILE_TIMEOUT)
    if t.is_alive():
        SKIPPED_FILES.append(f"{rel(file_path)} (timeout {label})")
        return False
    return result[0]


def scan_all():
    print("=" * 70)
    print("  VegasRoyalSpin System Analyzer v2")
    print(f"  Started: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
    print("=" * 70)

    html_files, js_files, css_files = find_all_files()

    print(f"\n[HTML] {len(html_files)} HTML/PHP files")
    print(f"[JS]   {len(js_files)} JavaScript files")
    print(f"[CSS]  {len(css_files)} CSS files")
    total = len(html_files) + len(js_files) + len(css_files)
    print(f"\nScanning {total} files...\n")
    done = 0

    for f in sorted(html_files):
        content = read_file_safe(f)
        if content:
            process_with_timeout(analyze_html_php, f, content, 'HTML')
        done += 1
        if done % 50 == 0:
            print(f"  [{done}/{total}] {done*100//total}%")

    for f in sorted(js_files):
        content = read_file_safe(f)
        if content:
            process_with_timeout(analyze_javascript, f, content, 'JS')
        done += 1
        if done % 50 == 0:
            print(f"  [{done}/{total}] {done*100//total}%")

    for f in sorted(css_files):
        content = read_file_safe(f)
        if content:
            process_with_timeout(analyze_css, f, content, 'CSS')
        done += 1
        if done % 50 == 0:
            print(f"  [{done}/{total}] {done*100//total}%")

    print(f"  [{done}/{total}] 100% — Complete!")

    if SKIPPED_FILES:
        print(f"\n  WARNING: {len(SKIPPED_FILES)} files skipped (timeout):")
        for sf in SKIPPED_FILES[:5]:
            print(f"     {sf}")

    generate_report()


def generate_report():
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    issues = collector.issues
    errors   = [i for i in issues if i['severity'] == 'error']
    warnings = [i for i in issues if i['severity'] == 'warning']
    infos    = [i for i in issues if i['severity'] == 'info']

    # JSON Report
    json_path = OUTPUT_DIR / f'scan_report_{timestamp}.json'
    report = {
        'timestamp': datetime.now().isoformat(),
        'project': 'VegasRoyalSpin',
        'total_issues': len(issues),
        'by_severity': {'error': len(errors), 'warning': len(warnings), 'info': len(infos)},
        'fixable': len([i for i in issues if i.get('fixable')]),
        'issues': issues,
    }
    with open(json_path, 'w', encoding='utf-8') as fh:
        json.dump(report, fh, indent=2, ensure_ascii=False)

    # Markdown Report
    md_path = OUTPUT_DIR / f'scan_report_{timestamp}.md'
    with open(md_path, 'w', encoding='utf-8') as fh:
        fh.write(f"# VegasRoyalSpin System Analysis Report\n\n")
        fh.write(f"**Date:** {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}\n\n")
        fh.write(f"## Summary\n\n")
        fh.write(f"| Severity | Count |\n|----------|-------|\n")
        fh.write(f"| Error | {len(errors)} |\n")
        fh.write(f"| Warning | {len(warnings)} |\n")
        fh.write(f"| Info | {len(infos)} |\n")
        fh.write(f"| **Total** | **{len(issues)}** |\n")
        fh.write(f"| 🔧 Fixable | {len([i for i in issues if i.get('fixable')])} |\n\n")

        fh.write(f"## By Category\n\n| Category | Count |\n|----------|-------|\n")
        for cat, cnt in Counter(i['category'] for i in issues).most_common():
            fh.write(f"| {cat} | {cnt} |\n")

        fh.write(f"\n## Top Files\n\n| File | Issues |\n|------|--------|\n")
        for fname, cnt in Counter(i['file'] for i in issues).most_common(20):
            fh.write(f"| {fname} | {cnt} |\n")

        if errors:
            fh.write(f"\n## Errors ({len(errors)})\n\n")
            for e in sorted(errors, key=lambda x: x['category']):
                fh.write(f"- **[{e['category']}]** `{e['file']}:{e['line']}` — {e['message']}\n")

        if warnings:
            fh.write(f"\n## Warnings ({len(warnings)})\n\n")
            for w in sorted(warnings, key=lambda x: x['category']):
                fh.write(f"- **[{w['category']}]** `{w['file']}:{w['line']}` — {w['message']}\n")

        if infos:
            fh.write(f"\n## Info (first 50 of {len(infos)})\n\n")
            for info in sorted(infos, key=lambda x: x['category'])[:50]:
                fh.write(f"- **[{info['category']}]** `{info['file']}:{info['line']}` — {info['message']}\n")
            if len(infos) > 50:
                fh.write(f"\n*...plus {len(infos)-50} more (see JSON report)*\n")

    # Console summary
    print("\n" + "=" * 70)
    print("  RESULTS")
    print("=" * 70)
    print(f"\n  Errors:   {len(errors)}")
    print(f"  Warnings: {len(warnings)}")
    print(f"  Info:     {len(infos)}")
    print(f"  Total:    {len(issues)}")
    print(f"  Fixable:  {len([i for i in issues if i.get('fixable')])}")

    print(f"\n  Top Categories:")
    for cat, cnt in Counter(i['category'] for i in issues).most_common(10):
        print(f"     {cat}: {cnt}")

    print(f"\n  Top Files:")
    for fname, cnt in Counter(i['file'] for i in issues).most_common(10):
        print(f"     {fname}: {cnt}")

    if errors:
        print(f"\n  CRITICAL ERRORS:")
        for e in errors[:15]:
            print(f"     [{e['category']}] {e['file']}:{e['line']} — {e['message']}")

    print(f"\n  JSON report: {rel(json_path)}")
    print(f"  Markdown report: {rel(md_path)}")
    print("=" * 70)


if __name__ == '__main__':
    if '--help' in sys.argv or '-h' in sys.argv:
        print(__doc__)
        sys.exit(0)
    scan_all()
