# Deprecated — do not require from production dispatch

`PublicMemberApiRuntime.php` was the legacy monolithic member API handler (~3000 lines).

**Replacement:** `admin/api/v2/routes/member_*.php` via `PublicMemberApiDispatcher` → `member_local.php`.

Kept for historical reference and emergency diff only. Remove from deploy bundles.
