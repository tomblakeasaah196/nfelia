<?php
/**
 * An item in the catalogue: its photo, price and stock; adding stock,
 * taking some out or setting the count (the stock sheet at the bottom); its
 * stock history; and Edit, Hide, Clone and Remove. Phone first, in the
 * app's own cards, tags and sheets. Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $product, $movements, $move_summary,
 *   $margin_pct, $stock_value, $move_filter
 */
$currency  = $business['currency_symbol'] ?? '₦';
$pid       = (int)$product['id'];
$is_svc    = (int)$product['is_service'] === 1;
$qty       = (float)$product['stock_qty'];
$threshold = (float)$product['low_stock_threshold'];
$unit      = (string)($product['unit'] ?? '');
$sell      = (float)$product['sell_price'];
$cost      = (float)$product['cost_price'];
$csrf      = '<input type="hidden" name="csrf_token" value="' . e($_SESSION['csrf_token'] ?? '') . '">';

[$stock_tag, $stock_cls] = match (true) {
    $is_svc             => ['Service', 'is-line'],
    $qty <= 0           => ['Out of stock', 'is-owed'],
    $qty <= $threshold  => ['Running low', 'is-warn'],
    default             => ['In stock', 'is-in'],
};
// Each kind of stock change, in words, with its icon; the filter uses the same words
$kinds   = ['stock_in' => ['Added', 'plus'], 'stock_out' => ['Taken out', 'minus'], 'sale' => ['Sold', 'sales'],
            'return' => ['Returned', 'refund'], 'adjustment' => ['Count corrected', 'edit']];
$filters = ['' => 'All', 'stock_in' => 'Added', 'stock_out' => 'Taken out', 'sale' => 'Sales', 'return' => 'Returns', 'adjustment' => 'Corrections'];
$filter  = (string)($move_filter ?? '');
?>
<div class="nf-page nf-pview" x-data="{ adjType: 'stock_in' }">

<!-- What it is: photo, name, price and where its stock stands -->
<section class="nf-card nf-pv-hero" aria-labelledby="nf-pv-name">
    <?php if (!empty($product['image_path'])): ?>
        <img class="nf-pv-photo" src="<?= APP_URL ?>/<?= e($product['image_path']) ?>" alt="" decoding="async">
    <?php endif; ?>
    <div class="nf-pv-main">
        <h1 class="nf-pv-name" id="nf-pv-name"><?= e($product['name']) ?></h1>
        <?php if ($sell > 0): ?>
            <p class="nf-pv-price"><?= planner_money($sell, $currency) ?><?php if (!$is_svc && $unit !== ''): ?><small> per <?= e(unit_word($unit, 1)) ?></small><?php endif; ?></p>
        <?php else: ?>
            <p class="nf-pv-price is-none">No price yet<small>Your shop shows “Ask for the price”</small></p>
        <?php endif; ?>
        <p class="nf-pv-tags">
            <?php if (!$product['is_active']): ?><span class="nf-tag is-line">Hidden</span><?php elseif (!(int)$product['on_shop']): ?><span class="nf-tag is-line">Not on your shop</span><?php endif; ?>
            <span class="nf-tag <?= $stock_cls ?>"><?= e($stock_tag) ?></span>
            <?php if ($product['category']): ?><span class="nf-tag is-line"><?= e($product['category']) ?></span><?php endif; ?>
            <?php if ($product['sku']): ?><span class="nf-tag is-line" title="Item code"><?= e($product['sku']) ?></span><?php endif; ?>
        </p>
    </div>
    <?php if ($product['description']): ?>
        <p class="nf-pv-desc"><?= e($product['description']) ?></p>
    <?php endif; ?>
    <?php if (!$product['is_active']): ?>
        <p class="nf-help">Hidden: it isn't on your shop, and it doesn't come up when you add items to an invoice, quote or order. Its stock and history stay.</p>
    <?php endif; ?>
</section>

<?php if ($product['is_active']): ?>
<!-- Show on my shop, in one tap (views/products/shop_switch.php) -->
<form class="nf-card nf-pv-shop" method="POST" action="<?= APP_URL ?>/products/toggle-shop/<?= $pid ?>">
    <?= $csrf ?><input type="hidden" name="on" value="<?= (int)$product['on_shop'] ? '0' : '1' ?>">
    <label class="nf-switch"><input type="checkbox" role="switch" onchange="nfShopSwitch(this)"<?= (int)$product['on_shop'] ? ' checked' : '' ?>>
        <span><b>Show on my shop</b><small>Off: it stays here and on invoices, quotes and orders, but your shop doesn't show it.</small></span></label>
</form>
<?php require BASE_PATH . '/views/products/shop_switch.php'; ?>
<?php endif; ?>

<!-- Edit, Hide, Clone, Remove. Their forms sit below, so the tiles stay one row. -->
<div class="nf-do">
    <a href="<?= APP_URL ?>/products/edit/<?= $pid ?>"><?= nav_icon('edit') ?><span>Edit</span></a>
    <?php /* Hidden: off the shop and out of the item pickers (toggle_status) */ ?>
    <button type="submit" form="nf-pv-toggle"><?= nav_icon('eye') ?><span><?= $product['is_active'] ? 'Hide' : 'Show again' ?></span></button>
    <button type="submit" form="nf-pv-clone"><?= nav_icon('copy') ?><span>Clone</span></button>
    <button type="submit" form="nf-pv-remove" class="is-danger"><?= nav_icon('trash') ?><span>Remove</span></button>
</div>
<form id="nf-pv-toggle" method="POST" action="<?= APP_URL ?>/products/toggle-status/<?= $pid ?>" hidden><?= $csrf ?></form>
<form id="nf-pv-clone" method="POST" action="<?= APP_URL ?>/products/duplicate/<?= $pid ?>" hidden><?= $csrf ?></form>
<form id="nf-pv-remove" method="POST" action="<?= APP_URL ?>/products/delete/<?= $pid ?>" hidden
      onsubmit="return confirm('Remove <?= e(addslashes($product['name'])) ?> from your catalogue? Support can bring it back.');"><?= $csrf ?></form>

<?php if (!$is_svc): ?>
<!-- Stock: how many, and the two things done most -->
<section class="nf-money nf-pv-stock" aria-labelledby="nf-pv-stock-h">
    <p class="nf-money-k" id="nf-pv-stock-h">In stock</p>
    <p class="nf-money-v<?= $qty <= 0 ? ' is-owed' : '' ?>"><?= e(stock_count($qty, $unit)) ?></p>
    <p class="nf-money-sub">
        <?php if ($qty > 0 && $cost > 0): ?>Worth <?= planner_money((float)$stock_value, $currency) ?> at cost. <?php endif; ?>
        You get a reminder to restock at <?= e(stock_count($threshold, $unit)) ?>.
    </p>
    <div class="nf-pv-stock-do">
        <button type="button" class="nf-btn nf-btn-primary" data-open="nf-stock" data-stock-add @click="adjType = 'stock_in'"><?= nav_icon('plus') ?>Add stock</button>
        <button type="button" class="nf-btn nf-btn-ghost" data-open="nf-stock" data-stock-out @click="adjType = 'stock_out'"><?= nav_icon('minus') ?>Take out</button>
    </div>
</section>
<?php endif; ?>

<!-- Price -->
<section class="nf-card" aria-labelledby="nf-pv-price-h">
    <div class="nf-card-h"><h2 id="nf-pv-price-h">Price</h2></div>
    <dl class="nf-kv">
        <div><dt>Selling price</dt><dd><?= $sell > 0 ? planner_money($sell, $currency) : 'Not set' ?></dd></div>
        <div><dt>Cost price</dt><dd><?= $cost > 0 ? planner_money($cost, $currency) : 'Not set' ?></dd></div>
        <?php /* Neutral until both prices are in; red only when it sells below its cost */ ?>
        <div><dt>Margin</dt><dd class="<?= $sell > 0 && $cost > 0 && $sell < $cost ? 'is-owed' : '' ?>"><?= $sell > 0 && $cost > 0 ? e($margin_pct . '%') : '—' ?></dd></div>
    </dl>
</section>

<?php if (!$is_svc): ?>
<!-- Stock history: the last 50 changes, newest first -->
<section class="nf-card is-flush nf-pv-history" aria-labelledby="nf-pv-hist-h">
    <div class="nf-card-h"><div><h2 id="nf-pv-hist-h">Stock history</h2><p>The last 50 changes, newest first</p></div></div>
    <nav class="nf-chips" aria-label="Show">
        <?php foreach ($filters as $key => $label): ?>
            <a href="<?= APP_URL ?>/products/view/<?= $pid ?><?= $key !== '' ? '?move_type=' . $key : '' ?>"<?= $filter === $key ? ' aria-current="true"' : '' ?>><?= e($label) ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if (!empty($movements)): ?>
        <div class="nf-items">
            <?php foreach ($movements as $mv):
                [$kind, $icon] = $kinds[$mv['type']] ?? [ucfirst(str_replace('_', ' ', (string)$mv['type'])), 'stock'];
                $up    = (float)$mv['stock_after'] >= (float)$mv['stock_before'];
                $moved = rtrim(rtrim(number_format((float)$mv['quantity'], 2), '0'), '.');
                $line  = implode(' · ', array_filter([fmt_date($mv['movement_date'], 'j M Y'), trim((string)($mv['note'] ?? '')), $mv['user_name'] ?? '']));
            ?>
                <div class="nf-item">
                    <span class="nf-tile-ic" aria-hidden="true"><?= nav_icon($icon) ?></span>
                    <span class="nf-item-main"><b><?= e($kind) ?></b><small><?= e($line) ?></small></span>
                    <span class="nf-item-end">
                        <b class="<?= $up ? 'is-in' : '' ?>"><?= ($up ? '+' : '−') . e($moved) ?></b>
                        <small><?= e(stock_count((float)$mv['stock_after'], $unit)) ?> after</small>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <p class="nf-card-note"><?= $filter === '' ? 'No changes yet. Adding stock, sales and corrections show here.' : 'None of these yet.' ?></p>
    <?php endif; ?>

    <?php if (!empty($move_summary)): ?>
        <p class="nf-card-note nf-pv-totals"><?= e(implode(' · ', array_map(
            fn($type, $ms) => ($filters[$type] ?? ucfirst((string)$type)) . ': ' . stock_count((float)$ms['total_qty'], $unit),
            array_keys($move_summary), $move_summary
        ))) ?></p>
    <?php endif; ?>
</section>

<!-- Add stock, take some out, or set the count -->
<dialog class="nf-sheet" id="nf-stock" aria-labelledby="nf-stock-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <h2 id="nf-stock-h" x-text="adjType === 'adjustment' ? 'Set the count' : (adjType === 'stock_out' ? 'Take out stock' : 'Add stock')">Add stock</h2>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>
    <form method="POST" action="<?= APP_URL ?>/products/adjust-stock/<?= $pid ?>" class="nf-form" data-once><?= $csrf ?>
        <div class="nf-seg is-3" role="radiogroup" aria-label="What happened">
            <label><input type="radio" name="adj_type" value="stock_in" x-model="adjType"><span class="opt"><?= nav_icon('plus') ?>Add stock</span></label>
            <label><input type="radio" name="adj_type" value="stock_out" x-model="adjType"><span class="opt"><?= nav_icon('minus') ?>Take out</span></label>
            <label><input type="radio" name="adj_type" value="adjustment" x-model="adjType"><span class="opt"><?= nav_icon('edit') ?>Set the count</span></label>
        </div>
        <label for="nf-stock-qty">
            <span x-text="adjType === 'adjustment' ? 'How many you have now' : (adjType === 'stock_out' ? 'How many to take out' : 'How many came in')">How many came in</span>
            <small>You have <?= e(stock_count($qty, $unit)) ?> now</small>
            <input id="nf-stock-qty" type="number" name="quantity" step="0.01" min="0" inputmode="decimal" required placeholder="0">
        </label>
        <?php /* Sent with every kind, as before; asked only when stock comes in */ ?>
        <label x-show="adjType === 'stock_in'">Cost each <small>What you paid<?= $unit !== '' ? ' per ' . e(unit_word($unit, 1)) : '' ?>. Optional.</small>
            <span class="nf-amount"><span><?= e($currency) ?></span><input id="nf-stock-cost" type="number" name="unit_cost" step="0.01" min="0" inputmode="decimal" value="<?= e((string)$product['cost_price']) ?>"></span>
        </label>
        <label for="nf-stock-note">Note <small>Optional. For example: new stock from the supplier.</small>
            <input id="nf-stock-note" type="text" name="note" maxlength="500" autocomplete="off">
        </label>
        <button type="submit" id="nf-stock-save" class="nf-btn nf-btn-primary nf-sheet-go"
                x-text="adjType === 'adjustment' ? 'Save the count' : (adjType === 'stock_out' ? 'Take out' : 'Add stock')">Add stock</button>
    </form>
</dialog>
<?php endif; ?>

<p class="nf-help nf-pv-meta">Added <?= fmt_date($product['created_at'], 'j M Y') ?> · Last changed <?= fmt_date($product['updated_at'], 'j M Y, H:i') ?></p>
</div>
