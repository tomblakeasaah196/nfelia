<?php
/**
 * "When will the customer get it?" — Immediate, or a date. Required on our
 * side: it puts the promise on Tasks & Calendar with a countdown. The
 * customer only sees it when "Show on the customer's invoice" is ticked.
 *
 * Expects $df = [
 *   'mode'   => 'immediate' | 'date' | '',
 *   'date'   => 'Y-m-d' | '',
 *   'show'   => bool,
 *   'handed' => bool,   offer "The customer has it already" under Immediate
 *   'id'     => string, unique prefix for element ids on the page
 *   'legend_hidden' => bool, when the card around it already asks the question
 * ]
 * Posts: deliver_mode, deliver_by, deliver_show, delivered_now.
 */
$df_mode = in_array($df['mode'] ?? '', ['immediate', 'date'], true) ? $df['mode'] : '';
$df_id   = preg_replace('/[^a-z0-9-]/', '', $df['id'] ?? 'df');
$df_date = $df['date'] ?? '';
?>
<div x-data="{ mode: '<?= $df_mode ?>' }" class="space-y-3">
    <fieldset>
        <legend class="<?= !empty($df['legend_hidden']) ? 'sr-only' : 'text-sm font-semibold text-brand-plum mb-2' ?>">When will the customer get it?</legend>
        <div class="flex flex-wrap gap-2">
            <label class="h-11 px-4 inline-flex items-center rounded-full border text-sm font-semibold cursor-pointer focus-within:ring-2 focus-within:ring-brand-burgundy"
                   :class="mode === 'immediate' ? 'border-brand-burgundy bg-[#F8EEF3] text-brand-burgundy' : 'border-brand-sand text-brand-plum/70'">
                <input type="radio" name="deliver_mode" value="immediate" x-model="mode" class="sr-only" required <?= $df_mode === 'immediate' ? 'checked' : '' ?>>
                Immediate
            </label>
            <label class="h-11 px-4 inline-flex items-center rounded-full border text-sm font-semibold cursor-pointer focus-within:ring-2 focus-within:ring-brand-burgundy"
                   :class="mode === 'date' ? 'border-brand-burgundy bg-[#F8EEF3] text-brand-burgundy' : 'border-brand-sand text-brand-plum/70'">
                <input type="radio" name="deliver_mode" value="date" x-model="mode" class="sr-only" <?= $df_mode === 'date' ? 'checked' : '' ?>>
                Pick a date
            </label>
        </div>
    </fieldset>

    <div x-show="mode === 'date'" <?= $df_mode === 'date' ? '' : 'style="display:none"' ?>>
        <label for="<?= $df_id ?>-date" class="block text-sm font-semibold text-brand-plum mb-1.5">Delivery date</label>
        <input id="<?= $df_id ?>-date" type="date" name="deliver_by" value="<?= e($df_date) ?>" :required="mode === 'date'"
               class="w-full h-11 px-3 text-sm bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
    </div>

    <?php if (!empty($df['handed'])): ?>
    <label x-show="mode === 'immediate'" <?= $df_mode === 'immediate' ? '' : 'style="display:none"' ?> class="flex items-center gap-2 min-h-[44px] text-sm text-brand-plum cursor-pointer">
        <input type="checkbox" name="delivered_now" value="1" class="w-5 h-5 accent-[#8B004A]">
        The customer has it already
    </label>
    <?php endif; ?>

    <label class="flex items-center gap-2 min-h-[44px] text-sm text-brand-plum cursor-pointer">
        <input type="checkbox" name="deliver_show" value="1" class="w-5 h-5 accent-[#8B004A]" <?= !empty($df['show']) ? 'checked' : '' ?>>
        Show the delivery date on the customer's invoice
    </label>
    <p class="text-xs text-brand-plum/60">It goes on your Tasks &amp; Calendar with a countdown until you mark it delivered.</p>
</div>
