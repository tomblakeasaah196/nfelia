<?php
/**
 * Nfelia — Command Center Dashboard View
 * Renders inside views/layouts/app.php
 *
 * Available from controller:
 *   $user, $business, $summary, $greeting,
 *   $low_stock_count, $overdue_count, $pending_deliveries,
 *   $recent_invoices, $low_stock_items, $pending_delivery_items
 */
$currency = $business['currency_symbol'] ?? '₦';
$user_role = strtolower($user['role'] ?? 'staff');
$is_management = in_array($user_role, ['owner', 'admin']);
?>

<!-- Greeting -->
<div class="mb-8">
    <h1 class="font-heading font-extrabold text-3xl sm:text-4xl text-brand-plum tracking-tight">
        <?= e($greeting) ?>, <?= e(explode(' ', $user['full_name'])[0]) ?>.
    </h1>
    <p class="text-sm font-medium text-brand-plum/60 mt-2">Here is your enterprise snapshot for <?= date('F Y') ?>.</p>
</div>

<!-- ===== FINANCIAL HEALTH CARDS (Role Restricted) ===== -->
<?php if ($is_management): ?>
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4 mb-8">
    <!-- Invoiced -->
    <div class="bg-brand-white p-5 rounded-[1.25rem] shadow-sm border border-brand-sand">
        <div class="data-text text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest mb-2">Invoiced (MTD)</div>
        <div class="font-heading font-extrabold text-lg sm:text-xl text-brand-plum truncate">
            <?= money((float)($summary['total_invoiced_this_month'] ?? 0), $currency) ?>
        </div>
    </div>
    <!-- Received -->
    <div class="bg-brand-white p-5 rounded-[1.25rem] shadow-sm border border-brand-sand">
        <div class="data-text text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest mb-2">Received (MTD)</div>
        <div class="font-heading font-extrabold text-lg sm:text-xl text-[#B69F5E] truncate">
            <?= money((float)($summary['total_received_this_month'] ?? 0), $currency) ?>
        </div>
    </div>
    <!-- Outstanding -->
    <div class="bg-brand-white p-5 rounded-[1.25rem] shadow-sm border border-brand-sand">
        <div class="data-text text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest mb-2">Outstanding</div>
        <div class="font-heading font-extrabold text-lg sm:text-xl text-[#8B004A] truncate">
            <?= money((float)($summary['total_outstanding'] ?? 0), $currency) ?>
        </div>
    </div>
    <!-- Expenses -->
    <div class="bg-brand-white p-5 rounded-[1.25rem] shadow-sm border border-brand-sand">
        <div class="data-text text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest mb-2">Expenses (MTD)</div>
        <div class="font-heading font-extrabold text-lg sm:text-xl text-brand-plum truncate">
            <?= money((float)($summary['total_expenses_this_month'] ?? 0), $currency) ?>
        </div>
    </div>
    <!-- Net Position -->
    <div class="col-span-2 md:col-span-1 xl:col-span-1 bg-brand-plum p-5 rounded-[1.25rem] shadow-sm border border-brand-plum">
        <div class="data-text text-[9px] font-bold text-brand-cream/50 uppercase tracking-widest mb-2">Net Position</div>
        <?php $net = (float)($summary['total_received_this_month'] ?? 0) - (float)($summary['total_expenses_this_month'] ?? 0); ?>
        <div class="font-heading font-extrabold text-lg sm:text-xl <?= $net >= 0 ? 'text-[#B69F5E]' : 'text-red-400' ?> truncate">
            <?= $net >= 0 ? '+' : '' ?><?= money($net, $currency) ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===== QUICK ACTIONS ===== -->
<div class="mb-10">
    <div class="flex items-center gap-3 overflow-x-auto no-scrollbar pb-2">
        <a href="<?= APP_URL ?>/quotations/create" class="shrink-0 flex items-center gap-2 bg-brand-white border border-brand-sand hover:border-brand-gold px-5 py-3 shadow-sm transition-all group rounded-xl">
            <svg class="w-4 h-4 text-brand-gold group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest whitespace-nowrap">New Quotation</span>
        </a>
        <a href="<?= APP_URL ?>/invoices/create" class="shrink-0 flex items-center gap-2 bg-brand-white border border-brand-sand hover:border-brand-gold px-5 py-3 shadow-sm transition-all group rounded-xl">
            <svg class="w-4 h-4 text-brand-gold group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest whitespace-nowrap">New Invoice</span>
        </a>
        <a href="<?= APP_URL ?>/receipts/create" class="shrink-0 flex items-center gap-2 bg-brand-white border border-brand-sand hover:border-brand-gold px-5 py-3 shadow-sm transition-all group rounded-xl">
            <svg class="w-4 h-4 text-brand-gold group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest whitespace-nowrap">New Receipt</span>
        </a>
        <?php if ($is_management): ?>
        <a href="<?= APP_URL ?>/expenses/create" class="shrink-0 flex items-center gap-2 bg-brand-white border border-brand-sand hover:border-brand-gold px-5 py-3 shadow-sm transition-all group rounded-xl">
            <svg class="w-4 h-4 text-brand-gold group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest whitespace-nowrap">Add Expense</span>
        </a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/deliveries/create" class="shrink-0 flex items-center gap-2 bg-brand-white border border-brand-sand hover:border-brand-gold px-5 py-3 shadow-sm transition-all group rounded-xl">
            <svg class="w-4 h-4 text-brand-gold group-hover:scale-110 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            <span class="text-[10px] font-bold text-brand-plum uppercase tracking-widest whitespace-nowrap">Dispatch Order</span>
        </a>
    </div>
</div>

<!-- ===== OMNI-SEARCH ===== -->
<div class="relative mb-10 z-30">
    <div class="relative group">
        <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
            <svg class="h-5 w-5 text-brand-plum/30 group-focus-within:text-brand-gold transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
        </div>
        <input type="text" id="omni-search" autocomplete="off" placeholder="Search clients, invoices, or stock..."
               class="block w-full pl-12 pr-4 py-4 bg-brand-white border border-brand-sand shadow-sm rounded-xl text-brand-plum placeholder-brand-plum/30 focus:outline-none focus:border-brand-gold focus:ring-0 transition-all font-medium text-sm">
        <div class="absolute inset-y-0 right-4 hidden sm:flex items-center pointer-events-none">
            <span class="text-[10px] font-mono font-bold text-brand-plum/20 bg-brand-sand/40 px-2 py-0.5 rounded">CTRL+K</span>
        </div>
    </div>
    <div id="omni-results" class="absolute top-full left-0 right-0 mt-2 bg-brand-white shadow-xl rounded-xl border border-brand-sand overflow-hidden hidden z-50 max-h-96 overflow-y-auto">
        <div class="p-2" id="omni-results-list"></div>
    </div>
</div>

<!-- ===== ACTIVITY FEED + ALERTS (2-Column) ===== -->
<div class="grid lg:grid-cols-3 gap-6 mb-8">

    <!-- Recent Invoices (2/3 width) -->
    <div class="lg:col-span-2 bg-brand-white rounded-xl border border-brand-sand shadow-sm overflow-hidden flex flex-col">
        <div class="flex items-center justify-between px-5 py-4 border-b border-brand-sand shrink-0">
            <h3 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Recent Invoices</h3>
            <a href="<?= APP_URL ?>/invoices" class="text-[10px] font-bold text-brand-gold uppercase tracking-widest hover:text-brand-burgundy transition-colors">View All</a>
        </div>
        <div class="flex-1 overflow-y-auto">
            <?php if (!empty($recent_invoices)): ?>
                <div class="divide-y divide-brand-sand">
                    <?php foreach ($recent_invoices as $inv): ?>
                        <a href="<?= APP_URL ?>/invoices/view/<?= $inv['id'] ?>" class="flex items-center justify-between px-5 py-4 hover:bg-brand-cream/30 transition-colors">
                            <div class="flex items-center gap-3 min-w-0">
                                <?php
                                $status_color = match($inv['status']) {
                                    'paid'           => 'bg-[#16a085]', // Green
                                    'partially_paid' => 'bg-[#B69F5E]', // Gold
                                    'overdue'        => 'bg-[#ff0000]', // Red
                                    'sent'           => 'bg-blue-400',
                                    'draft'          => 'bg-gray-300',
                                    'cancelled'      => 'bg-gray-400',
                                    default          => 'bg-brand-sand'
                                };
                                ?>
                                <div class="w-2.5 h-2.5 rounded-full shrink-0 <?= $status_color ?>"></div>
                                <div class="min-w-0">
                                    <div class="text-xs font-bold text-brand-plum truncate"><?= e($inv['customer_name'] ?? 'Unknown Client') ?></div>
                                    <div class="data-text text-[10px] text-brand-plum/50"><?= e($inv['doc_number']) ?> &middot; <?= !empty($inv['issue_date']) ? fmt_date($inv['issue_date'], 'd M') : 'N/A' ?></div>
                                </div>
                            </div>
                            <div class="text-right shrink-0 ml-4">
                                <div class="data-text text-xs font-bold text-brand-plum"><?= money((float)$inv['total'], $currency) ?></div>
                                <div class="text-[9px] font-bold uppercase tracking-wider <?= $inv['status'] === 'paid' ? 'text-[#16a085]' : ($inv['status'] === 'overdue' ? 'text-[#ff0000]' : 'text-[#B69F5E]') ?>"><?= e(str_replace('_', ' ', $inv['status'])) ?></div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="px-5 py-10 text-center h-full flex flex-col items-center justify-center">
                    <p class="text-xs text-brand-plum/40 font-medium">No invoices yet. Create your first one above.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right Column: Alerts -->
    <div class="space-y-6">
        
        <!-- Overdue Count Card (High Priority Alert) -->
        <?php if ($overdue_count > 0 && $is_management): ?>
            <a href="<?= APP_URL ?>/invoices?status=overdue" class="block bg-[#fff0f0] rounded-xl border border-[#ff0000]/20 shadow-sm p-5 hover:shadow-md transition-shadow">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-[#ff0000]/10 rounded-lg flex items-center justify-center shrink-0">
                        <svg class="w-5 h-5 text-[#ff0000]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <div class="text-xs font-bold text-[#ff0000]"><?= $overdue_count ?> Overdue Invoice<?= $overdue_count > 1 ? 's' : '' ?></div>
                        <div class="text-[10px] text-[#ff0000]/70 font-medium mt-0.5">Tap to review and send reminders</div>
                    </div>
                </div>
            </a>
        <?php endif; ?>

        <!-- Low Stock Alerts -->
        <div class="bg-brand-white rounded-xl border border-brand-sand shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-brand-sand">
                <h3 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Low Stock</h3>
                <?php if ($low_stock_count > 0): ?>
                    <span class="data-text text-[9px] font-bold text-brand-white bg-brand-burgundy px-2 py-0.5 rounded-full"><?= $low_stock_count ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($low_stock_items)): ?>
                <div class="divide-y divide-brand-sand max-h-48 overflow-y-auto">
                    <?php foreach ($low_stock_items as $item): ?>
                        <div class="flex items-center justify-between px-5 py-3">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-brand-plum truncate"><?= e($item['name']) ?></div>
                                <div class="data-text text-[10px] text-brand-plum/50"><?= e($item['sku'] ?? 'NO SKU') ?></div>
                            </div>
                            <div class="text-right shrink-0 ml-3">
                                <div class="data-text text-xs font-bold text-[#ff0000]"><?= (int)$item['stock_qty'] ?></div>
                                <div class="text-[9px] text-brand-plum/40">/ <?= (int)$item['low_stock_threshold'] ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="px-5 py-6 text-center">
                    <p class="text-xs text-brand-plum/40 font-medium">All stock levels healthy.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pending Deliveries -->
        <div class="bg-brand-white rounded-xl border border-brand-sand shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-brand-sand">
                <h3 class="font-heading font-bold text-xs text-brand-plum uppercase tracking-widest">Pending Deliveries</h3>
                <?php if ($pending_deliveries > 0): ?>
                    <span class="data-text text-[9px] font-bold text-brand-white bg-brand-plum px-2 py-0.5 rounded-full"><?= $pending_deliveries ?></span>
                <?php endif; ?>
            </div>
            <?php if (!empty($pending_delivery_items)): ?>
                <div class="divide-y divide-brand-sand max-h-48 overflow-y-auto">
                    <?php foreach ($pending_delivery_items as $del): ?>
                        <a href="<?= APP_URL ?>/deliveries/view/<?= $del['id'] ?>" class="flex items-center justify-between px-5 py-3 hover:bg-brand-cream/30 transition-colors">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-brand-plum truncate"><?= e($del['recipient_name'] ?? 'Unknown Recipient') ?></div>
                                <div class="data-text text-[10px] text-brand-plum/50"><?= e($del['doc_number']) ?></div>
                            </div>
                            <div class="text-[9px] font-bold uppercase tracking-wider text-[#B69F5E]"><?= e(str_replace('_', ' ', $del['status'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="px-5 py-6 text-center">
                    <p class="text-xs text-brand-plum/40 font-medium">No pending dispatches.</p>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ===== OMNI-SEARCH SCRIPT ===== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('omni-search');
    const resultsContainer = document.getElementById('omni-results');
    const resultsList = document.getElementById('omni-results-list');
    let timeout = null;

    // Safety check ensuring the global Nfelia object exists
    const API_URL = (typeof Nfelia !== 'undefined' && Nfelia.url) ? Nfelia.url : '';
    const CSRF_TOKEN = (typeof Nfelia !== 'undefined' && Nfelia.csrf) ? Nfelia.csrf : '';

    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') { e.preventDefault(); searchInput.focus(); }
        if (e.key === 'Escape') { resultsContainer.classList.add('hidden'); searchInput.blur(); }
    });

    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !resultsContainer.contains(e.target)) {
            resultsContainer.classList.add('hidden');
        }
    });

    searchInput.addEventListener('input', function() {
        const query = this.value.trim();
        clearTimeout(timeout);
        if (query.length < 2) { resultsContainer.classList.add('hidden'); return; }

        timeout = setTimeout(() => {
            $.ajax({
                url: API_URL + '/dashboard/search',
                method: 'POST',
                data: { query: query, csrf_token: CSRF_TOKEN },
                dataType: 'json',
                success: function(res) {
                    resultsList.innerHTML = '';
                    if (!res.results || res.results.length === 0) {
                        resultsList.innerHTML = '<div class="p-4 text-center text-sm font-medium text-brand-plum/50">No results found.</div>';
                    } else {
                        res.results.forEach(item => {
                            resultsList.innerHTML += `
                                <a href="${item.url}" class="flex items-center justify-between p-3 hover:bg-brand-sand/30 rounded-lg transition-colors border-b border-brand-sand/50 last:border-0">
                                    <div>
                                        <div class="text-sm font-bold text-brand-plum">${item.text}</div>
                                        <div class="text-[10px] font-mono text-brand-plum/60 mt-0.5">${item.sub}</div>
                                    </div>
                                    <div class="text-[9px] font-bold uppercase tracking-widest text-brand-gold bg-brand-gold/10 px-2 py-1 rounded">${item.type}</div>
                                </a>`;
                        });
                    }
                    resultsContainer.classList.remove('hidden');
                },
                error: function() {
                    resultsList.innerHTML = '<div class="p-4 text-center text-sm font-medium text-red-500/80">Search failed to connect.</div>';
                    resultsContainer.classList.remove('hidden');
                }
            });
        }, 300);
    });
});
</script>