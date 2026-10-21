<?php
/**
 * The bag bar, the product sheet, the bag with its three checkout steps, and
 * (live shops) the Status image maker. storefront.js fills them in. Checkout
 * posts to the shop's own /checkout: the cart says only which products and
 * how many, and the server looks up every name and price. The bag also lists
 * the shopper's recent orders, kept on their phone, with their tracking links.
 */
$banks    = $catalogue['banks'];
$bag_word = $settings['look'] === 'market' ? 'order' : 'bag';
?>
<div class="sf-bagbar" data-bagbar hidden>
    <div class="sf-bagbar__sum">
        <span data-bag-count-text>0 items</span>
        <strong data-bag-total>₦0</strong>
    </div>
    <button type="button" class="sf-bagbar__btn" data-open-bag><?= $bag_word === 'order' ? 'See my order' : 'View bag' ?></button>
</div>

<p class="sf-toast" role="status" aria-live="polite" data-toast></p>

<dialog class="sf-dialog sf-dialog--product" id="sf-product" aria-labelledby="sf-product-name">
    <div class="sf-dialog__panel">
        <button type="button" class="sf-dialog__close" data-close aria-label="Close"><?= sf_icon('close', 22) ?></button>
        <div class="sf-product__gallery" data-p-gallery></div>
        <div class="sf-product__body">
            <h2 class="sf-h2" id="sf-product-name" data-p-name></h2>
            <p class="sf-product__price" data-p-price></p>
            <div class="sf-prose sf-product__desc" data-p-desc></div>
            <div class="sf-product__variants" data-p-variants></div>
            <label class="sf-field">
                <span class="sf-field__label">Note for the shop <span class="sf-field__opt">(optional)</span></span>
                <input type="text" maxlength="255" data-p-note autocomplete="off">
            </label>
            <div class="sf-product__buy">
                <div class="sf-stepper sf-stepper--lg">
                    <button type="button" data-p-step="-1" aria-label="One fewer"><?= sf_icon('minus', 18) ?></button>
                    <output data-p-qty aria-live="polite">1</output>
                    <button type="button" data-p-step="1" aria-label="One more"><?= sf_icon('plus', 18) ?></button>
                </div>
                <button type="button" class="sf-btn sf-btn--grow" data-p-add>Add to <?= $bag_word ?></button>
            </div>
            <p class="sf-buy__meta" data-p-book-text hidden></p>
            <a class="sf-btn sf-btn--block" data-p-book hidden><?= sf_icon('calendar', 20) ?><span>Pick a time and book</span></a>
            <?php if ($wa !== ''): ?>
                <a class="sf-btn sf-btn--outline sf-btn--block" data-p-wa href="https://wa.me/<?= e($wa) ?>" target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Ask on WhatsApp</span></a>
            <?php endif; ?>
            <?php if ($mode === 'live'): ?>
                <div class="sf-share">
                    <button type="button" class="sf-share__btn" data-share-item><?= sf_icon('share', 18) ?><span>Share</span></button>
                    <button type="button" class="sf-share__btn" data-status-item><?= sf_icon('image', 18) ?><span>Image for your Status</span></button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</dialog>

<dialog class="sf-dialog sf-dialog--bag" id="sf-bag" aria-labelledby="sf-bag-title">
    <div class="sf-dialog__panel">
        <div class="sf-dialog__head">
            <button type="button" class="sf-iconbtn sf-iconbtn--bare" data-bag-back hidden aria-label="Back"><?= sf_icon('left', 22) ?></button>
            <h2 class="sf-h3" id="sf-bag-title" data-bag-title>Your <?= $bag_word ?></h2>
            <button type="button" class="sf-iconbtn sf-iconbtn--bare" data-close aria-label="Close"><?= sf_icon('close', 22) ?></button>
        </div>

        <div class="sf-step" data-step="bag">
            <ul class="sf-baglist" data-bag-list></ul>
            <p class="sf-empty" data-bag-empty>Your <?= $bag_word ?> is empty.</p>
            <div class="sf-recent" data-recent hidden>
                <p class="sf-recent__title">Your recent orders</p>
                <ul class="sf-recent__list" data-recent-list></ul>
            </div>
            <div class="sf-dialog__foot" data-bag-foot>
                <p class="sf-total"><span>Total</span><strong data-bag-total>₦0</strong></p>
                <button type="button" class="sf-btn sf-btn--block" data-go="details">Check out</button>
            </div>
        </div>

        <form class="sf-step sf-form" data-step="details" hidden novalidate>
            <p class="sf-step__count" tabindex="-1">Step 1 of 3: your details</p>
            <input type="text" name="website" class="sf-hp" tabindex="-1" autocomplete="off" aria-hidden="true">
            <label class="sf-field">
                <span class="sf-field__label">Your name</span>
                <input type="text" name="customer_name" required maxlength="100" autocomplete="name">
            </label>
            <label class="sf-field">
                <span class="sf-field__label">Phone or WhatsApp</span>
                <input type="tel" name="customer_phone" required maxlength="20" autocomplete="tel" inputmode="tel" placeholder="0803 123 4567">
            </label>
            <label class="sf-field">
                <span class="sf-field__label">Email <span class="sf-field__opt">(optional)</span></span>
                <input type="email" name="customer_email" maxlength="100" autocomplete="email">
            </label>
            <p class="sf-form__error" role="alert" data-step-error></p>
            <button type="submit" class="sf-btn sf-btn--block">Continue</button>
        </form>

        <form class="sf-step sf-form" data-step="delivery" hidden novalidate>
            <p class="sf-step__count" tabindex="-1">Step 2 of 3: getting it to you</p>
            <?php if ($sections['delivery']['on'] && $sections['delivery']['text'] !== ''): ?>
                <div class="sf-note"><?= sf_paragraphs($sections['delivery']['text']) ?></div>
            <?php endif; ?>
            <fieldset class="sf-options">
                <legend>How do you want it?</legend>
                <label class="sf-option">
                    <input type="radio" name="fulfillment_type" value="pickup" checked>
                    <span><strong>I'll pick it up</strong><small>From the shop</small></span>
                </label>
                <label class="sf-option">
                    <input type="radio" name="fulfillment_type" value="delivery">
                    <span><strong>Deliver it to me</strong><small>To your address</small></span>
                </label>
            </fieldset>
            <label class="sf-field" data-address hidden>
                <span class="sf-field__label">Delivery address</span>
                <textarea name="delivery_address" rows="2" maxlength="500" autocomplete="street-address"></textarea>
            </label>
            <label class="sf-field">
                <span class="sf-field__label">Note for the shop <span class="sf-field__opt">(optional)</span></span>
                <input type="text" name="customer_note" maxlength="1000" autocomplete="off">
            </label>
            <p class="sf-form__error" role="alert" data-step-error></p>
            <button type="submit" class="sf-btn sf-btn--block">Continue</button>
        </form>

        <form class="sf-step sf-form" data-step="pay" hidden novalidate>
            <p class="sf-step__count" tabindex="-1">Step 3 of 3: payment</p>
            <p class="sf-total"><span>To pay</span><strong data-bag-total>₦0</strong></p>
            <?php if ($banks): ?>
                <fieldset class="sf-options">
                    <legend>Pay by <?= e(sf_pay_way($banks)) ?> to</legend>
                    <?php foreach ($banks as $i => $bank): ?>
                        <label class="sf-option sf-option--bank">
                            <input type="radio" name="bank_id" value="<?= (int)$bank['id'] ?>" data-method="<?= ($bank['kind'] ?? 'bank') === 'mobile_money' ? 'mobile_money' : 'bank_transfer' ?>" <?= $i === 0 ? 'checked' : '' ?>>
                            <span>
                                <strong><?= e($bank['bank_name']) ?></strong>
                                <span class="sf-option__number"><?= e($bank['account_number']) ?></span>
                                <small><?= e($bank['account_name']) ?></small>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <label class="sf-field">
                    <span class="sf-field__label">Proof of payment <span class="sf-field__opt">(optional)</span></span>
                    <span class="sf-field__hint">A screenshot of your transfer helps the shop confirm it quickly.</span>
                    <input type="file" name="payment_proof" accept="image/jpeg,image/png,image/webp">
                </label>
            <?php else: ?>
                <p class="sf-note">The shop will contact you about payment after you order.</p>
            <?php endif; ?>
            <p class="sf-form__error" role="alert" data-step-error></p>
            <button type="submit" class="sf-btn sf-btn--block" data-place>Place my order</button>
            <?php if ($wa !== ''): ?>
                <button type="button" class="sf-btn sf-btn--outline sf-btn--block" data-place-wa><?= sf_icon('whatsapp', 20) ?><span>Send my order on WhatsApp instead</span></button>
            <?php endif; ?>
        </form>

        <div class="sf-step sf-done" data-step="done" hidden>
            <span class="sf-done__icon" aria-hidden="true"><?= sf_icon('check', 30) ?></span>
            <h3 class="sf-h2" tabindex="-1" data-done-title>Order placed</h3>
            <p>Your order number is <strong data-done-number></strong>.</p>
            <p data-done-text></p>
            <a class="sf-btn sf-btn--block" data-done-wa hidden target="_blank" rel="noopener"><?= sf_icon('whatsapp', 20) ?><span>Open WhatsApp to send it</span></a>
            <a class="sf-btn sf-btn--outline sf-btn--block" data-done-track hidden><?= sf_icon('truck', 20) ?><span>Follow your order</span></a>
            <p class="sf-fineprint" data-done-keep hidden>We saved the link in your <?= $bag_word ?>, so you can come back to it.</p>
            <button type="button" class="sf-btn sf-btn--outline sf-btn--block" data-close>Done</button>
        </div>
    </div>
</dialog>

<?php if ($mode === 'live'): ?>
<dialog class="sf-dialog sf-dialog--status" id="sf-status" aria-labelledby="sf-status-title">
    <div class="sf-dialog__panel">
        <div class="sf-dialog__head">
            <h2 class="sf-h3" id="sf-status-title">Image for your Status</h2>
            <button type="button" class="sf-iconbtn sf-iconbtn--bare" data-close aria-label="Close"><?= sf_icon('close', 22) ?></button>
        </div>
        <div class="sf-step sf-status">
            <p class="sf-status__hint">Post it on your WhatsApp Status or Instagram story. The product's link is on the image.</p>
            <div class="sf-status__frame">
                <img data-status-img alt="" hidden>
                <p class="sf-status__wait" data-status-wait role="status">Making your image…</p>
            </div>
            <button type="button" class="sf-btn sf-btn--block" data-status-share hidden><?= sf_icon('share', 20) ?><span>Share the image</span></button>
            <a class="sf-btn sf-btn--outline sf-btn--block" data-status-save hidden><?= sf_icon('image', 20) ?><span>Save the image</span></a>
        </div>
    </div>
</dialog>
<?php endif; ?>
