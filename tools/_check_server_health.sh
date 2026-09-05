#!/bin/bash
set -u
echo '======== TIME / UPTIME / LOAD ========'
date
uptime
cat /proc/loadavg
echo
echo '======== MEMORY / DISK ========'
free -m | head -3
df -h / /www /tmp 2>/dev/null | head -10
echo
echo '======== TOP CPU (15) ========'
ps -eo pid,pcpu,pmem,etime,cmd --sort=-pcpu | head -16
echo
echo '======== SERVICES ========'
echo -n 'httpd: '; pgrep -c httpd 2>/dev/null || echo 0
echo -n 'php-fpm: '; pgrep -c php-fpm 2>/dev/null || echo 0
echo -n 'mysqld: '; pgrep -c mysqld 2>/dev/null || echo 0
echo -n 'redis: '; pgrep -c redis-server 2>/dev/null || echo 0
/etc/init.d/httpd status 2>/dev/null | head -3
echo
echo '======== CONN STATES :80/:443 ========'
ss -ant '( sport = :80 or sport = :443 )' 2>/dev/null | awk 'NR>1{print $1}' | sort | uniq -c | sort -rn | head
echo "estab=$(ss -ant state established '( sport = :80 or sport = :443 )' 2>/dev/null | wc -l)"
echo
echo '======== IPTABLES FLOOD BAN ========'
iptables -L INPUT -n 2>/dev/null | grep -E '186.189.98.4|DROP' | head -10
echo
echo '======== PHP-FPM recent warnings ========'
tail -n 80 /www/server/php/83/var/log/php-fpm.log 2>/dev/null | grep -E 'WARNING|ERROR|seems busy|max_children|slow' | tail -20
echo
echo '======== PHP-FPM slow.log mtime / last stacks ========'
stat -c '%y %s' /www/server/php/83/var/log/slow.log 2>/dev/null || echo 'no slow.log'
tail -n 60 /www/server/php/83/var/log/slow.log 2>/dev/null | grep -E 'script_filename|^\[' | tail -25
echo
echo '======== APACHE error logs (recent) ========'
for f in /www/wwwlogs/error_log /www/wwwlogs/vegasroyalspin.com-error_log /www/wwwlogs/admin.vegasroyalspin.com-error_log /www/wwwlogs/vegasroyalspin119.com-error_log /www/wwwlogs/m.vegasroyalspin.com-error_log; do
  [ -f "$f" ] || continue
  echo "---- $f (mtime=$(stat -c %y "$f" | cut -d. -f1)) ----"
  tail -n 12 "$f"
  echo
done
echo
echo '======== ACCESS anomalies last ~400 lines (main+119) ========'
for f in /www/wwwlogs/vegasroyalspin.com-access_log /www/wwwlogs/vegasroyalspin119.com-access_log /www/wwwlogs/admin.vegasroyalspin.com-access_log /www/wwwlogs/access_log; do
  [ -f "$f" ] || continue
  echo "---- $f ----"
  echo -n 'status mix: '; tail -n 400 "$f" | awk '{print $9}' | sort | uniq -c | sort -rn | head -8 | tr '\n' ' '; echo
  echo -n '5xx: '; tail -n 400 "$f" | awk '$9 ~ /^5/ {c++} END{print c+0}'
  echo -n '4xx: '; tail -n 400 "$f" | awk '$9 ~ /^4/ {c++} END{print c+0}'
  echo 'top paths:'
  tail -n 300 "$f" | awk '{print $7}' | sort | uniq -c | sort -rn | head -8
  echo
done
echo
echo '======== MYSQL quick ========'
mysqladmin ping 2>/dev/null || true
mysql -N -e "SHOW GLOBAL STATUS WHERE Variable_name IN ('Threads_connected','Threads_running','Questions','Slow_queries','Aborted_connects');" 2>/dev/null | head -20
echo
echo '======== ROGUE grep / deploy procs ========'
pgrep -af 'grep -RIn' 2>/dev/null | head -5 || echo none
echo
echo '======== HTTP local smoke ========'
for u in 'https://127.0.0.1/' 'http://127.0.0.1/'; do
  code=$(curl -sk -o /dev/null -w '%{http_code} t=%{time_total}' --resolve vegasroyalspin.com:443:127.0.0.1 --resolve vegasroyalspin.com:80:127.0.0.1 -H 'Host: vegasroyalspin.com' "$u" 2>/dev/null || echo fail)
  echo "$u -> $code"
done
curl -sk -o /dev/null -w 'main_https code=%{http_code} t=%{time_total}\n' --resolve vegasroyalspin.com:443:127.0.0.1 -H 'Host: vegasroyalspin.com' 'https://vegasroyalspin.com/' 2>/dev/null || true
curl -sk -o /dev/null -w 'admin_https code=%{http_code} t=%{time_total}\n' --resolve admin.vegasroyalspin.com:443:127.0.0.1 -H 'Host: admin.vegasroyalspin.com' 'https://admin.vegasroyalspin.com/' 2>/dev/null || true
echo
echo '======== APACHE harden still applied? ========'
grep -E '^Timeout|^ProxyTimeout|RequestReadTimeout|^ServerLimit|MaxRequestWorkers' /www/server/apache/conf/extra/httpd-default.conf /www/server/apache/conf/extra/httpd-mpm.conf /www/server/apache/conf/httpd.conf 2>/dev/null | head -20
echo CHECK_DONE
