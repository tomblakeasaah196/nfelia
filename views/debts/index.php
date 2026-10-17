<?php
/**
 * Nfelia — Debt Tracker Dashboard + Listing
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $debts, $dashboard,
 *   $tab, $filter, $search, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';

$net = (float)$dashboard['receivable_balance'] - (float)$dashboard['payable_balance'];

$status_badge = function($s) {
    return match($s) {
        'active'      => 'bg-red-100 text-red-700',
        'settling'    => 'bg-amber-100 text-amber-700',
        'cleared'     => 'bg-green-100 text-green-700',
        'written_off' => 'bg-gray-200 text-gray-500',
        default       => 'bg-gray-100 text-gray-700',
    };
};
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Debt Tracker</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Track what's owed to you and what you owe</p>
    </div>
    <a href="<?= APP_URL ?>/debts/create?type=<?= $tab ?>" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        <span class="text-[10px] font-bold uppercase tracking-widest">Record Debt</span>
    </a>
</div>

<!-- Net Position Dashboard -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
    <!-- Receivable -->
    <div class="bg-brand-white p-5 rounded-xl border border-brand-sand shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">They Owe You</div>
            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 11l5-5m0 0l5 5m-5-5v12"/></svg>
            </div>
        </div>
        <div class="font-heading font-extrabold text-2xl text-green-600 truncate"><?= money((float)$dashboard['receivable_balance'], $currency) ?></div>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-[9px] text-brand-plum/40"><?= (int)$dashboard['receivable_count'] ?> active</span>
            <?php if ((int)$dashboard['receivable_overdue_count'] > 0): ?>
                <span class="text-[9px] font-bold text-red-500"><?= (int)$dashboard['receivable_overdue_count'] ?> overdue (<?= money((float)$dashboard['receivable_overdue'], $currency) ?>)</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Net Position -->
    <div class="bg-brand-white p-5 rounded-xl border-2 <?= $net >= 0 ? 'border-green-200' : 'border-red-200' ?> shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-3 text-center">Net Position</div>
        <div class="font-heading font-extrabold text-3xl text-center <?= $net >= 0 ? 'text-green-600' : 'text-red-600' ?> truncate">
            <?= $net >= 0 ? '+' : '' ?><?= money($net, $currency) ?>
        </div>
        <div class="text-center mt-2">
            <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $net >= 0 ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
                <?= $net >= 0 ? 'Net Positive' : 'Net Negative' ?>
            </span>
        </div>
    </div>

    <!-- Payable -->
    <div class="bg-brand-white p-5 rounded-xl border border-brand-sand shadow-sm">
        <div class="flex items-center justify-between mb-3">
            <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest">You Owe Them</div>
            <div class="w-8 h-8 bg-red-100 rounded-lg flex items-center justify-center">
                <svg class="w-4 h-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M17 13l-5 5m0 0l-5-5m5 5V6"/></svg>
            </div>
        </div>
        <div class="font-heading font-extrabold text-2xl text-red-600 truncate"><?= money((float)$dashboard['payable_balance'], $currency) ?></div>
        <div class="flex items-center gap-3 mt-2">
            <span class="text-[9px] text-brand-plum/40"><?= (int)$dashboard['payable_count'] ?> active</span>
            <?php if ((int)$dashboard['payable_overdue_count'] > 0): ?>
                <span class="text-[9px] font-bold text-red-500"><?= (int)$dashboard['payable_overdue_count'] ?> overdue (<?= money((float)$dashboard['payable_overdue'], $currency) ?>)</span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Tab Switcher -->
<div class="flex items-center gap-2 mb-6">
    <a href="<?= APP_URL ?>/debts?tab=receivable" class="px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all <?= $tab === 'receivable' ? 'btn-primary text-white shadow-sm' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-brand-gold' ?>">
        Receivable (<?= (int)$dashboard['receivable_count'] ?>)
    </a>
    <a href="<?= APP_URL ?>/debts?tab=payable" class="px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all <?= $tab === 'payable' ? 'btn-primary text-white shadow-sm' : 'bg-brand-white text-brand-plum/60 border border-brand-sand hover:border-brand-gold' ?>">
        Payable (<?= (int)$dashboard['payable_count'] ?>)
    </a>
</div>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/debts" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by description or <?= $tab === 'receivable' ? 'customer' : 'supplier' ?>..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="filter" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="">Active + Settling</option>
            <option value="active" <?= $filter === 'active' ? 'selected' : '' ?>>Active Only</option>
            <option value="settling" <?= $filter === 'settling' ? 'selected' : '' ?>>Settling</option>
            <option value="overdue" <?= $filter === 'overdue' ? 'selected' : '' ?>>Overdue</option>
            <option value="cleared" <?= $filter === 'cleared' ? 'selected' : '' ?>>Cleared</option>
            <option value="written_off" <?= $filter === 'written_off' ? 'selected' : '' ?>>Written Off</option>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="highest_balance" <?= $sort === 'highest_balance' ? 'selected' : '' ?>>Highest Balance</option>
            <option value="due_soonest" <?= $sort === 'due_soonest' ? 'selected' : '' ?>>Due Soonest</option>
        </select>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Filter</button>
        <?php if ($search || $filter || $sort !== 'newest'): ?>
            <a href="<?= APP_URL ?>/debts?tab=<?= e($tab) ?>" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Debt List -->
<?php if (!empty($debts)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest"><?= $tab === 'receivable' ? 'Customer' : 'Supplier' ?></th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Description</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Total</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Settled</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Balance</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Due</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($debts as $d):
                        $is_overdue = $d['due_date'] && $d['due_date'] < date('Y-m-d') && in_array($d['status'], ['active', 'settling']);
                    ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors <?= $is_overdue ? 'bg-red-50/30' : '' ?>">
                            <td class="px-5 py-4">
                                <?php if ($d['party_name']): ?>
                                    <a href="<?= APP_URL ?>/<?= $tab === 'receivable' ? 'customers' : 'suppliers' ?>/view/<?= $tab === 'receivable' ? $d['customer_id'] : $d['supplier_id'] ?>"
                                       class="text-xs font-bold text-brand-plum hover:text-brand-burgundy"><?= e($d['party_name']) ?></a>
                                <?php else: ?>
                                    <span class="text-xs text-brand-plum/40">—</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-xs text-brand-plum/70 max-w-[200px] truncate">
                                <?= e($d['description']) ?>
                                <?php if ($d['source'] !== 'manual'): ?>
                                    <span class="data-text text-[8px] font-bold uppercase tracking-wider px-1 py-0.5 rounded bg-blue-50 text-blue-500 ml-1"><?= $d['source'] ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$d['total_amount'], $currency) ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-green-600 text-right"><?= money((float)$d['amount_settled'], $currency) ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-right <?= (float)$d['balance'] > 0 ? 'text-red-600' : 'text-green-600' ?>">
                                <?= (float)$d['balance'] > 0 ? money((float)$d['balance'], $currency) : 'Cleared' ?>
                            </td>
                            <td class="px-5 py-4 data-text text-[10px] <?= $is_overdue ? 'text-red-600 font-bold' : 'text-brand-plum/60' ?>"><?= $d['due_date'] ? fmt_date($d['due_date'], 'd M Y') : '—' ?></td>
                            <td class="px-5 py-4 text-center">
                                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2 py-0.5 rounded <?= $status_badge($d['status']) ?>">
                                    <?= $is_overdue && $d['status'] !== 'cleared' ? 'Overdue' : ucfirst(str_replace('_', ' ', $d['status'])) ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= APP_URL ?>/debts/view/<?= $d['id'] ?>" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">View</a>
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
            <?php $qp = $_GET; unset($qp['page']); $base = APP_URL . '/debts?' . http_build_query($qp); ?>
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
            <svg class="w-8 h-8 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"/></svg>
        </div>
        <?php if ($search || $filter): ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No debts match your filters</h3>
            <a href="<?= APP_URL ?>/debts?tab=<?= e($tab) ?>" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest">Clear Filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No <?= $tab === 'receivable' ? 'receivable' : 'payable' ?> debts</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">
                <?= $tab === 'receivable'
                    ? 'Debts appear here automatically when invoices go overdue, or you can record them manually.'
                    : 'Debts appear here automatically from unpaid supplier expenses, or you can record them manually.' ?>
            </p>
            <a href="<?= APP_URL ?>/debts/create?type=<?= $tab ?>" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">Record Manually</a>
        <?php endif; ?>
    </div>
<?php endif; ?>