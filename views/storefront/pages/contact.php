<?php
/**
 * The contact page, name.nfelia.com/contact: the request form for custom
 * and bulk orders first (the home page's "made for you" button opens it),
 * then every other way to reach the shop, the questions people ask, and the
 * map. Without the form it is simply how to reach the shop.
 *
 * From shell.php: $sections, $labels, $quote_on, $wa, $contact, $map_on.
 */
$quote_section = $sections['quote'];
$contact_title = $quote_on ? ($quote_section['title'] !== '' ? $quote_section['title'] : $labels['quote']) : 'Contact us';
$contact_intro = $quote_on
    ? ($quote_section['text'] !== '' ? $quote_section['text'] : 'Tell us what you need and we will reply with a price.')
    : 'Here is how to reach ' . $business['name'] . '.';

// Every way in, as rows people can tap
$ways = [];
if ($wa !== '') {
    $ways[] = ['whatsapp', 'WhatsApp', 'Chat with us', 'https://wa.me/' . $wa, true];
}
if (!empty($contact['show_phone']) && !empty($business['phone'])) {
    $ways[] = ['phone', 'Call', (string)$business['phone'], 'tel:' . preg_replace('/[^\d+]/', '', (string)$business['phone']), false];
}
if (!empty($contact['show_email']) && !empty($business['email'])) {
    $ways[] = ['mail', 'Email', (string)$business['email'], 'mailto:' . $business['email'], false];
}
$visit = (!empty($contact['show_address']) || $map_on) ? sf_short_address($business) : '';
if ($visit !== '') {
    $ways[] = ['pin', 'Visit', $visit, $map_on ? '#sf-location' : '', false];
}
?>
<section class="sf-section sf-page sf-contact-page" aria-labelledby="sf-contact-title">
    <div class="sf-wrap">
        <div class="sf-page__head">
            <h1 class="sf-h1 sf-page__title" id="sf-contact-title"><?= e($contact_title) ?></h1>
            <?= sf_paragraphs($contact_intro, 'sf-lead') ?>
        </div>
        <div class="sf-contact-page__grid">
            <?php if ($quote_on): ?>
                <?php $section = $quote_section; $quote_form_only = true; include __DIR__ . '/../sections/quote.php'; ?>
            <?php endif; ?>
            <?php if ($ways): ?>
                <div class="sf-ways">
                    <h2 class="sf-h3"><?= $quote_on ? 'Other ways to reach us' : 'Reach us' ?></h2>
                    <ul class="sf-ways__list">
                        <?php foreach ($ways as [$way_icon, $way_label, $way_value, $way_href, $way_new]): ?>
                            <li>
                                <?php if ($way_href !== ''): ?><a class="sf-way" href="<?= e($way_href) ?>"<?= $way_new ? ' target="_blank" rel="noopener"' : '' ?>><?php else: ?><div class="sf-way"><?php endif; ?>
                                    <span class="sf-way__icon"><?= sf_icon($way_icon, 22) ?></span>
                                    <span class="sf-way__text"><strong><?= e($way_label) ?></strong><span><?= e($way_value) ?></span></span>
                                    <?php if ($way_href !== ''): ?><?= sf_icon('right', 20, 'sf-way__go') ?><?php endif; ?>
                                <?php if ($way_href !== ''): ?></a><?php else: ?></div><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if ($contact['text'] !== ''): ?>
                        <?= sf_paragraphs($contact['text'], 'sf-ways__note') ?>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php
if ($sections['faq']['on']) {
    $section = $sections['faq'];
    include __DIR__ . '/../sections/faq.php';
}
if ($sections['location']['on']) {
    $section = $sections['location'];
    include __DIR__ . '/../sections/location.php';
}
