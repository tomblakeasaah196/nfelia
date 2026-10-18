<?php
/**
 * The footer: how to reach the shop, its socials, and "Shop by Nfelia"
 * unless the owner has the add-on that hides it.
 */
$contact_section = $sections['contact'];
$social_icons = ['instagram' => 'Instagram', 'facebook' => 'Facebook', 'tiktok' => 'TikTok', 'twitter' => 'X'];
$footer_address = trim((string)($business['address'] ?? ''));
?>
<footer class="sf-footer" id="sf-contact">
    <div class="sf-wrap sf-footer__inner">
        <div class="sf-footer__main">
            <p class="sf-footer__brand"><?= e($business['name']) ?></p>
            <?php if ($contact_section['text'] !== ''): ?>
                <?= sf_paragraphs($contact_section['text'], 'sf-footer__text') ?>
            <?php endif; ?>
            <ul class="sf-footer__lines">
                <?php if ($contact_section['show_address'] && $footer_address !== ''): ?>
                    <li><?= sf_icon('pin', 18) ?><span><?= nl2br(e($footer_address), false) ?></span></li>
                <?php endif; ?>
                <?php if ($contact_section['show_phone'] && !empty($business['phone'])): ?>
                    <li><?= sf_icon('phone', 18) ?><a href="tel:<?= e(preg_replace('/[^\d+]/', '', (string)$business['phone'])) ?>"><?= e($business['phone']) ?></a></li>
                <?php endif; ?>
                <?php if ($contact_section['show_email'] && !empty($business['email'])): ?>
                    <li><?= sf_icon('mail', 18) ?><a href="mailto:<?= e($business['email']) ?>"><?= e($business['email']) ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
        <div class="sf-footer__actions">
            <?php if ($wa !== ''): ?>
                <a class="sf-btn sf-btn--outline sf-btn--footer" href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Chat on WhatsApp</span></a>
            <?php endif; ?>
            <?php foreach ($socials as $network => $url): ?>
                <a class="sf-iconbtn sf-iconbtn--footer" href="<?= e($url) ?>" target="_blank" rel="noopener" aria-label="<?= e($social_icons[$network]) ?>"><?= sf_icon($network === 'twitter' ? 'x' : $network, 20) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="sf-footer__end">
            <p>© <?= date('Y') ?> <?= e($business['name']) ?></p>
            <?php if (empty($business['hide_nfelia_credit'])): ?>
                <p><a href="<?= e(APP_URL . '/?from=' . rawurlencode((string)($business['subdomain'] ?? ''))) ?>" target="_blank" rel="noopener">Shop by Nfelia</a></p>
            <?php endif; ?>
        </div>
    </div>
</footer>
