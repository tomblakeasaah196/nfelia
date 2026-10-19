<?php
/**
 * Stock (or Services, for a service business). Renders inside
 * views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $products, $summary, $categories,
 *   $search, $category, $stock_status, $active_filter, $sort,
 *   $page, $total_pages, $total_items, $per_page
 */
$currency  = $business['currency_symbol'] ?? '₦';
$services  = ($business_type ?? '') === 'service';
$low       = (int)($summary['low_stock_count'] ?? 0);
$out       = (int)($summary['out_of_stock_count'] ?? 0);
$svc_count = (int)($summary['services_count'] ?? 0);
$keep      = ['q' => $search, 'category' => $category, 'stock' => $stock_status, 'active' => $active_filter, 'sort' => $sort !== 'newest' ? $sort : ''];
$filters   = ['' => 'All', 'low' => 'Running low', 'out' => 'Out of stock', 'restock' => 'Low or out', 'in_stock' => 'In stock'];
if ($svc_count > 0 || $services) $filters['service'] = 'Services';
$counts    = ['low' => $low, 'out' => $out, 'restock' => $low + $out, 'service' => $svc_count];
$sorts     = ['newest' => 'Newest first', 'oldest' => 'Oldest first', 'name_asc' => 'A to Z', 'name_desc' => 'Z to A',
              'stock_asc' => 'Least in stock', 'stock_desc' => 'Most in stock', 'price_asc' => 'Cheapest first', 'price_desc' => 'Dearest first'];
$qty_text  = fn(float $q) => rtrim(rtrim(number_format($q, 2), '0'), '.');
?>
<div class="nf-page">
<div class="nf-head">
    <h1 class="nf-large-title">Catalogue</h1>
    <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/products/create"><?= nav_icon('plus') ?>Add item</a>
</div>

<?php if (!$services): ?>
<section class="nf-money" aria-label="Stock you hold">
    <p class="nf-money-k">Stock you hold, at cost</p>
    <p class="nf-money-v"><?= planner_money((float)($summary['total_stock_value'] ?? 0), $currency) ?></p>
    <p class="nf-money-sub">
        <?= (int)($summary['total_products'] ?? 0) ?> item<?= (int)($summary['total_products'] ?? 0) === 1 ? '' : 's' ?>.
        <?php if ($low + $out > 0): ?>
            <a href="<?= e(screen_url('products', ['stock' => 'restock'])) ?>"><?= $low + $out ?> need restocking</a><?= $out > 0 ? ' (' . $out . ' out)' : '' ?>.
        <?php else: ?>
            Nothing is running low.
        <?php endif; ?>
    </p>
</section>
<?php endif; ?>

<form class="nf-find" method="GET" action="<?= APP_URL ?>/products" role="search">
    <?php foreach (['category' => $category, 'stock' => $stock_status, 'active' => $active_filter] as $k => $v): if ((string)$v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
    <?php if ($sort !== 'newest'): ?><input type="hidden" name="sort" value="<?= e($sort) ?>"><?php endif; ?>
    <label class="nf-search"><?= nav_icon('search') ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search by name or code" aria-label="Search <?= $services ? 'services' : 'stock' ?>" enterkeyhint="search" autocomplete="off">
    </label>
</form>

<?php if (!$services): ?>
<nav class="nf-chips" aria-label="Show">
    <?php foreach ($filters as $key => $label): ?>
        <a href="<?= e(screen_url('products', ['stock' => $key] + $keep)) ?>" data-prefetch<?= $stock_status === $key ? ' aria-current="true"' : '' ?>><?= $label ?><?php if (!empty($counts[$key])): ?> <small><?= $counts[$key] ?></small><?php endif; ?></a>
    <?php endforeach; ?>
</nav>
<?php endif; ?>

<?php if (!empty($products) || $category !== ''): ?>
    <div class="nf-tools">
        <p><?= (int)$total_items ?> item<?= (int)$total_items === 1 ? '' : 's' ?><?= $search !== '' ? ' for “' . e($search) . '”' : '' ?></p>
        <form method="GET" action="<?= APP_URL ?>/products" class="nf-pick">
            <?php foreach (['q' => $search, 'stock' => $stock_status, 'active' => $active_filter] as $k => $v): if ((string)$v !== ''): ?><input type="hidden" name="<?= $k ?>" value="<?= e($v) ?>"><?php endif; endforeach; ?>
            <?php if ($categories): ?>
                <select class="nf-select" name="category" aria-label="Category" onchange="this.form.submit()">
                    <option value="">Every category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= e($cat) ?>"<?= $category === $cat ? ' selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <select class="nf-select" name="sort" aria-label="Order" onchange="this.form.submit()">
                <?php foreach ($sorts as $k => $label): ?>
                    <option value="<?= $k ?>"<?= $sort === $k ? ' selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
<?php endif; ?>

<?php if (!empty($products)): ?>
    <div class="nf-items">
        <?php foreach ($products as $p):
            $is_svc = (int)$p['is_service'] === 1;
            $qty    = (float)$p['stock_qty'];
            if ($is_svc) {
                [$tag, $tag_cls] = ['Service', 'is-line'];
            } elseif ($qty <= 0) {
                [$tag, $tag_cls] = ['Out of stock', 'is-owed'];
            } elseif ($qty <= (float)$p['low_stock_threshold']) {
                [$tag, $tag_cls] = ['Only ' . $qty_text($qty) . ' left', 'is-warn'];
            } else {
                [$tag, $tag_cls] = [$qty_text($qty) . ' in stock', ''];
            }
            $line = implode(' · ', array_filter([$p['category'] ?? '', $p['sku'] ?? '']));
        ?>
            <a class="nf-item is-wrap<?= !$p['is_active'] ? ' is-off' : '' ?>" href="<?= APP_URL ?>/products/view/<?= (int)$p['id'] ?>" data-prefetch>
                <span class="nf-face is-square" aria-hidden="true">
                    <?php if (!empty($p['image_path'])): ?>
                        <img src="<?= APP_URL ?>/<?= e($p['image_path']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <?= nav_icon($is_svc ? 'task' : 'stock') ?>
                    <?php endif; ?>
                </span>
                <span class="nf-item-main">
                    <b><?= e($p['name']) ?></b>
                    <small><?= !$p['is_active'] ? 'Inactive' . ($line !== '' ? ' · ' . e($line) : '') : ($line !== '' ? e($line) : ($is_svc ? 'Service' : 'No category')) ?></small>
                </span>
                <span class="nf-item-end">
                    <b><?= planner_money((float)$p['sell_price'], $currency) ?></b>
                    <span class="nf-tag <?= $tag_cls ?>"><?= e($tag) ?></span>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <?php $pager = ['path' => 'products', 'params' => $keep, 'page' => $page, 'pages' => $total_pages];
          require BASE_PATH . '/views/partials/pager.php'; ?>

<?php elseif ($search !== '' || $category !== '' || $stock_status !== '' || (string)$active_filter !== ''): ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('search') ?></span>
        <?php if ($search !== ''): ?>
            <h2>Nothing matches “<?= e($search) ?>”</h2>
            <p>Check the spelling, or search by the item's code.</p>
        <?php else: ?>
            <h2><?= match ($stock_status) {
                'low'     => 'Nothing is running low',
                'out'     => 'Nothing is out of stock',
                'restock' => 'Nothing needs restocking',
                'service' => 'No services yet',
                default   => 'No items here',
            } ?></h2>
            <p><?= in_array($stock_status, ['low', 'out', 'restock'], true) ? 'Every item is above the level you set for it.' : 'No item matches these filters.' ?></p>
        <?php endif; ?>
        <a class="nf-link" href="<?= APP_URL ?>/products">Show everything</a>
    </div>
<?php else: ?>
    <div class="nf-empty">
        <span class="nf-tile-ic"><?= nav_icon('stock') ?></span>
        <h2><?= $services ? 'No services yet' : 'Nothing in stock yet' ?></h2>
        <p><?= $services ? 'Add what you offer and its price, and pick it quickly on quotes and invoices.' : 'Add what you sell. Nfelia counts it down as you sell and tells you when to restock.' ?></p>
        <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/products/create"><?= nav_icon('plus') ?>Add an item</a>
        <a class="nf-link" href="<?= APP_URL ?>/products/setup">Or add many at once</a>
    </div>
<?php endif; ?>

<?php if (!empty($products)): ?>
    <div class="nf-list nf-more-list">
        <a class="nf-row" href="<?= APP_URL ?>/products/setup"><span class="nf-tile-ic"><?= nav_icon('plus') ?></span>Add many items at once<span class="end"><?= nav_icon('chevron') ?></span></a>
        <a class="nf-row" href="<?= APP_URL ?>/products/export" download data-no-prefetch><span class="nf-tile-ic"><?= nav_icon('download') ?></span>Download the list as a spreadsheet<span class="end"><?= nav_icon('chevron') ?></span></a>
    </div>
<?php endif; ?>
</div>
