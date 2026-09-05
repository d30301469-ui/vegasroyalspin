#!/bin/bash
set -u
UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'

echo '=== same-origin game-launch on 119 (proper SNI) ==='
curl -sk -A "$UA" -X POST 'https://vegasroyalspin119.com/api/v2/game-launch' \
  --connect-to vegasroyalspin119.com:443:127.0.0.1:443 \
  -H 'Host: vegasroyalspin119.com' -H 'Content-Type: application/json' -H 'Accept: application/json' -H 'Origin: https://vegasroyalspin119.com' \
  -d '{"game_id":"aggregator:slot-pragmatic:vs20starlight","mode":"fun","demo":1,"platform":"MOBILE","open_mode":"redirect"}' \
  -w '\nhttp=%{http_code}\n' | head -c 900
echo

echo '=== same-origin on m. ==='
curl -sk -A "$UA" -X POST 'https://m.vegasroyalspin.com/api/v2/game-launch' \
  --connect-to m.vegasroyalspin.com:443:127.0.0.1:443 \
  -H 'Host: m.vegasroyalspin.com' -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"game_id":"aggregator:slot-pragmatic:vs20starlight","mode":"fun","demo":1,"platform":"MOBILE","open_mode":"redirect"}' \
  -w '\nhttp=%{http_code}\n' | head -c 900
echo

echo '=== play-page boot scripts present ==='
curl -skL -A "$UA" --connect-to vegasroyalspin119.com:443:127.0.0.1:443 \
  -o /tmp/_play2.html -w '%{http_code}\n' \
  -H 'Host: vegasroyalspin119.com' \
  'https://vegasroyalspin119.com/play?game_id=aggregator:slot-pragmatic:vs20starlight&mode=fun&demo=1&open_mode=redirect'
grep -oE 'src="/assets/js/[^"]+"|__MEMBER_API|__FRONTEND_DIRECT|play-page.js|auth-shared.js' /tmp/_play2.html | head -40
echo
python3 - <<'PY'
import re
html=open('/tmp/_play2.html',encoding='utf-8',errors='ignore').read()
for key in ['__MEMBER_API_BASE__','__FRONTEND_DIRECT_MEMBER_API__','__CSRF_TOKEN__','__USER_LOGGED_IN__','play-page.js']:
    m=re.search(re.escape(key)+r'[^<\n]{0,120}', html)
    print(key, '=>', (m.group(0)[:160] if m else 'MISSING'))
PY

echo
echo '=== recent game-launch statuses on m + admin ==='
python3 - <<'PY'
from collections import Counter
for path in ['/www/wwwlogs/m.vegasroyalspin.com-access_log','/www/wwwlogs/admin.vegasroyalspin.com-access_log','/www/wwwlogs/vegasroyalspin119.com-access_log']:
    try:
        with open(path,'rb') as f:
            f.seek(0,2); n=f.tell(); f.seek(max(0,n-3_000_000))
            lines=f.read().decode('utf-8','ignore').splitlines()[-8000:]
    except Exception as e:
        print(path, e); continue
    c=Counter(); samples=[]
    for line in lines:
        if 'game-launch' not in line: continue
        parts=line.split()
        code=parts[8] if len(parts)>8 else '?'
        c[code]+=1
        if len(samples)<5: samples.append(line[:220])
    print(path, 'counts', c, 'samples', samples)
PY
