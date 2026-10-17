<?php
/**
 * Nfelia — Inventory Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $products, $summary, $categories,
 *   $search, $category, $stock_status, $active_filter, $sort,
 *   $page, $total_pages, $total_items, $per_page
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- ===== PAGE HEADER ===== -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Inventory</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1"><?= (int)($summary['total_products'] ?? 0) ?> items across your catalog</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/products/setup" class="flex items-center gap-2 bg-brand-cream border border-brand-sand hover:border-brand-gold px-4 py-2.5 rounded-xl transition-all">
            <svg class="w-4 h-4 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Setup</span>
        </a>
        <a href="<?= APP_URL ?>/products/create" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span class="text-[10px] font-bold uppercase tracking-widest">Add Item</span>
        </a>
    </div>
</div>

<!-- ===== SUMMARY CARDS ===== -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Items</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum"><?= (int)($summary['total_products'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Stock Value</div>
        <div class="font-heading font-extrabold text-xl text-[#B69F5E] truncate"><?= money((float)($summary['total_stock_value'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Low Stock</div>
        <div class="font-heading font-extrabold text-xl text-amber-600"><?= (int)($summary['low_stock_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Out of Stock</div>
        <div class="font-heading font-extrabold text-xl text-red-600"><?= (int)($summary['out_of_stock_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm col-span-2 lg:col-span-1">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Services</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum"><?= (int)($summary['services_count'] ?? 0) ?></div>
    </div>
</div>

<!-- ===== FILTERS BAR ===== -->
<form method="GET" action="<?= APP_URL ?>/products" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <!-- Search -->
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search name, SKU..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">
        </div>

        <!-- Category -->
        <select name="category" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="">All Categories</option>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= e($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
            <?php endforeach; ?>
        </select>

        <!-- Stock Status -->
        <select name="stock" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="" <?= $stock_status === '' ? 'selected' : '' ?>>All Stock</option>
            <option value="in_stock" <?= $stock_status === 'in_stock' ? 'selected' : '' ?>>In Stock</option>
            <option value="low" <?= $stock_status === 'low' ? 'selected' : '' ?>>Low Stock</option>
            <option value="out" <?= $stock_status === 'out' ? 'selected' : '' ?>>Out of Stock</option>
            <option value="service" <?= $stock_status === 'service' ? 'selected' : '' ?>>Services Only</option>
        </select>

        <!-- Sort -->
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name A→Z</option>
            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z→A</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price Low→High</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price High→Low</option>
            <option value="stock_asc" <?= $sort === 'stock_asc' ? 'selected' : '' ?>>Stock Low→High</option>
            <option value="stock_desc" <?= $sort === 'stock_desc' ? 'selected' : '' ?>>Stock High→Low</option>
        </select>

        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Filter</button>

        <?php if ($search || $category || $stock_status || $sort !== 'newest'): ?>
            <a href="<?= APP_URL ?>/products" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy transition-colors">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- ===== EXPORT LINK ===== -->
<div class="flex items-center justify-between mb-4">
    <p class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest"><?= $total_items ?> result<?= $total_items !== 1 ? 's' : '' ?></p>
    <a href="<?= APP_URL ?>/products/export" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy transition-colors flex items-center gap-1">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Export CSV
    </a>
</div>

<!-- ===== PRODUCT LIST ===== -->
<?php if (!empty($products)): ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
        <?php foreach ($products as $p):
            $is_svc    = (int)$p['is_service'];
            $qty       = (float)$p['stock_qty'];
            $threshold = (float)$p['low_stock_threshold'];
            $margin    = $p['sell_price'] > 0 ? round(($p['sell_price'] - $p['cost_price']) / $p['sell_price'] * 100, 1) : 0;

            if ($is_svc) {
                $stock_badge_class = 'bg-blue-100 text-blue-700';
                $stock_badge_text  = 'Service';
            } elseif ($qty <= 0) {
                $stock_badge_class = 'bg-red-100 text-red-700';
                $stock_badge_text  = 'Out of Stock';
            } elseif ($qty <= $threshold) {
                $stock_badge_class = 'bg-amber-100 text-amber-700';
                $stock_badge_text  = 'Low: ' . (int)$qty . ' ' . e($p['unit'] ?? 'pcs');
            } else {
                $stock_badge_class = 'bg-green-100 text-green-700';
                $stock_badge_text  = (int)$qty . ' ' . e($p['unit'] ?? 'pcs');
            }
        ?>
            <a href="<?= APP_URL ?>/products/view/<?= $p['id'] ?>" class="group bg-brand-white border border-brand-sand rounded-xl shadow-sm hover:shadow-md hover:border-brand-gold/50 transition-all overflow-hidden flex flex-col <?= !$p['is_active'] ? 'opacity-60' : '' ?>">

                <!-- Image / Placeholder -->
                <div class="h-32 bg-brand-cream flex items-center justify-center overflow-hidden">
                    <?php if (!empty($p['image_path'])): ?>
                        <img src="<?= APP_URL ?>/<?= e($p['image_path']) ?>" alt="" class="w-full h-full object-cover group-hover:scale-105 transition-transform">
                    <?php else: ?>
                        <div class="text-center">
                            <svg class="w-10 h-10 text-brand-plum/10 mx-auto" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                            </svg>
                            <span class="text-[8px] font-bold text-brand-plum/15 uppercase tracking-widest mt-1 block"><?= $is_svc ? 'Service' : 'No Image' ?></span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="p-4 flex-1 flex flex-col">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-bold text-brand-plum truncate group-hover:text-brand-burgundy transition-colors"><?= e($p['name']) ?></h3>
                            <p class="data-text text-[10px] text-brand-plum/40 mt-0.5"><?= e($p['sku'] ?: '—') ?><?= $p['category'] ? ' · ' . e($p['category']) : '' ?></p>
                        </div>
                        <?php if (!$p['is_active']): ?>
                            <span class="text-[8px] font-bold text-brand-plum/30 uppercase tracking-widest bg-brand-sand px-1.5 py-0.5 rounded shrink-0">Inactive</span>
                        <?php endif; ?>
                    </div>

                    <div class="mt-auto pt-3 border-t border-brand-sand/50 flex items-center justify-between">
                        <div>
                            <div class="font-heading font-extrabold text-sm text-brand-plum"><?= money((float)$p['sell_price'], $currency) ?></div>
                            <?php if ((float)$p['cost_price'] > 0): ?>
                                <div class="data-text text-[9px] text-brand-plum/30 mt-0.5">Cost: <?= money((float)$p['cost_price'], $currency) ?> · <?= $margin ?>% margin</div>
                            <?php endif; ?>
                        </div>
                        <span class="data-text text-[9px] font-bold px-2 py-1 rounded-lg <?= $stock_badge_class ?>"><?= $stock_badge_text ?></span>
                    </div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- ===== PAGINATION ===== -->
    <?php if ($total_pages > 1): ?>
        <nav class="flex items-center justify-center gap-2 mb-8">
            <?php
            $query_params = $_GET;
            unset($query_params['page']);
            $base_url = APP_URL . '/products?' . http_build_query($query_params);
            ?>
            <?php if ($page > 1): ?>
                <a href="<?= $base_url ?>&page=<?= $page - 1 ?>" class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-brand-plum/60 bg-brand-white border border-brand-sand rounded-lg hover:border-brand-gold transition-all">Prev</a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="<?= $base_url ?>&page=<?= $i ?>"
                   class="w-9 h-9 flex items-center justify-center text-[10px] font-bold rounded-lg transition-all
                   <?= $i === $page ? 'btn-primary text-white shadow-sm' : 'text-brand-plum/60 bg-brand-white border border-brand-sand hover:border-brand-gold' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="<?= $base_url ?>&page=<?= $page + 1 ?>" class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-brand-plum/60 bg-brand-white border border-brand-sand rounded-lg hover:border-brand-gold transition-all">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <!-- Empty State -->
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-12 text-center mb-8">
        <div class="w-16 h-16 mx-auto bg-brand-cream rounded-2xl flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
        </div>
        <?php if ($search || $category || $stock_status): ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No products match your filters</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-4">Try adjusting your search or filters.</p>
            <a href="<?= APP_URL ?>/products" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">Clear All Filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">Your inventory is empty</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">Add your first product or service to start tracking stock and sales.</p>
            <div class="flex items-center justify-center gap-3">
                <a href="<?= APP_URL ?>/products/create" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">Add First Item</a>
                <a href="<?= APP_URL ?>/products/setup" class="bg-brand-cream border border-brand-sand text-brand-plum px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest hover:border-brand-gold transition-all">Bulk Import</a>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>