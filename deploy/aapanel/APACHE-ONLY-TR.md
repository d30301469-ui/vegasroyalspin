# aaPanel - Apache-only kurulum

> SSL: Cloudflare edge (origin HTTP). Ayrinti: [CLOUDFLARE-TR.md](CLOUDFLARE-TR.md)

Bu proje yalnizca Apache + .htaccess ile calisir. Nginx reverse proxy acik olmamalidir.

## Cloudflare + aaPanel ozet

1. Cloudflare: Flexible (kurulum), DNS proxied, Always Use HTTPS ON
2. aaPanel: Force HTTPS OFF, origin tarafinda Apache aktif
3. .env: CLOUDFLARE_SSL=1 ve ORIGIN_HTTP=1
4. Komut: php deploy/aapanel/fix-cloudflare-env.php

## Site ayarlari

Her site icin:
- admin.vegasroyalspin.com
- api.vegasroyalspin.com (ayni backend kod kokune alias)
- vegasroyalspin.com

Ayarlar:
1. Site directory = /www/wwwroot/DOMAIN/ (public/ degil)
2. PHP 8.1+ ve Apache etkin
3. Nginx Stop veya site bazli reverse proxy kapali
4. .htaccess kullanimi icin AllowOverride All

## .htaccess kaynaklari

- Frontend: deploy/apache/vegasroyalspin.com.htaccess
- Backend: deploy/apache/admin.vegasroyalspin.com.htaccess

## Kurulum sirasi

1. Backend deploy: https://admin.vegasroyalspin.com/install
2. Frontend deploy: https://vegasroyalspin.com/install
3. Frontend dogrulama: php deploy/aapanel/fix-frontend-env.php
4. Ping/health testleri

## Test komutlari

```bash
curl -sS https://admin.vegasroyalspin.com/ping.php
curl -sS https://api.vegasroyalspin.com/api/v2/site_settings.php
curl -sS https://vegasroyalspin.com/ping.php
curl -sS https://vegasroyalspin.com/health.php
```

Ayni sunucuda loopback test:

```bash
curl -sS -H "Host: admin.vegasroyalspin.com" http://127.0.0.1/ping.php
curl -sS -H "Host: admin.vegasroyalspin.com" http://127.0.0.1/api/v2/site_settings.php
```

## Sik hatalar

- 500/rewrite loop: Zipten gelen .htaccess ile degistirin
- 502/503 API: FRONTEND_API_ONLY=1 ve backend URL/env kontrolu
- /api/v2/api/v2/: SITE_URL veya FRONTEND_URL icinde path olmasin
- JWT 401: MEMBER_JWT_SECRET frontend ve backendde ayni olmali