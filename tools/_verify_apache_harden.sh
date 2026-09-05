#!/bin/bash
set -u
sleep 3
echo '=== LOAD ==='
cat /proc/loadavg
echo
echo '=== TOP CPU ==='
ps -eo pid,pcpu,pmem,cmd --sort=-pcpu | head -12
echo
echo '=== CONN STATES ==='
ss -ant '( sport = :80 or sport = :443 )' | awk 'NR>1{print $1}' | sort | uniq -c | sort -rn
echo
echo '=== flood IP still hitting? ==='
iptables -L INPUT -n | grep 186.189.98.4 || echo 'rule missing'
tail -n 200 /www/wwwlogs/access_log | grep -c '186.189.98.4' || true
echo 'other 400 flood IPs last 300 lines:'
tail -n 300 /www/wwwlogs/access_log | awk '$9==400 || $9==408 {print $1}' | sort | uniq -c | sort -rn | head -10
echo
echo '=== balance interval on disk ==='
grep -n 'INTERVAL_MS' /www/wwwroot/vegasroyalspin.com/assets/js/header-balance-poll.js | head -5
echo
echo '=== httpd workers cpu sum ==='
ps -eo pcpu,cmd | grep '[h]ttpd' | awk '{s+=$1} END{print s}'
echo HARDEN_VERIFY_OK
