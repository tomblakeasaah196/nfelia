<?php
/**
 * Add or edit an item in the catalogue. One column, phone first, in the
 * app's own sections, fields and buttons (as New order): photos first, as
 * they matter most on the shop; what it is; its price; its stock (or "this
 * is a service"); options such as length or colour; bookings, only for a
 * business that sells services or when it is one; the item code. The button
 * stays in reach at the bottom. Pictures go up with a progress bar (the
 * script at the end). Renders inside views/layouts/app.php.
 *
 * With AI on for the business, the photo screen comes first on a new item,
 * and "Fill from a photo" opens it from the form (views/products/ai_photo.php).
 *
 * Available from controller:
 *   $user, $business, $product (array or empty), $editing (bool), $categories_list,
 *   $product_images, $variant_groups, $ai (product_ai_state()), $ai_first (open the photo screen first)
 */
$currency  = $business['currency_symbol'] ?? '₦';
$biz_cat   = $business['category'] ?? 'other';
// Bookings are for services: shown when the business sells them, when this
// item is one, or when it is booked already (its fields are always sent)
$sells_services = in_array($business['business_type'] ?? 'product', ['service', 'mixed'], true);

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
$p_on_shop     = (string)($product['on_shop'] ?? '1') === '1';   // a new item goes on the shop unless they say
$ai_on         = !empty($ai['on']);
$ai_first      = $ai_on && !empty($ai_first);

// Suggested categories based on business type
$cat_suggestions = match($biz_cat) {
    'fashion'          => ['Fabrics', 'Ready-to-Wear', 'Accessories', 'Footwear', 'Bags', 'Jewelry', 'Materials', 'Alterations'],
    'beauty'           => ['Wigs', 'Bundles', 'Closures', 'Frontals', 'Edge Control'],
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

// Unit options. The key is what is saved (products.unit) and what
// stock_count() says: "1 bundle", "8 bundles".
$units = [
    'pcs'     => 'Pieces (pcs)',
    'bundle'  => 'Bundles',
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
// A unit from elsewhere (a CSV import) stays chosen, rather than turning into pieces on save
if ($p_unit !== '' && !isset($units[$p_unit])) {
    $units = [$p_unit => ucfirst($p_unit)] + $units;
}

$p_mode     = in_array($product['booking_mode'] ?? 'none', BOOKING_MODES, true) ? ($product['booking_mode'] ?? 'none') : 'none';
$p_minutes  = (int)($product['booking_minutes'] ?? 60) ?: 60;
$p_capacity = max(1, (int)($product['booking_capacity'] ?? 1));
$p_dep_type = in_array($product['deposit_type'] ?? 'none', ['none', 'percent', 'fixed'], true) ? ($product['deposit_type'] ?? 'none') : 'none';
$p_dep_val  = $product['deposit_value'] ?? '';
// Stays: a refundable caution fee, and cheaper prices for longer stays as typed
$p_caution  = isset($product['caution_fee']) && (float)$product['caution_fee'] > 0 ? (string)(float)$product['caution_fee'] : '';
$p_rates    = array_map(fn($r) => ['from' => (string)$r[0], 'price' => (string)(float)$r[1]],
                        booking_clean_rates($product['stay_rates'] ?? null, 0.0, false));

$name_hint = $biz_cat === 'beauty' ? 'e.g. Body wave bundle, 18 inches' : 'e.g. Ankara fabric, phone repair, jollof rice';
// Empty stays empty (not 0), so "leave it empty" reads true
$js_price  = fn($v) => ($v === '' || $v === null) ? "''" : (string)(float)$v;
?>
<div class="nf-page nf-oform nf-iform">
<h1 class="sr-only"><?= $editing ? 'Edit ' . $p_name : 'Add an item' ?></h1>

<?php if ($ai_on) require BASE_PATH . '/views/products/ai_photo.php'; ?>

<form method="POST" enctype="multipart/form-data" id="catalogue-form" data-no-spin<?= $ai_first ? ' hidden' : '' ?>
      action="<?= APP_URL ?>/products/<?= $editing ? 'edit/' . (int)$product['id'] : 'create' ?>"
      x-data="{
          isService: <?= $p_service ? 'true' : 'false' ?>,
          sellsServices: <?= $sells_services ? 'true' : 'false' ?>,
          autoSku: <?= ($editing && $p_sku !== '') ? 'false' : 'true' ?>,
          sellPrice: <?= $js_price($p_sell) ?>,
          costPrice: <?= $js_price($p_cost) ?>,
          get hasMargin() { return this.sellPrice > 0 && this.costPrice > 0; },
          get margin() {
              if (!this.hasMargin) return 0;
              return Math.round((this.sellPrice - this.costPrice) / this.sellPrice * 100);
          }
      }">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">

    <!-- Photos first: they sell it on the shop -->
    <section class="nf-sec" aria-labelledby="nf-if-photos-h"
             x-data="{
                previews: [],
                addPreviews(files) {
                    // A new pick replaces the last one, as the file box does
                    this.previews = [];
                    for (let f of files) {
                        const reader = new FileReader();
                        reader.onload = e => this.previews.push(e.target.result);
                        reader.readAsDataURL(f);
                    }
                },
                async deleteImage(imgId, el) {
                    if (!confirm('Delete this photo?')) return;
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
        <h2 id="nf-if-photos-h">Photos</h2>

        <?php if (!empty($product_images)): ?>
        <div class="nf-if-photos">
            <?php foreach ($product_images as $img): ?>
            <div data-img class="nf-if-photo group">
                <img src="<?= APP_URL ?>/<?= e($img['image_path']) ?>" alt="" loading="lazy" decoding="async">
                <?php if ($img['is_cover']): ?>
                    <span class="nf-if-badge">Cover</span>
                <?php endif; ?>
                <?php /* Always showing on touch screens, which have no hover; on hover only where there is a mouse */ ?>
                <div class="nf-if-photo-do [@media(hover:hover)]:opacity-0 [@media(hover:hover)]:group-hover:opacity-100 focus-within:opacity-100">
                    <?php if (!$img['is_cover']): ?>
                    <button type="button" @click="setCover(<?= (int)$img['id'] ?>)" aria-label="Make this the cover photo">Cover</button>
                    <?php endif; ?>
                    <button type="button" class="is-danger" @click="deleteImage(<?= (int)$img['id'] ?>, $el)" aria-label="Delete this photo">Delete</button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="nf-if-photos" x-show="previews.length > 0" x-cloak>
            <template x-for="(src, i) in previews" :key="i">
                <div class="nf-if-photo"><img :src="src" alt=""><span class="nf-if-badge is-new">New</span></div>
            </template>
        </div>

        <label class="nf-file nf-if-add">
            <?= nav_icon('image') ?>
            <span>
                <b x-text="previews.length ? 'Pick other photos' : 'Add photos'">Add photos</b>
                <small>JPG, PNG or WebP, up to 20 MB each. <?= empty($product_images) ? 'The first one is the cover on your shop.' : 'New ones go after these.' ?></small>
            </span>
            <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple
                   @change="addPreviews($event.target.files)">
        </label>
    </section>

    <!-- On the shop, or only in the catalogue. The hidden 0 is sent when the switch is off. -->
    <section class="nf-sec" aria-labelledby="nf-if-shop-h">
        <h2 id="nf-if-shop-h">Your shop</h2>
        <input type="hidden" name="on_shop" value="0">
        <label class="nf-switch"><input type="checkbox" role="switch" name="on_shop" value="1"<?= $p_on_shop ? ' checked' : '' ?>>
            <span><b>Show on my shop</b><small>Off: it stays in your catalogue and on invoices, quotes and orders, but your shop doesn't show it.</small></span></label>
        <?php if ($editing && isset($product['is_active']) && !(int)$product['is_active']): ?>
            <p class="nf-help">It is hidden now, so your shop leaves it out either way. Show it again from its page.</p>
        <?php endif; ?>
    </section>

    <!-- What it is -->
    <section class="nf-sec" aria-labelledby="nf-if-about-h">
        <h2 id="nf-if-about-h">About it</h2>
        <?php if ($ai_on): ?>
        <button type="button" class="nf-ai-fill" @click="window.dispatchEvent(new CustomEvent('nf-ai-open'))"><?= nav_icon('sparkle') ?>
            <span><b>Fill from a photo</b><small>Its name, description and category, written for you</small></span></button>
        <?php endif; ?>
        <label>Name
            <input type="text" name="name" value="<?= $p_name ?>" required placeholder="<?= e($name_hint) ?>" autocomplete="off">
        </label>
        <label>Category <small>Optional. Groups it on your shop.</small>
            <input type="text" name="category" value="<?= e($p_category) ?>" list="category-list" placeholder="Type or pick one" autocomplete="off">
            <datalist id="category-list">
                <?php foreach ($all_cats as $cat): ?>
                    <option value="<?= e($cat) ?>">
                <?php endforeach; ?>
            </datalist>
        </label>
        <label>Description <small>Optional. For your shop, invoices and quotes.</small>
            <textarea name="description" rows="3"><?= e($p_description) ?></textarea>
        </label>
    </section>

    <!-- Price -->
    <section class="nf-sec" aria-labelledby="nf-if-price-h">
        <h2 id="nf-if-price-h">Price</h2>
        <div class="nf-two">
            <label>Selling price
                <span class="nf-amount"><span><?= e($currency) ?></span><input type="number" name="sell_price" step="0.01" min="0" inputmode="decimal" x-model.number="sellPrice"
                       value="<?= e($p_sell) ?>" placeholder="0"></span>
            </label>
            <label>Cost price
                <span class="nf-amount"><span><?= e($currency) ?></span><input type="number" name="cost_price" step="0.01" min="0" inputmode="decimal" x-model.number="costPrice"
                       value="<?= e($p_cost) ?>" placeholder="0"></span>
            </label>
        </div>
        <?php /* No price is allowed: the shop then shows "Ask for the price" (sf_price_html) */ ?>
        <p class="nf-help">Leave the selling price empty to show “Ask for the price” on your shop. The cost is only for you.</p>
        <?php /* Neutral until both prices are in; red only when it sells below its cost */ ?>
        <p class="nf-if-margin">Margin
            <b :class="hasMargin && margin < 0 ? 'is-owed' : ''" x-text="hasMargin ? margin + '%' : '—'">—</b>
            <small x-text="!hasMargin ? 'Shows once both prices are in' : (margin < 0 ? 'It sells for less than it costs you' : 'Your profit as a share of the price')">Shows once both prices are in</small>
        </p>
    </section>

    <!-- Stock, or a service -->
    <section class="nf-sec" aria-labelledby="nf-if-stock-h">
        <h2 id="nf-if-stock-h">Stock</h2>
        <label class="nf-switch"><input type="checkbox" role="switch" name="is_service" value="1" x-model="isService">
            <span><b>This is a service</b><small>Like a styling or a repair: there is no stock to count.</small></span></label>
        <div class="nf-if-stock" x-show="!isService" x-transition>
            <?php if (!$editing): ?>
            <label>How many you have now <small>Saved as your opening stock</small>
                <input type="number" name="stock_qty" step="0.01" min="0" inputmode="decimal" value="<?= e($p_stock) ?>" placeholder="0">
            </label>
            <?php endif; ?>
            <div class="nf-two">
                <label>Counted in
                    <select name="unit">
                        <?php foreach ($units as $val => $label): ?>
                            <option value="<?= e($val) ?>" <?= $p_unit === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label>Remind me at
                    <input type="number" name="low_stock_threshold" step="0.01" min="0" inputmode="decimal" value="<?= e($p_threshold) ?>" placeholder="5">
                </label>
            </div>
            <p class="nf-help">Nfelia reminds you to restock when it gets down to this many.<?= $editing ? ' To change how many you have, use Add stock on the item’s page.' : '' ?></p>
        </div>
    </section>

    <!-- Options: lengths, colours, sizes -->
    <section class="nf-sec" aria-labelledby="nf-if-opts-h"
             x-data="{
                groups: <?= e(json_encode(array_map(function($g) {
                    $priced = (bool)array_filter($g['options'] ?? [], fn($o) => (float)($o['price_delta'] ?? 0) != 0.0);
                    return [
                        'name'    => $g['name'],
                        'priced'  => $priced,
                        'options' => array_map(function($o) {
                            $d = (float)($o['price_delta'] ?? 0);
                            return ['label' => $o['label'], 'price' => $d != 0.0 ? rtrim(rtrim(number_format($d, 2, '.', ''), '0'), '.') : ''];
                        }, $g['options'] ?? []),
                    ];
                }, $variant_groups ?? []), JSON_HEX_APOS | JSON_HEX_QUOT)) ?>,
                addGroup() { this.groups.push({ name: '', priced: false, options: [{ label: '', price: '' }] }); },
                removeGroup(i) { this.groups.splice(i, 1); },
                addOption(g) { g.options.push({ label: '', price: '' }); },
                removeOption(g, j) { g.options.splice(j, 1); if (!g.options.length) g.options.push({ label: '', price: '' }); }
             }">
        <h2 id="nf-if-opts-h">Options</h2>
        <p class="nf-help" x-show="groups.length === 0">No options. Add them if this comes in different lengths, colours or sizes.</p>
        <template x-for="(group, gi) in groups" :key="gi">
            <div class="nf-if-group">
                <div class="nf-if-group-h">
                    <label>Name
                        <input type="text" :name="'variant_group_name[' + gi + ']'" x-model="group.name" placeholder="e.g. Length or Colour" autocomplete="off">
                    </label>
                    <button type="button" class="nf-icon-btn" @click="removeGroup(gi)" aria-label="Remove these options"><?= nav_icon('trash') ?></button>
                </div>

                <p class="nf-q">Choices</p>
                <template x-for="(opt, oi) in group.options" :key="oi">
                    <div class="nf-if-opt">
                        <input type="text" :name="'variant_opt_label[' + gi + '][]'" x-model="opt.label" placeholder="e.g. 16 inches" aria-label="Choice" autocomplete="off">
                        <span class="nf-amount" x-show="group.priced" x-cloak title="Added to or taken off the price">
                            <span>±<?= e($currency) ?></span>
                            <input type="text" inputmode="decimal" :name="'variant_opt_price[' + gi + '][]'" x-model="opt.price" placeholder="0" :disabled="!group.priced" aria-label="Price change">
                        </span>
                        <button type="button" class="nf-icon-btn" @click="removeOption(group, oi)" aria-label="Remove this choice"><?= nav_icon('close') ?></button>
                    </div>
                </template>
                <button type="button" class="nf-link" @click="addOption(group)">Add another choice</button>

                <label class="nf-switch"><input type="checkbox" role="switch" x-model="group.priced">
                    <span><b>This changes the price</b><small>Type how much each choice adds or takes off</small></span></label>
                <p class="nf-help" x-show="group.priced" x-cloak>Use a minus for cheaper choices, like −2000. Leave it empty for no change.</p>
            </div>
        </template>
        <button type="button" class="nf-add" @click="addGroup()"><?= nav_icon('plus') ?><span x-text="groups.length ? 'Add another, like colour' : 'Add options, like length or colour'">Add options, like length or colour</span></button>
    </section>

    <!-- Bookings: people pick a time on the shop and pay a deposit -->
    <section class="nf-sec" aria-labelledby="nf-if-book-h"
             x-data="{ mode: '<?= e($p_mode) ?>', deposit: '<?= e($p_dep_type) ?>' }"
             x-show="sellsServices || isService || mode !== 'none'"<?= ($sells_services || $p_service || $p_mode !== 'none') ? '' : ' x-cloak' ?>>
        <h2 id="nf-if-book-h">Bookings</h2>
        <p class="nf-help">Let people pick a free time on your shop and pay a deposit to hold it.</p>
        <label>People book this
            <select name="booking_mode" x-model="mode">
                <option value="none" <?= $p_mode === 'none' ? 'selected' : '' ?>>No, people buy it</option>
                <option value="slot" <?= $p_mode === 'slot' ? 'selected' : '' ?>>A time slot, like an appointment</option>
                <option value="nights" <?= $p_mode === 'nights' ? 'selected' : '' ?>>By the night, like a room or apartment</option>
                <option value="day" <?= $p_mode === 'day' ? 'selected' : '' ?>>A whole day, like an event</option>
            </select>
        </label>
        <div x-show="mode !== 'none'" x-cloak class="nf-sub-q">
            <label x-show="mode === 'slot'">How long it takes
                <select name="booking_minutes">
                    <?php foreach ([15, 30, 45, 60, 90, 120, 150, 180, 240, 300, 360, 480] as $mins): ?>
                    <option value="<?= $mins ?>" <?= $p_minutes === $mins ? 'selected' : '' ?>><?= e(booking_duration_text($mins)) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label x-show="mode === 'day'">Bookings you can take a day
                <input type="number" name="booking_capacity" min="1" max="20" inputmode="numeric" value="<?= $p_capacity ?>">
            </label>
            <p class="nf-help" x-show="mode === 'nights'">The price is per night. Check-in and check-out times, and your house rules, are in Booking hours.</p>
            <div class="nf-sub-q" x-show="mode === 'nights'" x-cloak
                 x-data="{ rates: <?= e(json_encode($p_rates ?: [['from' => '', 'price' => '']])) ?> }">
                <label>Caution fee <small>Refundable</small>
                    <span class="nf-amount"><span><?= e($currency) ?></span><input type="number" name="caution_fee" min="0" step="0.01" inputmode="decimal" value="<?= e($p_caution) ?>" placeholder="0"></span>
                </label>
                <p class="nf-help">Guests pay it with the booking, and you give it back after they check out: we remind you. It isn't part of the stay's price, so it stays out of your money in.</p>
                <p class="nf-q">Cheaper for longer stays</p>
                <template x-for="(rate, i) in rates" :key="i">
                    <div class="nf-sub-q">
                        <div class="nf-two">
                            <label>From how many nights
                                <input type="number" name="stay_from[]" min="2" max="<?= BOOKING_MAX_NIGHTS ?>" inputmode="numeric" x-model="rate.from" placeholder="7">
                            </label>
                            <label>Price a night
                                <span class="nf-amount"><span><?= e($currency) ?></span><input type="number" name="stay_price[]" min="0" step="0.01" inputmode="decimal" x-model="rate.price" placeholder="75000"></span>
                            </label>
                        </div>
                        <p class="nf-q" x-show="rate.price !== '' && sellPrice > 0 && +rate.price >= sellPrice" x-cloak><span class="is-owed">Make it less than the nightly price, or guests won't see it.</span></p>
                    </div>
                </template>
                <button type="button" class="nf-add" x-show="rates.length < 3" @click="rates.push({ from: '', price: '' })"><?= nav_icon('plus') ?><span>Add a price for longer stays</span></button>
                <p class="nf-help">For example 7 nights or more at <?= e($currency) ?>75,000 a night. A stay pays the cheapest price it reaches. Leave a row empty to take it off.</p>
            </div>
            <div class="nf-if-deposit">
                <label>Deposit to book
                    <select name="deposit_type" x-model="deposit">
                        <option value="none" <?= $p_dep_type === 'none' ? 'selected' : '' ?>>No deposit</option>
                        <option value="percent" <?= $p_dep_type === 'percent' ? 'selected' : '' ?>>A share (%)</option>
                        <option value="fixed" <?= $p_dep_type === 'fixed' ? 'selected' : '' ?>>An amount (<?= e($currency) ?>)</option>
                    </select>
                </label>
                <label x-show="deposit !== 'none'">How much
                    <input type="number" name="deposit_value" min="0" step="0.01" inputmode="decimal" value="<?= e((string)$p_dep_val) ?>">
                </label>
            </div>
            <p class="nf-help">Paid by transfer to your bank account. You confirm each booking once you see it.</p>
            <a href="<?= APP_URL ?>/bookings/settings" class="nf-link">Set your booking hours</a>
        </div>
    </section>

    <!-- The item code -->
    <section class="nf-sec" aria-labelledby="nf-if-code-h">
        <h2 id="nf-if-code-h">Item code</h2>
        <label class="nf-switch"><input type="checkbox" role="switch" name="auto_sku" value="1" x-model="autoSku">
            <span><b>Make the code for me</b><small>Like ANK-0012: the first 3 letters of the name and a number</small></span></label>
        <label x-show="!autoSku" x-cloak>Your code <small>Also called SKU</small>
            <input type="text" name="sku" value="<?= $p_sku ?>" :disabled="autoSku" placeholder="Made for you if left empty" autocomplete="off">
        </label>
    </section>

    <!-- The one button, always in reach -->
    <div class="nf-sticky nf-if-go">
        <a class="nf-link" href="<?= APP_URL ?>/products<?= $editing ? '/view/' . (int)$product['id'] : '' ?>">Cancel</a>
        <button type="submit" class="nf-btn nf-btn-primary"><?= $editing ? 'Save changes' : 'Add to catalogue' ?></button>
    </div>
    <?php if ($editing): ?>
        <?php /* A form can't sit inside the edit form (D8): this button
                 sends the edit form's token to the duplicate address */ ?>
        <section class="nf-sec is-quiet">
            <button type="submit" formaction="<?= APP_URL ?>/products/duplicate/<?= (int)$product['id'] ?>" formnovalidate class="nf-link">Duplicate this item</button>
        </section>
    <?php endif; ?>
</form>
</div>

<!-- Upload progress: fills 0 → 100% while the pictures go up, then the next page confirms with a toast. -->
<div class="nf-upload" id="nf-upload" hidden role="status" aria-live="polite">
    <div class="nf-upload-card">
        <h3 id="nf-upload-title"><?= $editing ? 'Saving changes…' : 'Adding to your catalogue…' ?></h3>
        <p id="nf-upload-sub">Uploading your pictures. Keep this page open.</p>
        <div class="nf-upload-track"><div class="nf-upload-bar" id="nf-upload-bar"></div></div>
        <div class="nf-upload-pct" id="nf-upload-pct">0%</div>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('catalogue-form');
    if (!form || !window.FormData || !window.XMLHttpRequest) return;   // old browser: plain POST still works

    var overlay = document.getElementById('nf-upload');
    var bar     = document.getElementById('nf-upload-bar');
    var pct     = document.getElementById('nf-upload-pct');
    var sub     = document.getElementById('nf-upload-sub');
    var sending = false;

    function setPct(n) {
        n = Math.max(0, Math.min(100, Math.round(n)));
        if (bar) bar.style.width = n + '%';
        if (pct) pct.textContent = n + '%';
    }
    function toast(msg, type) {
        if (window.nfToast) window.nfToast(msg, type); else if (type === 'error') alert(msg);
    }

    form.addEventListener('submit', function (e) {
        // "Duplicate" posts to a different address (formaction) — let it submit normally.
        if (e.submitter && e.submitter.getAttribute('formaction')) return;
        if (sending) { e.preventDefault(); return; }
        // If the browser would block the submit for a missing field, let it show the hints.
        if (form.checkValidity && !form.checkValidity()) return;

        e.preventDefault();
        sending = true;

        var submitBtn = form.querySelector('button[type="submit"]:not([formaction])');
        if (window.nfSpinButton && submitBtn) window.nfSpinButton(submitBtn);

        if (overlay) { overlay.hidden = false; overlay.classList.remove('is-done', 'is-working'); }
        setPct(0);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        xhr.upload.addEventListener('progress', function (ev) {
            if (ev.lengthComputable) setPct(ev.loaded / ev.total * 100);
        });
        // Bytes are all sent; the server is now saving and resizing the images.
        xhr.upload.addEventListener('load', function () {
            setPct(100);
            if (overlay) overlay.classList.add('is-working');
            if (sub) sub.textContent = 'Finishing up…';
        });

        xhr.addEventListener('load', function () {
            if (overlay) overlay.classList.remove('is-working');
            var res = null;
            try { res = JSON.parse(xhr.responseText); } catch (err) {}
            if (xhr.status >= 200 && xhr.status < 300 && res && res.success && res.redirect) {
                setPct(100);
                if (overlay) overlay.classList.add('is-done');
                if (sub) sub.textContent = 'Done.';
                // The page we land on shows the green success toast.
                window.location.assign(res.redirect);
                return;
            }
            fail(res && res.message ? res.message : 'Could not save this. Check the form and try again.');
        });
        xhr.addEventListener('error', function () { fail('Upload failed. Check your connection and try again.'); });
        xhr.addEventListener('abort', function () { fail('Upload stopped.'); });

        function fail(msg) {
            sending = false;
            if (overlay) { overlay.hidden = true; overlay.classList.remove('is-working', 'is-done'); }
            if (window.unspinBtn && submitBtn) window.unspinBtn(submitBtn);
            toast(msg, 'error');
        }

        xhr.send(new FormData(form));
    });
})();
</script>