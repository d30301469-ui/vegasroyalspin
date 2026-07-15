# Favicon Issue - Complete Fix Guide

## Problem Summary
The favicon was showing CDN PNG instead of the local SVG file, and manifest URL was pointing to wrong subdomain.

## Root Cause
Database `site_ayarlar` table had incorrect URLs:
- `favicon_url`: "https://resmim.net/cdn/2026/07/15/C2vLan.png" ❌
- `manifest_url`: "https://admin.vegasroyalspin.com/assets/images/favicons/site.webmanifest" ❌

## Fixes Applied

### 1. Frontend HTML (✅ DEPLOYED)
- Added `type="image/svg+xml"` attribute to favicon links
- Added cache busting query parameter (`?v=<filemtime>`) to favicon URLs
- Files modified:
  - `views/layouts/head.php`
  - `views/layouts/head_full.php`
  - `mobile/views/layouts/head.php`
  - `admin/app/Views/layouts/auth.php`
- **Commits**: 29fba3d, 2fc1315

### 2. Apache Configuration (✅ DEPLOYED)
- Added SVG MIME type configuration
- Added cache control headers for favicon files
- Files modified:
  - `.htaccess`
  - `admin/.htaccess`
- **Commits**: bd970db, 2fc1315

### 3. API Cache Busting (✅ DEPLOYED)
- Added `applyFaviconCacheBusting()` method to API responses
- Cache busting applied to favicon_url in API branding section
- Files modified:
  - `api/SiteSettings.php`
  - `admin/api/SiteSettings.php`
- **Commits**: 2ad8394, 04bec11, c1b306d

### 4. Database Fix (⚠️ PENDING - MANUAL ACTION REQUIRED)

**You must run this SQL on production database:**

```sql
UPDATE site_ayarlar 
SET 
    favicon_url = '/assets/images/favicons/favicon.svg',
    manifest_url = '/assets/images/favicons/site.webmanifest'
WHERE id = 1;
```

Or run PHP script at: `/fix_production_favicon_db.php`

## How Cache Busting Works

1. **Frontend Views**: 
   ```php
   $headFaviconUrl = $headFaviconPath . '?v=' . (int)(filemtime(...) ?: time());
   // Result: /assets/images/favicons/favicon.svg?v=1783894561
   ```

2. **API Response**:
   ```json
   {
     "branding": {
       "favicon_url": "https://resmim.net/cdn/.../C2vLan.png?v=1783894561"
     }
   }
   ```

3. **Browser**: Automatically reloads favicon when file modification time changes

## Testing Steps

After running the database fix:

1. Clear browser cache (Ctrl+Shift+R or Cmd+Shift+R)
2. Reload https://vegasroyalspin.com/
3. Open browser DevTools → Network tab
4. Look for favicon.svg request with `?v=` parameter
5. Check DevTools → Elements → head → look for `type="image/svg+xml"`

## API Response Example

Before fix:
```json
"branding": {
  "favicon_url": "https://resmim.net/cdn/2026/07/15/C2vLan.png"
}
```

After fix:
```json
"branding": {
  "favicon_url": "/assets/images/favicons/favicon.svg?v=1783894561"
}
```

## Files to Keep

- `/fix_production_favicon_db.php` - PHP script to fix database
- `/FIX_DATABASE_FAVICON_URLS.sql` - SQL script to fix database
- `/admin/database/migrations/fix_favicon_manifest_urls.php` - Alternative fix migration

## Commits

- bd970db: Add SVG MIME type to .htaccess
- 29fba3d: Add type attribute to favicon links
- 2fc1315: Add cache busting to favicon URLs and cache headers
- 2ad8394: Add API cache busting function
- 04bec11: Fix cache busting port handling
- c1b306d: Simplify favicon cache busting for relative paths only
