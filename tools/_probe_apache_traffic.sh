#!/bin/bash
set -u
analyze() {
  local f="$1" n="${2:-800}"
  echo "======== $f ========"
  ls -lh "$f"
  echo "lines_last_${n}:"
  tail -n "$n" "$f" | wc -l
  echo 'top IPs:'
  tail -n "$n" "$f" | awk '{print $1}' | sort | uniq -c | sort -rn | head -12
  echo 'top paths:'
  tail -n "$n" "$f" | awk '{print $7}' | sort | uniq -c | sort -rn | head -15
  echo 'status:'
  tail -n "$n" "$f" | awk '{print $9}' | sort | uniq -c | sort -rn | head
  echo 'methods:'
  tail -n "$n" "$f" | awk '{print $6}' | tr -d '"' | sort | uniq -c | sort -rn | head
  echo 'req/min last hour-ish (by minute of last 2000):'
  tail -n 2000 "$f" | awk '{print $4}' | cut -d: -f1-3 | sort | uniq -c | tail -15
  echo 'UA top:'
  tail -n "$n" "$f" | awk -F\" '{print $6}' | sort | uniq -c | sort -rn | head -10
  echo
}

analyze /www/wwwlogs/vegasroyalspin119.com-access_log 1000
analyze /www/wwwlogs/m.vegasroyalspin119.com-access_log 800
analyze /www/wwwlogs/vegasroyalspin.com-access_log 800
analyze /www/wwwlogs/admin.vegasroyalspin.com-access_log 500
analyze /www/wwwlogs/access_log 500

echo '=== FIN-WAIT / Timeout context ==='
ss -ant '( sport = :80 or sport = :443 )' | awk 'NR>1{print $1}' | sort | uniq -c | sort -rn
echo
echo '=== ProxyTimeout / Timeout in apache ==='
grep -RInE '^Timeout|^ProxyTimeout|^RequestReadTimeout|KeepAlive ' /www/server/apache/conf/extra/httpd-default.conf /www/server/apache/conf/httpd.conf 2>/dev/null
grep -n ServerLimit /www/server/apache/conf/httpd.conf
sed -n '500,520p' /www/server/apache/conf/httpd.conf
echo
echo '=== EVENT MPM full ==='
sed -n '54,72p' /www/server/apache/conf/extra/httpd-mpm.conf
echo
echo '=== Is 119 a mirror of main? ==='
head -40 /www/server/panel/vhost/apache/vegasroyalspin119.com.conf
echo '----'
head -40 /www/server/panel/vhost/apache/vegasroyalspin.com.conf
