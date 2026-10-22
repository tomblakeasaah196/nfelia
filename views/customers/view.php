<?php
/**
 * Nfelia — Customer Profile / Detail View (CRM Page)
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $customer, $quotations, $invoices,
 *   $payments, $receipts, $debts, $stats, $wa_number,
 *   $orders, $kept (kept with you, for their next order), $kept_lines (kept_lines())
 */
$currency = $business['currency_symbol'] ?? '₦';
$wa_link  = $wa_number ? "https://wa.me/{$wa_number}" : '';

// Status config helper for invoices
$inv_status_badge = function($s) {
    return match($s) {
        'draft'          => 'bg-gray-100 text-gray-700',
        'sent'           => 'bg-blue-100 text-blue-700',
        'partially_paid' => 'bg-amber-100 text-amber-700',
        'paid'           => 'bg-green-100 text-green-700',
        'overdue'        => 'bg-red-100 text-red-700',
        'cancelled'      => 'bg-gray-200 text-gray-500',
        default          => 'bg-gray-100 text-gray-700',
    };
};
$quo_status_badge = function($s) {
    return match($s) {
        'draft'    => 'bg-gray-100 text-gray-700',
        'sent'     => 'bg-blue-100 text-blue-700',
        'accepted' => 'bg-green-100 text-green-700',
        'declined' => 'bg-red-100 text-red-700',
        'expired'  => 'bg-amber-100 text-amber-700',
        'converted'=> 'bg-purple-100 text-purple-700',
        default    => 'bg-gray-100 text-gray-700',
    };
};
?>

<!-- Breadcrumb + Actions -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
    <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/customers" class="text-brand-plum/40 hover:text-brand-plum transition-colors">
            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
        </a>
        <div>
            <h1 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight"><?= e($customer['full_name']) ?></h1>
            <div class="flex items-center gap-2 mt-0.5">
                <?php if ($customer['business_name']): ?>
                    <span class="text-sm text-brand-plum/60 font-medium"><?= e($customer['business_name']) ?></span>
                    <span class="text-brand-plum/20">•</span>
                <?php endif; ?>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap <?= $customer['is_active'] ? 'bg-green-100 text-green-700' : 'bg-gray-200 text-gray-500' ?>">
                    <?= $customer['is_active'] ? 'Active' : 'Inactive' ?>
                </span>
                <span class="text-sm text-brand-plum/50">Since <?= fmt_date($customer['created_at'], 'M Y') ?></span>
            </div>
        </div>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <a href="<?= APP_URL ?>/customers/edit/<?= $customer['id'] ?>" class="flex items-center gap-1.5 bg-brand-white border border-brand-sand hover:border-accent min-h-[44px] px-3 py-2 rounded-lg transition-all">
            <svg class="w-3.5 h-3.5 text-accent-text" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            <span class="text-sm font-semibold text-brand-plum">Edit</span>
        </a>

        <?php if ($wa_link): ?>
        <a href="<?= $wa_link ?>" target="_blank" class="flex items-center gap-1.5 bg-green-500 text-white min-h-[44px] px-3 py-2 rounded-lg hover:bg-green-600 transition-all">
            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            <span class="text-sm font-semibold">WhatsApp</span>
        </a>
        <?php endif; ?>

        <?php if ($customer['phone']): ?>
        <a href="tel:<?= e($customer['phone']) ?>" class="flex items-center gap-1.5 bg-brand-cream border border-brand-sand hover:border-accent min-h-[44px] px-3 py-2 rounded-lg transition-all">
            <svg class="w-3.5 h-3.5 text-brand-plum/50" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            <span class="text-sm font-semibold text-brand-plum/70">Call</span>
        </a>
        <?php endif; ?>

        <!-- Quick Actions -->
        <a href="<?= APP_URL ?>/orders/create?customer=<?= $customer['id'] ?>" class="flex items-center gap-1.5 btn-primary text-white min-h-[44px] px-3 py-2 rounded-lg transition-all">
            <span class="text-sm font-semibold">New order</span>
        </a>

        <a href="<?= APP_URL ?>/quotations/create?customer_id=<?= $customer['id'] ?>" class="flex items-center gap-1.5 bg-brand-white border border-brand-sand hover:border-accent min-h-[44px] px-3 py-2 rounded-lg transition-all">
            <span class="text-sm font-semibold text-brand-plum">New quotation</span>
        </a>

        <a href="<?= APP_URL ?>/tasks?new=appointment&customer=<?= $customer['id'] ?>" class="flex items-center gap-1.5 bg-brand-white border border-brand-sand hover:border-accent min-h-[44px] px-3 py-2 rounded-lg transition-all">
            <span class="text-sm font-semibold text-brand-plum">New appointment</span>
        </a>
    </div>
</div>

<!-- Contact Details Card -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6">
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div>
            <div class="text-sm text-brand-plum/60 mb-0.5">Phone</div>
            <div class="text-sm font-semibold text-brand-plum break-words"><?= $customer['phone'] ? e($customer['phone']) : '—' ?></div>
        </div>
        <div>
            <div class="text-sm text-brand-plum/60 mb-0.5">WhatsApp</div>
            <div class="text-sm font-semibold text-brand-plum break-words"><?= $customer['whatsapp'] ? e($customer['whatsapp']) : ($customer['phone'] ? 'Same as phone' : '—') ?></div>
        </div>
        <div>
            <div class="text-sm text-brand-plum/60 mb-0.5">Email</div>
            <div class="text-sm font-semibold text-brand-plum break-words"><?= $customer['email'] ? e($customer['email']) : '—' ?></div>
        </div>
        <div>
            <div class="text-sm text-brand-plum/60 mb-0.5">Address</div>
            <div class="text-sm font-semibold text-brand-plum break-words"><?= $customer['address'] ? e($customer['address']) : '—' ?></div>
        </div>
    </div>
    <?php if ($customer['notes']): ?>
        <div class="mt-3 pt-3 border-t border-brand-sand/50">
            <div class="text-sm text-brand-plum/60 mb-0.5">Notes</div>
            <p class="text-xs text-brand-plum/60 whitespace-pre-line"><?= e($customer['notes']) ?></p>
        </div>
    <?php endif; ?>
</div>

<!-- Coming up with this customer (Tasks & Calendar) -->
<?php if (!empty($upcoming)): ?>
<div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm mb-6 overflow-hidden">
    <div class="flex items-center justify-between gap-3 px-5 pt-4 pb-2">
        <h2 class="font-heading font-bold text-base text-brand-plum">Coming up with <?= e(planner_first_name($customer['full_name'])) ?></h2>
        <a href="<?= APP_URL ?>/tasks?new=task&customer=<?= $customer['id'] ?>" class="h-11 px-2 inline-flex items-center text-sm font-semibold text-accent-text">Add a task</a>
    </div>
    <ul class="divide-y divide-brand-sand">
        <?php foreach (array_slice($upcoming, 0, 6) as $it): ?>
            <li class="flex items-center justify-between gap-3 px-5 py-3">
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-brand-plum"><?= e($it['title']) ?></p>
                    <p class="text-xs text-brand-plum/60">
                        <?= e(planner_fmt_day($it['date'], planner_today())) ?><?= $it['time'] ? ', ' . e(planner_fmt_time($it['time'])) : '' ?><?= $it['kind'] === 'appointment' ? ' · Appointment' : '' ?><?= $it['parent_title'] ? ' · ' . e($it['parent_title']) : '' ?>
                        <?php if ($it['countdown']): ?><span class="ml-1 font-semibold <?= in_array($it['countdown']['tone'], ['late', 'today'], true) ? 'text-[#B42318]' : 'text-[#93370D]' ?>"><?= e($it['countdown']['label']) ?></span><?php endif; ?>
                    </p>
                </div>
                <?php if ($it['wa']): ?>
                    <a href="<?= e($it['wa']) ?>" target="_blank" rel="noopener" class="shrink-0 h-11 px-3 inline-flex items-center rounded-lg border border-brand-sand text-xs font-semibold text-[#0E7A3F] hover:border-[#0E7A3F]"><?= e($it['wa_label']) ?></a>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (count($upcoming) > 6): ?>
        <a href="<?= APP_URL ?>/tasks?tab=calendar" class="block px-5 py-3 text-sm font-semibold text-accent-text border-t border-brand-sand">See all <?= count($upcoming) ?> on the calendar</a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Financial Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="text-sm text-brand-plum/60 mb-1">Total invoiced</div>
        <div class="font-heading font-extrabold text-lg text-brand-plum truncate"><?= money((float)$customer['total_invoiced'], $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="text-sm text-brand-plum/60 mb-1">Total received</div>
        <div class="font-heading font-extrabold text-lg text-green-600 truncate"><?= money((float)$customer['total_received'], $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="text-sm text-brand-plum/60 mb-1">Outstanding</div>
        <div class="font-heading font-extrabold text-lg <?= (float)$customer['outstanding_balance'] > 0 ? 'text-red-600' : 'text-green-600' ?> truncate">
            <?= (float)$customer['outstanding_balance'] > 0 ? money((float)$customer['outstanding_balance'], $currency) : 'Clear' ?>
        </div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="text-sm text-brand-plum/60 mb-1">Invoices</div>
        <div class="font-heading font-extrabold text-lg text-brand-plum"><?= (int)$stats['invoice_count'] ?></div>
        <?php if ((int)$stats['overdue_count'] > 0): ?>
            <div class="text-xs font-semibold text-red-600 mt-0.5"><?= (int)$stats['overdue_count'] ?> overdue</div>
        <?php endif; ?>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="text-sm text-brand-plum/60 mb-1">Quotations</div>
        <div class="font-heading font-extrabold text-lg text-brand-plum"><?= (int)$stats['quote_count'] ?></div>
    </div>
</div>

<?php $first_name = planner_first_name($customer['full_name']) ?: 'them';
      $open_tab = ($_GET['tab'] ?? '') === 'kept' ? 'kept' : ($orders ? 'orders' : 'invoices');
      if ($kept > 0): ?>
    <p class="nf-kept-note" style="margin-bottom:16px"><span><b><?= planner_money($kept, $currency) ?></b> kept with you. It is offered on <?= e($first_name) ?>'s next order.</span></p>
<?php endif; ?>

<!-- Tabbed History Sections -->
<div x-data="{ tab: '<?= $open_tab ?>' }">

    <!-- Tab buttons -->
    <div class="flex items-center gap-1 mb-4 overflow-x-auto">
        <button @click="tab = 'orders'" :class="tab === 'orders' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="min-h-[44px] px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap">
            Orders (<?= count($orders) ?>)
        </button>
        <button @click="tab = 'kept'" :class="tab === 'kept' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="min-h-[44px] px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap">
            Kept with you<?= $kept > 0 ? ' (' . planner_money($kept, $currency) . ')' : '' ?>
        </button>
        <button @click="tab = 'invoices'" :class="tab === 'invoices' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="min-h-[44px] px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap">
            Invoices (<?= count($invoices) ?>)
        </button>
        <button @click="tab = 'quotations'" :class="tab === 'quotations' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="min-h-[44px] px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap">
            Quotations (<?= count($quotations) ?>)
        </button>
        <button @click="tab = 'payments'" :class="tab === 'payments' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="min-h-[44px] px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap">
            Payments (<?= count($payments) ?>)
        </button>
        <button @click="tab = 'receipts'" :class="tab === 'receipts' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="min-h-[44px] px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap">
            Receipts (<?= count($receipts) ?>)
        </button>
        <?php if (!empty($debts)): ?>
        <button @click="tab = 'debts'" :class="tab === 'debts' ? 'btn-primary text-white' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-accent'"
                class="min-h-[44px] px-4 py-2 rounded-lg text-sm font-semibold transition-all whitespace-nowrap">
            Debts (<?= count($debts) ?>)
        </button>
        <?php endif; ?>
    </div>

    <!-- ORDERS TAB -->
    <div x-show="tab === 'orders'" class="mb-8">
        <?php if ($orders): $today = planner_today(); $row_for_client = true; ?>
            <div class="nf-items">
                <?php foreach ($orders as $o) require BASE_PATH . '/views/partials/order_row.php'; ?>
            </div>
        <?php else: ?>
            <div class="nf-empty">
                <span class="nf-tile-ic"><?= nav_icon('orders') ?></span>
                <h2>No orders yet</h2>
                <p>Every sale with <?= e(planner_first_name($customer['full_name'])) ?> shows here.</p>
                <a class="nf-btn nf-btn-primary" href="<?= APP_URL ?>/orders/create?customer=<?= (int)$customer['id'] ?>"><?= nav_icon('plus') ?>New order</a>
            </div>
        <?php endif; ?>
    </div>

    <!-- KEPT WITH YOU TAB: money paid in advance or kept from an order, waiting for their next order -->
    <div x-show="tab === 'kept'" x-cloak class="mb-8">
        <section class="nf-card nf-callout is-kept" aria-labelledby="nf-kept-tab-h">
            <h2 id="nf-kept-tab-h"><?= $kept > 0 ? planner_money($kept, $currency) . ' kept with you' : 'Nothing kept with you yet' ?></h2>
            <p>When <?= e($first_name) ?> pays before choosing what to buy, add it here. A receipt is made, and their next order offers it to use, all or part.</p>
            <div class="nf-callout-do">
                <button type="button" class="nf-btn nf-btn-primary" data-open="nf-kept-add"><?= nav_icon('plus') ?>Add money</button>
            </div>
        </section>
        <?php if ($kept_lines): ?>
            <section class="nf-card" aria-label="Kept money, line by line">
                <ul class="nf-pays">
                    <?php foreach ($kept_lines as $k): $amt = (float)$k['amount']; ?>
                        <li>
                            <span class="nf-pay-main">
                                <b class="<?= $amt > 0 ? 'is-in' : '' ?>"><?= $amt < 0 ? '−' : '+' ?><?= planner_money(abs($amt), $currency) ?></b>
                                <small><?= e(screen_date($k['day'])) ?> · <?= e(match (true) {   // the order or receipt number is the link beside it
                                    !empty($k['receipt_id']) => 'Paid in advance',
                                    $amt > 0 => str_starts_with((string)$k['note'], 'Kept from cancelled') ? 'Kept when the order was cancelled' : 'Paid over on an order',
                                    str_starts_with((string)$k['note'], KEPT_USED_NOTE) => 'Used on an order',
                                    default => 'Given back',
                                }) ?></small>
                            </span>
                            <?php if (!empty($k['receipt_number'])): ?>
                                <a class="nf-link" href="<?= APP_URL ?>/receipts/view/<?= (int)$k['receipt_id'] ?>" data-prefetch><?= e($k['receipt_number']) ?></a>
                            <?php elseif (!empty($k['order_number'])): ?>
                                <a class="nf-link" href="<?= APP_URL ?>/orders/view/<?= (int)$k['order_id'] ?>" data-prefetch><?= e($k['order_number']) ?></a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>
    </div>

    <!-- INVOICES TAB -->
    <div x-show="tab === 'invoices'" class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
        <?php if (!empty($invoices)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Number</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Date</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Due</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-right">Total</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-right">Balance</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($invoices as $inv): ?>
                        <tr class="hover:bg-brand-cream/30 <?= $inv['status'] === 'overdue' ? 'bg-red-50/30' : '' ?>">
                            <td class="px-5 py-3 whitespace-nowrap"><a href="<?= APP_URL ?>/invoices/view/<?= $inv['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-accent-text"><?= e($inv['doc_number']) ?></a></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs text-brand-plum/60"><?= fmt_date($inv['issue_date'], 'd M Y') ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs <?= $inv['status'] === 'overdue' ? 'text-red-600 font-bold' : 'text-brand-plum/60' ?>"><?= $inv['due_date'] ? fmt_date($inv['due_date'], 'd M Y') : '—' ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$inv['total'], $currency) ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs font-bold text-right <?= (float)$inv['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= (float)$inv['balance_due'] > 0 ? money((float)$inv['balance_due'], $currency) : 'Paid' ?></td>
                            <td class="px-5 py-3 text-center"><span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap <?= $inv_status_badge($inv['status']) ?>"><?= ucfirst(str_replace('_', ' ', $inv['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="p-8 text-center text-xs text-brand-plum/40">No invoices yet for this customer.</div>
        <?php endif; ?>
    </div>

    <!-- QUOTATIONS TAB -->
    <div x-show="tab === 'quotations'" class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
        <?php if (!empty($quotations)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Number</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Date</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-right">Total</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($quotations as $q): ?>
                        <tr class="hover:bg-brand-cream/30">
                            <td class="px-5 py-3 whitespace-nowrap"><a href="<?= APP_URL ?>/quotations/view/<?= $q['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-accent-text"><?= e($q['doc_number']) ?></a></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs text-brand-plum/60"><?= fmt_date($q['issue_date'], 'd M Y') ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$q['total'], $currency) ?></td>
                            <td class="px-5 py-3 text-center"><span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap <?= $quo_status_badge($q['status']) ?>"><?= ucfirst(str_replace('_', ' ', $q['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="p-8 text-center text-xs text-brand-plum/40">No quotations yet for this customer.</div>
        <?php endif; ?>
    </div>

    <!-- PAYMENTS TAB -->
    <div x-show="tab === 'payments'" class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
        <?php if (!empty($payments)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Date</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-right">Amount</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Method</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Invoice</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($payments as $p): ?>
                        <tr class="hover:bg-brand-cream/30">
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs text-brand-plum/60"><?= fmt_date($p['payment_date'], 'd M Y') ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs font-bold text-green-600 text-right"><?= money((float)$p['amount'], $currency) ?></td>
                            <td class="px-5 py-3"><span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap bg-gray-100 text-gray-600"><?= ucfirst(str_replace('_', ' ', $p['method'])) ?></span></td>
                            <td class="px-5 py-3 whitespace-nowrap"><a href="<?= APP_URL ?>/invoices/view/<?= $p['invoice_id'] ?>" class="data-text text-xs font-bold text-blue-600 hover:text-blue-700"><?= e($p['invoice_number']) ?></a></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs text-brand-plum/50"><?= $p['reference'] ? e($p['reference']) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="p-8 text-center text-xs text-brand-plum/40">No payments recorded for this customer.</div>
        <?php endif; ?>
    </div>

    <!-- RECEIPTS TAB -->
    <div x-show="tab === 'receipts'" class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
        <?php if (!empty($receipts)): ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Number</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Date</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-right">Amount</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Method</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-center">Type</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($receipts as $r): ?>
                        <tr class="hover:bg-brand-cream/30">
                            <td class="px-5 py-3 whitespace-nowrap"><a href="<?= APP_URL ?>/receipts/view/<?= $r['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-accent-text"><?= e($r['doc_number']) ?></a></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs text-brand-plum/60"><?= fmt_date($r['issue_date'], 'd M Y') ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs font-bold text-green-600 text-right"><?= money((float)$r['amount'], $currency) ?></td>
                            <td class="px-5 py-3"><span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap bg-gray-100 text-gray-600"><?= ucfirst(str_replace('_', ' ', $r['method'])) ?></span></td>
                            <td class="px-5 py-3 text-center">
                                <span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap <?= $r['invoice_id'] ? 'bg-blue-100 text-blue-700' : ($r['is_advance'] ? 'bg-green-100 text-green-700' : 'bg-purple-100 text-purple-700') ?>">
                                    <?= $r['invoice_id'] ? 'Invoice' : ($r['is_advance'] ? 'Paid in advance' : 'Direct sale') ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
            <div class="p-8 text-center text-xs text-brand-plum/40">No receipts for this customer.</div>
        <?php endif; ?>
    </div>

    <!-- DEBTS TAB -->
    <?php if (!empty($debts)): ?>
    <div x-show="tab === 'debts'" class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-8">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Description</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-right">Total</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-right">Settled</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-right">Balance</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap">Due</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/60 whitespace-nowrap text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($debts as $d):
                        $d_badge = match($d['status']) {
                            'active'      => 'bg-red-100 text-red-700',
                            'settling'    => 'bg-amber-100 text-amber-700',
                            'cleared'     => 'bg-green-100 text-green-700',
                            'written_off' => 'bg-gray-200 text-gray-500',
                            default       => 'bg-gray-100 text-gray-700',
                        };
                    ?>
                        <tr class="hover:bg-brand-cream/30">
                            <td class="px-5 py-3">
                                <div class="text-sm font-semibold text-brand-plum break-words"><?= e($d['description']) ?></div>
                                <?php if ($d['invoice_id']): ?>
                                    <a href="<?= APP_URL ?>/invoices/view/<?= $d['invoice_id'] ?>" class="text-xs text-blue-600 font-semibold">View invoice</a>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$d['total_amount'], $currency) ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs font-bold text-green-600 text-right"><?= money((float)$d['amount_settled'], $currency) ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs font-bold text-red-600 text-right"><?= money((float)$d['balance'], $currency) ?></td>
                            <td class="px-5 py-3 whitespace-nowrap data-text text-xs text-brand-plum/60"><?= $d['due_date'] ? fmt_date($d['due_date'], 'd M Y') : '—' ?></td>
                            <td class="px-5 py-3 text-center"><span class="text-xs font-semibold px-2 py-0.5 rounded-full whitespace-nowrap <?= $d_badge ?>"><?= ucfirst(str_replace('_', ' ', $d['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Footer Actions -->
<div class="flex items-center justify-between mb-8">
    <form method="POST" action="<?= APP_URL ?>/customers/toggle_active/<?= $customer['id'] ?>" class="inline">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <button class="min-h-[44px] text-sm font-semibold <?= $customer['is_active'] ? 'text-amber-700 hover:text-amber-800' : 'text-green-700 hover:text-green-800' ?> transition-colors">
            <?= $customer['is_active'] ? 'Deactivate customer' : 'Reactivate customer' ?>
        </button>
    </form>

    <form method="POST" action="<?= APP_URL ?>/customers/delete/<?= $customer['id'] ?>" class="inline"
          onsubmit="return confirm('Remove this customer? They will no longer appear in your customer list.');">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?? '' ?>">
        <button class="min-h-[44px] text-sm font-semibold text-red-600 hover:text-red-700 transition-colors">Remove customer</button>
    </form>
</div>
<!-- Add money: paid in advance, kept for their next order -->
<dialog class="nf-sheet" id="nf-kept-add" aria-labelledby="nf-kept-add-h">
    <div class="nf-grab"></div>
    <div class="nf-sheet-h"><h2 id="nf-kept-add-h">Add money for <?= e($first_name) ?></h2><button type="button" class="nf-icon-btn" data-close aria-label="Close"><?= nav_icon('close') ?></button></div>
    <form method="POST" action="<?= APP_URL ?>/customers/add_money/<?= (int)$customer['id'] ?>" class="nf-form" data-once>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>"><?= form_once() ?>
        <label>How much
            <span class="nf-amount"><span><?= e($currency) ?></span><input type="text" name="amount" inputmode="decimal" required autocomplete="off"></span></label>
        <?php $pm_id = 'kept'; require BASE_PATH . '/views/orders/_methods.php'; ?>
        <label>Came in on <small>Reports count it on this day</small>
            <input type="date" name="date" value="<?= e(planner_today()) ?>" max="<?= e(planner_today()) ?>" required></label>
        <details class="nf-more-field"><summary>Add a reference</summary>
            <label>Reference <small>The transfer or POS reference. It goes on the receipt.</small><input type="text" name="reference" maxlength="100" autocomplete="off"></label>
        </details>
        <button class="nf-btn nf-btn-primary nf-sheet-go"><?= nav_icon('check') ?>Keep it for <?= e($first_name) ?></button>
        <p class="nf-help">A receipt is made for <?= e($first_name) ?> straight away. Their next order offers this money.</p>
    </form>
</dialog>
