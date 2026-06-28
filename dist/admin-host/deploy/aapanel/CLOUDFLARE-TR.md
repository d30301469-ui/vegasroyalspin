# Cloudflare SSL + aaPanel (origin HTTP)

Ziyaretçi **HTTPS** görür; sunucuda **Let's Encrypt gerekmez**. Apache yalnızca **port 80** dinler.

```
Ziyaretçi ──HTTPS──► Cloudflare ──HTTP──► aaPanel Apache (127.0.0.1:80)
```

---

## Cloudflare

Her domain (`vegasroyalspin.com`, `bo-nexthub.site`, `www`, `m.`):

1. DNS → **Proxied** (turuncu bulut)
2. **SSL/TLS → Overview → Encryption mode: Flexible** (kurulum) veya **Full (strict)** + Origin Certificate (canlı)
3. **Edge Certificates → Always Use HTTPS: ON**

---

## aaPanel

Her site:

1. **Website → SSL → Force HTTPS: KAPAT**
2. Origin'de Let's Encrypt **kullanmayın** (Flexible modda)
3. **App Store → Nginx Stop**, **Apache Running**
4. Site directory = `/www/wwwroot/DOMAIN/` (public/ değil)
5. PHP 8.1+ → Restart

### `m.vegasroyalspin.com` default sayfa açıyorsa

Bu ekran (`Site is created successfully!`) `m.` hostunun farklı site köküne bağlı olduğunu gösterir.

1. aaPanel → `vegasroyalspin.com` → **Domain** bölümüne `m.vegasroyalspin.com` ekleyin (aynı site).
2. Ayrı açılmış bir `m.vegasroyalspin.com` sitesi varsa silin veya document root'unu
   `/www/wwwroot/vegasroyalspin.com/` ile aynı yapın.
3. Cloudflare DNS'te `m` kaydı **Proxied** olmalı.
4. Değişiklikten sonra Apache/PHP-FPM restart yapın.

Hızlı kontrol:
```bash
curl -I https://vegasroyalspin.com/ping.php
curl -I https://m.vegasroyalspin.com/ping.php
```
İkisi de `200` dönmeli.

---

## Güvenlik: origin'i Cloudflare'e kilitle (ÖNEMLİ)

Flexible modda origin **HTTP:80** herkese açıktır. Biri Cloudflare'i atlayıp sunucu IP'sine
doğrudan vurabilir (şifresiz trafik + sahte `CF-Connecting-IP` başlığı riski). Bunu kapatın:

**aaPanel → Security → yalnızca Cloudflare IP aralıklarına 80 portunu açın**, gerisini kapatın.
Güncel liste: https://www.cloudflare.com/ips/

Bunu yaptıktan sonra:
- `CF-Connecting-IP` / `X-Forwarded-Proto` başlıkları **güvenilir** olur.
- Gerçek ziyaretçi IP'si (affiliate, ziyaret, login logları) doğru kaydedilir — kod
  `metropol_cloudflare_client_ip()` ile bu başlığı kullanır.

---

## Sağlayıcı callback'leri (MegaPayz / Drakon / BGaming / Casino)

Bu uçlar sunucu-sunucu webhook'tur ve IP allowlist kullanabilir:

- `DRAKON_CALLBACK_ALLOWED_IPS`, `MEGAPAYZ_CALLBACK_ALLOWED_IPS`, `CASINO_CALLBACK_ALLOWED_IPS`
- Domain **proxied** ise `REMOTE_ADDR` = Cloudflare IP olur → sağlayıcı IP allowlist'i **kırılır (403)**.
- Çözüm (birini seçin):
  1. Allowlist'i **boş bırakın**, bunun yerine imza/secret doğrulamasına güvenin
     (`DRAKON_CALLBACK_SECRET` vb.) — önerilen.
  2. Allowlist'e sağlayıcı IP'leri yerine **Cloudflare IP aralıklarını** yazın.
  3. Callback için **DNS-only (gri bulut)** ayrı subdomain kullanın.

---

## aaPanel: proxy_fcgi / 524 (kurulum takılıyor)

Cloudflare **524** = origin 100 sn içinde yanıt vermedi (PHP-FPM çöktü veya takıldı).

1. **Website → vegasroyalspin.com → PHP** → sürüm 8.1+ seç → **Restart**
2. **App Store → PHP 8.x → Settings → Configuration**:
   - `memory_limit = 256M`
   - `max_execution_time = 120`
   - `max_input_time = 120`
3. **Website → PHP-FPM → Restart**
4. Site dizini: `/www/wwwroot/vegasroyalspin.com/` içinde `index.php` olmalı:
   ```bash
   ls -la /www/wwwroot/vegasroyalspin.com/index.php
   ```
5. Teşhis sırası (tarayıcı):
   - `https://vegasroyalspin.com/ping.php` → anında JSON
   - `https://vegasroyalspin.com/install-probe.php` → tüm adımlar OK
   - `https://vegasroyalspin.com/install`
6. Kurulum bitince `install-complete.php` açılır; ana sayfa yavaşsa önce backend:
   ```bash
   cd /www/wwwroot/vegasroyalspin.com && php deploy/aapanel/fix-cloudflare-env.php
   ```

---

## `.env` kuralları

| Değişken | Değer |
|----------|--------|
| `CLOUDFLARE_SSL` | `1` |
| `ORIGIN_HTTP` | `1` |
| `SITE_URL`, `FRONTEND_URL`, `BACKEND_URL` | `https://...` (public) |
| `API_BACKEND_INTERNAL_*` | Aynı VM: `http://127.0.0.1/api/v2` + Host |

Otomatik düzeltme:

```bash
cd /www/wwwroot/vegasroyalspin.com
php deploy/aapanel/fix-cloudflare-env.php

cd /www/wwwroot/bo-nexthub.site
php deploy/aapanel/fix-cloudflare-env.php
```

---

## Kurulum sırası

```
1. https://bo-nexthub.site/install
2. MEMBER_JWT_SECRET kopyala
3. https://vegasroyalspin.com/install
4. https://vegasroyalspin.com/health.php → "ok": true
```

Loopback test (SSH):

```bash
curl -sS http://127.0.0.1/ping.php -H "Host: vegasroyalspin.com"
curl -sS http://127.0.0.1/ping.php -H "Host: bo-nexthub.site"
```

---

## Sık hatalar

| Belirti | Çözüm |
|--------|--------|
| Redirect loop | aaPanel Force HTTPS **KAPAT** |
| ERR_CONNECTION_CLOSED | PHP-FPM restart; `install-probe.php` |
| `.env` http URL | `fix-cloudflare-env.php` |
| Backend timeout | `API_BACKEND_INTERNAL_*` loopback |
| AH01909 SSL warn | Origin SSL kapatın; Cloudflare kullanın |
| Tüm loglarda aynı IP | Origin :80'i Cloudflare IP'lerine kilitleyin (gerçek IP başlığı) |
| Callback 403 IP_NOT_ALLOWED | Allowlist'i boşaltın (imza doğrulaması) veya CF IP yazın |
| **AH01276** index.php yok | Zip kökü `/www/wwwroot/vegasroyalspin.com/` olmalı (alt klasör değil) |
| **AH01909** SSL uyarısı | aaPanel site SSL **kapat**; yalnızca Cloudflare + origin **HTTP :80** |
| **proxy_fcgi** / **524** | PHP-FPM restart; `install-probe.php`; `memory_limit=256M`; yeni zip |
| Kurulum sonrası **524** | `install-complete.php` kullanın; backend loopback `.env` kontrolü |
| **HTTP 500** ana sayfa | `install-probe.php` → PHP-FPM restart; `fix-cloudflare-env.php`; JWT/APP_KEY kontrol |

Detay: [KURULUM-TR.md](KURULUM-TR.md), [APACHE-ONLY-TR.md](APACHE-ONLY-TR.md)
