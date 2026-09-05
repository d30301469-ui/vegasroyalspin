#!/bin/bash
set -u
UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
curl -skL -A "$UA" --connect-to vegasroyalspin119.com:443:127.0.0.1:443 \
  -H 'Host: vegasroyalspin119.com' -o /tmp/_mh2.html \
  'https://vegasroyalspin119.com/'
python3 - <<'PY'
import re
html=open('/tmp/_mh2.html',encoding='utf-8',errors='ignore').read()
print('len',len(html))
m=re.search(r'<body([^>]*)>', html)
print('body', m.group(0) if m else None)
m=re.search(r'<html([^>]*)>', html)
print('html', m.group(0)[:200] if m else None)
for c in ['mobile-site','layout-header-holder-bc','mobile-bc-header','shellPageHost','footer-bc','mobileFooter','X-Shell','hdr-main-content-bc']:
    print(c, html.count(c))
print('has_footer_include_marker', 'footerClockWidget' in html or 'mobile-footer-bc' in html)
print('tail', repr(html[-350:]))
# check if truncated mid-page
print('shellPageHost close', html.count('shellPageHost'))
print('play-btn pointer context sample')
idx=html.find('class="play-btn"')
print(html[idx-200:idx+150] if idx>=0 else None)
PY
