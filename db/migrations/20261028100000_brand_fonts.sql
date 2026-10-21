-- Brand fonts. Settings › Business › Branding now has two font pickers: a
-- primary font for headlines and a secondary one for text, each from the
-- library in app/helpers/brand.php (brand_font_library). With the brand
-- colours they lead on the shop, unless the owner picks something else in
-- Shop Studio, and on the links customers open (invoices, receipts,
-- quotations, deliveries).
--
-- brand_font_head   primary font, a key of brand_font_library()
-- brand_font_body   secondary font, a key of brand_font_library()
--
-- Both stay NULL until the owner chooses; the shop then keeps its look's own
-- fonts. Owners who picked one of the old pairs other than the default keep
-- it as their brand fonts. Inter + DM Sans was the default, so nobody counts
-- as having chosen it.

ALTER TABLE `businesses`
    ADD COLUMN IF NOT EXISTS `brand_font_head` VARCHAR(40) NULL DEFAULT NULL
        COMMENT 'Primary brand font, for headlines' AFTER `font_pairing`,
    ADD COLUMN IF NOT EXISTS `brand_font_body` VARCHAR(40) NULL DEFAULT NULL
        COMMENT 'Secondary brand font, for text' AFTER `brand_font_head`;

UPDATE `businesses`
SET `brand_font_head` = CASE `font_pairing`
        WHEN 'corporate_serif' THEN 'eb-garamond'
        WHEN 'bold_display'    THEN 'syne'
        WHEN 'classic_tech'    THEN 'roboto-mono'
        WHEN 'elegant_serif'   THEN 'playfair'
    END,
    `brand_font_body` = CASE `font_pairing`
        WHEN 'corporate_serif' THEN 'questrial'
        WHEN 'bold_display'    THEN 'outfit'
        WHEN 'classic_tech'    THEN 'roboto'
        WHEN 'elegant_serif'   THEN 'lato'
    END
WHERE `font_pairing` <> 'modern_sans'
  AND `brand_font_head` IS NULL
  AND `brand_font_body` IS NULL;
