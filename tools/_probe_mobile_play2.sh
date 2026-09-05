#!/bin/bash
set -u
UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'

echo '=== /play redirect chain (demo) ==='
curl -sk -A "$UA" -o /dev/null -w 'url=%{url_effective} code=%{http_code} redir=%{num_redirects}\n' \
  --resolve vegasroyalspin.com:443:127.0.0.1 \
  --resolve vegasroyalspin119.com:443:127.0.0.1 \
  --resolve m.vegasroyalspin.com:443:127.0.0.1 \
  -H 'Host: vegasroyalspin.com' \
  'https://vegasroyalspin.com/play?game_id=aggregator:slot-pragmatic:vs20starlight&mode=fun&demo=1&open_mode=redirect'

curl -skL -A "$UA" -o /tmp/_play.html -w 'final=%{url_effective} code=%{http_code}\n' \
  --resolve vegasroyalspin.com:443:127.0.0.1 \
  --resolve vegasroyalspin119.com:443:127.0.0.1 \
  --resolve m.vegasroyalspin.com:443:127.0.0.1 \
  -H 'Host: vegasroyalspin.com' \
  'https://vegasroyalspin.com/play?game_id=aggregator:slot-pragmatic:vs20starlight&mode=fun&demo=1&open_mode=redirect'

python3 - <<'PY'
import re
html=open('/tmp/_play.html','r',encoding='utf-8',errors='ignore').read()
print('len',len(html))
for pat in ['play-page.js','PLAY_BOOT','game-launch','playFrame','__PLAY_','open_mode','demo']:
    print(pat, html.lower().count(pat.lower()))
# extract boot payload if any
m=re.search(r'window\.__PLAY_[A-Z_]+__\s*=\s*(\{.*?\});', html, re.S)
print('boot', (m.group(0)[:300] if m else None))
m2=re.search(r'data-play-[a-z-]+=\"([^\"]+)\"', html)
print('data-play sample', m2.group(0) if m2 else None)
# look for script config
for m in re.finditer(r'<script[^>]*>.*?play.*?</script>', html, re.I|re.S):
    s=m.group(0)
    if 'game_id' in s or 'open_mode' in s or 'PLAY' in s:
        print('script_snip', s[:400].replace('\n',' '))
        break
PY

echo
echo '=== game-launch demo API ==='
curl -sk -A "$UA" -X POST 'https://127.0.0.1/api/v2/game-launch' \
  --resolve admin.vegasroyalspin.com:443:127.0.0.1 \
  -H 'Host: admin.vegasroyalspin.com' -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"game_id":"aggregator:slot-pragmatic:vs20starlight","mode":"fun","demo":1,"platform":"MOBILE","open_mode":"redirect"}' \
  -w '\nhttp=%{http_code}\n' | head -c 800
echo
echo
echo '=== via frontend proxy path if any ==='
curl -sk -A "$UA" -X POST 'https://127.0.0.1/api/v2/game-launch' \
  --resolve vegasroyalspin119.com:443:127.0.0.1 \
  -H 'Host: vegasroyalspin119.com' -H 'Content-Type: application/json' -H 'Accept: application/json' \
  -d '{"game_id":"aggregator:slot-pragmatic:vs20starlight","mode":"fun","demo":1,"platform":"MOBILE","open_mode":"redirect"}' \
  -w '\nhttp=%{http_code}\n' | head -c 800
echo

echo '=== home mobile OYNA markers ==='
curl -skL -A "$UA" -o /tmp/_home_m.html -w 'home=%{url_effective} %{http_code}\n' \
  --resolve vegasroyalspin.com:443:127.0.0.1 --resolve vegasroyalspin119.com:443:127.0.0.1 \
  -H 'Host: vegasroyalspin.com' 'https://vegasroyalspin.com/'
python3 - <<'PY'
import re
html=open('/tmp/_home_m.html','r',encoding='utf-8',errors='ignore').read()
print('OYNA', html.count('OYNA'), 'play-btn', html.count('play-btn'), 'handlePlay', html.count('handlePlay'), '__homeHandlePlayIntent', html.count('__homeHandlePlayIntent'))
# broken numeric ids?
ids=re.findall(r'/play\?game_id=(\d+)', html)
print('numeric_play_ids', sorted(set(ids))[:20], 'count', len(ids))
ids2=re.findall(r'/play\?game_id=([^\"&]+)', html)
from collections import Counter
c=Counter(ids2)
print('top play ids', c.most_common(10))
PY
