-- Normalize promotion image_url values for frontend rendering.
-- Run against the active application database (example: vegasroyalspin).

-- 1) Keep trusted external CDN URLs as-is.
--    (No update needed for icons.casinomilyon*.com or cms.casinomilyon*.com values.)

-- 2) Convert legacy absolute admin-host uploads URLs to frontend-friendly relative paths.
UPDATE promotions
SET image_url = REPLACE(image_url, 'https://admin.vegasroyalspin.com/uploads/', '/uploads/')
WHERE image_url LIKE 'https://admin.vegasroyalspin.com/uploads/%';

-- 3) Convert storage/admin upload prefixes to canonical /uploads.
UPDATE promotions
SET image_url = REPLACE(image_url, '/storage/uploads/', '/uploads/')
WHERE image_url LIKE '/storage/uploads/%';

UPDATE promotions
SET image_url = REPLACE(image_url, '/admin/uploads/', '/uploads/')
WHERE image_url LIKE '/admin/uploads/%';

-- 4) Example: restore a known CDN URL that was accidentally normalized.
-- Uncomment only if this exact case exists in your environment.
-- UPDATE promotions
-- SET image_url = 'https://icons.casinomilyon612.com/storage/medias/casinomilyon-18755179/content_18755179_7a28a81266d4fac19bd6bc3163e15151.webp'
-- WHERE image_url = '/uploads/promotions/1774927369_5b84eb4a5ffd7470.webp';

-- 5) Verification query.
SELECT id, title, image_url
FROM promotions
ORDER BY id;
