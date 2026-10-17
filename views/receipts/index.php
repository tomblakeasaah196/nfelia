<?php
/**
 * Nfelia — Receipt Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $receipts, $summary,
 *   $search, $type, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Receipts</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Payment Acknowledgements — <?= (int)($summary['total_receipts'] ?? 0) ?> total</p>
    </div>
    <a href="<?= APP_URL ?>/receipts/create" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <span class="text-[10px] font-bold uppercase tracking-widest">New Receipt</span>
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Receipts</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum"><?= (int)($summary['total_receipts'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Value</div>
        <div class="font-heading font-extrabold text-xl text-green-600 truncate"><?= money((float)($summary['total_value'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Invoice Payments</div>
        <div class="font-heading font-extrabold text-xl text-blue-600"><?= (int)($summary['invoice_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Direct Sales</div>
        <div class="font-heading font-extrabold text-xl text-purple-600"><?= (int)($summary['standalone_count'] ?? 0) ?></div>
        <?php if ((float)($summary['standalone_value'] ?? 0) > 0): ?>
            <div class="data-text text-[9px] font-semibold text-purple-500 mt-0.5"><?= money((float)$summary['standalone_value'], $currency) ?></div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/receipts" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by number or client..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="type" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="">All Types</option>
            <option value="invoice" <?= $type === 'invoice' ? 'selected' : '' ?>>Invoice Payments</option>
            <option value="standalone" <?= $type === 'standalone' ? 'selected' : '' ?>>Direct Sales</option>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="amount_desc" <?= $sort === 'amount_desc' ? 'selected' : '' ?>>Highest Amount</option>
            <option value="amount_asc" <?= $sort === 'amount_asc' ? 'selected' : '' ?>>Lowest Amount</option>
        </select>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Filter</button>
        <?php if ($search || $type || $sort !== 'newest'): ?>
            <a href="<?= APP_URL ?>/receipts" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Receipt List -->
<?php if (!empty($receipts)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Number</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Client</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Amount</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Method</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Type</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($receipts as $rec): ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors">
                            <td class="px-5 py-4">
                                <a href="<?= APP_URL ?>/receipts/view/<?= $rec['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-brand-burgundy"><?= e($rec['doc_number']) ?></a>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-xs font-semibold text-brand-plum"><?= e($rec['customer_name'] ?? 'Walk-in') ?></div>
                            </td>
                            <td class="px-5 py-4 data-text text-[10px] text-brand-plum/60"><?= fmt_date($rec['issue_date'], 'd M Y') ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-green-600 text-right"><?= money((float)$rec['amount'], $currency) ?></td>
                            <td class="px-5 py-4 text-center">
                                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-gray-100 text-gray-600"><?= ucfirst(str_replace('_', ' ', $rec['method'])) ?></span>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if ($rec['invoice_id']): ?>
                                    <a href="<?= APP_URL ?>/invoices/view/<?= $rec['invoice_id'] ?>" class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-blue-100 text-blue-700 hover:bg-blue-200"><?= e($rec['invoice_number']) ?></a>
                                <?php else: ?>
                                    <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-purple-100 text-purple-700">Direct Sale</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= APP_URL ?>/receipts/view/<?= $rec['id'] ?>" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">View</a>
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
            <?php $qp = $_GET; unset($qp['page']); $base = APP_URL . '/receipts?' . http_build_query($qp); ?>
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
            <svg class="w-8 h-8 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z"/></svg>
        </div>
        <?php if ($search || $type): ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No receipts match your filters</h3>
            <a href="<?= APP_URL ?>/receipts" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest">Clear Filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No receipts yet</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">Record a payment on an invoice or create a direct sale receipt.</p>
            <a href="<?= APP_URL ?>/receipts/create" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">Create Direct Sale</a>
        <?php endif; ?>
    </div>
<?php endif; ?>