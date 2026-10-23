<?php
/**
 * Shea's first screen: the skin finder. "What's your skin like?" and a chip
 * per skin type; picking one puts together a routine from what the shop
 * makes, a product per step in the order they are used (cleanse, tone,
 * treat, moisturise, protect), each with a swap when another one fits too,
 * and one button that adds the whole routine (storefront.js, "The skin
 * finder"). Before a pick, the steps wait as empty places. The routines are
 * all in the page and CSS shows the one picked, so it works before the
 * script arrives. storefront_routines() decides what goes in them.
 *
 * From hero.php: $routines, $headline, $text_html, $badge, $media, $frame,
 * and the shell's $business, $catalogue, $ctx.
 */
$skins = $routines['skins'];
// The empty places before a pick: the steps the routines use, up to four
$used = [];
foreach ($routines['routines'] as $steps) {
    foreach ($steps as $step) {
        $used[$step['step']] = true;
    }
}
$ghosts = array_slice(array_values(array_filter(array_keys(SF_ROUTINE_STEPS), fn($s) => isset($used[$s]))), 0, 4);
?>
<div class="sf-wrap sf-finder__grid">
    <div class="sf-finder__intro">
        <?php if ($badge !== ''): ?><p class="sf-hero__badge"><?= e($badge) ?></p><?php endif; ?>
        <h1 class="sf-h1 sf-finder__title" id="sf-hero-title"><?= e($headline) ?></h1>
        <?= $text_html ?>
    </div>

    <div class="sf-finder" data-finder>
        <fieldset class="sf-finder__skins">
            <legend class="sf-finder__ask">What's your skin like?</legend>
            <div class="sf-finder__chips">
                <?php foreach ($skins as $i => $skin): ?>
                    <label class="sf-skin">
                        <input type="radio" name="sf-skin" value="<?= $i ?>" data-skin="<?= $i ?>">
                        <span><span class="sf-skin__icon"><?= sf_icon(sf_skin_icon($skin), 18) ?></span><?= e($skin) ?></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </fieldset>

        <div class="sf-routine sf-routine--empty" data-routine-empty>
            <p class="sf-routine__hint">Pick one and we put a routine together from our products.</p>
            <ol class="sf-routine__steps" aria-hidden="true">
                <?php foreach ($ghosts as $n => $step): ?>
                    <li class="sf-step sf-step--ghost">
                        <span class="sf-step__n"><?= $n + 1 ?></span>
                        <span class="sf-step__media"></span>
                        <span class="sf-step__body"><span class="sf-step__what"><?= e(SF_ROUTINE_STEPS[$step]['name']) ?></span><span class="sf-step__line"></span></span>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>

        <?php $r = 0; foreach ($routines['routines'] as $skin => $steps):
            $total = 0.0;
            $asks  = false;
        ?>
            <div class="sf-routine" data-routine="<?= $r ?>" aria-live="polite">
                <h2 class="sf-routine__title">Your routine for <?= e(mb_strtolower($skin)) ?></h2>
                <ol class="sf-routine__steps">
                    <?php foreach ($steps as $n => $step):
                        $item   = $step['items'][0];
                        $choice = sf_default_choice($item);
                        $total += $choice['price'];
                        $asks   = $asks || $item['price'] <= 0;
                        $about  = SF_ROUTINE_STEPS[$step['step']];
                        $others = count($step['items']) - 1;
                    ?>
                        <li class="sf-step" style="--n: <?= $n ?>" data-step="<?= e($step['step']) ?>" data-step-items="<?= e(implode(',', array_column($step['items'], 'id'))) ?>" data-step-at="0">
                            <span class="sf-step__n"><?= $n + 1 ?></span>
                            <?= sf_open_link($item, sf_item_media($item, '64px'), 'sf-step__media', true) ?>
                            <span class="sf-step__body">
                                <span class="sf-step__what"><?= e($about['name']) ?> <span>· <?= e($about['why']) ?></span></span>
                                <?= sf_open_link($item, e($item['name']), 'sf-step__name') ?>
                                <span class="sf-step__opts"><?= e($choice['label']) ?></span>
                            </span>
                            <span class="sf-step__side">
                                <span class="sf-step__price"><?= $item['price'] > 0 ? e(sf_money($choice['price'], $ctx['currency'])) : 'Ask' ?></span>
                                <?php if ($others): ?>
                                    <button type="button" class="sf-step__swap" data-swap aria-label="<?= e('Swap for another ' . mb_strtolower($about['name']) . ' product, ' . ($others + 1) . ' to choose from') ?>"><?= sf_icon('swap', 16) ?><span aria-hidden="true">Swap</span></button>
                                <?php endif; ?>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <button type="button" class="sf-btn sf-btn--block sf-routine__add" data-add-routine>
                    <span>Add the routine</span><span class="sf-routine__total" data-routine-total><?= e(sf_money($total, $ctx['currency'])) . ($asks ? ' +' : '') ?></span>
                </button>
                <p class="sf-routine__note">Or <a href="<?= e(sf_page_href('catalogue')) ?>">see everything</a> and choose your own.</p>
            </div>
        <?php $r++; endforeach; ?>
    </div>

    <?php if ($frame !== ''): ?>
        <div class="sf-hero__frame sf-finder__photo<?= $media === '' ? ' sf-finder__photo--drawn' : '' ?>"><?= $frame ?></div>
    <?php endif; ?>
</div>
