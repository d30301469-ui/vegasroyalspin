# Backoffice API v2

Backend host (`bo-nexthub.site`) iki katman sunar:

| Katman | Base URL | Auth |
|--------|----------|------|
| **Public Member API** | `/api/v2/*` | JWT Bearer (üye) veya public GET |
| **Admin Internal API** | `/api/v2/internal/*` (veya admin oturumlu `/api/v2/*`) | Admin oturumu + CSRF |
| **Provider Callbacks** | `/api/v2/drakon_callback`, `/api/v2/bgaming-wallet/*`, `/api/v2/megapayz-callback` | İmza / IP |

## JSON sözleşmesi

Tüm yeni endpoint'ler `ApiResponse` zarfını kullanır:

```json
{
  "success": true,
  "ok": true,
  "code": 200,
  "message": "İsteğe bağlı açıklama",
  "data": {},
  "meta": {}
}
```

Hata:

```json
{
  "success": false,
  "ok": false,
  "code": 422,
  "message": "Doğrulama hatası",
  "error": "VALIDATION_ERROR",
  "errors": {}
}
```

## Public Member API (frontend)

### Auth
- `POST /api/v2/auth/login` — giriş, JWT döner
- `POST /api/v2/auth/register` — kayıt
- `POST /api/v2/auth/logout` — çıkış
- `POST /api/v2/auth/refresh` — token yenileme (Bearer gerekir)
- `GET /api/v2/auth/session` — oturum doğrulama

### Hesap
- `GET /api/v2/me` — profil + tercihler + limitler
- `GET|PATCH /api/v2/me/preferences` — bildirim/dil tercihleri
- `GET|PATCH /api/v2/me/limits` — sorumlu oyun limitleri
- `GET /api/v2/me/security-sessions` — aktif JWT oturumları
- `GET /api/v2/balance.php` veya `/api/v2/account/balance`
- `GET /api/v2/profile/detail`, `POST /api/v2/profile/update`

### İçerik (public GET)
- `/api/v2/content/sliders`, `footer`, `mobile-menu`, `homepage-sections`, `footer-pages`, `promotions`, `auth-sliders`
- `/api/v2/site_settings.php`, `announcements.php`, `games.php`, `winners.php`

### Finans & oyun
- `GET /api/v2/payment/methods`
- `POST /api/v2/deposit_payment.php`, `withdraw_payment.php`
- `GET /api/v2/history/deposits`, `history/withdrawals`
- `POST /api/v2/game_launch.php`

## Admin Internal API

Admin panel oturumu ile (`Cookie` + `X-CSRF-Token` POST'larda):

- `GET /api/v2/internal/dashboard/summary` — üye route'ları atlanır
- `GET /api/v2/internal/users`, `GET /api/v2/internal/users/{id}`
- `GET /api/v2/internal/compliance/kyc-queue`
- `POST /api/v2/internal/compliance/kyc/{id}/approve|reject`
- `GET /api/v2/internal/support/tickets`, `POST /api/v2/internal/support/tickets/{id}/reply`
- `GET|POST /api/v2/internal/promotions`

Panel UI (Phase 3):

- `/support/tickets` — destek listesi ve yanıt
- `/notifications` — üye bildirimi gönder
- `/kyc/review` — KYC onay/red

## Affiliate (üye)

- `GET /api/v2/referrals` — referans kodu + davet edilenler
- `GET /api/v2/affiliate/summary` — özet (share_link, total_referred)

## Provider callbacks

- Drakon: `POST /api/v2/drakon_callback`
- BGaming: `POST /api/v2/bgaming-wallet/{balance|play|rollback|...}`
- MegaPayz: `POST /api/v2/megapayz-callback`

## Test

```bash
php bin/test-api.php https://bo-nexthub.site
```

### Sorumlu oyun
- `GET|PATCH /api/v2/me/limits`
- `POST /api/v2/responsible-gaming/limits`
- `POST /api/v2/responsible-gaming/cool-off` — `{ "days": 7 }`
- `POST /api/v2/responsible-gaming/self-exclusion` — `{ "months": 6 }`

### KYC
- `GET /api/v2/kyc/status`
- `POST /api/v2/kyc/documents` — belge yükleme (path veya base64)

### Bildirimler & destek
- `GET /api/v2/notifications`
- `POST /api/v2/notifications/read-all`
- `POST /api/v2/notifications/{id}/read`
- `GET|POST /api/v2/support/tickets`
- `GET|POST /api/v2/support/tickets/{id}/messages`

### Uyumluluk (admin)

- `GET /api/v2/compliance/aml-alerts`
- `POST /api/v2/compliance/aml-alerts/{id}/resolve`
- `GET /api/v2/risk/alerts`
- `POST /api/v2/compliance/risk-alerts/{id}/resolve`

Panel: `/compliance/aml-alerts`, `/compliance/risk-alerts`

### Otomatik AML tetikleyicileri

Çekim talebi oluşturulduğunda (`MegaPayzService::createWithdraw`):

| Kural | Env | Varsayılan |
|-------|-----|------------|
| Yüksek tutarlı çekim | `COMPLIANCE_AML_WITHDRAW_THRESHOLD` | 25000 TRY |
| Hızlı yatırım→çekim | `COMPLIANCE_AML_RAPID_WITHDRAW_MIN` + `COMPLIANCE_AML_DEPOSIT_WINDOW_HOURS` | 5000 TRY / 24s |
| Çoklu bekleyen çekim | — | ≥2 pending → risk alert |

### Spor
- `POST /api/v2/sports/launch` — OKKO iframe (env gerekir)

## Member API modülleri

Üye route'ları parçalı include edilir:

- `api/v2/routes/member_auth.php` — giriş, kayıt, oturum, şifre
- `api/v2/routes/member_engagement.php` — aranma talebi, promosyon claim
- `api/v2/routes/member_wallet.php` — bakiye, profil, geçmiş, ödeme
- `api/v2/routes/member_extended.php` — KYC, bildirim, destek, spor meta

Admin route'ları: `api/v2/includes/admin_routes.php`

## OpenAPI

`docs/openapi-public-v2.yaml`

## Migration

```bash
php bin/install.php --migrate
```

Yeni tablolar: `user_member_settings`, `member_notifications`, `support_tickets`, `support_ticket_messages`, `aml_alerts`, `risk_alerts`
