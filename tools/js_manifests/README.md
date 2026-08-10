# Dynamic JS manifests (long-term unused-code safety)

False positives on files like `profile.js` come from treating nested / string-dispatched helpers as “unused”.

## Policy

| Mode | Strip |
|------|--------|
| `static` | High-confidence unused top-level functions may be stripped |
| `dynamic` | **Never** strip unless `tools/reports/js-runtime-usage.json` proves zero hits (`strip_policy: coverage_required`) |

## Annotations (in source)

```js
// @dynamic-file
// @entry openVegaPanel, closeVegaPanel
// @keep initProfileModal
```

## Commands

```bash
# Refresh manifests from window exports + annotations
python tools/generate_js_manifests.py --report

# File-level unused only (safe)
python tools/unused_js_cleaner.py --apply --files-only

# Symbol report including dynamic unreachable (informational)
python tools/unused_js_cleaner.py --symbols --report-dynamic --min-confidence low

# Strip dynamic symbols ONLY with coverage + explicit flag
python tools/unused_js_cleaner.py --apply --symbols --allow-dynamic-strip --min-confidence medium
```

## Runtime coverage

1. Open site with `?js_usage=1` (loads `assets/js/js-usage-probe.js`)
2. Click through profile / payments / modals
3. In DevTools: `copy(JSON.stringify(__JS_USAGE_DUMP__(), null, 2))`
4. Save as `tools/reports/js-runtime-usage.json` (map `window` hits into real file keys if needed)

Shape:

```json
{
  "generated_at": "…",
  "files": {
    "assets/js/profile.js": ["openVegaPanel", "initProfileModal"]
  }
}
```
