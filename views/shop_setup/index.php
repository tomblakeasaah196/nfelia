<?php
/**
 * Nfelia — Shop setup (renders inside views/layouts/app.php)
 *
 * The shop's link and its look on one screen. Owners land here until both are
 * chosen; afterwards looks are usually changed in Shop Studio, where the link
 * is changed too. The name field is assets/js/shop-link.js. The server checks
 * everything again on save.
 *
 * Each look has a "See it full size" button: a dialog shows the owner's own
 * shop in that look (ShopSetupController::preview).
 *
 * Available from ShopSetupController::index():
 *   $live_name, $pending_name, $was_rejected, $first_setup, $suggestions,
 *   $prefill, $current_look, $errors
 */
$base_host  = shop_base_host();
$name_error = $errors['shop_name'] ?? '';
$json_flags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;

// Each look's own font, cut down to the two letters on its thumbnail. These
// three only come in one weight.
$regular_only = ['Agbalumo', 'Gloock', 'Young Serif'];
$look_fonts   = [];
foreach (STOREFRONT_LOOKS as $look) {
    $look_fonts[] = 'family=' . str_replace(' ', '+', $look['font'])
                  . (in_array($look['font'], $regular_only, true) ? '' : ':wght@700');
}
$look_fonts_url = 'https://fonts.googleapis.com/css2?' . implode('&', array_unique($look_fonts)) . '&text=Aa&display=swap';
?>
<link href="<?= e($look_fonts_url) ?>" rel="stylesheet">

<div class="max-w-3xl mx-auto text-[#1F0A14]">

    <div class="mb-6">
        <h1 class="font-heading font-bold text-2xl tracking-tight"><?= e($title) ?></h1>
        <p class="mt-2 text-base text-[#5B4650]">
            <?= $first_setup
                ? 'Choose the address people will use to find your shop, and how your shop looks. You need both to carry on.'
                : 'Choose how your shop looks. Your link is changed from Shop Studio.' ?>
        </p>
    </div>

    <form method="POST" action="<?= APP_URL ?>/shop-setup/save" class="space-y-6"
          <?php if (!$live_name): ?>
          x-data="shopNameField(<?= e(json_encode([
              'base'        => $base_host,
              'checkUrl'    => APP_URL . '/shop-setup/check',
              'name'        => $prefill,
              'state'       => $name_error ? 'invalid' : '',
              'message'     => $name_error,
              'suggestions' => $suggestions,
          ], $json_flags)) ?>)"
          <?php endif; ?>>
        <?= csrf_field() ?>

        <!-- Link -->
        <section class="bg-white border border-brand-sand rounded-xl p-5" aria-labelledby="shop-link-heading">
            <h2 id="shop-link-heading" class="font-heading font-bold text-lg">Your shop link</h2>

            <?php if ($live_name): ?>
                <p class="mt-2 text-base">
                    <a href="<?= e(shop_url($live_name)) ?>" target="_blank" rel="noopener" class="font-semibold underline underline-offset-4 break-all"><?= e($live_name . '.' . $base_host) ?></a>
                </p>
                <?php if ($pending_name): ?>
                    <p class="mt-3 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        We're checking <strong><?= e($pending_name . '.' . $base_host) ?></strong>. Your current link keeps working until then.
                    </p>
                <?php endif; ?>
            <?php else: ?>
                <p class="mt-1 text-sm text-[#5B4650]">This is the address you share on WhatsApp, Instagram and anywhere else.</p>

                <?php if ($pending_name): ?>
                    <p class="mt-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        We're checking <strong><?= e($pending_name . '.' . $base_host) ?></strong>. Your shop goes live as soon as it is approved.
                        If you'd rather not wait, choose another name below.
                    </p>
                <?php elseif ($was_rejected): ?>
                    <p class="mt-4 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        We couldn't approve the last name you asked for. Please choose another one.
                    </p>
                <?php endif; ?>

                <label for="shop-name" class="block mt-4 text-sm font-semibold">Shop name</label>
                <div class="mt-1 flex items-stretch rounded-xl border-2 bg-white focus-within:ring-2 focus-within:ring-accent/30"
                     :class="borderClass">
                    <input id="shop-name" name="shop_name" type="text" required
                           autocomplete="off" autocapitalize="none" spellcheck="false" maxlength="40"
                           value="<?= e($prefill) ?>" x-model="name" @input.debounce.350ms="check()"
                           aria-describedby="shop-name-status shop-name-rules"
                           class="flex-1 min-w-0 h-12 px-4 text-base bg-transparent rounded-l-xl focus:outline-none">
                    <span class="flex items-center pr-4 text-base text-[#5B4650] select-none">.<?= e($base_host) ?></span>
                </div>
                <p id="shop-name-status" role="status" aria-live="polite" class="mt-2 text-sm font-medium min-h-[1.25rem]"
                   :class="statusClass" x-text="message"><?= e($name_error) ?></p>

                <div x-show="otherSuggestions.length" style="display:none;" class="mt-2">
                    <p class="text-sm text-[#5B4650]">Free names you could use:</p>
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="s in otherSuggestions" :key="s">
                            <button type="button" @click="pick(s)"
                                    class="h-11 px-4 rounded-xl border border-brand-sand bg-white text-sm font-semibold hover:border-accent"
                                    x-text="s"></button>
                        </template>
                    </div>
                </div>

                <p id="shop-name-rules" class="mt-4 text-sm text-[#5B4650]">
                    Use <?= SHOP_NAME_MIN ?> to <?= SHOP_NAME_MAX ?> letters, numbers or hyphens. No spaces.
                    You can change it once later.
                </p>
                <p class="mt-3 text-sm text-[#5B4650]">
                    Your shop's address:
                    <strong class="font-semibold text-[#1F0A14] break-words" x-text="'https://' + previewHost">https://<?= e(($prefill ?: 'yourshop') . '.' . $base_host) ?></strong>
                </p>
            <?php endif; ?>
        </section>

        <!-- Look -->
        <section class="bg-white border border-brand-sand rounded-xl p-5">
            <fieldset>
                <legend class="font-heading font-bold text-lg">Your look</legend>
                <p class="mt-1 text-sm text-[#5B4650]">Pick the style that suits what you sell. See any of them full size with your own products. You can change it later.</p>
                <?php if (!empty($errors['look'])): ?>
                    <p class="mt-2 text-sm font-medium text-[#B42318]"><?= e($errors['look']) ?></p>
                <?php endif; ?>

                <div class="mt-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
                    <?php foreach (STOREFRONT_LOOKS as $key => $look):
                        $button_shape = $look['shape'] === '50%' ? '9999px' : $look['shape'];
                    ?>
                    <div class="flex flex-col gap-1">
                    <label class="relative block cursor-pointer rounded-xl border border-brand-sand bg-white p-2 hover:border-accent/50
                                  has-[:checked]:border-accent has-[:checked]:ring-2 has-[:checked]:ring-accent
                                  has-[:focus-visible]:outline has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-accent">
                        <input type="radio" name="look" value="<?= e($key) ?>" class="peer sr-only" required <?= $key === $current_look ? 'checked' : '' ?>>
                        <span class="absolute top-3 right-3 hidden peer-checked:flex h-6 w-6 items-center justify-center rounded-full bg-accent text-on-accent" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        </span>

                        <!-- Thumbnail: the look's ground, ink, accent, font and shape -->
                        <span class="flex flex-col gap-2 rounded-lg overflow-hidden border border-black/5 p-3" aria-hidden="true"
                              style="background: <?= e($look['ground']) ?>; color: <?= e($look['ink']) ?>;">
                            <span class="text-2xl leading-none" style="font-family: '<?= e($look['font']) ?>', system-ui, sans-serif; font-weight: <?= in_array($look['font'], $regular_only, true) ? 400 : 700 ?>;">Aa</span>
                            <span class="flex gap-1.5">
                                <?php for ($i = 0; $i < 3; $i++): ?>
                                <span class="flex-1 h-8" style="background: <?= e($look['ink']) ?>; opacity: .14; border-radius: <?= e($look['shape']) ?>;"></span>
                                <?php endfor; ?>
                            </span>
                            <span class="block h-3.5 w-3/5" style="background: <?= e($look['accent']) ?>; border-radius: <?= e($button_shape) ?>;"></span>
                        </span>

                        <span class="block mt-2 px-1 text-sm font-semibold"><?= e($look['name']) ?></span>
                        <span class="block px-1 pb-1 text-xs text-[#5B4650] leading-snug"><?= e($look['for']) ?></span>
                    </label>
                    <button type="button" class="min-h-[44px] px-2 rounded-xl text-sm font-semibold text-accent-text underline underline-offset-4 hover:text-[#1F0A14]"
                            data-look-preview="<?= e($key) ?>" data-look-name="<?= e($look['name']) ?>">See it full size<span class="sr-only">: <?= e($look['name']) ?></span></button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </fieldset>
        </section>

        <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-end gap-3">
            <?php if (!$first_setup): ?>
                <a href="<?= APP_URL ?>/landing-page" class="h-12 px-5 inline-flex items-center justify-center rounded-xl text-base font-semibold text-[#5B4650] hover:text-[#1F0A14]">Back to Shop Studio</a>
            <?php endif; ?>
            <button type="submit" <?= $live_name ? '' : ':disabled="!canSubmit"' ?>
                    class="h-12 px-6 rounded-xl bg-accent text-on-accent text-base font-bold hover:opacity-90 disabled:opacity-50 disabled:cursor-not-allowed">
                Save my shop
            </button>
        </div>
    </form>
</div>

<!-- One look, full size, with the owner's own shop -->
<dialog id="look-preview" aria-labelledby="look-preview-title"
        class="m-0 p-0 w-full h-full max-w-none max-h-none bg-accent-soft backdrop:bg-black/50 sm:m-auto sm:w-[min(980px,94vw)] sm:h-[90vh] sm:rounded-2xl">
    <div class="flex flex-col h-full">
        <div class="flex items-center gap-2 px-3 py-2 bg-white border-b border-brand-sand">
            <h2 id="look-preview-title" class="flex-1 min-w-0 font-heading font-bold text-base truncate px-1">Look</h2>
            <button type="button" data-look-use class="h-11 px-4 rounded-xl bg-accent text-on-accent text-sm font-bold">Use this look</button>
            <button type="button" data-look-close class="h-11 w-11 inline-flex items-center justify-center rounded-xl text-[#1F0A14] hover:bg-accent-soft" aria-label="Close the preview">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </div>
        <iframe class="flex-1 w-full bg-white" title="Your shop in this look" data-look-frame></iframe>
    </div>
</dialog>
<script>
(function () {
    var dialog = document.getElementById('look-preview');
    if (!dialog || !dialog.showModal) { return; }
    var frame = dialog.querySelector('[data-look-frame]');
    var current = null;
    document.querySelectorAll('[data-look-preview]').forEach(function (button) {
        button.addEventListener('click', function () {
            current = button.dataset.lookPreview;
            dialog.querySelector('#look-preview-title').textContent = button.dataset.lookName + ' with your shop';
            frame.src = <?= json_encode(APP_URL . '/shop-setup/preview?look=', JSON_UNESCAPED_SLASHES) ?> + encodeURIComponent(current);
            dialog.showModal();
        });
    });
    dialog.querySelector('[data-look-close]').addEventListener('click', function () { dialog.close(); });
    dialog.querySelector('[data-look-use]').addEventListener('click', function () {
        var radio = document.querySelector('input[name="look"][value="' + current + '"]');
        if (radio) { radio.checked = true; radio.focus(); }
        dialog.close();
    });
    dialog.addEventListener('close', function () { frame.src = 'about:blank'; });
})();
</script>
<script src="<?= APP_URL ?>/assets/js/shop-link.js?v=2"></script>
