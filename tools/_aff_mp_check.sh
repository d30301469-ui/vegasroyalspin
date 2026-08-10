grep -Rni "approveAffiliateCryptoPayout\|affiliate_payout_id\|finalizeAffiliatePayout" /www/wwwroot/vegasroyalspin.com/admin/services/MegaPayzService.php /www/wwwroot/vegasroyalspin.com/services/MegaPayzService.php 2>/dev/null | head -40
ls /www/wwwroot/vegasroyalspin.com/database/migrations/*affiliate*crypto* 2>/dev/null
ls /www/wwwroot/vegasroyalspin.com/admin/database/migrations/*affiliate*crypto* 2>/dev/null
php -r '
$e=parse_ini_file("/www/wwwroot/vegasroyalspin.com/admin/.env");
$p=new PDO("mysql:host=".$e["DB_HOST"].";dbname=".$e["DB_DATABASE"],$e["DB_USERNAME"],$e["DB_PASSWORD"]);
foreach($p->query("SHOW COLUMNS FROM affiliate_payouts") as $r){echo $r["Field"]."\n";}
echo "---mp---\n";
foreach($p->query("SHOW COLUMNS FROM megapayz_transactions") as $r){ if(str_contains($r["Field"],"affiliate")||str_contains($r["Field"],"payout")) echo $r["Field"]."\n"; }
'
