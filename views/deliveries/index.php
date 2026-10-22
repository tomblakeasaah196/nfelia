<?php
/**
 * Nfelia — Delivery Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $deliveries, $summary, $statuses, $providers,
 *   $search, $status (filter), $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
// The status names in sentence case ("Pending dispatch"), as the rest of the app writes them
foreach ($statuses as $k => $s) { $statuses[$k]['label'] = ucfirst(strtolower($s['label'])); }
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Deliveries</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1"><?= (int)($summary['total_deliveries'] ?? 0) ?> total dispatches</p>
    </div>
    <a href="<?= APP_URL ?>/deliveries/create" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
        <span class="text-sm font-semibold">New delivery</span>
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-xs font-semibold text-brand-plum/60 mb-1">Pending</div>
        <div class="font-heading font-extrabold text-xl text-gray-600"><?= (int)($summary['pending_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-xs font-semibold text-brand-plum/60 mb-1">In progress</div>
        <div class="font-heading font-extrabold text-xl text-amber-600"><?= (int)($summary['active_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-xs font-semibold text-brand-plum/60 mb-1">Completed</div>
        <div class="font-heading font-extrabold text-xl text-green-600"><?= (int)($summary['completed_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-xs font-semibold text-brand-plum/60 mb-1">Received</div>
        <div class="font-heading font-extrabold text-xl text-purple-600"><?= (int)($summary['signed_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-xs font-semibold text-brand-plum/60 mb-1">Failed</div>
        <div class="font-heading font-extrabold text-xl text-red-600"><?= (int)($summary['failed_count'] ?? 0) ?></div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/deliveries" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by number, recipient, tracking..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-accent-text text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="status" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text">
            <option value="">All statuses</option>
            <?php foreach ($statuses as $k => $v): ?>
                <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v['label'] ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-accent-text">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="updated" <?= $sort === 'updated' ? 'selected' : '' ?>>Last updated</option>
        </select>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-sm font-semibold">Filter</button>
        <?php if ($search || $status || $sort !== 'newest'): ?>
            <a href="<?= APP_URL ?>/deliveries" class="text-sm font-semibold text-brand-plum/60 hover:text-accent-text">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Delivery List -->
<?php if (!empty($deliveries)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/70">Number</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/70">Recipient</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/70">Invoice</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/70">Provider</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/70">ETA</th>
                        <th class="px-5 py-3 text-xs font-semibold text-brand-plum/70 text-center">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($deliveries as $d): ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors">
                            <td class="px-5 py-4">
                                <a href="<?= APP_URL ?>/deliveries/view/<?= $d['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-accent-text"><?= e($d['doc_number']) ?></a>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-xs font-semibold text-brand-plum"><?= e($d['recipient_name']) ?></div>
                                <?php if ($d['recipient_phone']): ?>
                                    <div class="text-xs text-brand-plum/40"><?= e($d['recipient_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4">
                                <?php if ($d['invoice_number']): ?>
                                    <a href="<?= APP_URL ?>/invoices/view/<?= $d['invoice_id'] ?>" class="data-text text-xs font-bold text-blue-500 hover:text-blue-700"><?= e($d['invoice_number']) ?></a>
                                <?php else: ?>
                                    <span class="text-xs text-brand-plum/60">Standalone</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 text-xs text-brand-plum/60"><?= $d['provider'] ? ($providers[$d['provider']] ?? e(ucfirst($d['provider']))) : '—' ?></td>
                            <td class="px-5 py-4 data-text text-xs text-brand-plum/60"><?= $d['estimated_delivery'] ? fmt_date($d['estimated_delivery'], 'd M, H:i') : '—' ?></td>
                            <td class="px-5 py-4 text-center">
                                <span class="data-text text-xs font-semibold px-2 py-0.5 rounded <?= $statuses[$d['status']]['color'] ?? 'bg-gray-100 text-gray-700' ?>">
                                    <?= $statuses[$d['status']]['label'] ?? ucfirst($d['status']) ?>
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= APP_URL ?>/deliveries/view/<?= $d['id'] ?>" class="text-sm font-semibold text-accent-text hover:text-accent-text">View</a>
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
            <?php $qp = $_GET; unset($qp['page']); $base = APP_URL . '/deliveries?' . http_build_query($qp); ?>
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
            <svg class="w-8 h-8 text-brand-plum/20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a2 2 0 104 0m-4 0a2 2 0 11-4 0"/></svg>
        </div>
        <?php if ($search || $status): ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No deliveries match your filters</h3>
            <a href="<?= APP_URL ?>/deliveries" class="text-sm font-semibold text-accent-text">Clear filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No deliveries yet</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">Create a delivery note to track dispatches and get recipient sign-off confirmation.</p>
            <a href="<?= APP_URL ?>/deliveries/create" class="btn-primary text-white px-6 py-3 rounded-xl text-sm font-semibold shadow-sm">Create your first delivery</a>
        <?php endif; ?>
    </div>
<?php endif; ?>