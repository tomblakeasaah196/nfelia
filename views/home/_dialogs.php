<?php
/**
 * Nfelia — Terms, Privacy and Feedback dialogs, shared by the landing page
 * and the sign-up page. Native <dialog>: Escape and focus handling come
 * from the browser. Opened by any [data-dialog="<id>"] button (home.js).
 */
?>
<dialog class="sheet" id="terms" aria-labelledby="terms-title">
    <div class="sheet-head">
        <h2 id="terms-title">Terms of Service</h2>
        <button type="button" class="sheet-close" data-close aria-label="Close terms" data-tip="Close terms" data-tip-below><?= nf_icon('close') ?></button>
    </div>
    <div class="sheet-body">
        <p>Effective date: May 2026. Version 1.0</p>
        <h3>1. The agreement</h3>
        <p>By initializing a workspace on Nfelia, you agree to these structural terms. Nfelia provides the digital architecture, document generation pipeline, and tracking modules for your micro-business. We provide the packaging; you provide the operations.</p>
        <h3>2. Subscription</h3>
        <p>The Nfelia MVP standard protocol is capped at ₦10,000 per month. Billing is recurring. In the event of payment failure, your workspace will enter a 3-day grace period, after which access to generation modules will be restricted until arrears are cleared. Your historical data will remain intact.</p>
        <h3>3. Data ownership</h3>
        <p>You retain absolute ownership of all inventory, client CRM, and financial data inputted into your workspace. Nfelia acts solely as the processor and custodian of this data to generate your commercial documents.</p>
        <h3>4. Liability</h3>
        <p>While Nfelia is built on robust, shared hosting infrastructure, we do not assume liability for business losses, missed deliveries, or incorrect financial inputs resulting from user error or temporary server unavailability. You are responsible for verifying all document amounts before generating URL tokens.</p>
    </div>
</dialog>

<dialog class="sheet" id="privacy" aria-labelledby="privacy-title">
    <div class="sheet-head">
        <h2 id="privacy-title">Privacy Policy</h2>
        <button type="button" class="sheet-close" data-close aria-label="Close privacy policy" data-tip="Close privacy policy" data-tip-below><?= nf_icon('close') ?></button>
    </div>
    <div class="sheet-body">
        <p>Effective date: May 2026. Version 1.0</p>
        <h3>1. What we collect</h3>
        <p>To provide structural packaging for your business, Nfelia collects authentication data (email, password), operational data (inventory limits, pricing), and client CRM data (names, phone numbers, delivery addresses) that you manually input into the platform.</p>
        <h3>2. How we use it</h3>
        <p>Your data is strictly utilized to generate operational documents (Quotations, Invoices, Delivery Notes) and to populate your dashboard metrics. We do not mine your commercial data, nor do we access your client contact list for external communication.</p>
        <h3>3. Storage</h3>
        <p>Your workspace is hosted securely on our designated infrastructure. Database entries are isolated via unique business identifiers. URL tokens generated for your clients are public-facing but cryptographically randomized to prevent unauthorized sequential access.</p>
        <h3>4. We never sell your data</h3>
        <p>Nfelia will never sell, lease, or broker your financial flow data, client information, or operational metrics to third-party marketing or financial entities.</p>
    </div>
</dialog>

<dialog class="sheet" id="feedback" aria-labelledby="feedback-title">
    <div class="sheet-head">
        <h2 id="feedback-title">Send us feedback</h2>
        <button type="button" class="sheet-close" data-close aria-label="Close feedback form" data-tip="Close feedback form" data-tip-below><?= nf_icon('close') ?></button>
    </div>
    <form class="sheet-body" action="<?= APP_URL ?>/public-feedback" method="POST" data-validate>
        <?= csrf_field() ?>
        <p>Tell us what would make Nfelia better for your business. We read every message.</p>
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
