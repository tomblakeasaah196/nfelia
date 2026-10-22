<?php
/**
 * Nfelia — Supplier Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $suppliers, $summary,
 *   $search, $filter, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Suppliers</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1"><?= (int)($summary['active_count'] ?? 0) ?> active suppliers</p>
    </div>
    <a href="<?= APP_URL ?>/suppliers/create" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        <span class="text-sm font-semibold">Add a supplier</span>
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-sm font-semibold text-brand-plum/60 mb-1">Suppliers</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum"><?= (int)($summary['total_suppliers'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-sm font-semibold text-brand-plum/60 mb-1">Bought from them</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum truncate"><?= money((float)($summary['total_purchased'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-sm font-semibold text-brand-plum/60 mb-1">Paid to them</div>
        <div class="font-heading font-extrabold text-xl text-green-600 truncate"><?= money((float)($summary['total_paid'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm relative overflow-hidden">
        <div class="data-text text-sm font-semibold text-brand-plum/60 mb-1">You owe</div>
        <div class="font-heading font-extrabold text-xl text-red-600 truncate"><?= money((float)($summary['total_outstanding'] ?? 0), $currency) ?></div>
        <?php if ((int)($summary['owing_count'] ?? 0) > 0): ?>
            <div class="data-text text-xs font-semibold text-red-500 mt-0.5"><?= (int)$summary['owing_count'] ?> supplier<?= (int)$summary['owing_count'] > 1 ? 's' : '' ?> owed</div>
            <div class="absolute top-3 right-3 w-2.5 h-2.5 bg-red-500 rounded-full animate-pulse"></div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/suppliers" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by name, phone, email..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="filter" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text">
            <option value="">Active</option>
            <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All</option>
            <option value="owing" <?= $filter === 'owing' ? 'selected' : '' ?>>You owe them</option>
            <option value="clear" <?= $filter === 'clear' ? 'selected' : '' ?>>Nothing owed</option>
            <option value="inactive" <?= $filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text">
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Name A to Z</option>
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="highest_purchased" <?= $sort === 'highest_purchased' ? 'selected' : '' ?>>Most bought from</option>
            <option value="highest_owing" <?= $sort === 'highest_owing' ? 'selected' : '' ?>>Most owed</option>
            <option value="highest_paid" <?= $sort === 'highest_paid' ? 'selected' : '' ?>>Most paid</option>
        </select>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">Filter</button>
        <?php if ($search || $filter || $sort !== 'name'): ?>
            <a href="<?= APP_URL ?>/suppliers" class="text-sm font-semibold text-brand-plum/60 hover:text-accent-text">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Supplier List -->
<?php if (!empty($suppliers)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[13px] font-semibold text-brand-plum/60">Supplier</th>
                        <th class="px-5 py-3 text-[13px] font-semibold text-brand-plum/60">Contact</th>
                        <th class="px-5 py-3 text-[13px] font-semibold text-brand-plum/60 text-right">Purchased</th>
                        <th class="px-5 py-3 text-[13px] font-semibold text-brand-plum/60 text-right">Paid</th>
                        <th class="px-5 py-3 text-[13px] font-semibold text-brand-plum/60 text-right">You owe</th>
                        <th class="px-5 py-3 text-[13px] font-semibold text-brand-plum/60 text-center">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($suppliers as $s):
                        $has_debt = (float)$s['outstanding_balance'] > 0;
                        $has_unpaid = (int)($s['unpaid_expenses'] ?? 0) > 0;
                    ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors <?= $has_unpaid ? 'bg-red-50/30' : '' ?>">
                            <td class="px-5 py-4">
                                <a href="<?= APP_URL ?>/suppliers/view/<?= $s['id'] ?>" class="text-xs font-bold text-brand-plum hover:text-accent-text"><?= e($s['full_name']) ?></a>
                                <?php if ($s['business_name']): ?>
                                    <div class="text-xs text-brand-plum/50"><?= e($s['business_name']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-xs text-brand-plum/60"><?= e($s['phone'] ?? '—') ?></div>
                                <?php if ($s['email']): ?>
                                    <div class="text-xs text-brand-plum/40"><?= e($s['email']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$s['total_purchased'], $currency) ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-green-600 text-right"><?= money((float)$s['total_paid'], $currency) ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-right <?= $has_debt ? 'text-red-600' : 'text-green-600' ?>">
                                <?= $has_debt ? money((float)$s['outstanding_balance'], $currency) : 'Clear' ?>
                            </td>
                            <td class="px-5 py-4 text-center">
                                <?php if (!$s['is_active']): ?>
                                    <span class="nf-tag is-line">Inactive</span>
                                <?php elseif ($has_unpaid): ?>
                                    <span class="nf-tag is-owed">Unpaid</span>
                                <?php elseif ($has_debt): ?>
                                    <span class="nf-tag is-warn">Owing</span>
                                <?php else: ?>
                                    <span class="nf-tag is-in">Nothing owed</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= APP_URL ?>/suppliers/view/<?= $s['id'] ?>" class="text-sm font-semibold text-accent-text hover:text-accent-text">Open</a>
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
            <?php $qp = $_GET; unset($qp['page']); $base = APP_URL . '/suppliers?' . http_build_query($qp); ?>
            <?php if ($page > 1): ?>
                <a href="<?= $base ?>&page=<?= $page - 1 ?>" class="px-3 py-2 text-sm font-semibold text-brand-plum/60 bg-brand-white border border-brand-sand rounded-lg hover:border-accent">Prev</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <a href="<?= $base ?>&page=<?= $i ?>" class="w-9 h-9 flex items-center justify-center text-xs font-bold rounded-lg <?= $i === $page ? 'btn-primary text-white' : 'text-brand-plum/60 bg-brand-white border border-brand-sand hover:border-accent' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="<?= $base ?>&page=<?= $page + 1 ?>" class="px-3 py-2 text-sm font-semibold text-brand-plum/60 bg-brand-white border border-brand-sand rounded-lg hover:border-accent">Next</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

<?php else: ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm p-12 text-center">
        <div class="w-16 h-16 mx-auto bg-brand-cream rounded-2xl flex items-center justify-center mb-4">
            <svg class="w-8 h-8 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        </div>
        <?php if ($search || $filter): ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No suppliers match your filters</h3>
            <a href="<?= APP_URL ?>/suppliers" class="text-sm font-semibold text-accent-text">Clear filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No suppliers yet</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">Add your suppliers to track purchases, expenses, and what you owe them.</p>
            <a href="<?= APP_URL ?>/suppliers/create" class="btn-primary text-white px-6 py-3 rounded-xl text-sm font-semibold shadow-sm">Add your first supplier</a>
        <?php endif; ?>
    </div>
<?php endif; ?>