# aaPanel — Apache-only kurulum

> **SSL: Cloudflare edge (origin HTTP).** Bkz. [CLOUDFLARE-TR.md](CLOUDFLARE-TR.md)

Bu proje **yalnızca Apache + `.htaccess`** ile çalışır. Nginx yapılandırması gerekmez.

---

## Cloudflare + aaPanel (özet)

1. Cloudflare: **Flexible** + **Always Use HTTPS ON** + DNS proxied
2. aaPanel: **Force HTTPS OFF**, origin'de **Let's Encrypt yok**
3. `.env`: `CLOUDFLARE_SSL=1`, public URL'ler `https://`
4. `php deploy/aapanel/fix-cloudflare-env.php`

---

## aaPanel site ayarları

Her site için (`bo-nexthub.site`, `vegasroyalspin.com`):

1. **Website** → site → **Site directory** = `/www/wwwroot/DOMAIN/`
2. **PHP** → sürüm **8.1+**, web sunucusu **Apache** (mod_php veya php-fpm + Apache)
3. **Nginx reverse proxy kapalı** — Apache doğrudan **80/443** dinlemeli
4. **SSL** → aaPanel origin SSL **gerekmez** (Cloudflare Flexible). Force HTTPS **KAPAT**.
5. Site **Running**

Nginx hâlâ 443 dinliyorsa Apache `.htaccess` devreye girmez; aaPanel → App Store → **Nginx → Stop** veya site bazında reverse proxy kapatın.

---

## Apache modülleri

```bash
apachectl -M 2>/dev/null | grep rewrite || httpd -M 2>/dev/null | grep rewrite
```

`mod_rewrite` zorunlu. Site vhost'ta **AllowOverride All** olmalı (aaPanel genelde açar).

Örnek vhost parçaları: `deploy/apache/bo-nexthub.site.conf`, `deploy/apache/vegasroyalspin.com.conf`

---

## `.htaccess` (zip içinde)

| Site | Kaynak | Hedef |
|------|--------|-------|
| Frontend | `deploy/apache/vegasroyalspin.com.htaccess` | site kökü `.htaccess` |
| Backend | `deploy/apache/bo-nexthub.site.htaccess` | site kökü `.htaccess` |

Zip paketleri bu dosyaları otomatik köke kopyalar.

---

## Kurulum sırası

1. Backend zip → `bo-nexthub.site` → **Site directory = kök** → `/install` → MySQL + `MEMBER_JWT_SECRET`
2. Frontend zip → `vegasroyalspin.com` → **Site directory = kök (public/ değil)** → `/install` → aynı `MEMBER_JWT_SECRET`
3. Kurulum gelmiyorsa: `php scripts/reset-for-install.php --env` + `systemctl restart httpd`
4. Frontend `.env` doğrula: `php deploy/aapanel/fix-frontend-env.php`
5. Test: `ping.php` → `health.php`

Detaylı `.env`: [KURULUM-TR.md](KURULUM-TR.md)

---

## Test

```bash
curl -sS https://bo-nexthub.site/ping.php
curl -sS https://vegasroyalspin.com/ping.php
curl -sS 'https://bo-nexthub.site/api/v2/site_settings.php'
curl -sS https://vegasroyalspin.com/health.php
```

Beklenen frontend `health.php`: `"ok": true`, `"backend_reachable": "ok"`.

---

## Aynı sunucu (hairpin NAT)

Frontend ve backend aynı makinede ise PHP dış HTTPS timeout verebilir. Loopback + Host header kullanın:

```bash
curl -sS -H "Host: bo-nexthub.site" http://127.0.0.1/ping.php
curl -sS -H "Host: bo-nexthub.site" http://127.0.0.1/api/v2/site_settings.php
```

Frontend `.env`:

```env
API_BACKEND_INTERNAL_BASE_URL=http://127.0.0.1/api/v2
API_BACKEND_INTERNAL_HOST=bo-nexthub.site
```

`fix-frontend-env.php` bunu otomatik algılayabilir.

---

## Apache logları

```bash
tail -50 /www/wwwlogs/bo-nexthub.site-error_log
tail -50 /www/wwwlogs/vegasroyalspin.com-error_log
```

Log satırında `3345#0` veya `upstream https://127.0.0.1:8290` görürseniz istek hâlâ **nginx** üzerinden gidiyordur — nginx'i kapatın, Apache-only moda geçin.

---

## Sık hatalar (Apache)

| Belirti | Çözüm |
|---------|--------|
| 500 / rewrite loop | Zip `.htaccess` ile değiştir |
| 502 frontend API | Backend `health.php` + internal URL |
| 503 API | `FRONTEND_API_ONLY=1`, `fix-frontend-env.php` |
| 504 / timeout | PHP `max_execution_time` ≥ 120, backend DB |
| `/api/v2/api/v2/` | `SITE_URL` sadece domain (path yok) |
| JWT / 401 | `MEMBER_JWT_SECRET` iki tarafta aynı |
| Authorization kaybı | `.htaccess` Bearer pass-through satırları (zip'te var) |
