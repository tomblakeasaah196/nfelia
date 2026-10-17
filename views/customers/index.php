<?php
/**
 * Nfelia — Customer Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $customers, $summary,
 *   $search, $filter, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Customers</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1"><?= (int)($summary['active_count'] ?? 0) ?> active clients</p>
    </div>
    <a href="<?= APP_URL ?>/customers/create" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
        <span class="text-[10px] font-bold uppercase tracking-widest">New Customer</span>
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Customers</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum"><?= (int)($summary['total_customers'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Invoiced</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum truncate"><?= money((float)($summary['total_invoiced'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Received</div>
        <div class="font-heading font-extrabold text-xl text-green-600 truncate"><?= money((float)($summary['total_received'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm relative overflow-hidden">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Outstanding</div>
        <div class="font-heading font-extrabold text-xl text-red-600 truncate"><?= money((float)($summary['total_outstanding'] ?? 0), $currency) ?></div>
        <?php if ((int)($summary['owing_count'] ?? 0) > 0): ?>
            <div class="data-text text-[9px] font-semibold text-red-500 mt-0.5"><?= (int)$summary['owing_count'] ?> customer<?= (int)$summary['owing_count'] > 1 ? 's' : '' ?> owing</div>
            <div class="absolute top-3 right-3 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/customers" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, phone, email..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="filter" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="">Active</option>
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
            <option value="owing" <?= $filter === 'owing' ? 'selected' : '' ?>>Owing</option>
            <option value="clear" <?= $filter === 'clear' ? 'selected' : '' ?>>Clear Balance</option>
            <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A-Z</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="highest_invoiced" <?= $sort === 'highest_invoiced' ? 'selected' : '' ?>>Most Invoiced</option>
            <option value="highest_owing" <?= $sort === 'highest_owing' ? 'selected' : '' ?>>Highest Owing</option>
            <option value="highest_received" <?= $sort === 'highest_received' ? 'selected' : '' ?>>Most Received</option>
        </select>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Filter</button>
        <?php if ($search || $filter || $sort !== 'name'): ?>
            <a href="<?= APP_URL ?>/customers" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Customer List -->
<?php if (!empty($customers)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Customer</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Contact</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Invoiced</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Received</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Outstanding</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($customers as $c):
                        $has_debt = (float)$c['outstanding_balance'] > 0;
                        $is_overdue = (int)($c['overdue_invoices'] ?? 0) > 0;
                    ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors <?= $is_overdue ? 'bg-red-50/30' : '' ?>">
                            <td class="px-5 py-4">
                                <a href="<?= APP_URL ?>/customers/view/<?= $c['id'] ?>" class="text-xs font-bold text-brand-plum hover:text-brand-burgundy"><?= e($c['full_name']) ?></a>
                                <?php if ($c['business_name']): ?>
                                    <div class="text-[10px] text-brand-plum/50"><?= e($c['business_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-[10px] text-brand-plum/60"><?= e($c['phone'] ?? '—') ?></div>
                                <?php if ($c['email']): ?>
                                    <div class="text-[10px] text-brand-plum/40"><?= e($c['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$c['total_invoiced'], $currency) ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-green-600 text-right"><?= money((float)$c['total_received'], $currency) ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-right <?= $has_debt ? 'text-red-600' : 'text-green-600' ?>">
                                <?= $has_debt ? money((float)$c['outstanding_balance'], $currency) : 'Clear' ?>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if (!$c['is_active']): ?>
                                    <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-gray-200 text-gray-500">Inactive</span>
                                <?php elseif ($is_overdue): ?>
                                    <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-red-100 text-red-700">Overdue</span>
                                <?php elseif ($has_debt): ?>
                                    <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-amber-100 text-amber-700">Owing</span>
                                <?php else: ?>
                                    <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded bg-green-100 text-green-700">Clear</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= APP_URL ?>/customers/view/<?= $c['id'] ?>" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">Profile</a>
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
            <?php $qp = $_GET; unset($qp['page']); $base = APP_URL . '/customers?' . http_build_query($qp); ?>
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
            <svg class="w-8 h-8 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
        </div>
        <?php if ($search || $filter): ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No customers match your filters</h3>
            <a href="<?= APP_URL ?>/customers" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest">Clear Filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No customers yet</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">Add your first customer or they'll be auto-created when you make a quotation or invoice.</p>
            <a href="<?= APP_URL ?>/customers/create" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">Add First Customer</a>
        <?php endif; ?>
    </div>
<?php endif; ?>