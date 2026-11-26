<?php
/**
 * Nfelia — Terms, Privacy and Feedback dialogs, shared by the landing page
 * and the sign-up page. Native <dialog>: Escape and focus handling come
 * from the browser. Opened by any [data-dialog="<id>"] button (home.js).
 *
 * The Terms and Privacy dialogs show the same words as /terms and /privacy,
 * from views/site/legal/terms_body.php and privacy_body.php. Their ids
 * start with terms- and privacy- so they never clash with the page's own.
 */
?>
<dialog class="sheet" id="terms" aria-labelledby="terms-title">
    <div class="sheet-head">
        <h2 id="terms-title">Terms of Service</h2>
        <button type="button" class="sheet-close" data-close aria-label="Close terms" data-tip="Close terms" data-tip-below><?= nf_icon('close') ?></button>
    </div>
    <div class="sheet-body legal-sheet">
        <?php $legal_opts = ['h' => 'h3', 'sub' => 'h4', 'id' => 'terms-', 'meta' => true];
        require BASE_PATH . '/views/site/legal/terms_body.php'; ?>
    </div>
</dialog>

<dialog class="sheet" id="privacy" aria-labelledby="privacy-title">
    <div class="sheet-head">
        <h2 id="privacy-title">Privacy Policy</h2>
        <button type="button" class="sheet-close" data-close aria-label="Close privacy policy" data-tip="Close privacy policy" data-tip-below><?= nf_icon('close') ?></button>
    </div>
    <div class="sheet-body legal-sheet">
        <?php $legal_opts = ['h' => 'h3', 'sub' => 'h4', 'id' => 'privacy-', 'meta' => true];
        require BASE_PATH . '/views/site/legal/privacy_body.php';
        unset($legal_opts); ?>
    </div>
</dialog>

<dialog class="sheet" id="feedback" aria-labelledby="feedback-title">
    <div class="sheet-head">
        <h2 id="feedback-title">Send us feedback</h2>
        <button type="button" class="sheet-close" data-close aria-label="Close feedback form" data-tip="Close feedback form" data-tip-below><?= nf_icon('close') ?></button>
    </div>
    <form class="sheet-body" action="<?= APP_URL ?>/public-feedback" method="POST" data-validate>
        <?= csrf_field() ?>
        <p>Tell us what would make Nfelia better for your business. We read every message, and our team may reply on the number you give. See <a href="<?= e(site_url('privacy')) ?>">how we use your details</a>.</p>
        <div class="field">
            <label for="fb-business">Business name</label>
            <input id="fb-business" type="text" name="enterprise" required autocomplete="organization" data-error="Add your business name.">
        </div>
        <div class="field">
            <label for="fb-contact">WhatsApp or phone (optional)</label>
            <input id="fb-contact" type="tel" name="contact" autocomplete="tel" placeholder="+234">
        </div>
        <div class="field">
            <label for="fb-message">What should we add or fix?</label>
            <textarea id="fb-message" name="message" rows="4" required data-error="Write a short message so we know what to add or fix."></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Send feedback</button>
    </form>
</dialog>
