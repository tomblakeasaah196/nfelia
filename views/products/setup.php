<?php
/**
 * Nfelia — Inventory Setup / Configuration View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $categories_list, $recent_products, $product_count
 */
$currency = $business['currency_symbol'] ?? '₦';
$biz_cat  = $business['category'] ?? 'other';

// Sector-specific category suggestions
$suggested_cats = match($biz_cat) {
    'fashion'          => ['Fabrics', 'Ready-to-Wear', 'Accessories', 'Footwear', 'Bags', 'Jewelry', 'Tailoring Materials', 'Alterations (Service)'],
    'food_beverage'    => ['Ingredients', 'Beverages', 'Snacks & Pastries', 'Prepared Meals', 'Frozen Items', 'Bakery', 'Dairy & Eggs', 'Condiments & Spices', 'Catering (Service)'],
    'tech_repair'      => ['Phones', 'Laptops & Tablets', 'Accessories', 'Spare Parts', 'Screens & Displays', 'Batteries', 'Cables & Chargers', 'Repair Service', 'Software Install (Service)'],
    'distribution'     => ['Fast-Moving Goods', 'Slow-Moving Goods', 'Perishable', 'Non-Perishable', 'Imported', 'Local Supply', 'Bulk / Wholesale', 'Delivery (Service)'],
    'services'         => ['Consultation', 'Installation', 'Maintenance', 'Training & Coaching', 'Support Contract', 'Custom Work', 'Materials & Supplies'],
    'wholesale_retail' => ['Electronics', 'Home & Living', 'Personal Care', 'Stationery', 'Clothing', 'Hardware & Tools', 'FMCG', 'General Merchandise'],
    'creative'         => ['Design Services', 'Print Products', 'Digital Products', 'Photography', 'Art Materials', 'Commissions', 'Merchandise', 'Licensing'],
    default            => ['Products', 'Services', 'Supplies', 'Equipment', 'Materials', 'Spare Parts', 'Consumables', 'Custom Work'],
};

// Units relevant to business type
$suggested_units = match($biz_cat) {
    'fashion'          => ['pcs' => 'Pieces', 'yards' => 'Yards', 'meters' => 'Metres', 'pairs' => 'Pairs', 'rolls' => 'Rolls', 'sets' => 'Sets'],
    'food_beverage'    => ['pcs' => 'Pieces', 'kg' => 'Kilograms', 'g' => 'Grams', 'litres' => 'Litres', 'bottles' => 'Bottles', 'packs' => 'Packs', 'crates' => 'Crates', 'dozen' => 'Dozen'],
    'tech_repair'      => ['pcs' => 'Pieces', 'sets' => 'Sets', 'hours' => 'Hours'],
    'distribution'     => ['pcs' => 'Pieces', 'cartons' => 'Cartons', 'bags' => 'Bags', 'boxes' => 'Boxes', 'kg' => 'Kilograms', 'litres' => 'Litres', 'crates' => 'Crates', 'packs' => 'Packs'],
    'services'         => ['hours' => 'Hours', 'pcs' => 'Pieces', 'sets' => 'Sets', 'sessions' => 'Sessions'],
    'wholesale_retail' => ['pcs' => 'Pieces', 'dozen' => 'Dozen', 'cartons' => 'Cartons', 'boxes' => 'Boxes', 'packs' => 'Packs', 'kg' => 'Kilograms'],
    'creative'         => ['pcs' => 'Pieces', 'hours' => 'Hours', 'sheets' => 'Sheets', 'sets' => 'Sets', 'sqm' => 'Sq. Metres'],
    default            => ['pcs' => 'Pieces', 'kg' => 'Kilograms', 'litres' => 'Litres', 'boxes' => 'Boxes', 'hours' => 'Hours', 'sets' => 'Sets'],
};

// Progress tracking
$has_products = $product_count > 0;
$has_categories = !empty($categories_list);
$step_done = ($has_products && $has_categories) ? 3 : ($has_products ? 2 : ($has_categories ? 1 : 0));
?>

<!-- Page Header -->
<div class="flex items-center gap-3 mb-8">
    <a href="<?= APP_URL ?>/products" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Inventory Setup</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Configure your catalog, categories, and stock preferences</p>
    </div>
</div>

<!-- ===== PROGRESS INDICATOR ===== -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm mb-8">
    <div class="flex items-center justify-between mb-4">
        <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Setup Progress</h2>
        <span class="data-text text-[10px] font-bold text-brand-gold"><?= $product_count ?> item<?= $product_count !== 1 ? 's' : '' ?> added</span>
    </div>
    <div class="flex items-center gap-2 mb-3">
        <?php for ($i = 1; $i <= 3; $i++): ?>
            <div class="flex-1 h-2 rounded-full <?= $i <= $step_done ? 'bg-brand-gold' : 'bg-brand-sand' ?> transition-all"></div>
        <?php endfor; ?>
    </div>
    <div class="flex items-center justify-between text-[9px] font-bold uppercase tracking-widest">
        <span class="<?= $step_done >= 1 ? 'text-brand-gold' : 'text-brand-plum/30' ?>">Categories</span>
        <span class="<?= $step_done >= 2 ? 'text-brand-gold' : 'text-brand-plum/30' ?>">First Items</span>
        <span class="<?= $step_done >= 3 ? 'text-brand-gold' : 'text-brand-plum/30' ?>">Ready</span>
    </div>
</div>

<div class="grid lg:grid-cols-3 gap-6">

    <!-- ===== LEFT: SETUP PANELS (2/3) ===== -->
    <div class="lg:col-span-2 space-y-6">

        <!-- SECTION 1: Category Suggestions -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden" x-data="{ expanded: true }">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-6 py-5 text-left">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center <?= $has_categories ? 'bg-green-100' : 'bg-brand-gold/10' ?>">
                        <?php if ($has_categories): ?>
                            <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <span class="text-sm font-bold text-brand-gold">1</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="font-heading font-bold text-sm text-brand-plum">Product Categories</h3>
                        <p class="text-[10px] text-brand-plum/50 mt-0.5">Suggested for your <strong><?= e(str_replace('_', ' ', $biz_cat)) ?></strong> business</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-brand-plum/30 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="expanded" x-transition class="px-6 pb-6">
                <p class="text-xs text-brand-plum/50 leading-relaxed mb-4">
                    These categories are tailored for your industry. Select the ones that apply, or type your own when adding products. Categories help you filter, report, and organize your inventory.
                </p>

                <div class="flex flex-wrap gap-2 mb-4">
                    <?php foreach ($suggested_cats as $scat):
                        $already_used = in_array($scat, $categories_list);
                    ?>
                        <span class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-[10px] font-bold uppercase tracking-wider border transition-all
                            <?= $already_used ? 'bg-green-50 border-green-300 text-green-700' : 'bg-brand-cream border-brand-sand text-brand-plum/60 hover:border-brand-gold cursor-default' ?>">
                            <?php if ($already_used): ?>
                                <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <?php endif; ?>
                            <?= e($scat) ?>
                        </span>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($categories_list)): ?>
                    <div class="mt-3 pt-3 border-t border-brand-sand/50">
                        <p class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-2">Your active categories:</p>
                        <div class="flex flex-wrap gap-2">
                            <?php foreach ($categories_list as $ec): ?>
                                <span class="data-text text-[10px] font-bold text-brand-plum bg-brand-cream px-2.5 py-1 rounded"><?= e($ec) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <p class="text-[9px] text-brand-plum/30 mt-3">Categories are created automatically when you assign them to a product. No separate setup needed.</p>
            </div>
        </div>

        <!-- SECTION 2: Quick Add Product -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden" x-data="{ expanded: <?= $has_products ? 'false' : 'true' ?>, isService: false }">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-6 py-5 text-left">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center <?= $has_products ? 'bg-green-100' : 'bg-brand-gold/10' ?>">
                        <?php if ($has_products): ?>
                            <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                        <?php else: ?>
                            <span class="text-sm font-bold text-brand-gold">2</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h3 class="font-heading font-bold text-sm text-brand-plum">Quick Add Items</h3>
                        <p class="text-[10px] text-brand-plum/50 mt-0.5">Add products or services one at a time — fast and simple</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-brand-plum/30 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="expanded" x-transition class="px-6 pb-6">
                <form method="POST" action="<?= APP_URL ?>/products/setup">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <input type="hidden" name="setup_action" value="quick_add">

                    <div class="grid sm:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" required placeholder="e.g. Ankara Fabric, Screen Repair"
                                   class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Category</label>
                            <input type="text" name="category" list="setup-cat-list" placeholder="Select or type"
                                   class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                            <datalist id="setup-cat-list">
                                <?php foreach (array_unique(array_merge($suggested_cats, $categories_list)) as $sc): ?>
                                    <option value="<?= e($sc) ?>">
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-4 mb-4">
                        <div>
                            <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Sell Price (<?= e($currency) ?>)</label>
                            <input type="number" name="sell_price" step="0.01" min="0" placeholder="0.00"
                                   class="w-full px-3 py-2.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Cost Price (<?= e($currency) ?>)</label>
                            <input type="number" name="cost_price" step="0.01" min="0" placeholder="0.00"
                                   class="w-full px-3 py-2.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                        </div>
                        <div x-show="!isService">
                            <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Stock Qty</label>
                            <input type="number" name="stock_qty" step="0.01" min="0" placeholder="0"
                                   class="w-full px-3 py-2.5 text-xs font-mono font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                        </div>
                    </div>

                    <div class="flex items-center gap-4 mb-4">
                        <div>
                            <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-1.5">Unit</label>
                            <select name="unit" class="px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                                <?php foreach ($suggested_units as $uval => $ulbl): ?>
                                    <option value="<?= $uval ?>"><?= $ulbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer mt-5">
                            <input type="checkbox" name="is_service" value="1" x-model="isService"
                                   class="w-4 h-4 rounded border-brand-sand text-blue-500 focus:ring-blue-500">
                            <span class="text-[10px] font-bold text-brand-plum/60 uppercase tracking-wider">Service (no stock)</span>
                        </label>
                    </div>

                    <button type="submit" class="btn-primary text-white px-6 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
                        Add & Continue
                    </button>
                </form>
            </div>
        </div>

        <!-- SECTION 3: CSV Bulk Import -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden" x-data="{ expanded: false }">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-6 py-5 text-left">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-brand-cream flex items-center justify-center">
                        <svg class="w-4 h-4 text-brand-plum/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    </div>
                    <div>
                        <h3 class="font-heading font-bold text-sm text-brand-plum">Bulk Import (CSV)</h3>
                        <p class="text-[10px] text-brand-plum/50 mt-0.5">Upload a spreadsheet to add many items at once</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-brand-plum/30 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="expanded" x-transition class="px-6 pb-6">
                <div class="bg-brand-cream/50 border border-brand-sand rounded-lg p-4 mb-4">
                    <p class="text-xs text-brand-plum/60 font-medium leading-relaxed mb-3">
                        Prepare a CSV file with the following columns. Only <strong>Name</strong> is required — everything else is optional.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr class="border-b border-brand-sand">
                                    <th class="pb-2 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Column</th>
                                    <th class="pb-2 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Required</th>
                                    <th class="pb-2 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Example</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-brand-sand/50">
                                <?php
                                $csv_cols = [
                                    ['Name', 'Yes', 'Ankara Fabric'],
                                    ['SKU', 'No', 'ANK-0012'],
                                    ['Category', 'No', 'Fabrics'],
                                    ['Description', 'No', 'Premium quality ankara'],
                                    ['Sell Price', 'No', '3500.00'],
                                    ['Cost Price', 'No', '2000.00'],
                                    ['Stock Qty', 'No', '50'],
                                    ['Unit', 'No', 'yards'],
                                    ['Is Service', 'No', 'Yes or No'],
                                ];
                                foreach ($csv_cols as $cc):
                                ?>
                                    <tr>
                                        <td class="py-1.5 data-text text-[10px] font-bold text-brand-plum"><?= $cc[0] ?></td>
                                        <td class="py-1.5 text-[10px] <?= $cc[1] === 'Yes' ? 'text-red-600 font-bold' : 'text-brand-plum/40' ?>"><?= $cc[1] ?></td>
                                        <td class="py-1.5 data-text text-[10px] text-brand-plum/50"><?= $cc[2] ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <form method="POST" action="<?= APP_URL ?>/products/import" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                    <div class="flex items-center gap-3">
                        <label class="flex-1 cursor-pointer">
                            <div class="flex items-center gap-3 border-2 border-dashed border-brand-sand hover:border-brand-gold rounded-lg px-4 py-3 transition-all">
                                <svg class="w-5 h-5 text-brand-plum/30 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                <span class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest" id="csv-filename">Choose CSV file...</span>
                            </div>
                            <input type="file" name="csv_file" accept=".csv" class="hidden" onchange="document.getElementById('csv-filename').textContent = this.files[0]?.name || 'Choose CSV file...'">
                        </label>
                        <button type="submit" class="btn-primary text-white px-6 py-3 rounded-lg text-[10px] font-bold uppercase tracking-widest shadow-sm">Import</button>
                    </div>
                </form>

                <p class="text-[9px] text-brand-plum/25 mt-3">Duplicates (matching name) will be skipped. SKUs are auto-generated if left blank.</p>
            </div>
        </div>

        <!-- SECTION 4: How Stock Tracking Works -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden" x-data="{ expanded: false }">
            <button @click="expanded = !expanded" class="w-full flex items-center justify-between px-6 py-5 text-left">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="font-heading font-bold text-sm text-brand-plum">How Stock Tracking Works</h3>
                        <p class="text-[10px] text-brand-plum/50 mt-0.5">Understand automatic deductions, alerts, and audit trails</p>
                    </div>
                </div>
                <svg class="w-5 h-5 text-brand-plum/30 transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
            </button>

            <div x-show="expanded" x-transition class="px-6 pb-6 space-y-4">
                <!-- Automatic Deductions -->
                <div class="flex gap-3 p-4 bg-brand-cream/50 rounded-lg border border-brand-sand/50">
                    <div class="w-8 h-8 rounded-lg bg-green-100 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-sm">↓</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-brand-plum mb-1">Automatic Stock Deduction</h4>
                        <p class="text-[10px] text-brand-plum/50 leading-relaxed">When you send an invoice, stock is automatically reduced for each line item. The movement is logged as a <strong>"sale"</strong> in your audit trail with the invoice number as reference.</p>
                    </div>
                </div>

                <!-- Auto Restore -->
                <div class="flex gap-3 p-4 bg-brand-cream/50 rounded-lg border border-brand-sand/50">
                    <div class="w-8 h-8 rounded-lg bg-purple-100 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-sm">↩</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-brand-plum mb-1">Cancellation Restore</h4>
                        <p class="text-[10px] text-brand-plum/50 leading-relaxed">If an invoice is cancelled, stock is automatically restored. The movement is logged as a <strong>"return"</strong> so you always have a full paper trail.</p>
                    </div>
                </div>

                <!-- Manual Adjustments -->
                <div class="flex gap-3 p-4 bg-brand-cream/50 rounded-lg border border-brand-sand/50">
                    <div class="w-8 h-8 rounded-lg bg-amber-100 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-sm">⟳</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-brand-plum mb-1">Manual Adjustments</h4>
                        <p class="text-[10px] text-brand-plum/50 leading-relaxed">You can manually add stock (new purchase), remove stock (damage, loss), or set an exact count (after physical inventory). Every change is tracked with who did it, when, and why.</p>
                    </div>
                </div>

                <!-- Low Stock Alerts -->
                <div class="flex gap-3 p-4 bg-brand-cream/50 rounded-lg border border-brand-sand/50">
                    <div class="w-8 h-8 rounded-lg bg-red-100 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-sm">!</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-brand-plum mb-1">Low Stock Alerts</h4>
                        <p class="text-[10px] text-brand-plum/50 leading-relaxed">Set a threshold for each product. When stock falls to or below this level, it appears on your dashboard and inventory low-stock filter. Default threshold is 5 units.</p>
                    </div>
                </div>

                <!-- Services -->
                <div class="flex gap-3 p-4 bg-brand-cream/50 rounded-lg border border-brand-sand/50">
                    <div class="w-8 h-8 rounded-lg bg-blue-100 flex items-center justify-center shrink-0 mt-0.5">
                        <span class="text-sm">◇</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-brand-plum mb-1">Services vs. Products</h4>
                        <p class="text-[10px] text-brand-plum/50 leading-relaxed">Mark an item as a "service" and stock tracking is completely bypassed. Services still appear on invoices and quotations with pricing, but quantities are never tracked or deducted. Perfect for labour, consultations, or delivery charges.</p>
                    </div>
                </div>

                <!-- SKU Format -->
                <div class="flex gap-3 p-4 bg-brand-cream/50 rounded-lg border border-brand-sand/50">
                    <div class="w-8 h-8 rounded-lg bg-brand-cream flex items-center justify-center shrink-0 mt-0.5">
                        <span class="data-text text-[10px] font-bold text-brand-plum/50">#</span>
                    </div>
                    <div>
                        <h4 class="text-xs font-bold text-brand-plum mb-1">Auto-SKU Format</h4>
                        <p class="text-[10px] text-brand-plum/50 leading-relaxed">SKUs are auto-generated as: <strong class="data-text">first 3 letters of name + dash + zero-padded ID</strong>. Example: "Ankara Fabric" becomes <span class="data-text font-bold">ANK-0012</span>. You can always override with a custom SKU.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ===== RIGHT: RECENTLY ADDED + QUICK LINKS (1/3) ===== -->
    <div class="space-y-6">

        <!-- Quick Links -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-6">
            <h3 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Quick Actions</h3>
            <div class="space-y-2">
                <a href="<?= APP_URL ?>/products/create" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-brand-sand hover:border-brand-gold transition-all">
                    <svg class="w-4 h-4 text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
                    <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Full Add Form</span>
                </a>
                <a href="<?= APP_URL ?>/products" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-brand-sand hover:border-brand-gold transition-all">
                    <svg class="w-4 h-4 text-brand-plum/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                    <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">View All Items</span>
                </a>
                <a href="<?= APP_URL ?>/products/export" class="flex items-center gap-3 px-4 py-3 rounded-lg border border-brand-sand hover:border-brand-gold transition-all">
                    <svg class="w-4 h-4 text-brand-plum/40" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest">Export to CSV</span>
                </a>
            </div>
        </div>

        <!-- Units Reference -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-6">
            <h3 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Available Units</h3>
            <p class="text-[10px] text-brand-plum/40 mb-3">Common units for your business type:</p>
            <div class="flex flex-wrap gap-1.5">
                <?php foreach ($suggested_units as $uv => $ul): ?>
                    <span class="data-text text-[9px] font-bold text-brand-plum/50 bg-brand-cream px-2 py-1 rounded"><?= $ul ?></span>
                <?php endforeach; ?>
            </div>
            <p class="text-[9px] text-brand-plum/25 mt-3">All 24 units are available in the full add form, including sq. metres, litres, dozen, and more.</p>
        </div>

        <!-- Recently Added -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-brand-sand">
                <h3 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Recently Added</h3>
            </div>
            <?php if (!empty($recent_products)): ?>
                <div class="divide-y divide-brand-sand/50">
                    <?php foreach ($recent_products as $rp): ?>
                        <a href="<?= APP_URL ?>/products/view/<?= $rp['id'] ?>" class="flex items-center justify-between px-6 py-3 hover:bg-brand-cream/30 transition-colors">
                            <div class="min-w-0">
                                <div class="text-xs font-bold text-brand-plum truncate"><?= e($rp['name']) ?></div>
                                <div class="data-text text-[9px] text-brand-plum/40"><?= e($rp['sku']) ?><?= $rp['category'] ? ' · ' . e($rp['category']) : '' ?></div>
                            </div>
                            <div class="text-right shrink-0 ml-3">
                                <div class="data-text text-[10px] font-bold text-brand-plum"><?= money((float)$rp['sell_price'], $currency) ?></div>
                                <?php if ($rp['is_service']): ?>
                                    <span class="text-[8px] font-bold text-blue-600 uppercase">Service</span>
                                <?php else: ?>
                                    <span class="data-text text-[8px] text-brand-plum/30"><?= (int)$rp['stock_qty'] ?> in stock</span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="px-6 py-8 text-center">
                    <p class="text-xs text-brand-plum/30 font-medium">No items yet. Use the form above to add your first product.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Go to Inventory -->
        <?php if ($product_count >= 3): ?>
        <div class="bg-green-50 border border-green-200 rounded-xl p-6 text-center">
            <svg class="w-8 h-8 text-green-500 mx-auto mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <h3 class="font-heading font-bold text-sm text-green-800 mb-1">You're all set!</h3>
            <p class="text-[10px] text-green-700/70 mb-4">Your inventory is ready. You can always come back here to import more items.</p>
            <a href="<?= APP_URL ?>/products" class="inline-block btn-primary text-white px-6 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">View Inventory</a>
        </div>
        <?php endif; ?>
    </div>
</div>