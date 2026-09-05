#!/bin/bash
set -u
echo '=== FIND LOGS ==='
ls -lah /www/wwwlogs/ 2>/dev/null | head -40
find /www/wwwlogs -maxdepth 2 -type f -name '*vegas*' 2>/dev/null | head
find /www/wwwlogs -maxdepth 1 -type f -printf '%T@ %p %s\n' 2>/dev/null | sort -rn | head -20
echo
echo '=== VHOST CONF ==='
ls /www/server/panel/vhost/apache/ 2>/dev/null
for f in /www/server/panel/vhost/apache/*vegas* /www/server/panel/vhost/apache/*royal*; do
  [ -f "$f" ] || continue
  echo "---- $f ----"
  grep -nE 'ErrorLog|CustomLog|ProxyPass|SetHandler|Timeout|KeepAlive|RequestReadTimeout|LimitRequest|Rewrite|proxy:unix|fcgi' "$f" | head -40
done
echo
echo '=== HTTPD.CONF KEY ==='
grep -nE 'Include|Timeout|KeepAlive|ServerLimit|MaxRequestWorkers|Listen|mod_status|mod_reqtimeout' /www/server/apache/conf/httpd.conf | head -60
echo
echo '=== EVENT MPM ACTIVE BLOCK ==='
sed -n '54,75p' /www/server/apache/conf/extra/httpd-mpm.conf
echo
echo '=== DEFAULT TIMEOUTS ==='
sed -n '1,40p' /www/server/apache/conf/extra/httpd-default.conf
echo
echo '=== REQTIMEOUT / SECURITY ==='
grep -RInE 'RequestReadTimeout|mod_reqtimeout|ModSecurity|fail2ban' /www/server/apache/conf/ 2>/dev/null | head -20
ls /www/server/apache/modules/mod_reqtimeout* 2>/dev/null
echo
echo '=== LIVE ACCESS SAMPLE ==='
# newest large log
newest=$(find /www/wwwlogs -maxdepth 1 -type f -name '*.log' ! -name '*.error.log' -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | awk '{print $2}')
echo "newest=$newest"
if [ -n "${newest:-}" ] && [ -f "$newest" ]; then
  echo 'top IPs last 500:'
  tail -n 500 "$newest" | awk '{print $1}' | sort | uniq -c | sort -rn | head -15
  echo 'top paths last 500:'
  tail -n 500 "$newest" | awk '{print $7}' | sort | uniq -c | sort -rn | head -20
  echo 'status codes:'
  tail -n 500 "$newest" | awk '{print $9}' | sort | uniq -c | sort -rn | head
  echo 'user-agents sample:'
  tail -n 200 "$newest" | awk -F\" '{print $6}' | sort | uniq -c | sort -rn | head -15
fi
echo
echo '=== ERROR LOGS NEWEST ==='
ferr=$(find /www/wwwlogs -maxdepth 1 -type f -name '*error*' -printf '%T@ %p\n' 2>/dev/null | sort -rn | head -1 | awk '{print $2}')
echo "ferr=$ferr"
[ -n "${ferr:-}" ] && [ -f "$ferr" ] && tail -n 30 "$ferr"
echo
echo '=== SERVER-STATUS ==='
curl -sS -m 3 http://127.0.0.1/server-status?auto 2>/dev/null | head -40 || echo no_status
echo
echo '=== PHP-FPM SOCKET WAITERS ==='
ss -xp 2>/dev/null | grep -E 'php-cgi|php-fpm' | head -20
ls -la /tmp/php-cgi*.sock /dev/shm/php*.sock 2>/dev/null
echo
echo '=== APACHE SCOREBOARD VIA MOD_STATUS HTML ==='
curl -sS -m 3 'http://127.0.0.1/server-status' 2>/dev/null | grep -E 'BusyWorkers|IdleWorkers|requests currently|Scoreboard|Server uptime|Load' | head -20
echo
echo '=== TOP CPU NOW ==='
ps -eo pid,pcpu,pmem,etime,cmd --sort=-pcpu | head -15
cat /proc/loadavg
