<?php
/**
 * Nfelia — Delivery Detail View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $delivery, $items, $customer, $invoice,
 *   $wa_number, $statuses, $providers, $currency
 */

// Status workflow for timeline
$timeline_steps = [
    'pending_dispatch' => ['label' => 'Pending', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
    'dispatched'       => ['label' => 'Dispatched', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>'],
    'in_transit'       => ['label' => 'In Transit', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a2 2 0 104 0m-4 0a2 2 0 11-4 0"/>'],
    'delivered'        => ['label' => 'Delivered', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>'],
    'signed_off'       => ['label' => 'Signed Off', 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>'],
];

$status_order = array_keys($timeline_steps);
$current_idx  = array_search($delivery['status'], $status_order);
$is_failed    = $delivery['status'] === 'failed';

// Valid next statuses for transition buttons
$valid_transitions = [
    'pending_dispatch' => ['dispatched', 'failed'],
    'dispatched'       => ['in_transit', 'delivered', 'failed'],
    'in_transit'       => ['delivered', 'failed'],
    'delivered'        => ['signed_off'],
    'signed_off'       => [],
    'failed'           => ['pending_dispatch'],
];
$next_statuses = $valid_transitions[$delivery['status']] ?? [];

// Sign-off link
$signoff_url = APP_URL . '/sign/' . $delivery['signoff_token'];
$public_url  = APP_URL . '/delivery/' . $delivery['token'];
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/deliveries" class="w-9 h-9 flex items-center justify-center rounded-lg bg-brand-cream border border-brand-sand hover:border-brand-gold transition-colors">
            <svg class="w-4 h-4 text-brand-plum/60" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <div class="flex items-center gap-3">
                <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight"><?= e($delivery['doc_number']) ?></h1>
                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded <?= $statuses[$delivery['status']]['color'] ?? 'bg-gray-100 text-gray-700' ?>">
                    <?= $statuses[$delivery['status']]['label'] ?? ucfirst($delivery['status']) ?>
                </span>
            </div>
            <p class="text-xs text-brand-plum/50 font-medium mt-1">Created <?= fmt_date($delivery['created_at'], 'd M Y, H:i') ?></p>
        </div>
    </div>
    <div class="flex items-center gap-2">
        <?php if (in_array($delivery['status'], ['pending_dispatch', 'dispatched'])): ?>
            <a href="<?= APP_URL ?>/deliveries/edit/<?= $delivery['id'] ?>" class="flex items-center gap-2 text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest hover:text-brand-plum bg-brand-white border border-brand-sand px-4 py-2.5 rounded-xl hover:border-brand-gold transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                Edit
            </a>
        <?php endif; ?>
        <a href="<?= $public_url ?>" target="_blank" class="flex items-center gap-2 text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest hover:text-brand-plum bg-brand-white border border-brand-sand px-4 py-2.5 rounded-xl hover:border-brand-gold transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
            Preview
        </a>
    </div>
</div>

<!-- Status Timeline -->
<?php if (!$is_failed): ?>
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 mb-6 shadow-sm">
    <div class="flex items-center justify-between relative">
        <!-- Connecting line -->
        <div class="absolute top-5 left-0 right-0 h-0.5 bg-brand-sand"></div>
        <div class="absolute top-5 left-0 h-0.5 bg-brand-gold transition-all" style="width: <?= $current_idx !== false ? ($current_idx / (count($timeline_steps) - 1) * 100) : 0 ?>%"></div>

        <?php foreach ($timeline_steps as $sKey => $sData): ?>
            <?php
                $step_idx = array_search($sKey, $status_order);
                $is_done    = $current_idx !== false && $step_idx <= $current_idx;
                $is_current = $delivery['status'] === $sKey;
            ?>
            <div class="relative flex flex-col items-center z-10" style="flex: 1;">
                <div class="w-10 h-10 rounded-full flex items-center justify-center border-2 transition-all
                    <?= $is_current ? 'bg-brand-gold border-brand-gold text-white scale-110 shadow-md' : ($is_done ? 'bg-brand-gold/20 border-brand-gold text-brand-gold' : 'bg-brand-cream border-brand-sand text-brand-plum/30') ?>">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><?= $sData['icon'] ?></svg>
                </div>
                <span class="text-[9px] font-bold uppercase tracking-wider mt-2 text-center
                    <?= $is_current ? 'text-brand-gold' : ($is_done ? 'text-brand-plum/60' : 'text-brand-plum/30') ?>">
                    <?= $sData['label'] ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="bg-red-50 border border-red-200 rounded-xl p-5 mb-6 shadow-sm text-center">
    <svg class="w-8 h-8 text-red-400 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    <p class="text-sm font-bold text-red-700">Delivery Failed</p>
    <p class="text-[10px] text-red-500 mt-1">This delivery was marked as failed. You can retry by resetting to Pending.</p>
</div>
<?php endif; ?>

<!-- Status Transition Buttons -->
<?php if (!empty($next_statuses)): ?>
<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach ($next_statuses as $ns): ?>
        <form method="POST" action="<?= APP_URL ?>/deliveries/update_status/<?= $delivery['id'] ?>" class="inline"
              onsubmit="return confirm('Change status to <?= $statuses[$ns]['label'] ?>?')">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="<?= $ns ?>">
            <button type="submit" class="flex items-center gap-2 px-4 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm transition-all
                <?php if ($ns === 'failed'): ?>
                    bg-red-50 text-red-600 border border-red-200 hover:bg-red-100
                <?php elseif ($ns === 'signed_off'): ?>
                    bg-purple-50 text-purple-600 border border-purple-200 hover:bg-purple-100
                <?php elseif ($ns === 'pending_dispatch'): ?>
                    bg-gray-50 text-gray-600 border border-gray-200 hover:bg-gray-100
                <?php else: ?>
                    btn-primary text-white hover:shadow-md
                <?php endif; ?>">
                <?= $ns === 'failed' ? 'Mark Failed' : ($ns === 'pending_dispatch' ? 'Retry (Reset)' : 'Mark ' . $statuses[$ns]['label']) ?>
            </button>
        </form>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
    <!-- Left Column: Details -->
    <div class="lg:col-span-2 space-y-6">

        <!-- Recipient Card -->
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Recipient</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Name</div>
                    <div class="text-sm font-semibold text-brand-plum"><?= e($delivery['recipient_name']) ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Phone</div>
                    <div class="text-sm text-brand-plum"><?= $delivery['recipient_phone'] ? e($delivery['recipient_phone']) : '<span class="text-brand-plum/30">—</span>' ?></div>
                </div>
                <div class="sm:col-span-2">
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Delivery Address</div>
                    <div class="text-xs text-brand-plum leading-relaxed"><?= nl2br(e($delivery['delivery_address'])) ?></div>
                </div>
                <?php if ($customer): ?>
                <div class="sm:col-span-2 pt-2 border-t border-brand-sand/50">
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Linked Customer</div>
                    <a href="<?= APP_URL ?>/customers/view/<?= $customer['id'] ?>" class="text-xs font-bold text-blue-500 hover:text-blue-700"><?= e($customer['full_name']) ?></a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-brand-sand">
                <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Items (<?= count($items) ?>)</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="border-b border-brand-sand bg-brand-cream/50">
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">#</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Description</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Qty</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Unit</th>
                            <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Notes</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-sand/50">
                        <?php foreach ($items as $i => $item): ?>
                        <tr>
                            <td class="px-5 py-3 data-text text-[10px] text-brand-plum/40"><?= $i + 1 ?></td>
                            <td class="px-5 py-3">
                                <div class="text-xs font-semibold text-brand-plum"><?= e($item['product_name'] ?: $item['description']) ?></div>
                                <?php if ($item['product_name'] && $item['description'] !== $item['product_name']): ?>
                                    <div class="text-[10px] text-brand-plum/40"><?= e($item['description']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 data-text text-xs font-bold text-brand-plum text-center"><?= number_format((float)$item['quantity'], $item['quantity'] == (int)$item['quantity'] ? 0 : 2) ?></td>
                            <td class="px-5 py-3 text-[10px] text-brand-plum/60"><?= $item['unit'] ? e($item['unit']) : '—' ?></td>
                            <td class="px-5 py-3 text-[10px] text-brand-plum/50"><?= $item['notes'] ? e($item['notes']) : '—' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Dispatch Details -->
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Dispatch Details</h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php if ($delivery['pickup_address']): ?>
                <div class="sm:col-span-2 lg:col-span-3">
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Pickup Address</div>
                    <div class="text-xs text-brand-plum leading-relaxed"><?= nl2br(e($delivery['pickup_address'])) ?></div>
                </div>
                <?php endif; ?>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Provider</div>
                    <div class="text-xs font-semibold text-brand-plum"><?= $delivery['provider'] ? ($providers[$delivery['provider']] ?? e(ucfirst($delivery['provider']))) : '<span class="text-brand-plum/30">Not set</span>' ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Estimated Delivery</div>
                    <div class="data-text text-xs text-brand-plum"><?= $delivery['estimated_delivery'] ? fmt_date($delivery['estimated_delivery'], 'd M Y, H:i') : '<span class="text-brand-plum/30">—</span>' ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Dispatch Date</div>
                    <div class="data-text text-xs text-brand-plum"><?= $delivery['dispatch_date'] ? fmt_date($delivery['dispatch_date'], 'd M Y, H:i') : '<span class="text-brand-plum/30">Not dispatched yet</span>' ?></div>
                </div>
                <?php if ($delivery['actual_delivery']): ?>
                <div>
                    <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Actual Delivery</div>
                    <div class="data-text text-xs font-bold text-green-600"><?= fmt_date($delivery['actual_delivery'], 'd M Y, H:i') ?></div>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($delivery['rider_name'] || $delivery['rider_phone'] || $delivery['booking_reference']): ?>
            <div class="mt-4 pt-4 border-t border-brand-sand/50">
                <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-3">Rider / Driver</div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <?php if ($delivery['rider_name']): ?>
                    <div>
                        <div class="text-[9px] text-brand-plum/40">Name</div>
                        <div class="text-xs font-semibold text-brand-plum"><?= e($delivery['rider_name']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($delivery['rider_phone']): ?>
                    <div>
                        <div class="text-[9px] text-brand-plum/40">Phone</div>
                        <div class="text-xs text-brand-plum"><?= e($delivery['rider_phone']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($delivery['booking_reference']): ?>
                    <div>
                        <div class="text-[9px] text-brand-plum/40">Booking Ref</div>
                        <div class="data-text text-xs text-brand-plum"><?= e($delivery['booking_reference']) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($delivery['tracking_number'] || $delivery['tracking_url']): ?>
            <div class="mt-4 pt-4 border-t border-brand-sand/50">
                <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-3">Tracking</div>
                <div class="flex flex-wrap gap-4">
                    <?php if ($delivery['tracking_number']): ?>
                    <div>
                        <div class="text-[9px] text-brand-plum/40">Number</div>
                        <div class="data-text text-xs font-bold text-brand-plum"><?= e($delivery['tracking_number']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if ($delivery['tracking_url']): ?>
                    <div>
                        <div class="text-[9px] text-brand-plum/40">Link</div>
                        <a href="<?= e($delivery['tracking_url']) ?>" target="_blank" class="text-xs font-bold text-blue-500 hover:text-blue-700 flex items-center gap-1">
                            Track Shipment
                            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Notes -->
        <?php if ($delivery['special_instructions'] || $delivery['notes']): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-brand-plum mb-4 uppercase tracking-wider">Notes</h2>
            <?php if ($delivery['special_instructions']): ?>
            <div class="mb-3">
                <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Special Instructions</div>
                <div class="text-xs text-brand-plum leading-relaxed bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"><?= nl2br(e($delivery['special_instructions'])) ?></div>
            </div>
            <?php endif; ?>
            <?php if ($delivery['notes']): ?>
            <div>
                <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Internal Notes</div>
                <div class="text-xs text-brand-plum/70 leading-relaxed"><?= nl2br(e($delivery['notes'])) ?></div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- E-Signature Section (if signed) -->
        <?php if ($delivery['status'] === 'signed_off' && $delivery['signoff_name']): ?>
        <div class="bg-purple-50 border border-purple-200 rounded-xl p-5 shadow-sm">
            <h2 class="font-heading font-bold text-sm text-purple-700 mb-4 uppercase tracking-wider flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                Recipient Confirmation
            </h2>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <div class="text-[9px] font-bold text-purple-500 uppercase tracking-widest mb-1">Signed By</div>
                    <div class="text-sm font-bold text-purple-900"><?= e($delivery['signoff_name']) ?></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-purple-500 uppercase tracking-widest mb-1">Signed At</div>
                    <div class="data-text text-sm text-purple-900"><?= $delivery['signoff_timestamp'] ? fmt_date($delivery['signoff_timestamp'], 'd M Y, H:i') : '—' ?></div>
                </div>
                <?php if ($delivery['signoff_signature_path']): ?>
                <div class="sm:col-span-2">
                    <div class="text-[9px] font-bold text-purple-500 uppercase tracking-widest mb-2">Signature</div>
                    <div class="bg-white rounded-lg border border-purple-200 p-3 inline-block">
                        <img src="<?= APP_URL . '/deliveries/signature/' . (int)$delivery['id'] ?>" alt="Signature" class="max-h-20">
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right Column: Quick Actions & Info -->
    <div class="space-y-6">

        <!-- Invoice Link -->
        <?php if ($invoice): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <div class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-2">Linked Invoice</div>
            <a href="<?= APP_URL ?>/invoices/view/<?= $invoice['id'] ?>" class="flex items-center justify-between group">
                <div>
                    <div class="data-text text-sm font-bold text-blue-500 group-hover:text-blue-700"><?= e($invoice['doc_number']) ?></div>
                    <div class="text-[10px] text-brand-plum/50"><?= $currency ?><?= number_format((float)$invoice['total'], 2) ?> &middot; <?= ucfirst($invoice['status']) ?></div>
                </div>
                <svg class="w-4 h-4 text-brand-plum/30 group-hover:text-brand-gold" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>
        <?php endif; ?>

        <!-- Share Sign-Off Link -->
        <?php if (!in_array($delivery['status'], ['signed_off', 'failed'])): ?>
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm" x-data="{ copied: false }">
            <h3 class="font-heading font-bold text-sm text-brand-plum mb-3 uppercase tracking-wider">Sign-Off Link</h3>
            <p class="text-[10px] text-brand-plum/50 mb-3 leading-relaxed">Share this link with the recipient to collect their e-signature confirming receipt.</p>

            <!-- Copy Link -->
            <div class="flex gap-2 mb-3">
                <input type="text" value="<?= e($signoff_url) ?>" readonly id="signoff-url"
                       class="flex-1 px-3 py-2 text-[10px] font-medium bg-brand-cream border border-brand-sand rounded-lg text-brand-plum truncate">
                <button type="button" @click="navigator.clipboard.writeText('<?= e($signoff_url) ?>'); copied = true; setTimeout(() => copied = false, 2000)"
                        class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest bg-brand-cream border border-brand-sand rounded-lg hover:border-brand-gold transition-colors"
                        :class="copied ? 'text-green-600' : 'text-brand-plum/60'">
                    <span x-show="!copied">Copy</span>
                    <span x-show="copied" x-cloak>Copied!</span>
                </button>
            </div>

            <!-- WhatsApp Share -->
            <?php if ($wa_number): ?>
                <?php
                    $wa_msg = urlencode("Hi {$delivery['recipient_name']},\n\nYour delivery ({$delivery['doc_number']}) is ready. Please confirm receipt by signing here:\n\n{$signoff_url}\n\nThank you!\n— " . ($business['business_name'] ?? 'Nfelia'));
                ?>
                <a href="https://wa.me/<?= $wa_number ?>?text=<?= $wa_msg ?>" target="_blank"
                   class="w-full flex items-center justify-center gap-2 bg-green-500 hover:bg-green-600 text-white px-4 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-colors shadow-sm">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/><path d="M12 0C5.373 0 0 5.373 0 12c0 2.625.846 5.059 2.284 7.03L.789 23.685l4.843-1.276A11.93 11.93 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.75c-2.115 0-4.142-.655-5.85-1.893l-.42-.287-2.872.757.766-2.806-.313-.445A9.696 9.696 0 012.25 12c0-5.385 4.365-9.75 9.75-9.75S21.75 6.615 21.75 12s-4.365 9.75-9.75 9.75z"/></svg>
                    Send via WhatsApp
                </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Key Dates -->
        <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
            <h3 class="font-heading font-bold text-sm text-brand-plum mb-3 uppercase tracking-wider">Timeline</h3>
            <div class="space-y-3">
                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 rounded-full bg-brand-plum/30 mt-1.5 shrink-0"></div>
                    <div>
                        <div class="text-[10px] font-bold text-brand-plum/60">Created</div>
                        <div class="data-text text-xs text-brand-plum"><?= fmt_date($delivery['created_at'], 'd M Y, H:i') ?></div>
                    </div>
                </div>
                <?php if ($delivery['dispatch_date']): ?>
                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 rounded-full bg-blue-400 mt-1.5 shrink-0"></div>
                    <div>
                        <div class="text-[10px] font-bold text-brand-plum/60">Dispatched</div>
                        <div class="data-text text-xs text-brand-plum"><?= fmt_date($delivery['dispatch_date'], 'd M Y, H:i') ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($delivery['actual_delivery']): ?>
                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 rounded-full bg-green-400 mt-1.5 shrink-0"></div>
                    <div>
                        <div class="text-[10px] font-bold text-brand-plum/60">Delivered</div>
                        <div class="data-text text-xs text-brand-plum"><?= fmt_date($delivery['actual_delivery'], 'd M Y, H:i') ?></div>
                    </div>
                </div>
                <?php endif; ?>
                <?php if ($delivery['signoff_timestamp']): ?>
                <div class="flex items-start gap-3">
                    <div class="w-2 h-2 rounded-full bg-purple-400 mt-1.5 shrink-0"></div>
                    <div>
                        <div class="text-[10px] font-bold text-brand-plum/60">Signed Off</div>
                        <div class="data-text text-xs text-brand-plum"><?= fmt_date($delivery['signoff_timestamp'], 'd M Y, H:i') ?></div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Danger Zone -->
        <?php if (!in_array($delivery['status'], ['in_transit', 'delivered', 'signed_off'])): ?>
        <div class="bg-brand-white border border-red-200 rounded-xl p-5 shadow-sm">
            <h3 class="font-heading font-bold text-sm text-red-600 mb-2 uppercase tracking-wider">Danger Zone</h3>
            <p class="text-[10px] text-brand-plum/50 mb-3">Permanently remove this delivery note. This cannot be undone.</p>
            <form method="POST" action="<?= APP_URL ?>/deliveries/delete/<?= $delivery['id'] ?>"
                  onsubmit="return confirm('Delete <?= e($delivery['doc_number']) ?>? This cannot be undone.')">
                <?= csrf_field() ?>
                <button type="submit" class="w-full text-center text-[10px] font-bold text-red-500 uppercase tracking-widest hover:text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-2.5 hover:bg-red-100 transition-colors">Delete Delivery</button>
            </form>
        </div>
        <?php endif; ?>
    </div>
</div>