#!/bin/bash
set -u
UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
curl -skL -A "$UA" --connect-to vegasroyalspin119.com:443:127.0.0.1:443 \
  -H 'Host: vegasroyalspin119.com' -o /tmp/_mh.html \
  'https://vegasroyalspin119.com/'
python3 - <<'PY'
html=open('/tmp/_mh.html',encoding='utf-8',errors='ignore').read()
checks=['mobileFooter','mprofilePanel','__homeGameCardActivate','initGameOverlayTap','footer.php','home.js','game-wallet-picker','play-btn','overlay-active','bc-root-close','</html>']
for c in checks:
    print(f'{c}: {html.count(c)}')
print('len', len(html))
print('ends', repr(html[-200:]))
# script order near end
import re
scripts=re.findall(r'src="([^"]+\.js[^"]*)"', html)
print('last scripts', scripts[-8:])
PY
