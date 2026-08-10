wc -c /www/wwwroot/vegasroyalspin.com/admin/services/MegaPayzService.php /www/wwwroot/vegasroyalspin.com/services/MegaPayzService.php
grep -n "function approveWithdraw\|function handleCallback\|affiliate\|Affiliate" /www/wwwroot/vegasroyalspin.com/admin/services/MegaPayzService.php | head -60
echo '===='
grep -n "function updatePayout\|megapayz\|approveAffiliate\|crypto" /www/wwwroot/vegasroyalspin.com/admin/app/Controllers/AdminAffiliateController.php | head -40
echo '===='
# portal
ls /www/wwwroot/vegasroyalspin.com/affiliate-portal/app/Controllers/ 2>/dev/null
grep -Rni "MegaPayz\|crypto_network\|wallet" /www/wwwroot/ortaklik* 2>/dev/null | head -20
find /www/wwwroot -maxdepth 2 -type d -iname '*ortak*' 2>/dev/null
find /www/wwwroot -maxdepth 2 -type d -iname '*affil*' 2>/dev/null
