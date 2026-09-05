#!/bin/bash
set -u
echo '=== LOAD / MEM ==='
cat /proc/loadavg
free -m | head -3
echo
echo '=== HTTPD PROCESS SUMMARY ==='
ps -eo pid,ppid,pcpu,pmem,etime,stat,cmd | grep -E '[h]ttpd' | head -50
echo "httpd_count=$(pgrep -c httpd 2>/dev/null || echo 0)"
echo
echo '=== HTTPD -k LINES ==='
pgrep -af 'httpd' | head -40
echo
echo '=== APACHE -V ==='
/www/server/apache/bin/httpd -V 2>/dev/null | head -20
echo
echo '=== MPM / LIMITS ==='
grep -RInE 'MaxRequestWorkers|ServerLimit|ThreadsPerChild|StartServers|MaxClients|KeepAlive|Timeout|Prefork|Event|Worker|MaxConnectionsPerChild' /www/server/apache/conf/ 2>/dev/null | head -50
echo
echo '=== CONNECTIONS ==='
ss -s 2>/dev/null | head -15
echo 'established 80/443:'
ss -ant state established '( sport = :80 or sport = :443 )' 2>/dev/null | wc -l
echo 'states:'
ss -ant '( sport = :80 or sport = :443 )' 2>/dev/null | awk 'NR>1{print $1}' | sort | uniq -c | sort -rn | head
echo
echo '=== TOP REMOTE IPS (ESTAB) ==='
ss -ant state established '( sport = :80 or sport = :443 )' 2>/dev/null | awk 'NR>1{split($5,a,":"); print a[1]}' | sort | uniq -c | sort -rn | head -15
echo
echo '=== ACCESS LOG RATE (last 2 min worth of tail) ==='
for log in /www/wwwlogs/vegasroyalspin.com.log /www/wwwlogs/admin.vegasroyalspin.com.log /www/wwwlogs/*.log; do
  [ -f "$log" ] || continue
  echo "-- $log size=$(stat -c%s "$log" 2>/dev/null) --"
  tail -n 200 "$log" | awk '{print $1}' | sort | uniq -c | sort -rn | head -8
  echo 'paths:'
  tail -n 200 "$log" | awk '{print $7}' | sort | uniq -c | sort -rn | head -12
  echo
done 2>/dev/null | head -120
echo
echo '=== ERROR LOG TAIL ==='
for log in /www/wwwlogs/vegasroyalspin.com.error.log /www/wwwlogs/admin.vegasroyalspin.com.error.log /www/server/apache/logs/error_log; do
  [ -f "$log" ] || continue
  echo "-- $log --"
  tail -n 20 "$log"
  echo
done
echo
echo '=== SYSTEMD / SERVICE ==='
ps -eo pid,ppid,etime,cmd | grep -E '[a]pache|[h]ttpd' | awk 'NR==1 || $2==1 || /master|parent/' | head -20
ls -la /etc/init.d/httpd /etc/init.d/httpd* 2>/dev/null
/etc/init.d/httpd status 2>/dev/null | head -20
