#!/bin/bash
set -u
echo '=== play/game-launch hits last 5k lines ==='
for f in /www/wwwlogs/vegasroyalspin.com-access_log /www/wwwlogs/vegasroyalspin119.com-access_log /www/wwwlogs/m.vegasroyalspin.com-access_log /www/wwwlogs/admin.vegasroyalspin.com-access_log; do
  [ -f "$f" ] || continue
  echo "-- $f --"
  python3 - <<PY
from collections import Counter
path="$f"
with open(path,'rb') as fh:
    fh.seek(0,2); n=fh.tell(); fh.seek(max(0,n-2_500_000))
    lines=fh.read().decode('utf-8','ignore').splitlines()[-5000:]
c=Counter(); paths=Counter(); codes=Counter(); n=0
for line in lines:
    if '/play' not in line and 'game-launch' not in line:
        continue
    n+=1
    parts=line.split()
    if len(parts)>8:
        codes[parts[8]]+=1
        paths[parts[6][:120]]+=1
print('hits',n)
print('codes',codes.most_common(8))
print('paths',paths.most_common(8))
PY
done

echo
echo '=== mobile slot HTML markers ==='
curl -skL -A 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1' \
  -o /tmp/_slot_m.html -w 'final_url=%{url_effective} code=%{http_code}\n' \
  --resolve vegasroyalspin.com:443:127.0.0.1 --resolve m.vegasroyalspin.com:443:127.0.0.1 \
  -H 'Host: vegasroyalspin.com' 'https://vegasroyalspin.com/slot'
python3 - <<'PY'
from collections import Counter
import re
html=open('/tmp/_slot_m.html','r',encoding='utf-8',errors='ignore').read()
print('len',len(html))
for pat in ['mobile-site','actionButtons: true','actionButtons: false','play-btn','__slotHandlePlayIntent','slot-page-root--cm622','casinoGameItemContent','OYNA']:
    print(pat, html.count(pat))
# sample first play href if any
m=re.search(r'class="play-btn[^"]*"[^>]*href="([^"]+)"', html)
print('sample_play_href', m.group(1) if m else None)
m2=re.search(r'actionButtons:\s*(true|false)', html)
print('actionButtons', m2.group(1) if m2 else None)
print('body_class', re.search(r'<body[^>]*class="([^"]*)"', html).group(1) if re.search(r'<body[^>]*class="([^"]*)"', html) else None)
PY

echo
echo '=== demo launch smoke (public) ==='
# Hit a known demo-ish endpoint shape if present in HTML
python3 - <<'PY'
import re, urllib.request, ssl
html=open('/tmp/_slot_m.html','r',encoding='utf-8',errors='ignore').read()
m=re.search(r'href="(/play\?[^"]+demo=1[^"]*)"', html)
print('demo_href', m.group(1) if m else None)
PY
