<?php
/**
 * Nfelia — Quotation Listing View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $quotations, $summary,
 *   $search, $status, $sort, $page, $total_pages, $total_items
 */
$currency = $business['currency_symbol'] ?? '₦';
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Quotations</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Requests for Engagement — <?= (int)($summary['total_quotes'] ?? 0) ?> total</p>
    </div>
    <a href="<?= APP_URL ?>/quotations/create" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
        <span class="text-[10px] font-bold uppercase tracking-widest">New Quotation</span>
    </a>
</div>

<!-- Summary Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Total Quoted</div>
        <div class="font-heading font-extrabold text-xl text-brand-plum truncate"><?= money((float)($summary['total_value'] ?? 0), $currency) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Accepted</div>
        <div class="font-heading font-extrabold text-xl text-green-600"><?= (int)($summary['accepted_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Pending</div>
        <div class="font-heading font-extrabold text-xl text-amber-600"><?= (int)($summary['pending_count'] ?? 0) ?></div>
    </div>
    <div class="bg-brand-white p-4 rounded-xl border border-brand-sand shadow-sm">
        <div class="data-text text-[9px] font-bold text-brand-plum/40 uppercase tracking-widest mb-1">Expired</div>
        <div class="font-heading font-extrabold text-xl text-red-500"><?= (int)($summary['expired_count'] ?? 0) ?></div>
    </div>
</div>

<!-- Filters -->
<form method="GET" action="<?= APP_URL ?>/quotations" class="bg-brand-white border border-brand-sand rounded-xl p-4 mb-6 shadow-sm">
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex-1 min-w-[180px]">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-brand-plum/30" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input type="text" name="q" value="<?= e($search) ?>" placeholder="Search by number or client..."
                   class="w-full pl-10 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30">
        </div>
        <select name="status" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="">All Status</option>
            <?php foreach (['draft','sent','accepted','declined','expired','converted'] as $s): ?>
                <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
            <?php endforeach; ?>
        </select>
        <select name="sort" class="text-xs font-semibold text-brand-plum bg-brand-cream border border-brand-sand rounded-lg px-3 py-2.5 focus:outline-none focus:border-brand-gold uppercase tracking-wider">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
            <option value="total_desc" <?= $sort === 'total_desc' ? 'selected' : '' ?>>Highest Value</option>
            <option value="total_asc" <?= $sort === 'total_asc' ? 'selected' : '' ?>>Lowest Value</option>
        </select>
        <button type="submit" class="btn-primary text-white px-4 py-2.5 rounded-lg text-[10px] font-bold uppercase tracking-widest">Filter</button>
        <?php if ($search || $status || $sort !== 'newest'): ?>
            <a href="<?= APP_URL ?>/quotations" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Clear</a>
        <?php endif; ?>
    </div>
</form>

<!-- Quotation List -->
<?php if (!empty($quotations)): ?>
    <div class="bg-brand-white border border-brand-sand rounded-xl shadow-sm overflow-hidden mb-6">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-brand-sand bg-brand-cream/50">
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Number</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Client</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Date</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">Expiry</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-right">Total</th>
                        <th class="px-5 py-3 text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest text-center">Status</th>
                        <th class="px-5 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-brand-sand/50">
                    <?php foreach ($quotations as $q):
                        $status_cfg = match($q['status']) {
                            'draft'     => ['bg-gray-100 text-gray-700', 'Draft'],
                            'sent'      => ['bg-blue-100 text-blue-700', 'Sent'],
                            'accepted'  => ['bg-green-100 text-green-700', 'Accepted'],
                            'declined'  => ['bg-red-100 text-red-700', 'Declined'],
                            'expired'   => ['bg-amber-100 text-amber-700', 'Expired'],
                            'converted' => ['bg-purple-100 text-purple-700', 'Converted'],
                            default     => ['bg-gray-100 text-gray-700', ucfirst($q['status'])],
                        };
                    ?>
                        <tr class="hover:bg-brand-cream/30 transition-colors">
                            <td class="px-5 py-4">
                                <a href="<?= APP_URL ?>/quotations/view/<?= $q['id'] ?>" class="data-text text-xs font-bold text-brand-plum hover:text-brand-burgundy"><?= e($q['doc_number']) ?></a>
                            </td>
                            <td class="px-5 py-4">
                                <div class="text-xs font-semibold text-brand-plum"><?= e($q['customer_name'] ?? 'Unknown') ?></div>
                                <?php if ($q['customer_phone']): ?>
                                    <div class="data-text text-[10px] text-brand-plum/40"><?= e($q['customer_phone']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="px-5 py-4 data-text text-[10px] text-brand-plum/60"><?= fmt_date($q['issue_date'], 'd M Y') ?></td>
                            <td class="px-5 py-4 data-text text-[10px] text-brand-plum/60"><?= $q['expiry_date'] ? fmt_date($q['expiry_date'], 'd M Y') : '—' ?></td>
                            <td class="px-5 py-4 data-text text-xs font-bold text-brand-plum text-right"><?= money((float)$q['total'], $currency) ?></td>
                            <td class="px-5 py-4 text-center">
                                <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg <?= $status_cfg[0] ?>"><?= $status_cfg[1] ?></span>
                            </td>
                            <td class="px-5 py-4 text-right">
                                <a href="<?= APP_URL ?>/quotations/view/<?= $q['id'] ?>" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy">View</a>
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
            <?php
            $qp = $_GET; unset($qp['page']);
            $base = APP_URL . '/quotations?' . http_build_query($qp);
            ?>
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
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No quotations match your filters</h3>
            <a href="<?= APP_URL ?>/quotations" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest">Clear Filters</a>
        <?php else: ?>
            <h3 class="font-heading font-bold text-lg text-brand-plum mb-2">No quotations yet</h3>
            <p class="text-xs text-brand-plum/50 font-medium mb-6">Create your first Request for Engagement to impress your clients.</p>
            <a href="<?= APP_URL ?>/quotations/create" class="btn-primary text-white px-6 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm">Create First Quotation</a>
        <?php endif; ?>
    </div>
<?php endif; ?>