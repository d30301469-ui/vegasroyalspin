# shared/

Single source of truth for PHP used by both the frontend host and the admin panel.

| Path | Role |
|------|------|
| `shared/services/*.php` | Canonical service implementations |
| `shared/api/*.php` | Canonical API module implementations |
| `shared/runtime.php` | `shared_project_root()` / `shared_package_root()` helpers |

Root `services/` + `api/` and `admin/services/` + `admin/api/` are **thin loaders only**. Edit logic here, never in the loaders.

Deploy both hosts with this `shared/` directory (split-deploy must copy `shared/` alongside each package).

Verify: `php scripts/check-twins.php`
