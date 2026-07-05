# aaPanel kurulum — vegasroyalspin.com + admin.vegasroyalspin.com + api.vegasroyalspin.com

> **SSL: Cloudflare edge only** — [CLOUDFLARE-TR.md](CLOUDFLARE-TR.md)  
> **Web sunucusu: Apache-only.** [APACHE-ONLY-TR.md](APACHE-ONLY-TR.md)

---

## Cloudflare + origin HTTP (önerilen)

| Katman | Ayar |
|--------|------|
| Cloudflare | Flexible, Always Use HTTPS ON, DNS proxied |
| aaPanel | Force HTTPS **OFF**, Apache :80, Nginx **Stop** |
| `.env` | `CLOUDFLARE_SSL=1`, `SITE_URL=https://...` |
| Sunucu içi API | `API_BACKEND_INTERNAL_BASE_URL=http://127.0.0.1/api/v2` |

```bash
php deploy/aapanel/fix-cloudflare-env.php   # her iki site kökünde
```

---

## Özet

1. Zip yükle → kökte `.htaccess` (Apache rewrite)
2. PHP 8.1+, `mod_rewrite` açık, **Apache 80/443** dinlesin (nginx kapalı)
3. Backend önce: `admin.vegasroyalspin.com` → `/install`
4. Frontend: `vegasroyalspin.com` → `/install`
5. Test: `https://DOMAIN/ping.php`, `install-status.php`, sonra `health.php`

---

## Dosya yükleme

1. `dist/bo-nexthub-admin.zip` → `/www/wwwroot/admin.vegasroyalspin.com/`
2. `dist/vegasroyalspin-frontend.zip` → `/www/wwwroot/vegasroyalspin.com/`
3. **Yeni kurulum:** eski `storage/install.lock` ve `.env` dosyalarını silin (zip üzerine yazınca kalabilir). Veya: `php scripts/reset-for-install.php --env`
4. **Site directory** aaPanel'de domain kökü olmalı (`/www/wwwroot/DOMAIN/`) — `public/` alt klasörü **değil**
5. **`vendor/` zip içinde hazır gelir — sunucuda composer kurmanıza gerek yok**

Kurulum sihirbazı gelmiyorsa:

```bash
cd /www/wwwroot/admin.vegasroyalspin.com   # veya vegasroyalspin.com
php scripts/post-upload-check.php
php scripts/reset-for-install.php --env
systemctl restart httpd
curl -sS https://DOMAIN/install-status.php
```

---

## .env

### Backend (`admin.vegasroyalspin.com`)

```env
APP_ENV=production
DB_HOST=127.0.0.1
DB_DATABASE=...
DB_USERNAME=...
DB_PASSWORD=...
MEMBER_JWT_SECRET=...
FRONTEND_CMS_PURGE_SECRET=... (frontend ile aynı)
FRONTEND_URL=https://vegasroyalspin.com
```

### Frontend (`vegasroyalspin.com`)

```env
APP_ENV=production
FRONTEND_API_ONLY=1
SITE_URL=https://vegasroyalspin.com
BACKEND_URL=https://admin.vegasroyalspin.com
API_BACKEND_MAIN_BASE_URL=https://api.vegasroyalspin.com/api/v2
MEMBER_JWT_SECRET=... (backend ile aynı)
FRONTEND_CMS_PURGE_SECRET=... (backend ile aynı)
```

---

## Test

```bash
curl -sS https://admin.vegasroyalspin.com/ping.php
curl -sS https://vegasroyalspin.com/ping.php
curl -sS 'https://api.vegasroyalspin.com/api/v2/content/sliders?category=home'
curl -sS https://vegasroyalspin.com/diagnose.php
```

### Canlı smoke (deploy sonrası)

Sunucuda proje kökünden (varsayılan: Apache loopback — aynı VM'de public HTTPS timeout normal):

```bash
php scripts/live-probe-checklist.php
# Dış HTTPS de test etmek için (SSH'tan genelde timeout):
php scripts/live-probe-checklist.php --public
# veya
FRONTEND_URL=https://vegasroyalspin.com BACKEND_URL=https://admin.vegasroyalspin.com php scripts/live-probe-checklist.php
RUN_LIVE_PROBES=1 php scripts/test-all-layers.php
```

Manuel loopback (Apache dinliyorsa çalışmalı):

```bash
curl -sS -H "Host: admin.vegasroyalspin.com" http://127.0.0.1/ping.php
curl -sS -H "Host: vegasroyalspin.com" http://127.0.0.1/ping.php
# Port 443 only vhost:
curl -sk -H "Host: vegasroyalspin.com" https://127.0.0.1/ping.php
```

Loopback timeout → web sunucusu yerelde dinlemiyor:

```bash
php scripts/diagnose-web-stack.php
ss -tlnp | grep -E ':80|:443|:8080'
```

aaPanel: **Apache Running**, **Nginx Stop** (Apache-only). Detay: [APACHE-ONLY-TR.md](APACHE-ONLY-TR.md)

Beklenen: backend + frontend ping/health, CMS (`content/sliders`, `content/mobile-menu`), `auth/session` → OK.

### Frontend `.env` yoksa (MISSING)

```bash
cd /www/wwwroot/vegasroyalspin.com
php scripts/bootstrap-frontend-env.php
# MEMBER_JWT_SECRET → admin.vegasroyalspin.com .env ile ayni
nano .env
```

### Apache timeout (port OPEN, HTTP 0 bytes)

`ss -tlnp` ciktisinda yuzlerce `httpd` satiri varsa worker'lar kilitlenmistir:

```bash
systemctl restart httpd
# veya aaPanel → Apache → Restart
curl -sS -m 5 -H "Host: vegasroyalspin.com" http://127.0.0.1/ping.php
tail -50 /www/wwwlogs/vegasroyalspin.com-error_log
```

Guncel tani script'i yukleyin (`scripts/diagnose-web-stack.php` — [0] .env, [5] raw socket, [6] system curl bolumleri olmali).

---

### CMS cache purge (admin kayıt → frontend)

Her iki `.env` dosyasında aynı değer:

```env
FRONTEND_CMS_PURGE_SECRET=...
FRONTEND_URL=https://vegasroyalspin.com   # backend .env
```

Admin panelden mobil menü / footer / slider kaydı sonrası frontend CMS cache otomatik temizlenir.

---

## Sık hatalar

| Belirti | Çözüm |
|---------|--------|
| composer: `admin/` does not appear to be a folder | Frontend zip kullanın; sunucuda `composer install` **gerekmez**. Zorunluysa: `cp deploy/composer.frontend.json composer.json && composer install --no-dev` |
| SSL cert name mismatch (`AH01909`) | Cloudflare kullanın; aaPanel origin SSL + Force HTTPS **KAPAT** — bkz. [CLOUDFLARE-TR.md](CLOUDFLARE-TR.md) |
| `proxy_fcgi` connection reset | PHP-FPM çöktü — aşağıdaki **PHP-FPM** bölümü; `install-probe.php` açın |
| Kurulum ekranı gelmiyor | `install-status.php` açın; `php scripts/reset-for-install.php --env`; eski `install.lock` silin |
| Apache log: rewrite loop | `.htaccess` zip sürümüyle değiştir |
| 502 frontend API | Backend `health.php` + `.env` URL |
| `/api/v2/api/v2/` | `SITE_URL` sadece domain, path yok |
| Log `3345#0` / upstream 8290 | Nginx hâlâ önde — Apache-only: nginx Stop |
| 503 track-visit / API | `php deploy/aapanel/fix-frontend-env.php` |
| health timeout 8s | Backend down veya hairpin — `API_BACKEND_INTERNAL_BASE_URL` |

### SSL uyarısı (`AH01909` — sertifika domain ile uyuşmuyor)

Apache hâlâ çalışır ama tarayıcı uyarı verir; bazen PHP-FPM istekleri de kesilir.

**aaPanel:**

1. **Website** → `vegasroyalspin.com` → **SSL**
2. **Let's Encrypt** → domain: `vegasroyalspin.com`, `www.vegasroyalspin.com`
3. **Apply** → **Force HTTPS** (isteğe bağlı)
4. Aynısını `admin.vegasroyalspin.com` için tekrarla

Kontrol:

```bash
echo | openssl s_client -connect vegasroyalspin.com:443 -servername vegasroyalspin.com 2>/dev/null | openssl x509 -noout -subject -dates
# subject içinde vegasroyalspin.com olmalı
```

### PHP-FPM çökmesi (`proxy_fcgi` / `Connection reset` on `/install`)

Log satırı: `Failed to read FastCGI header` = PHP worker aniden öldü (fatal, segfault, pool tükendi).

**Sunucuda sırayla:**

```bash
cd /www/wwwroot/vegasroyalspin.com

# 1) PHP CLI — FPM olmadan
php -v
php ping.php
php install-probe.php
php install-status.php

# 2) Web (FPM üzerinden)
curl -sk https://vegasroyalspin.com/ping.php
curl -sk https://vegasroyalspin.com/install-probe.php
curl -sk https://vegasroyalspin.com/install-status.php

# 3) PHP-FPM log (sürüm 83 örnek — aaPanel'de PHP sürümüne göre değişir)
tail -80 /www/server/php/83/var/log/php-fpm.log
tail -80 /www/wwwlogs/vegasroyalspin.com-error_log

# 4) Yeniden başlat
systemctl restart php-fpm-83
systemctl restart httpd
```

**aaPanel:**

- **App Store** → **PHP 8.1+** → **Restart**
- Site → **PHP Version** → frontend ve backend **aynı** stabil sürüm (8.1 veya 8.3)
- **storage/** sahibi: `www` — `chown -R www:www storage && chmod -R 775 storage`

`install-probe.php` **OK** ama `/install` **502** ise Apache/SSL vhost karışıklığı; SSL adımını önce düzeltin.

`install-probe.php` **FAIL** ise çıktıdaki satırı düzeltin (eksik extension, storage yazılamıyor, vendor yok).

### Aynı sunucu — backend timeout (Apache loopback)

Frontend `.env` doğru ama `backend_reachable: fail:Operation timed out` ise PHP dış HTTPS'e çıkamıyor olabilir:

```bash
curl -sS -H "Host: admin.vegasroyalspin.com" http://127.0.0.1/ping.php
curl -sS -H "Host: admin.vegasroyalspin.com" http://127.0.0.1/api/v2/site_settings.php
curl -sS https://admin.vegasroyalspin.com/ping.php
```

Çalışan loopback için `.env`:

```env
API_BACKEND_INTERNAL_BASE_URL=http://127.0.0.1/api/v2
API_BACKEND_INTERNAL_HOST=admin.vegasroyalspin.com
```

Sonra: `curl -sS https://vegasroyalspin.com/health.php` → `"ok": true`

### .env hızlı düzeltme (frontend sunucuda)

```bash
cd /www/wwwroot/vegasroyalspin.com
php deploy/aapanel/fix-frontend-env.php
```
