#!/bin/bash
# Apache load hardening for vegasroyalspin production.
# - Lower Timeout / enable RequestReadTimeout
# - Cap MPM event workers for 8GB + php-fpm(60)
# - Drop TLS-on-80 flood source
# Safe: backs up files first; graceful reload.
set -euo pipefail

TS=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/root/apache_harden_backup_${TS}"
mkdir -p "$BACKUP_DIR"

DEFAULT_CONF=/www/server/apache/conf/extra/httpd-default.conf
MPM_CONF=/www/server/apache/conf/extra/httpd-mpm.conf
HTTPD_CONF=/www/server/apache/conf/httpd.conf

cp -a "$DEFAULT_CONF" "$BACKUP_DIR/"
cp -a "$MPM_CONF" "$BACKUP_DIR/"
cp -a "$HTTPD_CONF" "$BACKUP_DIR/"

echo "Backup -> $BACKUP_DIR"

# 1) Timeout 600 -> 60; enable RequestReadTimeout; ProxyTimeout
python3 - <<'PY'
from pathlib import Path
p = Path('/www/server/apache/conf/extra/httpd-default.conf')
text = p.read_text()
text2 = text
# Timeout
import re
text2 = re.sub(r'(?m)^Timeout\s+\d+', 'Timeout 60', text2)
if 'RequestReadTimeout' not in text2:
    text2 += """

# Added by apache harden: kill slow/junk clients fast (TLS-on-80 floods, scanners)
<IfModule reqtimeout_module>
  RequestReadTimeout header=5-20,MinRate=500 body=10,MinRate=500
</IfModule>
ProxyTimeout 60
"""
elif 'ProxyTimeout' not in text2:
    text2 += "\nProxyTimeout 60\n"
p.write_text(text2)
print('patched httpd-default.conf')
print('Timeout line:', [l for l in text2.splitlines() if l.startswith('Timeout')][:1])
PY

# 2) MPM event: sensible caps for this box
python3 - <<'PY'
from pathlib import Path
import re
p = Path('/www/server/apache/conf/extra/httpd-mpm.conf')
text = p.read_text()
# Replace event module block values
pattern = r'(<IfModule mpm_event_module>\s*)(.*?)(\s*</IfModule>)'
repl = r'''\1StartServers             2
    MinSpareThreads         25
    MaxSpareThreads        75
    ThreadsPerChild         25
    MaxRequestWorkers      400
    MaxConnectionsPerChild   5000\3'''
new, n = re.subn(pattern, repl, text, count=1, flags=re.S)
if n != 1:
    raise SystemExit(f'event mpm block replace failed count={n}')
p.write_text(new)
print('patched httpd-mpm.conf event block')
PY

# 3) ServerLimit must be >= MaxRequestWorkers/ThreadsPerChild => 400/25 = 16
python3 - <<'PY'
from pathlib import Path
import re
p = Path('/www/server/apache/conf/httpd.conf')
text = p.read_text()
text2, n = re.subn(r'(?m)^ServerLimit\s+\d+', 'ServerLimit 16', text)
if n < 1:
    raise SystemExit('ServerLimit not found')
p.write_text(text2)
print(f'patched ServerLimit occurrences={n}')
PY

# 4) Ban TLS-on-80 flood IP (persistent via iptables if available)
FLOOD_IP=186.189.98.4
if command -v iptables >/dev/null 2>&1; then
  if ! iptables -C INPUT -s "$FLOOD_IP" -j DROP 2>/dev/null; then
    iptables -I INPUT -s "$FLOOD_IP" -j DROP
    echo "iptables DROP $FLOOD_IP"
  else
    echo "iptables already drops $FLOOD_IP"
  fi
  # persist if iptables-save path exists (aaPanel often has /etc/sysconfig/iptables or firewall)
  if [ -d /etc/sysconfig ] && [ -f /etc/sysconfig/iptables ]; then
    iptables-save > /etc/sysconfig/iptables || true
  fi
fi

# 5) Configtest + graceful
echo '=== apachectl -t ==='
/www/server/apache/bin/apachectl -t
echo '=== graceful ==='
/www/server/apache/bin/apachectl graceful
sleep 2
echo '=== after ==='
cat /proc/loadavg
echo "httpd=$(pgrep -c httpd || true)"
ss -ant '( sport = :80 or sport = :443 )' | awk 'NR>1{print $1}' | sort | uniq -c | sort -rn | head
echo 'Timeout now:'
grep -E '^Timeout|^ProxyTimeout|RequestReadTimeout' /www/server/apache/conf/extra/httpd-default.conf
echo 'Event MPM:'
sed -n '/mpm_event_module/,/IfModule/p' /www/server/apache/conf/extra/httpd-mpm.conf | head -15
echo 'ServerLimit:'
grep -E '^ServerLimit' /www/server/apache/conf/httpd.conf
echo HARDEN_OK
