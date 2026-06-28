# VegasRoyalSpin — Sorunsuz Deploy Rehberi (Apache-only)

## Web sunucusu

**Apache + `.htaccess`** — nginx yapılandırması gerekmez. aaPanel'de nginx reverse proxy kapalı, Apache 80/443 dinlemeli.

Detay: `deploy/aapanel/APACHE-ONLY-TR.md`

## Paketler

| Dosya | Hedef sunucu |
|-------|----------------|
| `dist/vegasroyalspin-frontend.zip` | `/www/wwwroot/vegasroyalspin.com/` |
| `dist/bo-nexthub-admin.zip` | `/www/wwwroot/bo-nexthub.site/` |

Mobil: aaPanel → `m.vegasroyalspin.com` alias → **aynı** document root.

---

## 1. Backend (bo-nexthub.site)

```bash
cd /www/wwwroot/bo-nexthub.site
unzip bo-nexthub-admin.zip
composer install --no-dev --optimize-autoloader
cp ENV.example .env
```

**Zorunlu .env:** `APP_ENV=production`, `APP_DEBUG=false`, `DB_*`, `MEMBER_JWT_SECRET` (frontend ile aynı).

**Test:** `https://bo-nexthub.site/api/v2/site_settings.php` → JSON 200

---

## 2. Frontend (vegasroyalspin.com)

```bash
cd /www/wwwroot/vegasroyalspin.com
unzip vegasroyalspin-frontend.zip
composer install --no-dev --optimize-autoloader
cp ENV.example .env
```

**Zorunlu .env:** `FRONTEND_API_ONLY=1`, `API_BACKEND_MAIN_BASE_URL=https://bo-nexthub.site/api/v2`, `SESSION_COOKIE_DOMAIN=.vegasroyalspin.com`, **DB_* yok**.

---

## 3. Mimari

Frontend → yalnızca HTTP → `bo-nexthub.site/api/v2`. MySQL yalnızca backend'de.
