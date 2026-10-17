<?php
/**
 * Nfelia — Order Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $orders, $summary,
 *   $search, $status, $channel, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';

$status_badges = [
    'pending'              => ['label' => 'Pending',      'bg' => 'bg-amber-100',  'text' => 'text-amber-700'],
    'pending_verification' => ['label' => 'Verify POP',   'bg' => 'bg-blue-100',   'text' => 'text-blue-700'],
    'approved'             => ['label' => 'Approved',     'bg' => 'bg-green-100',  'text' => 'text-green-700'],
    'rejected'             => ['label' => 'Rejected',     'bg' => 'bg-red-100',    'text' => 'text-red-700'],
    'processing'           => ['label' => 'Processing',   'bg' => 'bg-purple-100', 'text' => 'text-purple-700'],
    'fulfilled'            => ['label' => 'Fulfilled',    'bg' => 'bg-emerald-100','text' => 'text-emerald-700'],
    'cancelled'            => ['label' => 'Cancelled',    'bg' => 'bg-gray-100',   'text' => 'text-gray-500'],
];
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Orders</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Storefront orders — <?= (int)($summary['total_orders'] ?? 0) ?> total</p>
    </div>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Value</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum truncate"><?= money((float)($summary['total_value'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm relative overflow-hidden">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Pending Review</div>
        <div class="font-heading font-extrabold text-xl text-amber-600"><?= (int)($summary['pending_count'] ?? 0) + (int)($summary['verification_count'] ?? 0) ?></div>
        <div class="data-text text-[9px] font-semibold text-amber-500 mt-0.5"><?= money((float)($summary['pending_value'] ?? 0), $currency) ?></div>
        <?php if (((int)($summary['pending_count'] ?? 0) + (int)($summary['verification_count'] ?? 0)) > 0): ?>
            <div class="absolute top-3 right-3 w-2.5 h-2.5 bg-amber-500 rounded-full animate-pulse"></div>
        <?php endif; ?>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Approved</div>
        <div class="font-heading font-extrabold text-xl text-green-600"><?= (int)($summary['approved_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Completed</div>
        <div class="font-heading font-extrabold text-xl text-emerald-600"><?= (int)($summary['completed_count'] ?? 0) ?></div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/orders" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by order #, name, or phone..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="status" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="">All Status</option>
            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
            <option value="pending_verification" <?= $status === 'pending_verification' ? 'selected' : '' ?>>Verify POP</option>
            <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
            <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>Processing</option>
            <option value="fulfilled" <?= $status === 'fulfilled' ? 'selected' : '' ?>>Fulfilled</option>
            <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
            <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
        </select>
        <select name="channel" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="">All Channels</option>
            <option value="cart_pop" <?= $channel === 'cart_pop' ? 'selected' : '' ?>>Cart + POP</option>
            <option value="whatsapp" <?= $channel === 'whatsapp' ? 'selected' : '' ?>>WhatsApp</option>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest First</option>
            <option value="total_desc" <?= $sort === 'total_desc' ? 'selected' : '' ?>>Amount ↓</option>
            <option value="total_asc" <?= $sort === 'total_asc' ? 'selected' : '' ?>>Amount ↑</option>
        </select>
        <button type="submit" class="btn-primary px-5 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Filter</button>
    </div>
</form>

<!-- Orders Table -->
<?php if (empty($orders)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl p-12 text-center shadow-sm">
        <svg class="w-12 h-12 mx-auto text-brand-plum/15 mb-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1"><path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>
        <p class="text-sm font-semibold text-brand-plum/40">No orders found</p>
        <p class="text-xs text-brand-plum/25 mt-1">Orders placed on your landing page will appear here.</p>
    </div>
<?php else: ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-4 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Order #</th>
                        <th class="px-4 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Customer</th>
                        <th class="px-4 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest hidden sm:table-cell">Channel</th>
                        <th class="px-4 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Total</th>
                        <th class="px-4 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Status</th>
                        <th class="px-4 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest hidden lg:table-cell">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($orders as $o):
                        $badge = $status_badges[$o['status']] ?? ['label' => ucfirst($o['status']), 'bg' => 'bg-gray-100', 'text' => 'text-gray-500'];
                    ?>
                    <tr class="hover:bg-brand-cream/30 transition-colors cursor-pointer" onclick="window.location='<?= APP_URL ?>/orders/view/<?= $o['id'] ?>'">
                        <td class="px-4 py-3">
                            <span class="data-text text-xs font-bold text-brand-plum"><?= e($o['order_number']) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="text-xs font-semibold text-brand-plum"><?= e($o['customer_name']) ?></div>
                            <div class="text-[10px] text-brand-plum/40"><?= e($o['customer_phone']) ?></div>
                        </td>
                        <td class="px-4 py-3 hidden sm:table-cell">
                            <?php if ($o['channel'] === 'whatsapp'): ?>
                                <span class="inline-flex items-center gap-1 text-[9px] font-bold text-green-600 uppercase tracking-wider">
                                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/></svg>
                                    WhatsApp
                                </span>
                            <?php else: ?>
                                <span class="text-[9px] font-bold text-brand-plum/40 uppercase tracking-wider">Cart</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3">
                            <span class="data-text text-xs font-bold accent-primary"><?= money((float)$o['total'], $currency) ?></span>
                        </td>
                        <td class="px-4 py-3">
                            <span class="inline-flex px-2.5 py-1 rounded-full text-[9px] font-bold uppercase tracking-wider <?= $badge['bg'] ?> <?= $badge['text'] ?>"><?= $badge['label'] ?></span>
                        </td>
                        <td class="px-4 py-3 hidden lg:table-cell">
                            <span class="data-text text-[10px] text-brand-plum/40"><?= date('M j, Y', strtotime($o['created_at'])) ?></span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="flex items-center justify-between mt-6">
        <span class="text-[10px] text-brand-plum/40 font-medium"><?= $total_items ?> orders — Page <?= $page ?> of <?= $total_pages ?></span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
                <a href="<?= APP_URL ?>/orders?page=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&channel=<?= urlencode($channel) ?>&sort=<?= urlencode($sort) ?>"
                   class="px-3 py-1.5 text-xs font-bold rounded-lg border border-brand-sand text-brand-plum/60 hover:bg-brand-cream transition-colors">← Prev</a>
            <?php endif; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= APP_URL ?>/orders?page=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>&channel=<?= urlencode($channel) ?>&sort=<?= urlencode($sort) ?>"
                   class="px-3 py-1.5 text-xs font-bold rounded-lg border border-brand-sand text-brand-plum/60 hover:bg-brand-cream transition-colors">Next →</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
