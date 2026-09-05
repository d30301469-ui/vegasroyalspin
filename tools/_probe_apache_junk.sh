#!/bin/bash
set -u
echo '=== default vhost ==='
cat /www/server/panel/vhost/apache/0.default.conf
echo
echo '=== access_log binary sample ==='
tail -c 800 /www/wwwlogs/access_log | xxd | head -30
echo
echo '=== access_log printable lines last 30 ==='
tail -n 50 /www/wwwlogs/access_log | strings | tail -30
echo
echo '=== balance counts recent ==='
grep -c '/api/v2/balance' /www/wwwlogs/vegasroyalspin119.com-access_log | head -1
# last 5 minutes approx by scanning end
python3 - <<'PY'
from collections import Counter
from datetime import datetime
import re
path='/www/wwwlogs/vegasroyalspin119.com-access_log'
# read last 3MB
with open(path,'rb') as f:
    f.seek(0,2); n=f.tell(); f.seek(max(0,n-3_000_000))
    data=f.read().decode('utf-8','ignore')
lines=data.splitlines()[-5000:]
bal=0
by_min=Counter()
ips=Counter()
for line in lines:
    if '/api/v2/balance' not in line: continue
    bal+=1
    m=re.search(r'\[(\d{2}/\w{3}/\d{4}:\d{2}:\d{2})', line)
    if m: by_min[m.group(1)] += 1
    ip=line.split(' ',1)[0]
    ips[ip]+=1
print('balance_in_last_5k_lines', bal)
print('top_mins', by_min.most_common(8))
print('top_ips', ips.most_common(5))
PY
echo
echo '=== ProxyTimeout present? ==='
grep -RIn 'ProxyTimeout' /www/server/apache/conf/ /www/server/panel/vhost/apache/ 2>/dev/null | head
echo '=== reqtimeout include? ==='
grep -n reqtimeout /www/server/apache/conf/httpd.conf
grep -n 'httpd-default' /www/server/apache/conf/httpd.conf
