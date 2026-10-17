<?php
/**
 * Nfelia — Invoice Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $invoices, $summary,
 *   $search, $status, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Invoices</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Commercial Invoices — <?= (int)($summary['total_invoices'] ?? 0) ?> total</p>
    </div>
    <a href="<?= APP_URL ?>/invoices/create" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <span class="text-[10px] font-bold uppercase tracking-widest">New Invoice</span>
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Invoiced</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum truncate"><?= money((float)($summary['total_value'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Collected</div>
        <div class="font-heading font-extrabold text-xl text-green-600 truncate"><?= money((float)($summary['total_collected'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Outstanding</div>
        <div class="font-heading font-extrabold text-xl text-amber-600 truncate"><?= money((float)($summary['total_outstanding'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm relative overflow-hidden">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Overdue</div>
        <div class="font-heading font-extrabold text-xl text-red-600"><?= (int)($summary['overdue_count'] ?? 0) ?></div>
        <?php if ((float)($summary['overdue_value'] ?? 0) > 0): ?>
            <div class="data-text text-[9px] font-semibold text-red-500 mt-0.5"><?= money((float)$summary['overdue_value'], $currency) ?></div>
        <?php endif; ?>
        <?php if ((int)($summary['overdue_count'] ?? 0) > 0): ?>
            <div class="absolute top-3 right-3 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/invoices" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by number or client..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="status" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="">All Status</option>
            <?php foreach (['draft','sent','partially_paid','paid','overdue','cancelled'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $s)) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="due_date" <?= $sort === 'due_date' ? 'selected' : '' ?>>Due Date</option>
            <option value="total_desc" <?= $sort === 'total_desc' ? 'selected' : '' ?>>Highest Value</option>
            <option value="balance_desc" <?= $sort === 'balance_desc' ? 'selected' : '' ?>>Highest Balance</option>
        </select>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Filter</button>
        <?php if ($search || $status || $sort !== 'newest'): ?>
            <a href="<?= APP_URL ?>/invoices" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Invoice List -->
<?php if (!empty($invoices)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Number</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Client</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Due</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Total</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Balance</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($invoices as $inv):
                        $s_cfg = match($inv['status']) {
                            'draft'          => ['bg-gray-100 text-gray-700', 'Draft'],
                            'sent'           => ['bg-blue-100 text-blue-700', 'Sent'],
                            'partially_paid' => ['bg-amber-100 text-amber-700', 'Partial'],
                            'paid'           => ['bg-green-100 text-green-700', 'Paid'],
                            'overdue'        => ['bg-red-100 text-red-700', 'Overdue'],
                            'cancelled'      => ['bg-gray-200 text-gray-500', 'Cancelled'],
                            default          => ['bg-gray-100 text-gray-700', ucfirst($inv['status'])],
                        };
                        $is_overdue = $inv['status'] === 'overdue';
                    ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors <?= $is_overdue ? 'bg-red-50/30' : '' ?>">
                            <td class="px-5 py-4">
                                <a href="<?= APP_URL ?>/invoices/view/<?= $inv['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-brand-burgundy"><?= e($inv['doc_number']) ?></a>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-xs font-semibold text-brand-plum"><?= e($inv['customer_name'] ?? 'Unknown') ?></div>
                            </td>
                            <td class="px-5 py-4 data-text text-[10px] text-brand-plum/60"><?= fmt_date($inv['issue_date'], 'd M Y') ?></td>
                            <td class="px-5 py-4 data-text text-[10px] <?= $is_overdue ? 'text-red-600 font-bold' : 'text-brand-plum/60' ?>"><?= $inv['due_date'] ? fmt_date($inv['due_date'], 'd M Y') : '—' ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$inv['total'], $currency) ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-right <?= (float)$inv['balance_due'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= (float)$inv['balance_due'] > 0 ? money((float)$inv['balance_due'], $currency) : 'Paid' ?></td>
                            <td class="px-5 py-4 text-center">
                                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg <?= $s_cfg[0] ?>"><?= $s_cfg[1] ?></span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= APP_URL ?>/invoices/view/<?= $inv['id'] ?>" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">View</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav class="flex items-center justify-center gap-2 mb-8">
            <?php $qp = $_GET; unset($qp['page']); $base = APP_URL . '/invoices?' . http_build_query($qp); ?>
            <?php if ($page > 1): ?>
                <a href="<?= $base ?>&page=<?= $page - 1 ?>" class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-brand-plum/60 bg-brand-white border border-brand-sand rounded-lg hover:border-brand-gold">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="<?= $base ?>&page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center text-[10px] font-bold rounded-lg <?= $i === $page ? 'btn-primary text-white' : 'text-brand-plum/60 bg-brand-white border border-brand-sand hover:border-brand-gold' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= $base ?>&page=<?= $page + 1 ?>" class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-brand-plum/60 bg-brand-white border border-brand-sand rounded-lg hover:border-brand-gold">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-12 text-center">
        <div class="w-16 h-16 mx-auto bg-brand-cream rounded-2xl flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
        </div>
        <?php if ($search || $status): ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No invoices match your filters</h3>
            <a href="<?= APP_URL ?>/invoices" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest">Clear Filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No invoices yet</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">Create your first Commercial Invoice or convert a quotation.</p>
            <a href="<?= APP_URL ?>/invoices/create" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">Create First Invoice</a>
        <?php endif; ?>
    </div>
<?php endif; ?>