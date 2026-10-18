<?php
/**
 * Custom and bulk orders: a form people fill in to ask for a price. It posts
 * to the shop's own /quote-request; the request lands in the owner's quote
 * requests and their customer list.
 */
$quote_title = $section['title'] !== '' ? $section['title'] : $labels['quote'];
$quote_text  = $section['text'] !== '' ? $section['text'] : 'Tell us what you need and we will reply with a price.';
$extras = array_filter([
    'quantity_range'    => $section['quantity_range'] ? ['How many do you need?', 'For example, 50 pieces', 'off'] : null,
    'budget'            => $section['budget'] ? ['Your budget', 'For example, ' . sf_money(50000, $currency) . ' to ' . sf_money(100000, $currency), 'off'] : null,
    'timeline'          => $section['timeline'] ? ['When do you need it?', 'For example, in two weeks', 'off'] : null,
    'delivery_location' => $section['delivery_location'] ? ['Where should we deliver?', 'Town or full address', 'street-address'] : null,
]);
?>
<section class="sf-section sf-quote" id="sf-quote" aria-labelledby="sf-quote-title">
    <div class="sf-wrap">
        <div class="sf-quote__card">
            <div class="sf-quote__intro">
                <h2 class="sf-h2" id="sf-quote-title"><?= e($quote_title) ?></h2>
                <?= sf_paragraphs($quote_text, 'sf-quote__text') ?>
            </div>
            <form class="sf-form" action="/quote-request" method="post" data-quote-form novalidate>
                <input type="text" name="website" class="sf-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
                <div class="sf-form__row">
                    <label class="sf-field">
                        <span class="sf-field__label">Your name</span>
                        <input type="text" name="name" required maxlength="100" autocomplete="name">
                    </label>
                    <label class="sf-field">
                        <span class="sf-field__label">Phone or WhatsApp</span>
                        <input type="tel" name="phone" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="0803 123 4567">
                    </label>
                </div>
                <label class="sf-field">
                    <span class="sf-field__label">Email <span class="sf-field__opt">(optional)</span></span>
                    <input type="email" name="email" maxlength="100" autocomplete="email">
                </label>
                <?php if ($section['company_name']): ?>
                    <label class="sf-field">
                        <span class="sf-field__label">Company or organisation <span class="sf-field__opt">(optional)</span></span>
                        <input type="text" name="company_name" maxlength="200" autocomplete="organization">
                    </label>
                <?php endif; ?>
                <label class="sf-field">
                    <span class="sf-field__label">What do you need?</span>
                    <textarea name="message" rows="4" required maxlength="5000"></textarea>
                </label>
                <?php if ($extras): ?>
                    <div class="sf-form__row">
                        <?php foreach ($extras as $name => [$label, $placeholder, $autocomplete]): ?>
                            <label class="sf-field">
                                <span class="sf-field__label"><?= e($label) ?> <span class="sf-field__opt">(optional)</span></span>
                                <input type="text" name="<?= e($name) ?>" maxlength="200" placeholder="<?= e($placeholder) ?>" autocomplete="<?= e($autocomplete) ?>">
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <div class="sf-form__foot">
                    <button type="submit" class="sf-btn">Send my request</button>
                    <p class="sf-form__status" role="status" data-form-status></p>
                </div>
            </form>
        </div>
    </div>
</section>
