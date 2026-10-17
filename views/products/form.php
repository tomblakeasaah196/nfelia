<?php
/**
 * Nfelia — Product Create / Edit Form
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $product (array or empty), $editing (bool), $categories_list
 */
$currency  = $business['currency_symbol'] ?? '₦';
$biz_cat   = $business['category'] ?? 'other';

// Pre-populate from $product (works for both old POST data and DB row)
$p_name        = e($product['name'] ?? '');
$p_sku         = e($product['sku'] ?? '');
$p_category    = $product['category'] ?? '';
$p_description = $product['description'] ?? '';
$p_sell        = $product['sell_price'] ?? '';
$p_cost        = $product['cost_price'] ?? '';
$p_stock       = $product['stock_qty'] ?? '';
$p_threshold   = $product['low_stock_threshold'] ?? '5';
$p_unit        = $product['unit'] ?? 'pcs';
$p_service     = !empty($product['is_service']);
$p_image       = $product['image_path'] ?? '';

// Suggested categories based on business type
$cat_suggestions = match($biz_cat) {
    'fashion'          => ['Fabrics', 'Ready-to-Wear', 'Accessories', 'Footwear', 'Bags', 'Jewelry', 'Materials', 'Alterations'],
    'food_beverage'    => ['Ingredients', 'Beverages', 'Snacks', 'Prepared Meals', 'Frozen', 'Bakery', 'Dairy', 'Condiments'],
    'tech_repair'      => ['Phones', 'Laptops', 'Accessories', 'Spare Parts', 'Screens', 'Batteries', 'Cables', 'Repair Service'],
    'distribution'     => ['Fast-Moving', 'Slow-Moving', 'Perishable', 'Non-Perishable', 'Bulk', 'Imported', 'Local'],
    'services'         => ['Consultation', 'Installation', 'Maintenance', 'Training', 'Support', 'Custom Work'],
    'wholesale_retail' => ['Electronics', 'Home & Living', 'Personal Care', 'Stationery', 'Clothing', 'Hardware', 'General'],
    'creative'         => ['Design Services', 'Print', 'Digital', 'Photography', 'Materials', 'Art Supplies', 'Commissions'],
    default            => ['General', 'Services', 'Supplies', 'Equipment', 'Materials', 'Custom'],
};
// Merge with existing categories
$all_cats = array_unique(array_merge($cat_suggestions, $categories_list ?? []));
sort($all_cats);

// Unit options
$units = [
    'pcs'     => 'Pieces (pcs)',
    'kg'      => 'Kilograms (kg)',
    'g'       => 'Grams (g)',
    'litres'  => 'Litres (L)',
    'ml'      => 'Millilitres (ml)',
    'yards'   => 'Yards (yd)',
    'meters'  => 'Metres (m)',
    'feet'    => 'Feet (ft)',
    'inches'  => 'Inches (in)',
    'boxes'   => 'Boxes',
    'packs'   => 'Packs',
    'pairs'   => 'Pairs',
    'dozen'   => 'Dozen',
    'cartons' => 'Cartons',
    'bags'    => 'Bags',
    'rolls'   => 'Rolls',
    'sheets'  => 'Sheets',
    'bottles' => 'Bottles',
    'crates'  => 'Crates',
    'sets'    => 'Sets',
    'hours'   => 'Hours (hr)',
    'sqm'     => 'Sq. Metres',
    'sqft'    => 'Sq. Feet',
];
?>

<!-- Page Header -->
<div class="flex items-center gap-3 mb-8">
    <a href="<?= APP_URL ?>/products" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
    </a>
    <h1 class="font-heading font-extrabold text-2xl text-brand-plum tracking-tight"><?= $editing ? 'Edit Product' : 'Add New Item' ?></h1>
</div>

<form method="POST" enctype="multipart/form-data"
      action="<?= APP_URL ?>/products/<?= $editing ? 'edit/' . (int)$product['id'] : 'create' ?>"
      x-data="{
          isService: <?= $p_service ? 'true' : 'false' ?>,
          autoSku: <?= ($editing && $p_sku !== '') ? 'false' : 'true' ?>,
          sellPrice: <?= (float)($p_sell ?: 0) ?>,
          costPrice: <?= (float)($p_cost ?: 0) ?>,
          get margin() {
              if (this.sellPrice <= 0) return 0;
              return Math.round((this.sellPrice - this.costPrice) / this.sellPrice * 100);
          }
      }">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <div class="grid lg:grid-cols-3 gap-6">

        <!-- ===== LEFT COLUMN (2/3) ===== -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Basic Information -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-5">Basic Information</h2>

                <!-- Name -->
                <div class="mb-5">
                    <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Product / Service Name <span class="text-red-500">*</span></label>
                    <input type="text" name="name" value="<?= $p_name ?>" required placeholder="e.g. Ankara Fabric, Phone Repair, Jollof Rice"
                           class="w-full px-4 py-3 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                </div>

                <!-- SKU Row -->
                <div class="mb-5">
                    <div class="flex items-center justify-between mb-2">
                        <label class="text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest">SKU</label>
                        <label class="flex items-center gap-2 cursor-pointer" x-show="!<?= $editing ? 'false' : 'true' ?> || true">
                            <input type="checkbox" name="auto_sku" value="1" x-model="autoSku" class="w-3.5 h-3.5 rounded border-brand-sand text-brand-gold focus:ring-brand-gold">
                            <span class="text-[9px] font-semibold text-brand-plum/50 uppercase tracking-wider">Auto-generate</span>
                        </label>
                    </div>
                    <input type="text" name="sku" value="<?= $p_sku ?>" placeholder="Auto-generated if left blank"
                           :disabled="autoSku" :class="autoSku ? 'bg-brand-sand/50 cursor-not-allowed' : 'bg-brand-cream'"
                           class="w-full px-4 py-3 text-sm font-medium font-mono border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                    <p class="text-[9px] text-brand-plum/30 mt-1" x-show="autoSku">Format: first 3 letters + ID number (e.g. ANK-0012)</p>
                </div>

                <!-- Category -->
                <div class="mb-5">
                    <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Category</label>
                    <input type="text" name="category" value="<?= e($p_category) ?>" list="category-list"
                           placeholder="Type or select a category"
                           class="w-full px-4 py-3 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                    <datalist id="category-list">
                        <?php foreach ($all_cats as $cat): ?>
                            <option value="<?= e($cat) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>

                <!-- Description -->
                <div>
                    <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Description <span class="text-brand-plum/30 font-normal">(optional)</span></label>
                    <textarea name="description" rows="3" placeholder="Brief description for invoices and quotations..."
                              class="w-full px-4 py-3 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25 resize-none"><?= e($p_description) ?></textarea>
                </div>
            </div>

            <!-- Pricing -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-5">Pricing</h2>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Selling Price (<?= e($currency) ?>) <span class="text-red-500">*</span></label>
                        <input type="number" name="sell_price" step="0.01" min="0" x-model.number="sellPrice"
                               value="<?= e($p_sell) ?>" placeholder="0.00"
                               class="w-full px-4 py-3 text-sm font-medium font-mono bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Cost Price (<?= e($currency) ?>)</label>
                        <input type="number" name="cost_price" step="0.01" min="0" x-model.number="costPrice"
                               value="<?= e($p_cost) ?>" placeholder="0.00"
                               class="w-full px-4 py-3 text-sm font-medium font-mono bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Margin</label>
                        <div class="w-full px-4 py-3 bg-brand-sand/30 border border-brand-sand rounded-lg">
                            <span class="data-text text-sm font-bold"
                                  :class="margin > 30 ? 'text-green-600' : (margin > 0 ? 'text-amber-600' : 'text-red-600')"
                                  x-text="margin + '%'">0%</span>
                        </div>
                        <p class="text-[8px] text-brand-plum/25 mt-1">Profit as % of sell price</p>
                    </div>
                </div>
            </div>

            <!-- Stock & Unit (hidden for services) -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm" x-show="!isService" x-transition>
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-5">Stock & Units</h2>

                <div class="grid sm:grid-cols-3 gap-4">
                    <?php if (!$editing): ?>
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Opening Stock</label>
                        <input type="number" name="stock_qty" step="0.01" min="0" value="<?= e($p_stock) ?>" placeholder="0"
                               class="w-full px-4 py-3 text-sm font-medium font-mono bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                        <p class="text-[9px] text-brand-plum/30 mt-1">Logged as initial stock-in</p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Low Stock Alert At</label>
                        <input type="number" name="low_stock_threshold" step="0.01" min="0" value="<?= e($p_threshold) ?>" placeholder="5"
                               class="w-full px-4 py-3 text-sm font-medium font-mono bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                    </div>
                    <div>
                        <label class="block text-[10px] font-bold text-brand-plum/70 uppercase tracking-widest mb-2">Unit of Measure</label>
                        <select name="unit" class="w-full px-4 py-3 text-sm font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum">
                            <?php foreach ($units as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $p_unit === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== RIGHT COLUMN (1/3) ===== -->
        <div class="space-y-6">

            <!-- Service Toggle -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Item Type</h2>
                <label class="flex items-center gap-3 cursor-pointer p-3 rounded-lg border transition-all"
                       :class="isService ? 'border-blue-400 bg-blue-50' : 'border-brand-sand bg-brand-cream/50'">
                    <input type="checkbox" name="is_service" value="1" x-model="isService"
                           class="w-4 h-4 rounded border-brand-sand text-blue-500 focus:ring-blue-500">
                    <div>
                        <div class="text-xs font-bold text-brand-plum">This is a service</div>
                        <div class="text-[10px] text-brand-plum/50 mt-0.5">Stock tracking will be bypassed</div>
                    </div>
                </label>
                <div x-show="isService" x-transition class="mt-3 p-3 bg-blue-50/50 rounded-lg border border-blue-200/50">
                    <p class="text-[10px] text-blue-700 font-medium leading-relaxed">Services don't carry inventory. They appear on invoices and quotations but stock quantities won't be tracked or deducted.</p>
                </div>
            </div>

            <!-- Multi-Image Upload -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm"
                 x-data="{
                    previews: [],
                    addPreviews(files) {
                        for (let f of files) {
                            const reader = new FileReader();
                            reader.onload = e => this.previews.push(e.target.result);
                            reader.readAsDataURL(f);
                        }
                    },
                    async deleteImage(imgId, el) {
                        if (!confirm('Delete this image?')) return;
                        const fd = new FormData();
                        fd.append('csrf_token', Nfelia.csrf);
                        const resp = await fetch(Nfelia.url + '/products/delete-image/' + imgId, { method: 'POST', body: fd });
                        if (resp.ok) el.closest('[data-img]').remove();
                    },
                    async setCover(imgId) {
                        const fd = new FormData();
                        fd.append('csrf_token', Nfelia.csrf);
                        await fetch(Nfelia.url + '/products/set-cover/' + imgId, { method: 'POST', body: fd });
                        location.reload();
                    }
                 }">
                <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest mb-4">Product Images</h2>

                <!-- Existing images (edit mode) -->
                <?php if (!empty($product_images)): ?>
                <div class="grid grid-cols-3 gap-2 mb-4">
                    <?php foreach ($product_images as $img): ?>
                    <div data-img class="relative group rounded-lg overflow-hidden border border-brand-sand bg-brand-cream aspect-square">
                        <img src="<?= APP_URL ?>/<?= e($img['image_path']) ?>" class="w-full h-full object-cover">
                        <?php if ($img['is_cover']): ?>
                            <span class="absolute top-1 left-1 text-[7px] font-bold uppercase tracking-wider bg-brand-primary text-white px-1.5 py-0.5 rounded">Cover</span>
                        <?php endif; ?>
                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center gap-2">
                            <?php if (!$img['is_cover']): ?>
                            <button type="button" @click="setCover(<?= (int)$img['id'] ?>)" class="text-white text-[8px] font-bold uppercase bg-white/20 px-2 py-1 rounded hover:bg-white/40">Cover</button>
                            <?php endif; ?>
                            <button type="button" @click="deleteImage(<?= (int)$img['id'] ?>, $el)" class="text-white text-[8px] font-bold uppercase bg-red-500/60 px-2 py-1 rounded hover:bg-red-500">Delete</button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- New upload previews -->
                <template x-if="previews.length > 0">
                    <div class="grid grid-cols-3 gap-2 mb-4">
                        <template x-for="(src, i) in previews" :key="i">
                            <div class="rounded-lg overflow-hidden border border-brand-gold bg-brand-cream aspect-square relative">
                                <img :src="src" class="w-full h-full object-cover">
                                <span class="absolute top-1 left-1 text-[7px] font-bold uppercase tracking-wider bg-green-500 text-white px-1.5 py-0.5 rounded">New</span>
                            </div>
                        </template>
                    </div>
                </template>

                <label class="block w-full text-center cursor-pointer border-2 border-dashed border-brand-sand hover:border-brand-gold rounded-lg py-4 transition-all">
                    <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden"
                           @change="addPreviews($event.target.files)">
                    <svg class="w-5 h-5 text-brand-plum/30 mx-auto mb-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                    <span class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest">Upload Images</span>
                    <span class="block text-[8px] text-brand-plum/20 mt-0.5">JPG, PNG or WebP · Max 2 MB each · Multiple allowed</span>
                </label>
            </div>

            <!-- Variant Options -->
            <div class="bg-brand-white border border-brand-sand rounded-xl p-6 shadow-sm"
                 x-data="{
                    groups: <?= e(json_encode(array_map(function($g) {
                        return ['name' => $g['name'], 'options' => $g['options_str'] ?? ''];
                    }, $variant_groups ?? []), JSON_HEX_APOS | JSON_HEX_QUOT)) ?>,
                    addGroup() { this.groups.push({ name: '', options: '' }); },
                    removeGroup(i) { this.groups.splice(i, 1); }
                 }">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Variants</h2>
                    <button type="button" @click="addGroup()" class="text-[9px] font-bold uppercase tracking-wider px-3 py-1.5 rounded-lg border border-brand-sand hover:border-brand-gold text-brand-plum/60 hover:text-brand-plum transition-all">+ Add Option Group</button>
                </div>
                <p class="text-[9px] text-brand-plum/40 mb-4" x-show="groups.length === 0">No variants defined. Add option groups like Color, Size, etc.</p>
                <template x-for="(group, idx) in groups" :key="idx">
                    <div class="mb-3 p-3 bg-brand-cream rounded-lg border border-brand-sand">
                        <div class="flex items-center gap-2 mb-2">
                            <input type="text" :name="'variant_group_name[' + idx + ']'" x-model="group.name" placeholder="e.g. Color, Size"
                                   class="flex-1 px-3 py-2 text-xs font-bold bg-white border border-brand-sand rounded focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                            <button type="button" @click="removeGroup(idx)" class="text-red-400 hover:text-red-600 transition-colors p-1">
                                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                        </div>
                        <input type="text" :name="'variant_group_options[' + idx + ']'" x-model="group.options" placeholder="Comma-separated: Black, White, Red"
                               class="w-full px-3 py-2 text-xs bg-white border border-brand-sand rounded focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/25">
                    </div>
                </template>
            </div>

            <!-- Action Buttons -->
            <div class="space-y-3">
                <button type="submit" class="w-full btn-primary text-white py-3.5 rounded-xl text-[11px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
                    <?= $editing ? 'Save Changes' : 'Add to Inventory' ?>
                </button>
                <a href="<?= APP_URL ?>/products<?= $editing ? '/view/' . (int)$product['id'] : '' ?>"
                   class="block w-full text-center bg-brand-cream border border-brand-sand text-brand-plum py-3.5 rounded-xl text-[11px] font-bold uppercase tracking-widest hover:border-brand-gold transition-all">
                    Cancel
                </a>
                <?php if ($editing): ?>
                    <form method="POST" action="<?= APP_URL ?>/products/duplicate/<?= (int)$product['id'] ?>" class="pt-2">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
                        <button type="submit" class="w-full text-center text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-gold transition-colors py-2">
                            Duplicate This Product
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>