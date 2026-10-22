<?php
/**
 * Nfelia — Customer Create / Edit Form
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $customer (empty or existing), $editing
 */
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-3 mb-6">
    <a href="<?= APP_URL ?>/customers" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight">
        <?= $editing ? 'Edit — ' . e($customer['full_name']) : 'New customer' ?>
    </h1>
</div>

<form method="POST" action="<?= APP_URL ?>/customers/<?= $editing ? 'edit/' . $customer['id'] : 'create' ?>" class="max-w-2xl">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm space-y-5">

        <h2 class="font-heading font-bold text-base text-brand-plum">Contact details</h2>
        <p class="text-sm text-brand-plum/60 -mt-3">Add a phone number or an email.</p>
        <?php if (!$editing): ?>
            <!-- Shown by assets/js/phone.js where the phone can share its contacts (Android Chrome). -->
            <button type="button" data-pick-contact hidden class="w-full sm:w-auto inline-flex items-center justify-center gap-2 min-h-[44px] px-4 rounded-lg border border-brand-sand bg-brand-cream text-sm font-semibold text-brand-plum hover:border-accent">
                <?= nav_icon('clients') ?>Pick from phone contacts
            </button>
        <?php endif; ?>

        <!-- Full Name -->
        <div>
            <label class="text-sm font-semibold text-brand-plum block mb-1">Full name <span class="text-red-500">*</span></label>
            <input type="text" name="full_name" value="<?= e($customer['full_name'] ?? '') ?>" required
                   class="w-full px-3 py-2.5 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum"
                   placeholder="e.g. Emeka Obiora">
        </div>

        <!-- Business Name -->
        <div>
            <label class="text-sm font-semibold text-brand-plum block mb-1">Business name</label>
            <input type="text" name="business_name" value="<?= e($customer['business_name'] ?? '') ?>"
                   class="w-full px-3 py-2.5 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum"
                   placeholder="e.g. Emeka & Sons Enterprises">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Phone -->
            <div>
                <label class="text-sm font-semibold text-brand-plum block mb-1">Phone number</label>
                <input type="tel" name="phone" maxlength="20" value="<?= e($customer['phone'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum"
                       placeholder="08012345678">
            </div>

            <!-- WhatsApp -->
            <div>
                <label class="text-sm font-semibold text-brand-plum block mb-1">WhatsApp number</label>
                <input type="tel" name="whatsapp" maxlength="20" value="<?= e($customer['whatsapp'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum"
                       placeholder="Same as phone if blank">
            </div>
        </div>

        <!-- Email -->
        <div>
            <label class="text-sm font-semibold text-brand-plum block mb-1">Email</label>
            <input type="email" name="email" value="<?= e($customer['email'] ?? '') ?>"
                   class="w-full px-3 py-2.5 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum"
                   placeholder="customer@email.com">
        </div>

        <!-- Address -->
        <div>
            <label class="text-sm font-semibold text-brand-plum block mb-1">Address</label>
            <textarea name="address" rows="2"
                      class="w-full px-3 py-2.5 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum resize-none"
                      placeholder="Street address, city, state..."><?= e($customer['address'] ?? '') ?></textarea>
        </div>

        <!-- Notes -->
        <div>
            <label class="text-sm font-semibold text-brand-plum block mb-1">Notes</label>
            <textarea name="notes" rows="3"
                      class="w-full px-3 py-2.5 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum resize-none"
                      placeholder="Internal notes about this customer..."><?= e($customer['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="btn-primary text-white min-h-[48px] px-6 py-3 rounded-xl text-sm font-semibold shadow-sm hover:shadow-md transition-all">
            <?= $editing ? 'Save changes' : 'Add customer' ?>
        </button>
        <a href="<?= APP_URL ?>/customers" class="min-h-[44px] inline-flex items-center px-2 text-sm font-semibold text-brand-plum/60 hover:text-accent-text">Cancel</a>
    </div>
</form>