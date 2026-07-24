#!/usr/bin/env python3
"""
VegasRoyalSpin PHP Deep Analyzer v3
Deep PHP security and code quality analysis
- SQL injection detection
- Insecure function usage
- Hardcoded credentials
- PHP 8.x compatibility
- Missing error handling
- Cross-file architecture analysis
"""

import os
import re
import json
import hashlib
from pathlib import Path
from collections import defaultdict, Counter
from datetime import datetime

PROJECT_ROOT = Path(r"c:\laragon\www\vegasroyalspin")

EXCLUDE_DIRS = {
    'vendor', 'node_modules', '.git', '.venv', '__pycache__',
    'storage', 'logs', 'cache', 'upload', 'uploads',
    'cert.gcb.cw', 'archive', 'deploy', '.github', '.claude',
    'fonts', 'images', 'games-img', 'footer', 'bin'
}

EXCLUDE_FILES = {
    'swiper-bundle.min.js', 'swiper-bundle.min.css',
    'runtime.js', 'vendors.js', '2026.js', 'admin-ui.js',
    'vendor-chartjs.js', 'vendor-fullcalendar.js',
}

CRITICAL = 'CRITICAL'
HIGH = 'HIGH'
MEDIUM = 'MEDIUM'
LOW = 'LOW'
INFO = 'INFO'


def should_skip(file_path: Path) -> bool:
    parts = set(file_path.parts)
    if parts & EXCLUDE_DIRS:
        return True
    if file_path.name in EXCLUDE_FILES:
        return True
    return False


def read_file(file_path: Path) -> str | None:
    for enc in ('utf-8', 'latin-1', 'cp1254', 'iso-8859-9'):
        try:
            with open(file_path, 'r', encoding=enc, errors='replace') as f:
                return f.read()
        except Exception:
            continue
    return None


# ---------------------------------------------------------------------------
# PHP Deep Analysis Engine
# ---------------------------------------------------------------------------

class PHPDeepAnalyzer:
    def __init__(self):
        self.issues = []
        self.stats = Counter()
        self.classes = defaultdict(list)
        self.functions = defaultdict(list)
        self.file_hashes = {}
        self.db_queries = []
        self.includes = defaultdict(set)

    # ── SECURITY CHECKS ──────────────────────────────────────────

    def check_sql_injection(self, file_path: Path, content: str, lines: list):
        """Detect SQL injection patterns."""
        risky_patterns = [
            # Direct variable interpolation in query strings
            (r'(?i)(mysqli_query|mysql_query|pg_query|->query|->exec|->prepare|DB::raw)\s*\(\s*["\']\s*\.\s*\$', CRITICAL,
             'SQL injection risk: variable concatenated into query string'),
            (r'(?i)(mysqli_query|mysql_query|->query|->exec)\s*\(\s*["\'].*?\$\w+', CRITICAL,
             'SQL injection risk: variable inside query string'),
            (r'(?i)WHERE\s+\w+\s*=\s*[\'"]?\s*\$_(GET|POST|REQUEST|COOKIE)', CRITICAL,
             'SQL injection: direct user input in WHERE clause'),
            (r'(?i)(INSERT|UPDATE|DELETE)\s+.*?\$_(GET|POST|REQUEST)', CRITICAL,
             'SQL injection: user input in INSERT/UPDATE/DELETE'),
            (r'(?i)->query\s*\(\s*\$', HIGH,
             'Dynamic SQL query - verify prepared statement usage'),
            (r'(?i)sprintf\s*\(\s*["\'].*?(INSERT|UPDATE|DELETE|SELECT|WHERE).*?["\']\s*,', MEDIUM,
             'sprintf used with SQL - consider prepared statements'),
        ]
        for pattern, severity, msg in risky_patterns:
            for m in re.finditer(pattern, content):
                ln = content[:m.start()].count('\n') + 1
                self.issues.append({
                    'file': str(file_path), 'line': ln, 'severity': severity,
                    'category': 'php-security', 'check': 'sql-injection',
                    'message': msg,
                    'snippet': content[m.start():m.start()+100].strip()[:100]
                })

    def check_insecure_functions(self, file_path: Path, content: str, lines: list):
        """Detect dangerous/insecure PHP function usage."""
        patterns = [
            (r'\beval\s*\(', CRITICAL, 'eval() - arbitrary code execution'),
            (r'\bexec\s*\(', HIGH, 'exec() - shell command execution'),
            (r'\bshell_exec\s*\(', HIGH, 'shell_exec() - shell command execution'),
            (r'\bsystem\s*\(', HIGH, 'system() - shell command execution'),
            (r'\bpassthru\s*\(', HIGH, 'passthru() - raw command output'),
            (r'\bpopen\s*\(', HIGH, 'popen() - process pipe'),
            (r'\bproc_open\s*\(', HIGH, 'proc_open() - process execution'),
            (r'\bunserialize\s*\(\s*\$_(GET|POST|REQUEST|COOKIE|SERVER)', HIGH,
             'unserialize() on user input - object injection'),
            (r'\bextract\s*\(\s*\$_(GET|POST|REQUEST)', HIGH,
             'extract() on user input - variable injection'),
            (r'\b(include|require|include_once|require_once)\s*\$_(GET|POST|REQUEST)', CRITICAL,
             'File inclusion via user input - LFI/RFI'),
            (r'\b(include|require|include_once|require_once)\s*\$', MEDIUM,
             'Dynamic file inclusion - verify path sanitization'),
            (r'\bcreate_function\s*\(', HIGH, 'create_function() removed in PHP 8.0'),
            (r'\b(mysql_connect|mysql_query|mysql_fetch|mysql_real_escape|mysql_select_db|mysql_close|mysql_num_rows)\s*\(', HIGH,
             'Deprecated mysql_* extension - use PDO/mysqli'),
            (r'\beach\s*\(', MEDIUM, 'each() deprecated in PHP 7.2, removed in 8.0'),
            (r'@\s*(mysqli_query|mysql_query|file_get_contents|file_put_contents|include|require|unlink|mkdir|curl_exec|json_decode)', MEDIUM,
             'Error suppression (@) hides failures'),
            (r'\bparse_str\s*\(\s*\$_(GET|POST|REQUEST)', MEDIUM,
             'parse_str() on user input - variable injection'),
            (r'\bassert\s*\(', MEDIUM, 'assert() can execute code - avoid in production'),
            (r'\bpreg_replace\s*\(\s*["\'].*?/e["\']', HIGH,
             'preg_replace with /e modifier - removed in PHP 7.0'),
        ]

        for pattern, severity, msg in patterns:
            for m in re.finditer(pattern, content):
                ln = content[:m.start()].count('\n') + 1
                self.issues.append({
                    'file': str(file_path), 'line': ln, 'severity': severity,
                    'category': 'php-security', 'check': 'insecure-function',
                    'message': msg,
                    'snippet': content[m.start():m.start()+100].strip()[:100]
                })

    def check_xss_risks(self, file_path: Path, content: str, lines: list):
        """Detect XSS vulnerabilities."""
        patterns = [
            (r'\becho\s+\$_(GET|POST|REQUEST|COOKIE)\[', HIGH,
             'XSS: Direct echo of user input without escaping'),
            (r'\bprint\s+\$_(GET|POST|REQUEST|COOKIE)\[', HIGH,
             'XSS: Direct print of user input'),
            (r'<\?=\s*\$\w+(?!.*htmlspecialchars|.*htmlentities|.*h\(|.*text\(|.*e\()', MEDIUM,
             'Short echo without escaping - potential XSS'),
            (r'\.innerHTML\s*=|\.html\s*\(\s*\$', MEDIUM,
             'innerHTML/.html() with variable - verify escaping in JS context'),
        ]
        for pattern, severity, msg in patterns:
            for m in re.finditer(pattern, content):
                ln = content[:m.start()].count('\n') + 1
                self.issues.append({
                    'file': str(file_path), 'line': ln, 'severity': severity,
                    'category': 'php-security', 'check': 'xss',
                    'message': msg,
                    'snippet': content[m.start():m.start()+100].strip()[:100]
                })

    def check_hardcoded_secrets(self, file_path: Path, content: str, lines: list):
        """Detect hardcoded credentials and secrets."""
        patterns = [
            (r'(?i)(password|passwd|pass|pwd)\s*=\s*["\'][^\'"]{3,}["\']', CRITICAL,
             'Hardcoded password found'),
            (r'(?i)(api[_-]?key|api[_-]?secret|secret[_-]?key)\s*=\s*["\'][^\'"]{8,}["\']', CRITICAL,
             'Hardcoded API key/secret'),
            (r'(?i)(token|jwt[_-]?secret|encrypt[_-]?key)\s*=\s*["\'][^\'"]{8,}["\']', CRITICAL,
             'Hardcoded token/encryption key'),
            (r'(?i)(db[_-]?password|db[_-]?pass|database[_-]?password)\s*=\s*["\'][^\'"]{3,}["\']', CRITICAL,
             'Hardcoded database password'),
            (r'(?i)(smtp[_-]?password|mail[_-]?password|email[_-]?password)\s*=\s*["\'][^\'"]{3,}["\']', CRITICAL,
             'Hardcoded email/SMTP password'),
            (r'(?i)(private[_-]?key|rsa[_-]?key|ssh[_-]?key)\s*=\s*["\']-----BEGIN', CRITICAL,
             'Hardcoded private key'),
        ]
        for pattern, severity, msg in patterns:
            for m in re.finditer(pattern, content):
                ln = content[:m.start()].count('\n') + 1
                # Skip if it's reading from env/getenv
                line = lines[ln-1] if ln <= len(lines) else ''
                if 'getenv' in line.lower() or '$_ENV' in line or '$_SERVER' in line or '$env' in line.lower():
                    continue
                self.issues.append({
                    'file': str(file_path), 'line': ln, 'severity': severity,
                    'category': 'php-security', 'check': 'hardcoded-secret',
                    'message': msg,
                    'snippet': content[m.start():m.start()+80].strip()[:80]
                })

    # ── CODE QUALITY CHECKS ──────────────────────────────────────

    def check_error_handling(self, file_path: Path, content: str, lines: list):
        """Check for missing error handling."""
        # Empty catch blocks
        for m in re.finditer(r'catch\s*\([^)]*\)\s*\{\s*\}', content):
            ln = content[:m.start()].count('\n') + 1
            self.issues.append({
                'file': str(file_path), 'line': ln, 'severity': MEDIUM,
                'category': 'php-quality', 'check': 'empty-catch',
                'message': 'Empty catch block - silently ignoring errors',
                'snippet': 'catch (...) { }'
            })

        # die/exit with message
        for m in re.finditer(r'\b(die|exit)\s*\(\s*["\']', content):
            ln = content[:m.start()].count('\n') + 1
            self.issues.append({
                'file': str(file_path), 'line': ln, 'severity': LOW,
                'category': 'php-quality', 'check': 'hard-exit',
                'message': 'Hard exit/die with message - poor error UX',
                'snippet': content[m.start():m.start()+60].strip()[:60]
            })

        # try without catch
        try_blocks = list(re.finditer(r'\btry\s*\{', content))
        catch_blocks = list(re.finditer(r'\bcatch\s*\(', content))
        if len(try_blocks) > len(catch_blocks):
            self.issues.append({
                'file': str(file_path), 'line': 1, 'severity': LOW,
                'category': 'php-quality', 'check': 'try-without-catch',
                'message': f'{len(try_blocks)} try blocks vs {len(catch_blocks)} catch blocks - possible missing catch',
            })

    def check_debug_code(self, file_path: Path, content: str, lines: list):
        """Detect debug code left in production."""
        seen = set()
        for m in re.finditer(r'\b(var_dump|print_r|dd|dump)\s*\(', content):
            ln = content[:m.start()].count('\n') + 1
            if m.group(1) == 'dd' and 'dd(' not in seen:
                seen.add('dd(')
            self.issues.append({
                'file': str(file_path), 'line': ln, 'severity': MEDIUM,
                'category': 'php-quality', 'check': 'debug-code',
                'message': f'{m.group(1)}() debug function in production code',
                'snippet': content[m.start():m.start()+60].strip()[:60]
            })

    def check_todo_fixme(self, file_path: Path, content: str, lines: list):
        """Find TODO/FIXME markers."""
        for m in re.finditer(r'(?i)(?:^|\s)(//|#|/\*)\s*(TODO|FIXME|HACK|XXX|TEMP|KLUDGE|WORKAROUND)\b', content):
            ln = content[:m.start()].count('\n') + 1
            self.issues.append({
                'file': str(file_path), 'line': ln, 'severity': INFO,
                'category': 'php-quality', 'check': 'todo',
                'message': f'{m.group(2)}: {lines[ln-1].strip()[:80]}' if ln <= len(lines) else m.group(2),
            })

    def check_php8_compat(self, file_path: Path, content: str, lines: list):
        """Check PHP 8.x compatibility issues."""
        # Deprecated: ${var} in strings
        for m in re.finditer(r'\$\{[^}]+\}', content):
            if '{$' in m.group() and 'env' not in m.group().lower():
                ln = content[:m.start()].count('\n') + 1
                self.issues.append({
                    'file': str(file_path), 'line': ln, 'severity': LOW,
                    'category': 'php-compat', 'check': 'php8-syntax',
                    'message': '${var} in string deprecated in PHP 8.2',
                    'snippet': m.group()[:60]
                })

        # Deprecated: # comments (in some contexts)
        # implicit float to int conversion warnings
        for m in re.finditer(r'(int|intval)\s*\(\s*\$\w+\s*\*\s*', content):
            ln = content[:m.start()].count('\n') + 1
            self.issues.append({
                'file': str(file_path), 'line': ln, 'severity': LOW,
                'category': 'php-compat', 'check': 'php8-int-cast',
                'message': 'Float multiplication before int cast - PHP 8.x deprecation',
                'snippet': content[m.start():m.start()+60].strip()[:60]
            })

    def check_session_csrf(self, file_path: Path, content: str, lines: list):
        """Check session and CSRF security."""
        # Multiple session_start
        count = len(re.findall(r'\bsession_start\s*\(\s*\)', content))
        if count > 1:
            self.issues.append({
                'file': str(file_path), 'line': 1, 'severity': LOW,
                'category': 'php-security', 'check': 'session-multiple',
                'message': f'session_start() called {count} times - may cause warnings',
            })

        # POST without CSRF check
        if 'method="post"' in content.lower() or "method='post'" in content.lower():
            if 'csrf' not in content.lower() and 'CSRF' not in content and '_token' not in content:
                self.issues.append({
                    'file': str(file_path), 'line': 1, 'severity': HIGH,
                    'category': 'php-security', 'check': 'missing-csrf',
                    'message': 'POST form without visible CSRF protection',
                })

    def check_global_state(self, file_path: Path, content: str, lines: list):
        """Check for excessive global usage."""
        global_count = len(re.findall(r'\bglobal\s+\$', content))
        if global_count > 2:
            self.issues.append({
                'file': str(file_path), 'line': 1, 'severity': MEDIUM,
                'category': 'php-quality', 'check': 'global-state',
                'message': f'{global_count} global variable usages - consider DI',
            })

    # ── MAIN ANALYZE ─────────────────────────────────────────────

    def analyze_file(self, file_path: Path) -> list:
        issues_before = len(self.issues)
        content = read_file(file_path)
        if content is None:
            return []

        lines = content.split('\n')
        self.stats['files'] += 1
        self.stats['lines'] += len(lines)

        # Compute hash for duplication detection
        self.file_hashes[str(file_path)] = hashlib.sha256(
            content.encode('utf-8', errors='replace')
        ).hexdigest()

        # Extract classes and functions
        for m in re.finditer(r'\bclass\s+(\w+)', content):
            self.classes[m.group(1)].append(str(file_path))
        for m in re.finditer(r'\bfunction\s+(\w+)\s*\(', content):
            self.functions[m.group(1)].append(str(file_path))

        # Extract DB queries
        for m in re.finditer(r'(?:DB::|->query|->exec|->prepare)\s*\(\s*["\']([^"\']{10,})["\']', content):
            self.db_queries.append({
                'file': str(file_path),
                'query': m.group(1)[:200]
            })

        # Extract includes
        for m in re.finditer(r'(?:include|require|include_once|require_once)\s*[\'"]([^\'"]+)[\'"]', content):
            self.includes[str(file_path)].add(m.group(1))

        # Run all checks
        self.check_sql_injection(file_path, content, lines)
        self.check_insecure_functions(file_path, content, lines)
        self.check_xss_risks(file_path, content, lines)
        self.check_hardcoded_secrets(file_path, content, lines)
        self.check_error_handling(file_path, content, lines)
        self.check_debug_code(file_path, content, lines)
        self.check_todo_fixme(file_path, content, lines)
        self.check_php8_compat(file_path, content, lines)
        self.check_session_csrf(file_path, content, lines)
        self.check_global_state(file_path, content, lines)

        return self.issues[issues_before:]

    # ── CROSS-FILE ANALYSIS ──────────────────────────────────────

    def finalize(self) -> list:
        cross_issues = []

        # Duplicate class definitions
        for class_name, paths in self.classes.items():
            unique = set(paths)
            if len(unique) > 1:
                cross_issues.append({
                    'file': paths[0], 'line': 0, 'severity': MEDIUM,
                    'category': 'php-architecture', 'check': 'duplicate-class',
                    'message': f'Class "{class_name}" defined in {len(unique)} files',
                    'snippet': ', '.join(sorted(set(Path(p).name for p in unique)))
                })

        # Duplicate function definitions
        for func_name, paths in self.functions.items():
            unique = set(paths)
            if len(unique) > 1 and func_name not in {'__construct', '__destruct', '__get', '__set', '__call', '__toString', '__invoke'}:
                cross_issues.append({
                    'file': paths[0], 'line': 0, 'severity': LOW,
                    'category': 'php-architecture', 'check': 'duplicate-function',
                    'message': f'Function "{func_name}" defined in {len(unique)} files',
                    'snippet': ', '.join(sorted(set(Path(p).name for p in unique)))
                })

        # Duplicate files (exact match)
        hash_map = defaultdict(list)
        for fpath, fhash in self.file_hashes.items():
            hash_map[fhash].append(fpath)
        for fhash, fpaths in hash_map.items():
            if len(fpaths) > 1:
                dirs = {str(Path(p).parent) for p in fpaths}
                if len(dirs) > 1:
                    cross_issues.append({
                        'file': fpaths[0], 'line': 0, 'severity': HIGH,
                        'category': 'php-architecture', 'check': 'duplicate-file',
                        'message': f'Identical file in {len(fpaths)} locations',
                        'snippet': ', '.join(sorted(set(Path(p).name for p in fpaths)))
                    })

        # Detect services/admin duplication pattern
        svc_root = {Path(p).name for p in self.file_hashes if 'services' in str(p).lower() and 'admin' not in str(p).lower()}
        svc_admin = {Path(p).name for p in self.file_hashes if 'admin/services' in str(p).lower()}
        shared = svc_root & svc_admin
        if shared:
            cross_issues.append({
                'file': 'N/A', 'line': 0, 'severity': HIGH,
                'category': 'php-architecture', 'check': 'service-duplication',
                'message': f'{len(shared)} services duplicated between root/services/ and admin/services/',
                'snippet': ', '.join(sorted(shared)[:15])
            })

        return cross_issues


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------

def find_php_files(root: Path) -> list:
    files = []
    for f in root.rglob('*.php'):
        if not should_skip(f):
            files.append(f)
    return sorted(files)


def generate_report(all_issues: list, stats: Counter, output_dir: Path):
    timestamp = datetime.now().strftime('%Y%m%d_%H%M%S')
    sev_counts = Counter(i['severity'] for i in all_issues)
    cat_counts = Counter(i['category'] for i in all_issues)
    check_counts = Counter(i['check'] for i in all_issues)

    # JSON
    json_path = output_dir / f'php_deep_analysis_{timestamp}.json'
    report = {
        'timestamp': datetime.now().isoformat(),
        'project': 'VegasRoyalSpin',
        'total_files': stats['files'],
        'total_lines': stats['lines'],
        'total_issues': len(all_issues),
        'by_severity': dict(sev_counts),
        'by_category': dict(cat_counts),
        'by_check': dict(check_counts.most_common(50)),
        'issues': sorted(all_issues, key=lambda x: (
            {'CRITICAL': 0, 'HIGH': 1, 'MEDIUM': 2, 'LOW': 3, 'INFO': 4}.get(x['severity'], 99),
            x['file'], x['line']
        ))
    }
    with open(json_path, 'w', encoding='utf-8') as f:
        json.dump(report, f, indent=2, ensure_ascii=False)

    # Markdown
    md_path = output_dir / f'php_deep_analysis_{timestamp}.md'
    with open(md_path, 'w', encoding='utf-8') as f:
        f.write('# VegasRoyalSpin PHP Deep Analysis Report\n\n')
        f.write(f'**Date:** {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}\n\n')

        f.write('## Summary\n\n')
        f.write(f'| Metric | Value |\n|--------|-------|\n')
        f.write(f'| PHP Files Analyzed | {stats["files"]} |\n')
        f.write(f'| PHP Lines of Code | {stats["lines"]:,} |\n')
        f.write(f'| Total Issues | {len(all_issues)} |\n\n')

        f.write('### By Severity\n\n')
        f.write('| Severity | Count |\n|----------|-------|\n')
        for s in [CRITICAL, HIGH, MEDIUM, LOW, INFO]:
            f.write(f'| {s} | {sev_counts.get(s, 0)} |\n')

        f.write('\n### By Category\n\n')
        for cat, cnt in cat_counts.most_common():
            f.write(f'| {cat} | {cnt} |\n')

        f.write('\n### Top Checks\n\n')
        for check, cnt in check_counts.most_common(20):
            f.write(f'| {check} | {cnt} |\n')

        # Top files
        f.write(f'\n## Top 15 Files\n\n')
        for fname, cnt in Counter(i['file'] for i in all_issues).most_common(15):
            try:
                rel = str(Path(fname).relative_to(PROJECT_ROOT))
            except ValueError:
                rel = fname
            f.write(f'- **{rel}** — {cnt} issues\n')

        # Detailed by severity
        for sev in [CRITICAL, HIGH, MEDIUM, LOW, INFO]:
            sev_issues = [i for i in all_issues if i['severity'] == sev]
            if not sev_issues:
                continue
            f.write(f'\n## {sev} Issues ({len(sev_issues)})\n\n')
            by_check = defaultdict(list)
            for issue in sev_issues:
                by_check[issue['check']].append(issue)

            for check_name, items in sorted(by_check.items()):
                f.write(f'### {check_name} ({len(items)})\n\n')
                for item in items[:10]:
                    try:
                        rel = str(Path(item['file']).relative_to(PROJECT_ROOT))
                    except ValueError:
                        rel = item['file']
                    f.write(f'- **`{rel}:{item["line"]}`** — {item["message"]}\n')
                    if item.get('snippet'):
                        f.write(f'  ```\n  {item["snippet"][:120]}\n  ```\n')
                if len(items) > 10:
                    f.write(f'\n  ... and {len(items)-10} more\n')
                f.write('\n')

    # Console Output
    print('\n' + '=' * 70)
    print('  PHP DEEP ANALYSIS RESULTS')
    print('=' * 70)
    print(f'\n  Files Analyzed: {stats["files"]}')
    print(f'  Lines of Code:  {stats["lines"]:,}')
    print(f'  Total Issues:   {len(all_issues)}')
    for s in [CRITICAL, HIGH, MEDIUM, LOW, INFO]:
        c = sev_counts.get(s, 0)
        if c:
            print(f'    {s}: {" " * (8 - len(s))}{c}')
    print(f'\n  JSON Report: {json_path}')
    print(f'  MD Report:   {md_path}')
    print('=' * 70)

    return json_path, md_path


def main():
    output_dir = PROJECT_ROOT / 'tools' / 'reports'
    output_dir.mkdir(parents=True, exist_ok=True)

    print('=' * 70)
    print('  VegasRoyalSpin PHP Deep Analyzer v3')
    print(f'  Started: {datetime.now().strftime("%Y-%m-%d %H:%M:%S")}')
    print('=' * 70)

    php_files = find_php_files(PROJECT_ROOT)
    print(f'\n  Found {len(php_files)} PHP files to analyze\n')

    analyzer = PHPDeepAnalyzer()
    all_issues = []

    for i, f in enumerate(php_files):
        if (i + 1) % 150 == 0:
            print(f'  Progress: {i+1}/{len(php_files)} ({(i+1)*100//len(php_files)}%)')
        issues = analyzer.analyze_file(f)
        all_issues.extend(issues)

    # Cross-file analysis
    print(f'  Progress: {len(php_files)}/{len(php_files)} (100%)')
    print('  Running cross-file analysis...')
    cross_issues = analyzer.finalize()
    all_issues.extend(cross_issues)

    generate_report(all_issues, analyzer.stats, output_dir)


if __name__ == '__main__':
    main()
