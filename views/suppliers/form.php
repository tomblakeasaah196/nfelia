<?php
/**
 * Nfelia — Supplier Create / Edit Form
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $supplier (empty or existing), $editing
 */
?>

<!-- Breadcrumb -->
<div class="flex items-center gap-3 mb-6">
    <a href="<?= APP_URL ?>/suppliers" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight">
        <?= $editing ? 'Edit — ' . e($supplier['full_name']) : 'New Supplier' ?>
    </h1>
</div>

<form method="POST" action="<?= APP_URL ?>/suppliers/<?= $editing ? 'edit/' . $supplier['id'] : 'create' ?>" class="max-w-2xl">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm space-y-5">

        <h2 class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Supplier Information</h2>
        <p class="text-[10px] text-brand-plum/40 -mt-3">Phone or email is required</p>

        <!-- Full Name -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Supplier / Contact Name <span class="text-red-500">*</span></label>
            <input type="text" name="full_name" value="<?= e($supplier['full_name'] ?? '') ?>" required
                   class="w-full px-3 py-2.5 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                   placeholder="e.g. Alhaji Musa">
        </div>

        <!-- Business Name -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Business Name</label>
            <input type="text" name="business_name" value="<?= e($supplier['business_name'] ?? '') ?>"
                   class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                   placeholder="e.g. Musa General Supplies Ltd">
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <!-- Phone -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Phone Number</label>
                <input type="tel" name="phone" maxlength="50" value="<?= e($supplier['phone'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       placeholder="08012345678">
            </div>

            <!-- WhatsApp -->
            <div>
                <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">WhatsApp Number</label>
                <input type="tel" name="whatsapp" maxlength="50" value="<?= e($supplier['whatsapp'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       placeholder="Same as phone if blank">
            </div>
        </div>

        <!-- Email -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Email</label>
            <input type="email" name="email" value="<?= e($supplier['email'] ?? '') ?>"
                   class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                   placeholder="supplier@email.com">
        </div>

        <!-- Address -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Address</label>
            <textarea name="address" rows="2"
                      class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum resize-none"
                      placeholder="Shop / warehouse address..."><?= e($supplier['address'] ?? '') ?></textarea>
        </div>

        <!-- Notes -->
        <div>
            <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1">Notes</label>
            <textarea name="notes" rows="3"
                      class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum resize-none"
                      placeholder="What they supply, payment terms, etc..."><?= e($supplier['notes'] ?? '') ?></textarea>
        </div>
    </div>

    <!-- Actions -->
    <div class="flex items-center gap-3 mt-6">
        <button type="submit" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
            <?= $editing ? 'Update Supplier' : 'Add Supplier' ?>
        </button>
        <a href="<?= APP_URL ?>/suppliers" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Cancel</a>
    </div>
</form>