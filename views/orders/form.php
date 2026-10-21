<?php
/**
 * New order and Change items: one screen, top to bottom. Customer · Items ·
 * In the shop or online (and, online, pickup or delivery) · Payment, with the
 * total and one button always in reach at the bottom. A sale in the shop,
 * paid in full and taken now, is saved as a quick sale. Items are picked from
 * a photo list, then chosen (colour, size, how many) in one step; on the
 * screen each is one short row that opens that step again. Driven by
 * orderForm() in assets/js/orders.js; the server checks everything again.
 * Renders inside views/layouts/app.php.
 *
 * Available from controller:
 *   $user, $business, $form_mode (new | quick | edit), $old (what was sent, or
 *   the order's lines when changing items), $order (edit), $products, $picked, $error
 */
$currency = $business['currency_symbol'] ?? '₦';
$today    = planner_today();
$is_edit  = $form_mode === 'edit';

$paid_so_far = 0.0;
if ($is_edit) {
    $inv = order_invoice(get_db(), (int)$order['business_id'], $order);
    $paid_so_far = $inv ? (float)$inv['amount_paid'] : 0.0;
}

$data = [
    'mode'      => $form_mode,
    'currency'  => $currency,
    'today'     => $today,
    'tomorrow'  => planner_add_days($today, 1),
    'week'      => planner_add_days($today, 7),
    'products'  => $products,
    'customer'  => $picked,
    'paidSoFar' => $paid_so_far,
    'searchUrl' => APP_URL . '/orders/customers',
    'draftKey'  => $is_edit ? null : 'nf-order-draft',
    'lastKey'   => 'nf-order-last-' . (int)$business['id'],
    'restore'   => empty($old) && !$is_edit,
    'old'       => [
        'customer_name'    => (string)($old['customer_name'] ?? ''),
        'customer_phone'   => (string)($old['customer_phone'] ?? ''),
        'customer_email'   => (string)($old['customer_email'] ?? ''),
        'items'            => array_values(array_map(fn($i) => [
            'product_id' => (int)($i['product_id'] ?? 0) ?: null, 'name' => (string)($i['name'] ?? ''),
            'quantity' => (string)($i['quantity'] ?? '1'), 'unit_price' => (string)($i['unit_price'] ?? ''), 'note' => (string)($i['note'] ?? ''),
            'variant' => (string)($i['variant'] ?? ''),
        ], array_filter((array)($old['items'] ?? []), 'is_array'))),
        'discount'         => (string)($old['discount'] ?? ''),
        'sale'             => in_array($old['sale'] ?? '', ['shop', 'online'], true) ? $old['sale'] : '',
        'fulfillment'      => (string)($old['fulfillment'] ?? 'now'),
        'deliver_by'       => (string)($old['deliver_by'] ?? ''),
        'delivery_address' => (string)($old['delivery_address'] ?? ''),
        'pay'              => (string)($old['pay'] ?? 'now'),
        'paid_now'         => (string)($old['paid_now'] ?? ''),
        'method'           => (string)($old['method'] ?? 'cash'),
        'reference'        => (string)($old['reference'] ?? ''),
        'pay_by'           => (string)($old['pay_by'] ?? ''),
        'note'             => (string)($old['note'] ?? ''),
        'use_kept'         => !empty($old['use_kept']),
        'walk_in'          => !empty($old['walk_in']) && empty($old['customer_name']) && empty($old['customer_id']),
    ],
];
$action = $is_edit ? APP_URL . '/orders/update/' . (int)$order['id'] : APP_URL . '/orders/save';
?>
<script type="application/json" id="order-form-data"><?= json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?></script>

<div class="nf-page nf-oform" x-data="orderForm()" x-cloak>
<h1 class="sr-only"><?= $is_edit ? 'Change items' : 'New order' ?></h1>
<?php if ($is_edit): ?>
    <p class="nf-sub">Order <?= e($order['order_number']) ?> for <?= e($order['customer_name']) ?>. Its invoice keeps its number; stock follows the change.</p>
<?php endif; ?>

<form method="POST" action="<?= $action ?>" @submit="submit($event)" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><?= $is_edit ? '' : form_once() ?>
    <?php if ($is_edit): ?><input type="hidden" name="mode" value="edit"><?php else: ?><input type="hidden" name="mode" :value="isQuick() ? 'quick' : 'new'"><input type="hidden" name="sale" :value="old.sale"><input type="hidden" name="walk_in" :value="walkIn ? '1' : ''"><?php endif; ?>
    <?php if ($error !== ''): ?><p class="nf-err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <p class="nf-err" role="alert" x-show="problem" x-text="problem"></p>

    <?php if (!$is_edit): ?>
    <!-- Customer -->
    <section class="nf-sec" aria-labelledby="nf-sec-c">
        <h2 id="nf-sec-c">Customer</h2>

        <template x-if="customer">
            <div class="nf-picked">
                <span class="nf-face" x-text="initials(customer.name)" aria-hidden="true"></span>
                <span class="nf-picked-main"><b x-text="customer.name"></b><small x-text="customer.phone || 'No phone'"></small></span>
                <button type="button" class="nf-link" @click="clearCustomer()">Change</button>
                <input type="hidden" name="customer_id" :value="customer.id">
            </div>
        </template>

        <template x-if="!customer && walkIn">
            <div class="nf-picked is-walkin">
                <span class="nf-face" aria-hidden="true"><?= nav_icon('user') ?></span>
                <span class="nf-picked-main"><b>Walk-in customer</b><small>No name needed when they pay in full</small></span>
                <button type="button" class="nf-link" @click="walkIn = false; $nextTick(() => $refs.find && $refs.find.focus())">Add a name</button>
            </div>
        </template>

        <div x-show="!customer && !walkIn">
            <div x-show="!newCustomer" class="nf-findc">
                <label class="nf-search"><?= nav_icon('search') ?>
                    <input type="search" x-ref="find" x-model="query" @input.debounce.250ms="search()" placeholder="Name or phone" aria-label="Find a customer" autocomplete="off" enterkeyhint="search">
                </label>
                <div class="nf-results-inline" x-show="query.trim() !== ''">
                    <template x-for="c in found" :key="c.id">
                        <button type="button" class="nf-item" @click="pickCustomer(c)">
                            <span class="nf-face" x-text="initials(c.name)" aria-hidden="true"></span>
                            <span class="nf-item-main"><b x-text="c.name"></b><small x-text="c.phone"></small></span>
                            <span class="nf-tag is-in" x-show="c.kept > 0" x-text="money(c.kept) + ' kept'"></span>
                        </button>
                    </template>
                    <button type="button" class="nf-item is-new" @click="startNew()">
                        <span class="nf-face" aria-hidden="true"><?= nav_icon('plus') ?></span>
                        <span class="nf-item-main"><b x-text="'New customer: ' + query.trim()"></b><small>Add their name and phone</small></span>
                    </button>
                </div>
                <div class="nf-findc-do" x-show="query.trim() === ''">
                    <button type="button" class="nf-link" @click="startNew()">New customer</button>
                    <button type="button" class="nf-link" @click="walkIn = true">No name, paying now</button>
                </div>
            </div>
            <div x-show="newCustomer" class="nf-form is-flat">
                <label>Name<input type="text" name="customer_name" x-model="old.customer_name" maxlength="100" autocomplete="off" :required="newCustomer"></label>
                <label>Phone <small>For WhatsApp messages and reminders</small><input type="tel" name="customer_phone" x-model="old.customer_phone" maxlength="20" inputmode="tel" autocomplete="off"></label>
                <details class="nf-more-field" :open="old.customer_email !== ''"><summary>Add email</summary>
                    <label>Email <small>Order news goes there too</small><input type="email" name="customer_email" x-model="old.customer_email" maxlength="100" autocomplete="off"></label>
                </details>
                <button type="button" class="nf-link" @click="newCustomer = false">Find a customer instead</button>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Items -->
    <section class="nf-sec" aria-labelledby="nf-sec-i">
        <h2 id="nf-sec-i">Items</h2>
        <ul class="nf-olist" x-show="items.length">
            <template x-for="(it, i) in items" :key="it.key">
                <li>
                    <input type="hidden" :name="'items[' + i + '][product_id]'" :value="it.product_id || ''">
                    <input type="hidden" :name="'items[' + i + '][variant]'" :value="it.variant || ''">
                    <input type="hidden" :name="'items[' + i + '][name]'" :value="it.name">
                    <input type="hidden" :name="'items[' + i + '][quantity]'" :value="it.quantity">
                    <input type="hidden" :name="'items[' + i + '][unit_price]'" :value="it.unit_price">
                    <input type="hidden" :name="'items[' + i + '][note]'" :value="it.note">
                    <button type="button" class="nf-orow-btn" @click="editItem(i)" :aria-label="'Change ' + (it.name || 'this item')">
                        <span class="nf-thumb" aria-hidden="true"><span x-text="initials(it.name)"></span><template x-if="it.photo"><img :src="it.photo.src" :data-full="it.photo.full" alt="" loading="lazy" decoding="async"></template></span>
                        <span class="nf-orow-main">
                            <b x-text="it.name || 'Name this item'" :class="{ 'is-owed': !it.name }"></b>
                            <small x-show="itemWords(it)" x-text="itemWords(it)"></small>
                            <small class="is-owed" x-show="short(it)" x-text="stockWords(it)"></small>
                        </span>
                        <span class="nf-orow-end"><b x-text="money(lineTotal(it))"></b><small x-text="fmtQty(num(it.quantity)) + ' × ' + money(num(it.unit_price))"></small></span>
                    </button>
                </li>
            </template>
        </ul>
        <button type="button" class="nf-add" @click="openPicker()"><?= nav_icon('plus') ?><span x-text="items.length ? 'Add another item' : 'Add an item'"></span></button>

        <div class="nf-disc">
            <button type="button" class="nf-link" x-show="!showDiscount" @click="showDiscount = true">Add discount</button>
            <label x-show="showDiscount">Discount <small>Taken off the total</small>
                <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" name="discount" inputmode="decimal" x-model="old.discount" autocomplete="off"></span></label>
        </div>
    </section>

    <?php if (!$is_edit): ?>
    <!-- In the shop, or online; online, then pickup or delivery -->
    <section class="nf-sec" aria-labelledby="nf-sec-h">
        <h2 id="nf-sec-h">Where is the customer?</h2>
        <div class="nf-seg is-2">
            <label><input type="radio" name="nf-sale" value="shop" x-model="old.sale"><span class="opt"><?= nav_icon('hand') ?>In the shop</span></label>
            <label><input type="radio" name="nf-sale" value="online" x-model="old.sale"><span class="opt"><?= nav_icon('phone') ?>Online or call</span></label>
        </div>
        <input type="hidden" name="fulfillment" :value="old.fulfillment">
        <template x-if="old.sale === 'shop'">
            <label class="nf-switch"><input type="checkbox" role="switch" :checked="old.fulfillment === 'pickup'" @change="old.fulfillment = $event.target.checked ? 'pickup' : 'now'">
                <span><b>They'll collect it another day</b><small>Made to measure, or not ready yet</small></span></label>
        </template>
        <div x-show="old.sale === 'online'" class="nf-seg is-2">
            <label><input type="radio" name="nf-how" value="pickup" x-model="old.fulfillment"><span class="opt"><?= nav_icon('pin') ?>Picks up</span></label>
            <label><input type="radio" name="nf-how" value="delivery" x-model="old.fulfillment"><span class="opt"><?= nav_icon('delivery') ?>Delivery</span></label>
        </div>
        <div x-show="old.fulfillment !== 'now'" class="nf-sub-q">
            <p class="nf-q" x-text="old.fulfillment === 'delivery' ? 'Delivery day' : 'Pickup day'"></p>
            <div class="nf-chipset">
                <label><input type="radio" value="today" x-model="whenPick"><span>Today</span></label>
                <label><input type="radio" value="tomorrow" x-model="whenPick"><span>Tomorrow</span></label>
                <label><input type="radio" value="pick" x-model="whenPick"><span>Pick a day</span></label>
                <label><input type="radio" value="" x-model="whenPick"><span>Not sure</span></label>
            </div>
            <input type="date" x-show="whenPick === 'pick'" x-model="whenDate" :min="today" aria-label="The day they get it">
            <input type="hidden" name="deliver_by" :value="deliverBy()">
            <label x-show="old.fulfillment === 'delivery'" class="nf-form-l">Address
                <textarea name="delivery_address" x-model="old.delivery_address" rows="2" maxlength="500" placeholder="Street, area, landmark"></textarea></label>
        </div>
    </section>

    <!-- Payment -->
    <section class="nf-sec" aria-labelledby="nf-sec-p">
        <h2 id="nf-sec-p">Payment</h2>
        <div class="nf-seg is-3">
            <label><input type="radio" name="pay" value="now" x-model="old.pay"><span class="opt"><?= nav_icon('check') ?>Paid now</span></label>
            <label><input type="radio" name="pay" value="part" x-model="old.pay"><span class="opt"><?= nav_icon('money') ?>Part now</span></label>
            <label><input type="radio" name="pay" value="later" x-model="old.pay"><span class="opt"><?= nav_icon('clock') ?>Pay later</span></label>
        </div>

        <label x-show="old.pay === 'part'" class="nf-form-l">Paid now <small x-show="total() > 0" x-text="'Of ' + money(total())"></small>
            <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" name="paid_now" inputmode="decimal" x-model="old.paid_now" autocomplete="off"></span></label>

        <div x-show="old.pay !== 'later'">
            <?php $pm_id = 'form'; $pm_checked = 'cash'; $pm_model = 'old.method'; require BASE_PATH . '/views/orders/_methods.php'; ?>
            <details class="nf-more-field" :open="old.reference !== ''"><summary>Add a reference</summary>
                <label class="nf-form-l">Reference <small>The transfer or POS reference</small><input type="text" name="reference" x-model="old.reference" maxlength="100" autocomplete="off"></label>
            </details>
        </div>

        <template x-if="customer && customer.kept > 0 && total() > 0">
            <label class="nf-switch is-card"><input type="checkbox" role="switch" name="use_kept" value="1" x-model="old.use_kept">
                <span><b x-text="'Use ' + money(Math.min(customer.kept, total())) + ' kept from before'"></b><small x-text="first(customer.name) + ' has ' + money(customer.kept) + ' kept with you.'"></small></span></label>
        </template>

        <div x-show="balance() > 0" class="nf-sub-q">
            <p class="nf-q">Balance <b class="is-owed" x-text="money(balance())"></b> due by <?= nf_info('Balance', 'A balance goes into Debts straight away, so you always see who owes you. On the pay-by day you get a reminder on Tasks, with a WhatsApp message ready to send.') ?></p>
            <div class="nf-chipset">
                <label x-show="old.fulfillment !== 'now' && deliverBy()"><input type="radio" value="when" x-model="payByPick"><span>The day they get it</span></label>
                <label><input type="radio" value="week" x-model="payByPick"><span>In a week</span></label>
                <label><input type="radio" value="pick" x-model="payByPick"><span>Pick a day</span></label>
            </div>
            <input type="date" x-show="payByPick === 'pick'" x-model="payByDate" :min="today" aria-label="Balance due by">
            <input type="hidden" name="pay_by" :value="payBy()">
        </div>
    </section>

    <section class="nf-sec is-quiet">
        <button type="button" class="nf-link" x-show="!showNote" @click="showNote = true">Add a note for yourself</button>
        <label x-show="showNote" class="nf-form-l">Your note <small>Only your team sees it</small>
            <textarea name="note" x-model="old.note" rows="2" maxlength="1000"></textarea></label>
    </section>
    <?php else: ?>
        <?php if ($paid_so_far > 0): ?>
            <p class="nf-help nf-edit-paid">Paid so far: <b><?= planner_money($paid_so_far, $currency) ?></b>. It stays paid. If the new total is less, you choose to give the difference back or keep it for next time.</p>
        <?php endif; ?>
    <?php endif; ?>

    <!-- The total, and the one button -->
    <div class="nf-sticky">
        <div class="nf-sticky-sum">
            <b x-text="money(total())"></b>
            <small x-text="sumWords()"></small>
        </div>
        <button class="nf-btn nf-btn-primary" :disabled="submitting" x-text="buttonWords()"><?= $is_edit ? 'Save changes' : 'Save order' ?></button>
    </div>
</form>

<!-- Pick an item: a photo list, then the item's own step (colour, size, how many) -->
<dialog class="nf-sheet nf-pick" id="nf-pick" aria-labelledby="nf-pick-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h">
        <button type="button" class="nf-icon-btn" x-show="pickStep === 'item' && editing < 0" @click="backToList()" aria-label="Back to the list"><?= nav_icon('back') ?></button>
        <h2 id="nf-pick-h" x-text="pickStep === 'item' ? (draft && draft.product_id ? draft.name : 'Your own item') : 'Add an item'">Add an item</h2>
        <button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button>
    </div>

    <div x-show="pickStep === 'list'">
        <div class="nf-pick-find">
            <label class="nf-search"><?= nav_icon('search') ?>
                <input type="search" x-ref="pickq" x-model="pickQuery" placeholder="Search your stock" aria-label="Search your stock" autocomplete="off" enterkeyhint="search"></label>
        </div>
        <div class="nf-pick-list">
            <template x-for="p in pickList()" :key="p.id">
                <button type="button" class="nf-item" @click="openItem(p)" :class="{ 'is-off': p.stock !== null && p.stock <= 0 }">
                    <span class="nf-thumb" aria-hidden="true"><span x-text="initials(p.name)"></span><template x-if="p.photo"><img :src="p.photo.src" :data-full="p.photo.full" alt="" loading="lazy" decoding="async"></template></span>
                    <span class="nf-item-main"><b x-text="p.name"></b><small x-text="p.stock === null ? 'Service' : (p.stock <= 0 ? 'Out of stock' : fmtQty(p.stock) + ' in stock')"></small></span>
                    <span class="nf-item-end"><b x-text="money(p.price)"></b><small x-show="p.variants.length" x-text="p.variants.map(function (g) { return g.name; }).join(' · ')"></small></span>
                </button>
            </template>
            <p class="nf-sheet-note" x-show="products.length && !pickList().length" x-text="'Nothing in your stock matches “' + pickQuery + '”.'"></p>
            <p class="nf-sheet-note" x-show="!products.length">Nothing in your stock yet. Type the item below.</p>
        </div>
        <div class="nf-pick-own">
            <p class="nf-q">Not in your stock? Type it</p>
            <div class="nf-pick-own-row">
                <input type="text" x-model="ownName" placeholder="What is it?" aria-label="Item name" maxlength="200">
                <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" inputmode="decimal" x-model="ownPrice" placeholder="Price" aria-label="Price"></span>
            </div>
            <button type="button" class="nf-btn nf-btn-ghost" @click="addOwn()" :disabled="!ownName.trim()"><?= nav_icon('plus') ?>Add it</button>
        </div>
    </div>

    <template x-if="pickStep === 'item' && draft">
        <div class="nf-pitem">
            <template x-if="draft.photo">
                <div class="nf-pitem-photo"><img :src="draft.photo.full" alt="" decoding="async"></div>
            </template>
            <div class="nf-pitem-sum">
                <template x-if="!draft.product_id">
                    <label class="nf-pitem-name">What is it?<input type="text" x-model="draft.name" maxlength="200"></label>
                </template>
                <p class="nf-pitem-price"><b x-text="money(num(draft.unit_price))"></b><small x-show="draft.stock !== null && draft.stock !== undefined" :class="{ 'is-owed': short(draft) }" x-text="stockWords(draft)"></small></p>
            </div>
            <template x-for="(group, gi) in (draft.variants || [])" :key="gi">
                <fieldset class="nf-pitem-group">
                    <legend x-text="group.name"></legend>
                    <div class="nf-chipset">
                        <template x-for="(opt, oi) in group.options" :key="oi">
                            <label><input type="radio" :name="'pv-' + gi" :value="opt.label" x-model="draft.choices[group.name]" @change="onVariant(draft)">
                                <span><span x-text="opt.label"></span><small x-show="opt.delta" x-text="(opt.delta > 0 ? '+' : '−') + money(Math.abs(opt.delta))"></small></span></label>
                        </template>
                    </div>
                </fieldset>
            </template>
            <div class="nf-pitem-row">
                <div class="nf-pitem-l"><span aria-hidden="true">How many</span>
                    <span class="nf-qty is-big" role="group" aria-label="How many">
                        <button type="button" @click="step(draft, -1)" aria-label="One less"><?= nav_icon('minus') ?></button>
                        <input type="text" inputmode="decimal" x-model="draft.quantity" aria-label="Quantity">
                        <button type="button" @click="step(draft, 1)" aria-label="One more"><?= nav_icon('plus') ?></button>
                    </span></div>
                <label class="nf-pitem-l">Price each
                    <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" inputmode="decimal" x-model="draft.unit_price" aria-label="Price each"></span></label>
            </div>
            <div class="nf-pitem-note">
                <button type="button" class="nf-link" x-show="!draft.showNote && !draft.note" @click="draft.showNote = true; $nextTick(() => $refs.pnote && $refs.pnote.focus())">Add a note</button>
                <label x-show="draft.showNote || draft.note" class="nf-pitem-l">Note <small>Measurements, a name to print…</small>
                    <input type="text" x-ref="pnote" x-model="draft.note" maxlength="255"></label>
            </div>
            <div class="nf-pitem-do">
                <button type="button" class="nf-btn nf-btn-primary" @click="saveItem()" :disabled="!(draft.name || '').trim()" x-text="(editing >= 0 ? 'Save' : 'Add') + ' · ' + money(lineTotal(draft))"></button>
                <button type="button" class="nf-link is-danger" x-show="editing >= 0" @click="removeItem(editing)"><?= nav_icon('trash') ?>Remove from order</button>
            </div>
        </div>
    </template>
</dialog>
</div>
<script src="<?= APP_URL ?>/assets/js/orders.js?v=<?= (int)@filemtime(BASE_PATH . '/assets/js/orders.js') ?>"></script>
