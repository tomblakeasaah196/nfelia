<?php
/**
 * Nfelia — Product Detail + Stock Movements View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $product, $movements, $move_summary,
 *   $margin_pct, $stock_value, $move_filter
 */
$currency = $business['currency_symbol'] ?? '₦';
$is_svc   = (int)$product['is_service'];
$qty      = (float)$product['stock_qty'];
$threshold = (float)$product['low_stock_threshold'];
?>

<!-- Breadcrumb + Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/products" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <h1 class="font-heading font-extrabold text-2xl text-brand-plum tracking-tight truncate"><?= e($product['name']) ?></h1>
        <?php if (!$product['is_active']): ?>
            <span class="text-[9px] font-bold text-brand-plum/40 bg-brand-sand px-2 py-1 rounded uppercase tracking-widest">Inactive</span>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-2">
        <a href="<?= APP_URL ?>/products/edit/<?= $product['id'] ?>" class="flex items-center gap-2 bg-brand-white border border-brand-sand hover:border-brand-gold px-4 py-2.5 rounded-xl transition-all">
            <svg class="w-4 h-4 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Edit</span>
        </a>
        <form method="POST" action="<?= APP_URL ?>/products/duplicate/<?= $product['id'] ?>" class="inline">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <button type="submit" class="flex items-center gap-2 bg-brand-white border border-brand-sand hover:border-brand-gold px-4 py-2.5 rounded-xl transition-all">
                <svg class="w-4 h-4 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest">Clone</span>
            </button>
        </form>
        <form method="POST" action="<?= APP_URL ?>/products/delete/<?= $product['id'] ?>"
              onsubmit="return confirm('Remove <?= e(addslashes($product['name'])) ?> from inventory? This can be undone by support.');">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
            <button type="submit" class="flex items-center gap-2 bg-brand-white border border-red-200 hover:border-red-400 px-4 py-2.5 rounded-xl transition-all">
                <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                <span class="text-[10px] font-bold text-red-500 uppercase tracking-widest">Remove</span>
            </button>
        </form>
    </div>
</div>

<!-- ===== PRODUCT HEADER CARD ===== -->
<div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="flex flex-col sm:flex-row">
        <!-- Image -->
        <div class="sm:w-48 h-48 sm:h-auto bg-brand-cream flex items-center justify-center shrink-0">
            <?php if (!empty($product['image_path'])): ?>
                <img src="<?= APP_URL ?>/<?= e($product['image_path']) ?>" alt="" class="w-full h-full object-cover">
            <?php else: ?>
                <svg class="w-16 h-16 text-brand-plum/10" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
            <?php endif; ?>
        </div>

        <!-- Details -->
        <div class="flex-1 p-6">
            <div class="flex flex-wrap items-center gap-3 mb-4">
                <span class="data-text text-xs font-bold text-brand-plum/50 bg-brand-cream px-3 py-1 rounded-lg"><?= e($product['sku'] ?: 'No SKU') ?></span>
                <?php if ($product['category']): ?>
                    <span class="text-[10px] font-bold text-brand-plum/50 bg-brand-cream px-3 py-1 rounded-lg uppercase tracking-wider"><?= e($product['category']) ?></span>
                <?php endif; ?>
                <span class="text-[10px] font-bold px-3 py-1 rounded-lg uppercase tracking-wider <?= $is_svc ? 'bg-blue-100 text-blue-700' : ($qty <= 0 ? 'bg-red-100 text-red-700' : ($qty <= $threshold ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700')) ?>">
                    <?= $is_svc ? 'Service' : ($qty <= 0 ? 'Out of Stock' : ($qty <= $threshold ? 'Low Stock' : 'In Stock')) ?>
                </span>
            </div>

            <?php if ($product['description']): ?>
                <p class="text-sm text-brand-plum/60 leading-relaxed mb-4"><?= e($product['description']) ?></p>
            <?php endif; ?>

            <!-- Metrics Grid -->
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Sell Price</div>
                    <div class="font-heading font-extrabold text-lg text-brand-plum"><?= money((float)$product['sell_price'], $currency) ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Cost Price</div>
                    <div class="data-text font-bold text-sm text-brand-plum/70"><?= money((float)$product['cost_price'], $currency) ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Margin</div>
                    <div class="data-text font-bold text-sm <?= $margin_pct > 30 ? 'text-green-600' : ($margin_pct > 0 ? 'text-amber-600' : 'text-red-600') ?>"><?= $margin_pct ?>%</div>
                </div>
                <?php if (!$is_svc): ?>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Stock Value</div>
                    <div class="data-text font-bold text-sm text-[#B69F5E]"><?= money($stock_value, $currency) ?></div>
                </div>
                <?php else: ?>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Type</div>
                    <div class="text-sm font-bold text-blue-600">Service</div>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!$is_svc): ?>
            <div class="mt-4 pt-4 border-t border-brand-sand/50 flex items-center gap-6">
                <div>
                    <span class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Current Stock:</span>
                    <span class="data-text font-bold text-sm text-brand-plum ml-1"><?= number_format($qty, $qty == (int)$qty ? 0 : 2) ?> <?= e($product['unit'] ?? 'pcs') ?></span>
                </div>
                <div>
                    <span class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">Alert At:</span>
                    <span class="data-text font-bold text-sm text-brand-plum/60 ml-1"><?= number_format($threshold, $threshold == (int)$threshold ? 0 : 2) ?> <?= e($product['unit'] ?? 'pcs') ?></span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php if (!$is_svc): ?>
<!-- ===== STOCK ADJUSTMENT FORM ===== -->
<div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-6 mb-6"
     x-data="{ adjOpen: false, adjType: 'stock_in' }">
    <div class="flex items-center justify-between">
        <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Stock Adjustment</h2>
        <button @click="adjOpen = !adjOpen" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy transition-colors flex items-center gap-1">
            <svg class="w-3.5 h-3.5" :class="adjOpen ? 'rotate-45' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span x-text="adjOpen ? 'Close' : 'Adjust Stock'"></span>
        </button>
    </div>

    <form method="POST" action="<?= APP_URL ?>/products/adjust-stock/<?= $product['id'] ?>" x-show="adjOpen" x-transition class="mt-5">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

        <div class="grid sm:grid-cols-4 gap-4 mb-4">
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Type</label>
                <select name="adj_type" x-model="adjType"
                        class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    <option value="stock_in">Stock In (+)</option>
                    <option value="stock_out">Stock Out (−)</option>
                    <option value="adjustment">Set Exact Quantity</option>
                </select>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2" x-text="adjType === 'adjustment' ? 'New Quantity' : 'Quantity'">Quantity</label>
                <input type="number" name="quantity" step="0.01" min="0" required placeholder="0"
                       class="w-full px-3 py-2.5 text-xs font-mono font-bold bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Unit Cost (<?= e($currency) ?>)</label>
                <input type="number" name="unit_cost" step="0.01" min="0" value="<?= e($product['cost_price']) ?>" placeholder="0.00"
                       class="w-full px-3 py-2.5 text-xs font-mono bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Reason / Note</label>
                <input type="text" name="note" placeholder="e.g. Restocked from supplier"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
            </div>
        </div>

        <div class="flex items-center gap-3">
            <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest shadow-sm">Apply Adjustment</button>
            <span class="data-text text-[10px] text-brand-plum/30">Current stock: <strong><?= number_format($qty, $qty == (int)$qty ? 0 : 2) ?></strong> <?= e($product['unit'] ?? 'pcs') ?></span>
        </div>
    </form>
</div>

<!-- ===== STOCK MOVEMENT HISTORY ===== -->
<div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between px-6 py-4 border-b border-brand-sand gap-3">
        <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Stock Movement History</h2>

        <!-- Movement type filter -->
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar">
            <?php
            $move_types = [
                ''           => 'All',
                'stock_in'   => 'In',
                'stock_out'  => 'Out',
                'sale'       => 'Sale',
                'return'     => 'Return',
                'adjustment' => 'Adj.',
            ];
            foreach ($move_types as $mv_val => $mv_label):
                $mv_active = ($move_filter ?? '') === $mv_val;
            ?>
                <a href="<?= APP_URL ?>/products/view/<?= $product['id'] ?><?= $mv_val ? '?move_type=' . $mv_val : '' ?>"
                   class="shrink-0 text-[9px] font-bold uppercase tracking-widest px-3 py-1.5 rounded-lg transition-all <?= $mv_active ? 'btn-primary text-white' : 'text-brand-plum/50 bg-brand-cream hover:bg-brand-sand' ?>"><?= $mv_label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (!empty($movements)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-6 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                        <th class="px-6 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Type</th>
                        <th class="px-6 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Qty</th>
                        <th class="px-6 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Before</th>
                        <th class="px-6 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">After</th>
                        <th class="px-6 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Note</th>
                        <th class="px-6 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">By</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($movements as $mv):
                        $type_config = match($mv['type']) {
                            'stock_in'   => ['icon' => '↑', 'color' => 'text-green-600 bg-green-100', 'label' => 'Stock In'],
                            'stock_out'  => ['icon' => '↓', 'color' => 'text-red-600 bg-red-100',     'label' => 'Stock Out'],
                            'sale'       => ['icon' => '◆', 'color' => 'text-blue-600 bg-blue-100',   'label' => 'Sale'],
                            'return'     => ['icon' => '↩', 'color' => 'text-purple-600 bg-purple-100', 'label' => 'Return'],
                            'adjustment' => ['icon' => '⟳', 'color' => 'text-amber-600 bg-amber-100', 'label' => 'Adjustment'],
                            default      => ['icon' => '·', 'color' => 'text-gray-600 bg-gray-100',   'label' => ucfirst($mv['type'])],
                        };
                    ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors">
                            <td class="px-6 py-3 data-text text-[10px] text-brand-plum/60 whitespace-nowrap"><?= fmt_date($mv['movement_date'], 'd M Y') ?></td>
                            <td class="px-6 py-3">
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider px-2 py-1 rounded <?= $type_config['color'] ?>">
                                    <?= $type_config['icon'] ?> <?= $type_config['label'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-3 data-text text-xs font-bold text-brand-plum text-right"><?= number_format((float)$mv['quantity'], 2) ?></td>
                            <td class="px-6 py-3 data-text text-[10px] text-brand-plum/40 text-right"><?= number_format((float)$mv['stock_before'], 2) ?></td>
                            <td class="px-6 py-3 data-text text-[10px] font-bold text-brand-plum text-right"><?= number_format((float)$mv['stock_after'], 2) ?></td>
                            <td class="px-6 py-3 text-[10px] text-brand-plum/60 max-w-[200px] truncate"><?= e($mv['note'] ?? '—') ?></td>
                            <td class="px-6 py-3 text-[10px] text-brand-plum/40 whitespace-nowrap"><?= e($mv['user_name'] ?? 'System') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="px-6 py-12 text-center">
            <p class="text-xs text-brand-plum/40 font-medium">No stock movements recorded yet.</p>
        </div>
    <?php endif; ?>

    <!-- Movement Summary -->
    <?php if (!empty($move_summary)): ?>
    <div class="px-6 py-4 bg-brand-cream/30 border-t border-brand-sand flex flex-wrap gap-4">
        <?php foreach ($move_summary as $type => $ms):
            $ms_label = match($type) {
                'stock_in' => 'Total In', 'stock_out' => 'Total Out', 'sale' => 'Sales',
                'return' => 'Returns', 'adjustment' => 'Adjustments', default => ucfirst($type),
            };
        ?>
            <div class="text-[9px]">
                <span class="font-bold text-brand-plum/40 uppercase tracking-widest"><?= $ms_label ?>:</span>
                <span class="data-text font-bold text-brand-plum ml-1"><?= (int)$ms['cnt'] ?> (<?= number_format((float)$ms['total_qty'], 2) ?> units)</span>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; /* end !$is_svc */ ?>

<!-- ===== PRODUCT METADATA ===== -->
<div class="text-center text-[9px] text-brand-plum/25 font-medium space-x-4 mb-4">
    <span>Created: <?= fmt_date($product['created_at'], 'd M Y H:i') ?></span>
    <span>Updated: <?= fmt_date($product['updated_at'], 'd M Y H:i') ?></span>
    <span>ID: <?= $product['id'] ?></span>
</div>