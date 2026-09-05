#!/bin/bash
set +e

LOGDIR=/www/wwwlogs
echo "=== SERVER TIME ==="
date

analyze() {
  local f="$1"
  local label="$2"
  [ -f "$f" ] || { echo "MISSING: $f"; return; }
  echo ""
  echo "========== $label =========="
  echo "File: $f ($(du -h "$f" | awk '{print $1}'))"
  echo "-- status counts (session/login/balance/refresh) --"
  grep -E 'api/v2/(auth/session|auth/login|auth/refresh|balance)' "$f" 2>/dev/null \
    | awk '{print $9}' | sort | uniq -c | sort -rn | head -15
  echo "-- last 25 session/balance hits --"
  grep -E 'api/v2/(auth/session|balance)' "$f" 2>/dev/null | tail -25
  echo "-- recent 401 session/balance (last 8) --"
  grep -E 'api/v2/(auth/session|balance)' "$f" 2>/dev/null | grep ' 401 ' | tail -8
  echo "-- recent 200 session/balance (last 8) --"
  grep -E 'api/v2/(auth/session|balance)' "$f" 2>/dev/null | grep ' 200 ' | tail -8
}

analyze "$LOGDIR/vegasroyalspin119.com-access_log" "FRONTEND vegasroyalspin119.com"
analyze "$LOGDIR/vegasroyalspin.com-access_log" "FRONTEND vegasroyalspin.com"
analyze "$LOGDIR/m.vegasroyalspin119.com-access_log" "MOBILE m.vegasroyalspin119.com"
analyze "$LOGDIR/admin.vegasroyalspin.com-access_log" "ADMIN API"

echo ""
echo "========== ERROR LOGS (session/jwt/auth) =========="
for f in \
  "$LOGDIR/vegasroyalspin119.com-error_log" \
  "$LOGDIR/vegasroyalspin.com-error_log" \
  "$LOGDIR/admin.vegasroyalspin.com-error_log" \
  "$LOGDIR/error_log"
do
  [ -f "$f" ] || continue
  echo "--- $(basename "$f") (last 25 matches) ---"
  grep -iE 'session|jwt|auth|BackendMemberApiProxy|member_jwt|csrf|restore-cookie|401|403' "$f" 2>/dev/null | tail -25
done

echo ""
echo "========== POST-DEPLOY WINDOW (~15:10-15:20 CEST today) =========="
for f in "$LOGDIR/vegasroyalspin119.com-access_log" "$LOGDIR/vegasroyalspin.com-access_log"; do
  [ -f "$f" ] || continue
  echo "--- $(basename "$f") ---"
  awk '$4 ~ /28\/Aug\/2026:15:1[0-9]/ || $4 ~ /28\/Aug\/2026:15:20/' "$f" \
    | grep -E 'api/v2/(auth/session|balance|auth/login)' \
    | awk '{print $9}' | sort | uniq -c | sort -rn
  echo "sample lines:"
  awk '$4 ~ /28\/Aug\/2026:15:1[0-9]/ || $4 ~ /28\/Aug\/2026:15:20/' "$f" \
    | grep -E 'api/v2/(auth/session|balance|auth/login)' | tail -12
done

echo ""
echo "========== LIVE PROBE (no cookie) =========="
code=$(curl -sk -o /tmp/sess_probe.json -w '%{http_code}' 'https://vegasroyalspin119.com/api/v2/auth/session')
echo "GET /auth/session without cookie: HTTP $code"
head -c 200 /tmp/sess_probe.json; echo

echo ""
echo "LOG_PROBE_OK"
