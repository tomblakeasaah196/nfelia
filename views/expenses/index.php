<?php
/**
 * Nfelia — Expense Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $expenses, $summary, $category_breakdown, $categories,
 *   $search, $category (filter), $status, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Expenses</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1"><?= (int)($summary['total_expenses'] ?? 0) ?> recorded expenses</p>
    </div>
    <a href="<?= APP_URL ?>/expenses/create" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        <span class="text-[10px] font-bold uppercase tracking-widest">Record Expense</span>
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">All Recorded</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum truncate"><?= money((float)($summary['total_amount'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Paid Out</div>
        <div class="font-heading font-extrabold text-xl text-green-600 truncate"><?= money((float)($summary['total_paid'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm relative overflow-hidden">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Unpaid</div>
        <div class="font-heading font-extrabold text-xl text-red-600 truncate"><?= money((float)($summary['total_unpaid'] ?? 0), $currency) ?></div>
        <?php if ((int)($summary['unpaid_count'] ?? 0) > 0): ?>
            <div class="data-text text-[9px] font-semibold text-red-500 mt-0.5"><?= (int)$summary['unpaid_count'] ?> unpaid</div>
            <div class="absolute top-3 right-3 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></div>
        <?php endif; ?>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Stock Purchases</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum truncate"><?= money((float)($summary['stock_purchase_total'] ?? 0), $currency) ?></div>
        <div class="data-text text-[9px] text-brand-plum/40 mt-0.5"><?= (int)($summary['stock_purchase_count'] ?? 0) ?> purchase<?= (int)($summary['stock_purchase_count'] ?? 0) !== 1 ? 's' : '' ?></div>
    </div>
</div>

<!-- Category Breakdown (horizontal chips) -->
<?php if (!empty($category_breakdown)): ?>
<div class="flex flex-wrap gap-2 mb-6">
    <?php foreach ($category_breakdown as $cb): ?>
        <a href="<?= APP_URL ?>/expenses?category=<?= urlencode($cb['category']) ?>"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-brand-cream border border-brand-sand hover:border-accent transition-all <?= $category === $cb['category'] ? 'border-accent bg-accent/10' : '' ?>">
            <span class="text-brand-plum/60"><?= $categories[$cb['category']] ?? ucfirst(str_replace('_', ' ', $cb['category'])) ?></span>
            <span class="text-brand-plum/30"><?= money((float)$cb['cat_total'], $currency) ?></span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/expenses" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search description, number, supplier..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="category" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text uppercase tracking-wider">
            <option value="">All Categories</option>
            <?php foreach ($categories as $k => $v): ?>
                <option value="<?= $k ?>" <?= $category === $k ? 'selected' : '' ?>><?= $v ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text uppercase tracking-wider">
            <option value="">All Status</option>
            <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid</option>
            <option value="partial" <?= $status === 'partial' ? 'selected' : '' ?>>Partial</option>
            <option value="unpaid" <?= $status === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text uppercase tracking-wider">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="highest_amount" <?= $sort === 'highest_amount' ? 'selected' : '' ?>>Highest Amount</option>
            <option value="lowest_amount" <?= $sort === 'lowest_amount' ? 'selected' : '' ?>>Lowest Amount</option>
        </select>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Filter</button>
        <?php if ($search || $category || $status || $sort !== 'newest'): ?>
            <a href="<?= APP_URL ?>/expenses" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-accent-text">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Expense List -->
<?php if (!empty($expenses)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Number</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Category</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Description</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Supplier</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Amount</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($expenses as $exp):
                        $pay_badge = match($exp['payment_status']) {
                            'paid'    => 'bg-green-100 text-green-700',
                            'partial' => 'bg-amber-100 text-amber-700',
                            'unpaid'  => 'bg-red-100 text-red-700',
                            default   => 'bg-gray-100 text-gray-700',
                        };
                    ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors <?= $exp['payment_status'] === 'unpaid' ? 'bg-red-50/30' : '' ?>">
                            <td class="px-5 py-4">
                                <a href="<?= APP_URL ?>/expenses/view/<?= $exp['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-accent-text"><?= e($exp['doc_number']) ?></a>
                            </td>
                            <td class="px-5 py-4">
                                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-brand-cream text-brand-plum/60"><?= ucfirst(str_replace('_', ' ', $exp['category'])) ?></span>
                            </td>
                            <td class="px-5 py-4 text-xs text-brand-plum/70 max-w-[200px] truncate"><?= e($exp['description']) ?></td>
                            <td class="px-5 py-4">
                                <?php if ($exp['supplier_name']): ?>
                                    <span class="text-[10px] font-medium text-brand-plum/60"><?= e($exp['supplier_name']) ?></span>
                                <?php else: ?>
                                    <span class="text-[10px] text-brand-plum/30">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 data-text text-[10px] text-brand-plum/60"><?= fmt_date($exp['expense_date'], 'd M Y') ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$exp['amount'], $currency) ?></td>
                            <td class="px-5 py-4 text-center">
                                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $pay_badge ?>"><?= ucfirst($exp['payment_status']) ?></span>
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
            <?php $qp = $_GET; unset($qp['page']); $base = APP_URL . '/expenses?' . http_build_query($qp); ?>
            <?php if ($page > 1): ?>
                <a href="<?= $base ?>&page=<?= $page - 1 ?>" class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-brand-plum/60 bg-brand-white border border-brand-sand rounded-lg hover:border-accent">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="<?= $base ?>&page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center text-[10px] font-bold rounded-lg <?= $i === $page ? 'btn-primary text-white' : 'text-brand-plum/60 bg-brand-white border border-brand-sand hover:border-accent' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= $base ?>&page=<?= $page + 1 ?>" class="px-3 py-2 text-[10px] font-bold uppercase tracking-widest text-brand-plum/60 bg-brand-white border border-brand-sand rounded-lg hover:border-accent">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-12 text-center">
        <div class="w-16 h-16 mx-auto bg-brand-cream rounded-2xl flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <?php if ($search || $category || $status): ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No expenses match your filters</h3>
            <a href="<?= APP_URL ?>/expenses" class="text-[10px] font-bold text-accent-text uppercase tracking-widest">Clear Filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No expenses yet</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">Record your business expenses to track spending, stock purchases, and supplier payments.</p>
            <a href="<?= APP_URL ?>/expenses/create" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">Record First Expense</a>
        <?php endif; ?>
    </div>
<?php endif; ?>