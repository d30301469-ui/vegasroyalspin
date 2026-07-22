# Deprecated — do not require from production dispatch

The legacy monolithic member API handler (`PublicMemberApiRuntime.php`, ~3000 lines)
has been removed.

**Replacement:** `admin/api/v2/routes/member_*.php` via `PublicMemberApiDispatcher` → `member_local.php`.
