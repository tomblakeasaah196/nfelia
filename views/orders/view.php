<?php
/**
 * Nfelia — Order Detail View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $order, $items, $invoice, $receipt, $delivery_doc
 */
$currency = $business['currency_symbol'] ?? '₦';

$status_badges = [
    'pending'              => ['label' => 'Pending',      'bg' => 'bg-amber-100',  'text' => 'text-amber-700', 'dot' => 'bg-amber-500'],
    'pending_verification' => ['label' => 'Verify POP',   'bg' => 'bg-blue-100',   'text' => 'text-blue-700',  'dot' => 'bg-blue-500'],
    'approved'             => ['label' => 'Approved',     'bg' => 'bg-green-100',  'text' => 'text-green-700', 'dot' => 'bg-green-500'],
    'rejected'             => ['label' => 'Rejected',     'bg' => 'bg-red-100',    'text' => 'text-red-700',   'dot' => 'bg-red-500'],
    'processing'           => ['label' => 'Processing',   'bg' => 'bg-purple-100', 'text' => 'text-purple-700','dot' => 'bg-purple-500'],
    'fulfilled'            => ['label' => 'Fulfilled',    'bg' => 'bg-emerald-100','text' => 'text-emerald-700','dot' => 'bg-emerald-500'],
    'cancelled'            => ['label' => 'Cancelled',    'bg' => 'bg-gray-100',   'text' => 'text-gray-500',  'dot' => 'bg-gray-400'],
];
$badge = $status_badges[$order['status']] ?? ['label' => ucfirst($order['status']), 'bg' => 'bg-gray-100', 'text' => 'text-gray-500', 'dot' => 'bg-gray-400'];
$can_approve = in_array($order['status'], ['pending', 'pending_verification']);
$can_advance = in_array($order['status'], ['approved', 'processing']);
?>

<!-- Back + Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div class="flex items-center gap-4">
        <a href="<?= APP_URL ?>/orders" class="w-9 h-9 rounded-xl bg-brand-cream border border-brand-sand flex items-center justify-center text-brand-plum/40 hover:text-brand-plum hover:border-brand-gold transition-all">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight"><?= e($order['order_number']) ?></h1>
            <p class="text-xs text-brand-plum/50 font-medium mt-0.5">Placed <?= date('M j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
        </div>
    </div>
    <div class="flex items-center gap-3">
        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider <?= $badge['bg'] ?> <?= $badge['text'] ?>">
            <span class="w-2 h-2 rounded-full <?= $badge['dot'] ?>"></span>
            <?= $badge['label'] ?>
        </span>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Left Column: Order Items + Payment -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Order Items -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand-sand/50">
                <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Order Items</h3>
            </div>
            <div class="divide-y divide-brand-sand/30">
                <?php foreach ($items as $item): ?>
                <div class="px-5 py-4 flex items-start gap-4">
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-bold text-brand-plum"><?= e($item['product_name']) ?></div>
                        <?php if ($item['variant_selection']): ?>
                            <div class="text-[10px] text-brand-plum/40 mt-0.5"><?= e($item['variant_selection']) ?></div>
                        <?php endif; ?>
                        <?php if ($item['item_note']): ?>
                            <div class="text-[10px] text-brand-plum/30 mt-0.5 italic">"<?= e($item['item_note']) ?>"</div>
                        <?php endif; ?>
                        <?php if (in_array($order['status'], ['pending', 'pending_verification'], true) && isset($item['stock_qty']) && !$item['is_service'] && (float)$item['quantity'] > (float)$item['stock_qty']): ?>
                            <div class="text-[11px] font-semibold text-red-700 mt-1"><?= (float)$item['stock_qty'] <= 0 ? 'Out of stock' : 'Only ' . (float)$item['stock_qty'] . ' ' . e($item['unit'] ?: '') . ' in stock' ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <div class="data-text text-xs font-bold accent-primary"><?= money((float)$item['line_total'], $currency) ?></div>
                        <div class="text-[10px] text-brand-plum/40"><?= (int)$item['quantity'] ?> × <?= money((float)$item['unit_price'], $currency) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="px-5 py-4 border-t border-brand-sand bg-brand-cream/30 flex justify-between items-center">
                <span class="text-xs font-bold text-brand-plum/60 uppercase tracking-wider">Total</span>
                <span class="font-heading font-extrabold text-lg accent-primary"><?= money((float)$order['total'], $currency) ?></span>
            </div>
        </div>

        <!-- Payment Info -->
        <?php if ($order['payment_method'] !== 'none' || $order['payment_proof_path']): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand-sand/50">
                <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Payment</h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex items-center gap-3 text-sm">
                    <span class="text-brand-plum/40 text-xs font-bold uppercase tracking-wider w-28">Method</span>
                    <span class="font-semibold text-brand-plum"><?= ucwords(str_replace('_', ' ', $order['payment_method'])) ?></span>
                </div>
                <?php if ($order['payment_proof_path']): ?>
                <div>
                    <span class="text-brand-plum/40 text-xs font-bold uppercase tracking-wider">Proof of Payment</span>
                    <div class="mt-2">
                        <a href="<?= APP_URL ?>/orders/proof/<?= (int)$order['id'] ?>" target="_blank" class="inline-block rounded-xl overflow-hidden border border-brand-sand hover:border-brand-gold transition-colors">
                            <img src="<?= APP_URL ?>/orders/proof/<?= (int)$order['id'] ?>" alt="Payment proof" class="max-w-xs max-h-48 object-contain">
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($order['payment_note']): ?>
                <div class="flex items-start gap-3 text-sm">
                    <span class="text-brand-plum/40 text-xs font-bold uppercase tracking-wider w-28">Note</span>
                    <span class="text-brand-plum/70 text-xs"><?= e($order['payment_note']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Linked Documents -->
        <?php if ($invoice || $receipt || $delivery_doc): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand-sand/50">
                <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Linked Documents</h3>
            </div>
            <div class="p-5 space-y-3">
                <?php if ($invoice): ?>
                <a href="<?= APP_URL ?>/invoices/edit/<?= $invoice['id'] ?>" class="flex items-center justify-between p-3 rounded-lg border border-brand-sand hover:border-brand-gold hover:bg-brand-cream/30 transition-all">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                        <div>
                            <div class="text-xs font-bold text-brand-plum">Invoice <?= e($invoice['doc_number']) ?></div>
                            <div class="text-[10px] text-brand-plum/40"><?= money((float)$invoice['total'], $currency) ?> — <?= ucfirst($invoice['status']) ?></div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                <?php endif; ?>
                <?php if ($receipt): ?>
                <a href="<?= APP_URL ?>/receipts/view/<?= $receipt['id'] ?>" class="flex items-center justify-between p-3 rounded-lg border border-brand-sand hover:border-brand-gold hover:bg-brand-cream/30 transition-all">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <div>
                            <div class="text-xs font-bold text-brand-plum">Receipt <?= e($receipt['doc_number']) ?></div>
                            <div class="text-[10px] text-brand-plum/40"><?= money((float)$receipt['amount'], $currency) ?> — <?= ucfirst($receipt['status']) ?></div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                <?php endif; ?>
                <?php if ($delivery_doc): ?>
                <a href="<?= APP_URL ?>/deliveries/view/<?= $delivery_doc['id'] ?>" class="flex items-center justify-between p-3 rounded-lg border border-brand-sand hover:border-brand-gold hover:bg-brand-cream/30 transition-all">
                    <div class="flex items-center gap-3">
                        <svg class="w-5 h-5 text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a2 2 0 104 0m-4 0a2 2 0 11-4 0"/></svg>
                        <div>
                            <div class="text-xs font-bold text-brand-plum">Delivery <?= e($delivery_doc['doc_number']) ?></div>
                            <div class="text-[10px] text-brand-plum/40"><?= ucfirst($delivery_doc['status']) ?></div>
                        </div>
                    </div>
                    <svg class="w-4 h-4 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Right Column: Customer info + Actions -->
    <div class="space-y-6">

        <!-- Customer Details -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand-sand/50">
                <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Customer</h3>
            </div>
            <div class="p-5 space-y-3">
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-0.5">Name</div>
                    <div class="text-sm font-bold text-brand-plum"><?= e($order['customer_name']) ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-0.5">Phone</div>
                    <a href="tel:<?= e($order['customer_phone']) ?>" class="text-sm font-medium accent-primary hover:underline"><?= e($order['customer_phone']) ?></a>
                </div>
                <?php if ($order['customer_email']): ?>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-0.5">Email</div>
                    <a href="mailto:<?= e($order['customer_email']) ?>" class="text-sm font-medium accent-primary hover:underline"><?= e($order['customer_email']) ?></a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Fulfillment -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand-sand/50">
                <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Fulfillment</h3>
            </div>
            <div class="p-5 space-y-3">
                <div class="flex items-center gap-2">
                    <?php if ($order['fulfillment_type'] === 'delivery'): ?>
                        <svg class="w-4 h-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a2 2 0 104 0m-4 0a2 2 0 11-4 0"/></svg>
                        <span class="text-sm font-bold text-brand-plum">Delivery</span>
                    <?php else: ?>
                        <svg class="w-4 h-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                        <span class="text-sm font-bold text-brand-plum">Pickup</span>
                    <?php endif; ?>
                </div>
                <?php if ($order['delivery_address']): ?>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-0.5">Address</div>
                    <div class="text-xs text-brand-plum/70"><?= e($order['delivery_address']) ?></div>
                </div>
                <?php endif; ?>
                <?php if ($order['customer_note']): ?>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-0.5">Customer Note</div>
                    <div class="text-xs text-brand-plum/70 italic">"<?= e($order['customer_note']) ?>"</div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- The shopper's links: tracking, and the review once the order is done -->
        <?php if ($track_url): ?>
        <?php
        $first_name = preg_split('/\s+/u', trim((string)$order['customer_name']))[0] ?: 'there';
        // Where the order stands, ready to send (the email says the same: shop_order_tell()).
        $send_track = shop_order_whatsapp_text($order, (string)$business['name'], $track_url, $delivery_doc['status'] ?? null);
        $send_review = $review_link ? 'Hello ' . $first_name . ', thank you for your order ' . $order['order_number'] . '. Would you rate it? It takes a few seconds: ' . $review_link : '';
        ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden scroll-mt-24" id="review" x-data="{ copied: '' }">
            <div class="px-5 py-4 border-b border-brand-sand/50">
                <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Customer's links</h3>
            </div>
            <div class="p-5 space-y-4">
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-1">Order tracking</div>
                    <p class="text-xs text-brand-plum/60 mb-2">Your customer follows this order here. No account needed.</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" class="h-10 px-3 text-xs font-bold rounded-lg border border-brand-sand text-brand-plum hover:bg-brand-cream"
                                @click="navigator.clipboard && navigator.clipboard.writeText(<?= e(json_encode($track_url)) ?>).then(() => { copied = 'track'; setTimeout(() => copied = '', 2000) })">
                            <span x-show="copied !== 'track'">Copy link</span><span x-show="copied === 'track'" x-cloak>Copied</span>
                        </button>
                        <?php if ($customer_wa !== ''): ?>
                        <a href="https://wa.me/<?= e($customer_wa) ?>?text=<?= e(rawurlencode($send_track)) ?>" target="_blank" rel="noopener"
                           class="h-10 px-3 inline-flex items-center text-xs font-bold rounded-lg border border-brand-sand text-brand-plum hover:bg-brand-cream">Send update on WhatsApp</a>
                        <?php endif; ?>
                        <a href="<?= e($track_url) ?>" target="_blank" rel="noopener"
                           class="h-10 px-3 inline-flex items-center text-xs font-bold rounded-lg text-brand-plum/70 hover:underline">Open</a>
                    </div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/30 uppercase tracking-widest mb-1">Review</div>
                    <?php if (!$review): ?>
                        <p class="text-xs text-brand-plum/60">Once the delivery is signed for, or you mark the order fulfilled, your customer can rate it. Reviews show on your shop.</p>
                    <?php elseif ($review['status'] === 'invited'): ?>
                        <p class="text-xs text-brand-plum/60 mb-2">Waiting for your customer. Their tracking page asks them too.</p>
                        <?php if ($customer_wa !== '' && $send_review !== ''): ?>
                        <a href="https://wa.me/<?= e($customer_wa) ?>?text=<?= e(rawurlencode($send_review)) ?>" target="_blank" rel="noopener"
                           class="h-10 px-3 inline-flex items-center text-xs font-bold rounded-lg border border-brand-sand text-brand-plum hover:bg-brand-cream">Ask for a review on WhatsApp</a>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-sm font-bold text-brand-plum"><?= str_repeat('★', (int)$review['rating']) ?><span class="text-brand-plum/20"><?= str_repeat('★', 5 - (int)$review['rating']) ?></span> <span class="text-xs font-medium text-brand-plum/60"><?= (int)$review['rating'] ?> out of 5</span></p>
                        <?php if ($review['comment']): ?>
                        <p class="text-xs text-brand-plum/70 italic mt-1">"<?= e($review['comment']) ?>"</p>
                        <?php endif; ?>
                        <?php if ($review['status'] === 'hidden'): ?>
                        <p class="text-[11px] text-red-600 mt-1">Hidden from your shop by Nfelia for breaking the review rules.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Order Details -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand-sand/50">
                <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Details</h3>
            </div>
            <div class="p-5 space-y-2 text-xs">
                <div class="flex justify-between">
                    <span class="text-brand-plum/40 font-bold uppercase tracking-wider">Channel</span>
                    <span class="font-semibold text-brand-plum"><?= $order['channel'] === 'whatsapp' ? 'WhatsApp' : 'Cart + POP' ?></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-brand-plum/40 font-bold uppercase tracking-wider">Currency</span>
                    <span class="font-semibold text-brand-plum"><?= e($order['currency_code']) ?></span>
                </div>
                <?php if ($order['reviewed_at']): ?>
                <div class="flex justify-between">
                    <span class="text-brand-plum/40 font-bold uppercase tracking-wider">Reviewed</span>
                    <span class="font-semibold text-brand-plum"><?= date('M j, Y', strtotime($order['reviewed_at'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Actions -->
        <?php if ($can_approve): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-5 space-y-3">
            <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-2">Actions</h3>
            <form method="POST" action="<?= APP_URL ?>/orders/approve/<?= $order['id'] ?>" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <?php
                $df = ['mode' => '', 'date' => '', 'show' => true, 'handed' => false, 'id' => 'order'];
                require BASE_PATH . '/views/partials/deliver_fields.php';
                ?>
                <button type="submit" class="w-full btn-primary text-white text-xs font-bold uppercase tracking-widest py-3 rounded-xl shadow-sm hover:shadow-md transition-all"
                        onclick="return confirm('Approve this order? This will create an invoice<?= $order['payment_proof_path'] ? ', receipt' : '' ?><?= $order['fulfillment_type'] === 'delivery' ? ', and delivery note' : '' ?>.')">
                    Approve Order
                </button>
            </form>
            <form method="POST" action="<?= APP_URL ?>/orders/reject/<?= $order['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <button type="submit" class="w-full text-red-600 border border-red-200 bg-red-50 hover:bg-red-100 text-xs font-bold uppercase tracking-widest py-3 rounded-xl transition-colors"
                        onclick="return confirm('Reject this order?')">
                    Reject Order
                </button>
            </form>
        </div>
        <?php endif; ?>

        <?php if ($can_advance): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-5 space-y-3">
            <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-2">Update Status</h3>
            <?php if ($order['status'] === 'approved'): ?>
            <form method="POST" action="<?= APP_URL ?>/orders/update-status/<?= $order['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="status" value="processing">
                <button type="submit" class="w-full btn-secondary text-white text-xs font-bold uppercase tracking-widest py-3 rounded-xl">Mark as Processing</button>
            </form>
            <form method="POST" action="<?= APP_URL ?>/orders/update-status/<?= $order['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="status" value="fulfilled">
                <button type="submit" class="w-full text-emerald-700 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-xs font-bold uppercase tracking-widest py-3 rounded-xl transition-colors">Mark as Fulfilled</button>
            </form>
            <?php elseif ($order['status'] === 'processing'): ?>
            <form method="POST" action="<?= APP_URL ?>/orders/update-status/<?= $order['id'] ?>">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="status" value="fulfilled">
                <button type="submit" class="w-full text-emerald-700 border border-emerald-200 bg-emerald-50 hover:bg-emerald-100 text-xs font-bold uppercase tracking-widest py-3 rounded-xl transition-colors">Mark as Fulfilled</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
