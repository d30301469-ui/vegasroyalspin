-- Production Database Fix for Favicon URLs
-- Run this SQL on the production database to fix favicon and manifest URLs

UPDATE site_ayarlar 
SET 
    favicon_url = '/assets/images/favicons/favicon.svg',
    manifest_url = '/assets/images/favicons/site.webmanifest'
WHERE id = 1;

-- Verify the update
SELECT id, favicon_url, manifest_url FROM site_ayarlar WHERE id = 1;
