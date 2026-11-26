-- Shop Studio's "Everything you sell" kept its own list ("Only show these"):
-- once an owner ticked any product there, the shop showed only those, so a
-- product added later stayed off the shop even with Show on my shop on. Now
-- products.on_shop alone says what a shop shows, ticked in the Catalogue or
-- in Studio (storefront_catalogue, LandingPageController::on_shop).
--
-- So that each shop looks as it did, a product its list left out goes off
-- the shop. Products added since Show on my shop came in (28 October 2026,
-- 15:20) keep the choice made when they were added. A shop never published
-- from Studio used the old Landing Page's featured products as that list.
-- Then the lists are emptied, so running this twice changes nothing.

UPDATE products p
JOIN businesses b ON b.id = p.business_id
SET p.on_shop = 0
WHERE p.deleted_at IS NULL
  AND p.created_at < '2026-10-28 15:20:00'
  AND JSON_LENGTH(JSON_EXTRACT(b.storefront_published, REPLACE(JSON_UNQUOTE(JSON_SEARCH(b.storefront_published, 'one', 'catalogue', NULL, '$.sections[*].type')), '.type', '.only'))) > 0
  AND NOT JSON_CONTAINS(JSON_EXTRACT(b.storefront_published, REPLACE(JSON_UNQUOTE(JSON_SEARCH(b.storefront_published, 'one', 'catalogue', NULL, '$.sections[*].type')), '.type', '.only')), CAST(p.id AS CHAR));

UPDATE products p
JOIN businesses b ON b.id = p.business_id
SET p.on_shop = 0
WHERE p.deleted_at IS NULL
  AND p.created_at < '2026-10-28 15:20:00'
  AND b.storefront_published IS NULL
  AND JSON_LENGTH(b.featured_product_ids) > 0
  AND NOT JSON_CONTAINS(b.featured_product_ids, CAST(p.id AS CHAR));

UPDATE businesses
SET storefront_published = REGEXP_REPLACE(storefront_published, '"only":\\s*\\[[0-9,\\s]*\\]', '"only":[]'),
    storefront_draft     = REGEXP_REPLACE(storefront_draft, '"only":\\s*\\[[0-9,\\s]*\\]', '"only":[]')
WHERE storefront_published LIKE '%"only"%' OR storefront_draft LIKE '%"only"%';

UPDATE businesses SET featured_product_ids = NULL
WHERE storefront_published IS NULL AND featured_product_ids IS NOT NULL;
