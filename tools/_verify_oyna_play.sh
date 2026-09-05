#!/bin/bash
set -e
ROOT=/www/wwwroot/vegasroyalspin.com
grep -n "surfaceUa\|wallet.empty\|playIsMobileClient\|partials/footer.php\|isMobilePlayLaunchMode\|Direct launch" \
  "$ROOT/core/bootstrap.php" "$ROOT/assets/js/home.js" "$ROOT/assets/js/game-wallet-picker.js" \
  "$ROOT/pages/play.php" "$ROOT/mobile/views/pages/home.php" "$ROOT/assets/js/slot.js" | head -50

for D in /www/wwwroot/vegasroyalspin119.com /www/wwwroot/m.vegasroyalspin.com /www/wwwroot/m.vegasroyalspin119.com; do
  if [ -d "$D" ]; then
    same=$(readlink -f "$D" 2>/dev/null || echo "$D")
    rootsame=$(readlink -f "$ROOT")
    if [ "$same" = "$rootsame" ]; then echo "SAME_DOCROOT $D"; continue; fi
    for f in core/bootstrap.php mobile/views/pages/home.php assets/js/home.js assets/js/slot.js assets/js/game-wallet-picker.js pages/play.php; do
      if [ -f "$ROOT/$f" ]; then
        mkdir -p "$(dirname "$D/$f")"
        cp -a "$ROOT/$f" "$D/$f" && echo "MIRRORED $f -> $D"
      fi
    done
  fi
done

UA='Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1'
for URL in 'https://vegasroyalspin119.com/' 'https://m.vegasroyalspin119.com/' 'https://vegasroyalspin.com/'; do
  echo "==== $URL"
  html=$(curl -skL -A "$UA" --max-time 25 "$URL" || true)
  echo "len=${#html}"
  echo "$html" | grep -oE '<html[^>]*>' | head -1
  echo "$html" | grep -oE '<body[^>]*>' | head -1
  echo -n "mobileFooter="; echo "$html" | grep -c mobileFooter || true
  echo -n "mobile-site body="; echo "$html" | grep -c 'class="mobile-site' || true
  echo -n "is-mobile html="; echo "$html" | grep -c 'class="is-mobile' || true
  echo -n "is-web html="; echo "$html" | grep -c 'class="is-web"' || true
  echo -n "homeHandlePlay="; echo "$html" | grep -c __homeHandlePlayIntent || true
  echo "TAIL:"; echo "$html" | tail -c 350; echo
done

PLAY=$(curl -skL -A "$UA" --max-time 25 'https://vegasroyalspin119.com/play?game_id=aggregator:slot-pragmatic:vs20fruitswx&mode=fun&demo=1' || true)
echo "==== PLAY"
echo "$PLAY" | grep -oE '"open_mode":"[^"]+"' | head -5
echo -n "play-redirecting="; echo "$PLAY" | grep -c play-redirecting || true
echo -n "playFrame="; echo "$PLAY" | grep -c playFrame || true
echo -n "play-shell-body="; echo "$PLAY" | grep -c play-shell-body || true
echo VERIFY_OK
