<?php
/**
 * views/admin/index.php — Founder Admin Panel (all tabs)
 * Variables vary by tab: $tab, $metrics, $businesses, $feedbacks, $referrals, $revenue, $expenses, $model, etc.
 */
$tabs = [
    'dashboard'  => ['label' => 'Dashboard',  'url' => APP_URL . '/admin'],
    'businesses' => ['label' => 'Businesses', 'url' => APP_URL . '/admin/businesses'],
    'feedback'   => ['label' => 'Feedback',   'url' => APP_URL . '/admin/feedback'],
    'referrals'  => ['label' => 'Referrals',  'url' => APP_URL . '/admin/referrals'],
    'support'    => ['label' => 'Support',    'url' => APP_URL . '/admin/support'],
    'shop_names' => ['label' => 'Shop names', 'url' => APP_URL . '/admin/shop-names'],
    'reviews'    => ['label' => 'Reviews',    'url' => APP_URL . '/admin/reviews'],
    'financials' => ['label' => 'Financials', 'url' => APP_URL . '/admin/financials'],
];
?>

<div class="max-w-7xl mx-auto px-4 py-6 space-y-6">

    <!-- Admin Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-brand-plum">Founder Admin</h1>
            <p class="text-sm text-brand-plum/50">Nfelia platform management</p>
        </div>
        <span class="px-3 py-1 bg-red-100 text-red-700 text-xs font-bold rounded-full uppercase">Admin Only</span>
    </div>

    <?php if (is_owner() && ops_active_admins() === 0): ?>
    <!-- Nfelia Ops: the first admin is set up from here, once (app/ops/). -->
    <div class="rounded-xl border border-brand-burgundy/20 bg-white p-5 flex flex-col sm:flex-row sm:items-center gap-4 justify-between">
        <div>
            <h2 class="text-lg font-semibold text-brand-plum">Nfelia Ops is ready</h2>
            <p class="text-sm text-brand-plum/70 mt-1">The team's own place at <?= e(ops_host()) ?>. Set up your Ops account as its first admin; you'll get an email to choose a password, then invite the rest of the team from there.</p>
        </div>
        <form method="post" action="<?= APP_URL ?>/admin/ops-setup">
            <?= csrf_field() ?>
            <button type="submit" class="px-4 py-2 rounded-lg bg-brand-burgundy text-white font-semibold text-sm whitespace-nowrap">Set up my Ops account</button>
        </form>
    </div>
    <?php endif; ?>

    <!-- Tabs -->
    <nav class="flex gap-1 bg-gray-100 rounded-xl p-1 overflow-x-auto">
        <?php foreach ($tabs as $key => $t): ?>
        <a href="<?= $t['url'] ?>"
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap transition-colors <?= $tab === $key ? 'bg-white text-brand-plum shadow-sm' : 'text-gray-500 hover:text-gray-700' ?>">
            <?= $t['label'] ?>
        </a>
        <?php endforeach; ?>
    </nav>

    <!-- ═══════════════════════════════════════
         DASHBOARD TAB
         ═══════════════════════════════════════ -->
    <?php if ($tab === 'dashboard'): ?>

    <!-- Key Metrics -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Total Businesses</p>
            <p class="text-3xl font-bold text-brand-plum mt-1"><?= number_format($metrics['total_businesses']) ?></p>
            <p class="text-xs text-gray-400 mt-1">+<?= $metrics['signups_30d'] ?> last 30 days</p>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">MRR</p>
            <p class="text-3xl font-bold text-green-600 mt-1">₦<?= number_format($metrics['mrr']) ?></p>
            <p class="text-xs text-gray-400 mt-1"><?= $metrics['paying'] ?> paying</p>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Avg Rating</p>
            <p class="text-3xl font-bold text-yellow-500 mt-1"><?= $metrics['avg_rating'] ?: '—' ?> <span class="text-lg">★</span></p>
            <p class="text-xs text-gray-400 mt-1"><?= $metrics['total_feedback'] ?> reviews</p>
        </div>
        <div class="bg-white rounded-xl border p-4">
            <p class="text-xs text-gray-500 uppercase tracking-wide">Revenue (Month)</p>
            <p class="text-3xl font-bold text-brand-gold mt-1">₦<?= number_format($metrics['revenue_this_month']) ?></p>
            <p class="text-xs text-gray-400 mt-1">₦<?= number_format($metrics['total_revenue']) ?> all-time</p>
        </div>
    </div>

    <!-- Status Breakdown -->
    <div class="grid sm:grid-cols-2 gap-4">
        <div class="bg-white rounded-xl border p-5">
            <h3 class="font-semibold text-brand-plum mb-3">Subscription Status</h3>
            <div class="space-y-2">
                <?php
                $statuses = ['trial' => ['Trial', 'bg-blue-500'], 'active' => ['Active', 'bg-green-500'], 'past_due' => ['Past Due', 'bg-amber-500'], 'suspended' => ['Suspended', 'bg-red-500'], 'cancelled' => ['Cancelled', 'bg-gray-400']];
                $total = max(1, array_sum($metrics['status_counts']));
                foreach ($statuses as $s => [$label, $color]):
                    $count = $metrics['status_counts'][$s] ?? 0;
                    $pct = round($count / $total * 100);
                ?>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-500 w-20"><?= $label ?></span>
                    <div class="flex-1 bg-gray-100 rounded-full h-2"><div class="<?= $color ?> h-2 rounded-full" style="width:<?= $pct ?>%"></div></div>
                    <span class="text-xs font-medium text-gray-700 w-8 text-right"><?= $count ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Revenue Chart -->
        <div class="bg-white rounded-xl border p-5">
            <h3 class="font-semibold text-brand-plum mb-3">Revenue (6 Months)</h3>
            <canvas id="revenueChart" height="160"></canvas>
        </div>
    </div>

    <!-- Recent Signups -->
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b"><h3 class="font-semibold text-brand-plum">Recent Signups</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr><th class="px-4 py-2 text-left">Business</th><th class="px-4 py-2 text-left">Status</th><th class="px-4 py-2 text-left">Trial Ends</th><th class="px-4 py-2 text-left">Signed Up</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($recent_signups as $biz): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2.5 font-medium"><?= htmlspecialchars($biz['name']) ?></td>
                        <td class="px-4 py-2.5">
                            <?php $sc = ['trial'=>'bg-blue-100 text-blue-700','active'=>'bg-green-100 text-green-700','past_due'=>'bg-amber-100 text-amber-700','suspended'=>'bg-red-100 text-red-600','cancelled'=>'bg-gray-100 text-gray-600'][$biz['sub_status']??'trial'] ?? 'bg-gray-100 text-gray-600'; ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $sc ?>"><?= ucfirst($biz['sub_status'] ?? 'trial') ?></span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-500 text-xs"><?= $biz['trial_ends_at'] ? date('M j', strtotime($biz['trial_ends_at'])) : '—' ?></td>
                        <td class="px-4 py-2.5 text-gray-400 text-xs"><?= date('M j, Y', strtotime($biz['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($revenue_chart, 'month')) ?>,
            datasets: [{
                label: 'Revenue (₦)',
                data: <?= json_encode(array_column($revenue_chart, 'revenue')) ?>,
                backgroundColor: 'rgba(197, 165, 90, 0.7)',
                borderRadius: 6
            }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
    </script>

    <?php endif; ?>


    <!-- ═══════════════════════════════════════
         BUSINESSES TAB
         ═══════════════════════════════════════ -->
    <?php if ($tab === 'businesses'): ?>

    <div class="bg-white rounded-xl border overflow-hidden" x-data="{ actionBiz: null, actionType: '' }">
        <!-- Filters -->
        <div class="px-5 py-4 border-b flex flex-col sm:flex-row gap-3">
            <form method="GET" action="<?= APP_URL ?>/admin/businesses" class="flex gap-2 flex-1">
                <input type="text" name="q" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search businesses..."
                       class="flex-1 border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-gold/30">
                <select name="status" class="border rounded-lg px-3 py-2 text-sm">
                    <option value="all">All Status</option>
                    <?php foreach (['trial','active','past_due','suspended','cancelled'] as $s): ?>
                    <option value="<?= $s ?>" <?= ($filter ?? '') === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="px-4 py-2 bg-brand-plum text-white rounded-lg text-sm font-medium">Filter</button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">ID</th>
                        <th class="px-4 py-2 text-left">Business</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Users</th>
                        <th class="px-4 py-2 text-left">Last Login</th>
                        <th class="px-4 py-2 text-left">Created</th>
                        <th class="px-4 py-2 text-left">Shop by Nfelia</th>
                        <th class="px-4 py-2 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($businesses as $biz): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2.5 text-gray-400 text-xs">#<?= $biz['id'] ?></td>
                        <td class="px-4 py-2.5">
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($biz['name']) ?></p>
                            <p class="text-xs text-gray-400"><?= htmlspecialchars($biz['email'] ?? '') ?></p>
                        </td>
                        <td class="px-4 py-2.5">
                            <?php $sc = ['trial'=>'bg-blue-100 text-blue-700','active'=>'bg-green-100 text-green-700','past_due'=>'bg-amber-100 text-amber-700','suspended'=>'bg-red-100 text-red-600','cancelled'=>'bg-gray-100 text-gray-600'][$biz['sub_status']??'trial'] ?? 'bg-gray-100 text-gray-600'; ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $sc ?>"><?= ucfirst($biz['sub_status'] ?? '—') ?></span>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600"><?= $biz['user_count'] ?>/<?= ($biz['user_seats'] ?? 1) + ($biz['extra_seats'] ?? 0) ?></td>
                        <td class="px-4 py-2.5 text-gray-400 text-xs"><?= $biz['last_login'] ? date('M j, g:ia', strtotime($biz['last_login'])) : 'Never' ?></td>
                        <td class="px-4 py-2.5 text-gray-400 text-xs"><?= date('M j, Y', strtotime($biz['created_at'])) ?></td>
                        <td class="px-4 py-2.5">
                            <?php $hidden = (int)($biz['hide_nfelia_credit'] ?? 0) === 1; ?>
                            <form method="POST" action="<?= APP_URL ?>/admin/shop-credit" class="flex items-center gap-2">
                                <?= csrf_field() ?>
                                <input type="hidden" name="business_id" value="<?= (int)$biz['id'] ?>">
                                <input type="hidden" name="hide" value="<?= $hidden ? '0' : '1' ?>">
                                <span class="text-xs <?= $hidden ? 'text-green-700' : 'text-gray-500' ?>"><?= $hidden ? 'Hidden (add-on)' : 'Shown' ?></span>
                                <button class="px-2 py-1 bg-gray-100 text-gray-700 rounded text-xs hover:bg-gray-200"><?= $hidden ? 'Show it' : 'Hide it' ?></button>
                            </form>
                        </td>
                        <td class="px-4 py-2.5">
                            <div class="flex gap-1">
                                <?php if (($biz['sub_status'] ?? 'trial') !== 'active'): ?>
                                <button @click="adminAction(<?= $biz['id'] ?>, 'activate')" class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs hover:bg-green-200">Activate</button>
                                <?php endif; ?>
                                <?php if (in_array($biz['sub_status'] ?? '', ['trial', 'active', 'past_due'])): ?>
                                <button @click="adminAction(<?= $biz['id'] ?>, 'suspend')" class="px-2 py-1 bg-red-100 text-red-600 rounded text-xs hover:bg-red-200">Suspend</button>
                                <?php endif; ?>
                                <?php if (($biz['sub_status'] ?? 'trial') === 'trial'): ?>
                                <button @click="adminAction(<?= $biz['id'] ?>, 'extend_trial', 7)" class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs hover:bg-blue-200">+7 days</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function adminAction(bizId, action, days) {
        if (!confirm('Are you sure you want to ' + action + ' this business?')) return;
        const fd = new FormData();
        fd.append('business_id', bizId);
        fd.append('action', action);
        fd.append('csrf_token', '<?= csrf_token() ?>');
        if (days) fd.append('days', days);
        fetch('<?= APP_URL ?>/admin/manage-subscription', { method: 'POST', body: fd })
            .then(r => r.json()).then(d => { if (d.success) location.reload(); else alert(d.error || 'Failed'); });
    }
    </script>

    <?php endif; ?>


    <!-- ═══════════════════════════════════════
         FEEDBACK TAB
         ═══════════════════════════════════════ -->
    <?php if ($tab === 'feedback'): ?>

    <!-- Rating Overview -->
    <div class="bg-white rounded-xl border p-5 flex flex-col sm:flex-row gap-6 items-center">
        <div class="text-center">
            <p class="text-5xl font-bold text-yellow-500"><?= number_format($avg_rating, 1) ?></p>
            <div class="flex gap-0.5 justify-center mt-1">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg class="w-5 h-5 <?= $i <= round($avg_rating) ? 'text-yellow-400' : 'text-gray-200' ?>" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <?php endfor; ?>
            </div>
            <p class="text-xs text-gray-400 mt-1"><?= $total_fb ?> reviews</p>
        </div>
        <div class="flex-1 space-y-1 w-full">
            <?php for ($r = 5; $r >= 1; $r--): $cnt = $rating_dist[$r] ?? 0; $pct = $total_fb > 0 ? round($cnt / $total_fb * 100) : 0; ?>
            <div class="flex items-center gap-2">
                <span class="text-xs text-gray-500 w-4"><?= $r ?></span>
                <svg class="w-3.5 h-3.5 text-yellow-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                <div class="flex-1 bg-gray-100 rounded-full h-2"><div class="bg-yellow-400 h-2 rounded-full" style="width:<?= $pct ?>%"></div></div>
                <span class="text-xs text-gray-400 w-8 text-right"><?= $cnt ?></span>
            </div>
            <?php endfor; ?>
        </div>
    </div>

    <!-- Messages from the home page: people who aren't signed up yet. The
         notification for each one links here (#prospects). -->
    <div id="prospects" class="scroll-mt-24 mb-6">
        <h3 class="font-semibold text-gray-900 mb-3">Messages from the home page</h3>
        <div class="space-y-3">
            <?php foreach ($prospects ?? [] as $pr):
                $digits = preg_replace('/\D+/', '', (string)$pr['contact_info']); ?>
            <div class="bg-white rounded-xl border p-4">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-medium text-sm text-gray-900"><?= htmlspecialchars($pr['enterprise_name']) ?></p>
                        <p class="text-xs text-gray-500 mt-0.5">
                            <?php if (strlen($digits) >= 7): ?>
                                <a class="underline" href="https://wa.me/<?= htmlspecialchars($digits) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($pr['contact_info']) ?></a>
                            <?php else: ?>
                                <?= htmlspecialchars($pr['contact_info'] ?: 'No contact given') ?>
                            <?php endif; ?>
                        </p>
                    </div>
                    <p class="text-xs text-gray-400 shrink-0"><?= date('M j, Y', strtotime($pr['created_at'])) ?></p>
                </div>
                <p class="mt-2 text-sm text-gray-600"><?= nl2br(htmlspecialchars($pr['message'])) ?></p>
            </div>
            <?php endforeach; ?>
            <?php if (empty($prospects)): ?>
            <div class="text-center py-6 text-gray-400"><p>No messages yet.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Feedback List -->
    <div class="space-y-3">
        <?php foreach ($feedbacks as $fb): ?>
        <div class="bg-white rounded-xl border p-4">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="font-medium text-sm text-gray-900"><?= htmlspecialchars($fb['user_name'] ?? 'Unknown') ?></p>
                        <span class="text-xs text-gray-400">·</span>
                        <p class="text-xs text-gray-400"><?= htmlspecialchars($fb['business_name'] ?? '') ?></p>
                    </div>
                    <div class="flex gap-0.5 mt-1">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                        <svg class="w-4 h-4 <?= $i <= $fb['rating'] ? 'text-yellow-400' : 'text-gray-200' ?>" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/></svg>
                        <?php endfor; ?>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <span class="px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-500"><?= str_replace('_', ' ', $fb['trigger_action']) ?></span>
                    <p class="text-xs text-gray-400 mt-1"><?= date('M j, Y', strtotime($fb['created_at'])) ?></p>
                </div>
            </div>
            <?php if ($fb['message']): ?>
            <p class="mt-2 text-sm text-gray-600"><?= nl2br(htmlspecialchars($fb['message'])) ?></p>
            <?php endif; ?>
            <?php if ($fb['image_path']): ?>
            <img src="<?= APP_URL . '/' . htmlspecialchars($fb['image_path']) ?>" alt="Feedback image" class="mt-3 rounded-lg max-h-48 object-cover">
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php if (empty($feedbacks)): ?>
        <div class="text-center py-12 text-gray-400"><p>No feedback yet.</p></div>
        <?php endif; ?>
    </div>

    <?php endif; ?>


    <!-- ═══════════════════════════════════════
         REFERRALS TAB
         ═══════════════════════════════════════ -->
    <?php if ($tab === 'referrals'): ?>

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-2xl font-bold text-brand-plum"><?= (int)($ref_stats['total'] ?? 0) ?></p>
            <p class="text-xs text-gray-500 mt-1">Total Referrals</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-2xl font-bold text-blue-600"><?= (int)($ref_stats['converted'] ?? 0) ?></p>
            <p class="text-xs text-gray-500 mt-1">Converted</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-2xl font-bold text-green-600"><?= (int)($ref_stats['rewarded'] ?? 0) ?></p>
            <p class="text-xs text-gray-500 mt-1">Rewarded</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-2xl font-bold text-red-500">₦<?= number_format(($ref_stats['total_discount_kobo'] ?? 0) / 100) ?></p>
            <p class="text-xs text-gray-500 mt-1">Discount Given</p>
        </div>
    </div>

    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-2 text-left">Referrer</th>
                        <th class="px-4 py-2 text-left">Referred</th>
                        <th class="px-4 py-2 text-left">Status</th>
                        <th class="px-4 py-2 text-left">Discount</th>
                        <th class="px-4 py-2 text-left">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($referrals as $ref): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2.5 font-medium"><?= htmlspecialchars($ref['referrer_name'] ?? '—') ?></td>
                        <td class="px-4 py-2.5"><?= htmlspecialchars($ref['referred_name'] ?? $ref['referred_email'] ?? '—') ?></td>
                        <td class="px-4 py-2.5">
                            <?php $badge = ['pending'=>'bg-gray-100 text-gray-600','converted'=>'bg-blue-100 text-blue-700','rewarded'=>'bg-green-100 text-green-700','expired'=>'bg-red-100 text-red-600'][$ref['status']] ?? 'bg-gray-100 text-gray-600'; ?>
                            <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $badge ?>"><?= ucfirst($ref['status']) ?></span>
                            <?php if ($ref['discount_applied']): ?><span class="text-xs text-gray-400 ml-1">Applied</span><?php endif; ?>
                        </td>
                        <td class="px-4 py-2.5 text-gray-600">₦<?= number_format($ref['discount_amount'] / 100) ?></td>
                        <td class="px-4 py-2.5 text-gray-400 text-xs"><?= date('M j, Y', strtotime($ref['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($referrals)): ?>
        <div class="text-center py-12 text-gray-400"><p>No referrals yet.</p></div>
        <?php endif; ?>
    </div>

    <?php endif; ?>


    <!-- ═══════════════════════════════════════
         SUPPORT TAB
         ═══════════════════════════════════════ -->
    <?php if ($tab === 'support'): ?>

    <!-- Ticket Counts -->
    <div class="grid grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-2xl font-bold text-blue-600"><?= $ticket_counts['open'] ?? 0 ?></p>
            <p class="text-xs text-gray-500 mt-1">Open</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-2xl font-bold text-green-600"><?= $ticket_counts['replied'] ?? 0 ?></p>
            <p class="text-xs text-gray-500 mt-1">Replied</p>
        </div>
        <div class="bg-white rounded-xl border p-4 text-center">
            <p class="text-2xl font-bold text-gray-400"><?= $ticket_counts['closed'] ?? 0 ?></p>
            <p class="text-xs text-gray-500 mt-1">Closed</p>
        </div>
    </div>

    <!-- Filter -->
    <div class="flex gap-2">
        <?php foreach (['all' => 'All', 'open' => 'Open', 'replied' => 'Replied', 'closed' => 'Closed'] as $fk => $fl): ?>
        <a href="<?= APP_URL ?>/admin/support?status=<?= $fk ?>"
           class="px-3 py-1.5 rounded-lg text-xs font-medium <?= ($filter ?? 'all') === $fk ? 'bg-brand-plum text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">
            <?= $fl ?>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Ticket List -->
    <div class="space-y-4">
        <?php foreach ($support_tickets as $ticket): ?>
        <div class="bg-white rounded-xl border p-5 scroll-mt-24" id="ticket-<?= (int)$ticket['id'] ?>" x-data="{ showReply: false }">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 flex-wrap">
                        <p class="font-semibold text-sm text-gray-900"><?= htmlspecialchars($ticket['subject']) ?></p>
                        <?php
                        $tbadge = match($ticket['status']) {
                            'open'    => 'bg-blue-100 text-blue-700',
                            'replied' => 'bg-green-100 text-green-700',
                            'closed'  => 'bg-gray-100 text-gray-500',
                            default   => 'bg-gray-100 text-gray-500',
                        };
                        ?>
                        <span class="px-2 py-0.5 rounded-full text-xs font-medium <?= $tbadge ?>"><?= ucfirst($ticket['status']) ?></span>
                    </div>
                    <p class="text-xs text-gray-400 mt-1">
                        <?= htmlspecialchars($ticket['user_name'] ?? '') ?> · <?= htmlspecialchars($ticket['business_name'] ?? '') ?>
                        · <?= date('M j, Y g:ia', strtotime($ticket['created_at'])) ?>
                    </p>
                </div>
                <?php if ($ticket['status'] !== 'closed'): ?>
                <button @click="showReply = !showReply" class="px-3 py-1.5 bg-brand-plum text-white text-xs rounded-lg hover:opacity-90 shrink-0">Reply</button>
                <?php endif; ?>
            </div>

            <p class="mt-3 text-sm text-gray-700"><?= nl2br(htmlspecialchars($ticket['message'])) ?></p>

            <?php if ($ticket['admin_reply']): ?>
            <div class="mt-3 bg-green-50 border border-green-200 rounded-lg p-3">
                <p class="text-xs font-medium text-green-700 mb-1">Your reply:</p>
                <p class="text-sm text-gray-700"><?= nl2br(htmlspecialchars($ticket['admin_reply'])) ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= $ticket['replied_at'] ? date('M j g:ia', strtotime($ticket['replied_at'])) : '' ?></p>
            </div>
            <?php endif; ?>

            <!-- Reply Form -->
            <div x-show="showReply" x-collapse class="mt-4">
                <form method="POST" action="<?= APP_URL ?>/admin/reply-ticket" class="space-y-3">
                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                    <input type="hidden" name="ticket_id" value="<?= $ticket['id'] ?>">
                    <textarea name="reply" rows="3" required placeholder="Type your reply..."
                              class="w-full border rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-brand-gold/30 resize-none"></textarea>
                    <div class="flex items-center gap-3">
                        <select name="new_status" class="border rounded-lg px-3 py-2 text-xs">
                            <option value="replied">Mark as Replied</option>
                            <option value="closed">Close Ticket</option>
                        </select>
                        <button type="submit" class="px-4 py-2 bg-brand-plum text-white text-xs font-semibold rounded-lg hover:opacity-90">Send Reply</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($support_tickets)): ?>
        <div class="text-center py-12 text-gray-400"><p>No support tickets yet.</p></div>
        <?php endif; ?>
    </div>

    <?php endif; ?>


    <!-- ═══════════════════════════════════════
         SHOP NAMES TAB
         ═══════════════════════════════════════ -->
    <?php if ($tab === 'shop_names'): ?>

    <div class="bg-white rounded-xl border p-5">
        <h2 class="font-semibold text-brand-plum">Shop names to review</h2>
        <p class="text-sm text-gray-500 mt-1">
            These names look like a bank, telco, government body or an official account. Approve the ones that are
            a real shop's own name. Until you decide, a new shop has no link and a renamed shop keeps its current one.
        </p>
    </div>

    <div class="space-y-4">
        <?php foreach ($pending_names as $shop_req):
            $req_host = $shop_req['subdomain_requested'] . '.' . shop_base_host();
        ?>
        <div class="bg-white rounded-xl border p-5">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="min-w-0">
                    <p class="font-semibold text-base text-gray-900 break-all"><?= e($req_host) ?></p>
                    <p class="text-sm text-gray-600 mt-1"><?= e($shop_req['name']) ?> · <?= e(ucfirst(str_replace('_', ' ', $shop_req['category']))) ?></p>
                    <p class="text-sm text-gray-500 mt-1">
                        <?= $shop_req['subdomain'] ? 'Rename from ' . e($shop_req['subdomain'] . '.' . shop_base_host()) : 'First link' ?>
                        · asked <?= e(date('M j, Y g:ia', strtotime((string)$shop_req['subdomain_requested_at']))) ?>
                    </p>
                    <p class="text-sm text-gray-500 mt-1 break-all"><?= e($shop_req['email']) ?><?= $shop_req['phone'] ? ' · ' . e($shop_req['phone']) : '' ?></p>
                </div>
                <div class="flex gap-2 shrink-0">
                    <form method="POST" action="<?= APP_URL ?>/admin/review-shop-name">
                        <?= csrf_field() ?>
                        <input type="hidden" name="business_id" value="<?= (int)$shop_req['id'] ?>">
                        <input type="hidden" name="name" value="<?= e($shop_req['subdomain_requested']) ?>">
                        <input type="hidden" name="decision" value="approve">
                        <button type="submit" class="h-11 px-4 bg-green-700 text-white text-sm font-semibold rounded-lg hover:opacity-90">Approve</button>
                    </form>
                    <form method="POST" action="<?= APP_URL ?>/admin/review-shop-name"
                          onsubmit="return confirm(<?= e(json_encode('Turn down ' . $req_host . '? ' . $shop_req['name'] . ($shop_req['subdomain'] ? ' keeps its current link.' : ' will be asked to choose another name.'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)">
                        <?= csrf_field() ?>
                        <input type="hidden" name="business_id" value="<?= (int)$shop_req['id'] ?>">
                        <input type="hidden" name="name" value="<?= e($shop_req['subdomain_requested']) ?>">
                        <input type="hidden" name="decision" value="reject">
                        <button type="submit" class="h-11 px-4 bg-white border border-red-200 text-red-700 text-sm font-semibold rounded-lg hover:bg-red-50">Turn down</button>
                    </form>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($pending_names)): ?>
        <div class="text-center py-12 text-gray-400"><p>No shop names waiting for review.</p></div>
        <?php endif; ?>
    </div>

    <?php endif; ?>


    <!-- ═══════════════════════════════════════
         REVIEWS TAB
         ═══════════════════════════════════════ -->
    <?php if ($tab === 'reviews'): ?>

    <div class="bg-white rounded-xl border p-5">
        <h2 class="font-semibold text-brand-plum">Reviews on shops</h2>
        <p class="text-sm text-gray-500 mt-1">
            Only shoppers whose orders were delivered or collected can post one, once per order, and shop owners can't
            change them. Hide a review only if it breaks the rules: abuse, someone's private details, or spam.
        </p>
        <div class="flex flex-wrap gap-2 mt-4">
            <?php foreach (['all' => 'All', 'low' => '1 or 2 stars', 'hidden' => 'Hidden'] as $key => $label): ?>
            <a href="<?= APP_URL ?>/admin/reviews<?= $key === 'all' ? '' : '?show=' . $key ?>"
               class="h-9 px-3 inline-flex items-center rounded-lg text-sm font-medium <?= $review_filter === $key ? 'bg-brand-plum text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200' ?>"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="space-y-3">
        <?php foreach ($reviews as $rv): ?>
        <div class="bg-white rounded-xl border p-5 <?= $rv['status'] === 'hidden' ? 'opacity-70' : '' ?>">
            <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                <div class="min-w-0">
                    <p class="text-base text-amber-500"><?= str_repeat('★', (int)$rv['rating']) ?><span class="text-gray-200"><?= str_repeat('★', 5 - (int)$rv['rating']) ?></span></p>
                    <?php if ($rv['comment']): ?>
                    <p class="text-sm text-gray-800 mt-1 whitespace-pre-line break-words"><?= e($rv['comment']) ?></p>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500 mt-2">
                        <?= e(review_display_name((string)$rv['customer_name'])) ?> · <?= e($rv['business_name']) ?>
                        <?= $rv['subdomain'] ? '(' . e($rv['subdomain'] . '.' . shop_base_host()) . ')' : '' ?>
                        · <?= e($rv['order_number']) ?> · <?= e(date('M j, Y', strtotime((string)$rv['submitted_at']))) ?>
                        <?= $rv['status'] === 'hidden' ? '· <span class="text-red-600 font-medium">hidden</span>' : '' ?>
                    </p>
                </div>
                <form method="POST" action="<?= APP_URL ?>/admin/review-visibility" class="shrink-0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="review_id" value="<?= (int)$rv['id'] ?>">
                    <?php if ($rv['status'] === 'hidden'): ?>
                    <input type="hidden" name="hide" value="0">
                    <button type="submit" class="h-11 px-4 bg-white border text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-50">Show again</button>
                    <?php else: ?>
                    <input type="hidden" name="hide" value="1">
                    <button type="submit" class="h-11 px-4 bg-white border border-red-200 text-red-700 text-sm font-semibold rounded-lg hover:bg-red-50"
                            onclick="return confirm(<?= e(json_encode('Hide this review from ' . $rv['business_name'] . '\'s shop?', JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) ?>)">Hide</button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($reviews)): ?>
        <div class="text-center py-12 text-gray-400"><p>No reviews here yet.</p></div>
        <?php endif; ?>
    </div>

    <?php endif; ?>


    <!-- ═══════════════════════════════════════
         FINANCIALS TAB
         ═══════════════════════════════════════ -->
    <?php if ($tab === 'financials'): ?>
    <?php $sub_tab = $sub_tab ?? 'overview'; ?>

    <!-- Sub-tabs -->
    <div class="flex gap-2 overflow-x-auto">
        <?php foreach (['overview' => 'Overview', 'expenses' => 'Expenses', 'model' => 'Financial Model'] as $sk => $sl): ?>
        <a href="<?= APP_URL ?>/admin/financials?sub=<?= $sk ?>"
           class="px-4 py-2 rounded-lg text-sm font-medium whitespace-nowrap <?= $sub_tab === $sk ? 'bg-brand-plum text-white' : 'bg-white border text-gray-600 hover:bg-gray-50' ?>">
            <?= $sl ?>
        </a>
        <?php endforeach; ?>
    </div>

    <?php if ($sub_tab === 'overview'): ?>
    <!-- Revenue vs Expenses overview -->
    <div class="grid sm:grid-cols-3 gap-4">
        <div class="bg-white rounded-xl border p-5 border-l-4 border-l-green-500">
            <p class="text-xs text-gray-500 uppercase">Revenue (MRR)</p>
            <p class="text-3xl font-bold text-green-600 mt-1">₦<?= number_format($revenue['mrr']) ?></p>
            <p class="text-xs text-gray-400 mt-1">₦<?= number_format($revenue['this_month']) ?> this month · ₦<?= number_format($revenue['all_time']) ?> all-time</p>
        </div>
        <div class="bg-white rounded-xl border p-5 border-l-4 border-l-red-500">
            <p class="text-xs text-gray-500 uppercase">Expenses (Monthly)</p>
            <p class="text-3xl font-bold text-red-600 mt-1">₦<?= number_format($expenses['monthly_recurring']) ?></p>
            <p class="text-xs text-gray-400 mt-1">₦<?= number_format($expenses['this_month']) ?> this month · ₦<?= number_format($expenses['all_time']) ?> all-time</p>
        </div>
        <div class="bg-white rounded-xl border p-5 border-l-4 <?= $model['profit'] >= 0 ? 'border-l-green-500' : 'border-l-red-500' ?>">
            <p class="text-xs text-gray-500 uppercase">Net Profit (Monthly)</p>
            <p class="text-3xl font-bold <?= $model['profit'] >= 0 ? 'text-green-600' : 'text-red-600' ?> mt-1">
                <?= $model['profit'] >= 0 ? '' : '-' ?>₦<?= number_format(abs($model['profit'])) ?>
            </p>
            <p class="text-xs text-gray-400 mt-1"><?= $model['margin'] ?>% margin</p>
        </div>
    </div>

    <!-- Expense Categories -->
    <?php if (!empty($expense_categories)): ?>
    <div class="bg-white rounded-xl border p-5">
        <h3 class="font-semibold text-brand-plum mb-3">Expenses This Month by Category</h3>
        <div class="space-y-2">
            <?php
            $max_exp = max(array_column($expense_categories, 'total'));
            foreach ($expense_categories as $cat):
                $pct = $max_exp > 0 ? round($cat['total'] / $max_exp * 100) : 0;
            ?>
            <div class="flex items-center gap-3">
                <span class="text-xs text-gray-600 w-20 capitalize"><?= htmlspecialchars($cat['category']) ?></span>
                <div class="flex-1 bg-gray-100 rounded-full h-3"><div class="bg-red-400 h-3 rounded-full" style="width:<?= $pct ?>%"></div></div>
                <span class="text-xs font-medium text-gray-700 w-24 text-right">₦<?= number_format($cat['total'] / 100) ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>


    <?php if ($sub_tab === 'expenses'): ?>
    <!-- Add Expense Form -->
    <div class="bg-white rounded-xl border p-5">
        <h3 class="font-semibold text-brand-plum mb-4">Add Expense</h3>
        <form method="POST" action="<?= APP_URL ?>/admin/add-expense" class="grid sm:grid-cols-2 gap-4">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Category</label>
                <select name="category" required class="w-full border rounded-lg px-3 py-2 text-sm">
                    <?php foreach (['server'=>'Server/Hosting','marketing'=>'Marketing','salary'=>'Salary','tools'=>'Tools/Software','domain'=>'Domain','design'=>'Design','legal'=>'Legal','other'=>'Other'] as $k=>$v): ?>
                    <option value="<?= $k ?>"><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Amount (₦)</label>
                <input type="number" name="amount" step="0.01" min="0" required class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Description</label>
                <input type="text" name="description" placeholder="e.g. Namecheap renewal" class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Date</label>
                <input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required class="w-full border rounded-lg px-3 py-2 text-sm">
            </div>
            <div class="flex items-center gap-2 sm:col-span-2">
                <input type="checkbox" name="recurring" id="exp_recurring" class="rounded border-gray-300">
                <label for="exp_recurring" class="text-sm text-gray-600">This is a monthly recurring expense</label>
            </div>
            <div class="sm:col-span-2">
                <button type="submit" class="px-5 py-2 bg-brand-plum text-white text-sm font-semibold rounded-lg hover:opacity-90">Add Expense</button>
            </div>
        </form>
    </div>

    <!-- Expense List -->
    <div class="bg-white rounded-xl border overflow-hidden">
        <div class="px-5 py-4 border-b"><h3 class="font-semibold text-brand-plum">Expenses This Month</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr><th class="px-4 py-2 text-left">Category</th><th class="px-4 py-2 text-left">Description</th><th class="px-4 py-2 text-left">Amount</th><th class="px-4 py-2 text-left">Date</th><th class="px-4 py-2 text-left">Recurring</th><th class="px-4 py-2"></th></tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <?php foreach ($expense_list as $exp): ?>
                    <tr class="hover:bg-gray-50/50">
                        <td class="px-4 py-2.5 capitalize"><?= htmlspecialchars($exp['category']) ?></td>
                        <td class="px-4 py-2.5 text-gray-600"><?= htmlspecialchars($exp['description'] ?? '—') ?></td>
                        <td class="px-4 py-2.5 font-medium">₦<?= number_format($exp['amount'] / 100) ?></td>
                        <td class="px-4 py-2.5 text-gray-400 text-xs"><?= date('M j', strtotime($exp['expense_date'])) ?></td>
                        <td class="px-4 py-2.5"><?= $exp['recurring'] ? '<span class="text-xs text-blue-600">Monthly</span>' : '—' ?></td>
                        <td class="px-4 py-2.5">
                            <form method="POST" action="<?= APP_URL ?>/admin/delete-expense" class="inline" onsubmit="return confirm('Delete?')">
                                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                <input type="hidden" name="expense_id" value="<?= $exp['id'] ?>">
                                <button class="text-red-400 hover:text-red-600 text-xs">Delete</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($expense_list)): ?>
        <div class="text-center py-8 text-gray-400 text-sm">No expenses this month.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>


    <?php if ($sub_tab === 'model'): ?>
    <!-- Financial Model / Breakeven Calculator -->
    <div class="bg-white rounded-xl border p-6" x-data="financialModel()">
        <h3 class="font-semibold text-brand-plum mb-1">Financial Model</h3>
        <p class="text-sm text-gray-500 mb-5">See where you stand and how many subscribers you need.</p>

        <!-- Current State -->
        <div class="grid sm:grid-cols-3 gap-4 mb-6 p-4 bg-gray-50 rounded-xl">
            <div>
                <p class="text-xs text-gray-500">Active Subscribers</p>
                <p class="text-2xl font-bold text-brand-plum"><?= $model['active_subs'] ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">On Trial</p>
                <p class="text-2xl font-bold text-blue-600"><?= $model['trial_subs'] ?></p>
            </div>
            <div>
                <p class="text-xs text-gray-500">Breakeven At</p>
                <p class="text-2xl font-bold text-amber-600"><?= $model['breakeven_users'] ?> <span class="text-sm font-normal">subscribers</span></p>
            </div>
        </div>

        <!-- Interactive Calculator -->
        <div class="grid sm:grid-cols-2 gap-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Monthly Recurring Expenses (₦)</label>
                    <input type="number" x-model.number="expenses" class="w-full border rounded-lg px-3 py-2 text-sm" step="1000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Price Per Subscriber (₦)</label>
                    <input type="number" x-model.number="price" class="w-full border rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Target Monthly Profit (₦)</label>
                    <input type="number" x-model.number="targetProfit" class="w-full border rounded-lg px-3 py-2 text-sm" step="10000">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Expected Trial → Paid Conversion (%)</label>
                    <input type="range" x-model.number="conversionRate" min="5" max="80" class="w-full accent-brand-plum">
                    <p class="text-xs text-gray-400 mt-0.5" x-text="conversionRate + '%'"></p>
                </div>
            </div>

            <div class="space-y-4">
                <!-- Results -->
                <div class="bg-brand-plum/5 rounded-xl p-4 space-y-3">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Breakeven subscribers</span>
                        <span class="font-bold text-brand-plum" x-text="breakevenSubs"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">For target profit</span>
                        <span class="font-bold text-green-600" x-text="targetSubs + ' subscribers'"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Trials needed (at <span x-text="conversionRate"></span>%)</span>
                        <span class="font-bold text-blue-600" x-text="trialsNeeded + ' signups'"></span>
                    </div>
                    <hr class="border-gray-200">
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Projected monthly revenue</span>
                        <span class="font-bold text-green-600" x-text="'₦' + projectedRevenue.toLocaleString()"></span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-sm text-gray-600">Projected margin</span>
                        <span class="font-bold" :class="projectedMargin >= 0 ? 'text-green-600' : 'text-red-600'" x-text="projectedMargin + '%'"></span>
                    </div>
                </div>

                <!-- Quick Scenarios -->
                <div class="bg-gray-50 rounded-xl p-4">
                    <p class="text-xs font-medium text-gray-500 mb-2 uppercase">Scenarios</p>
                    <div class="space-y-1.5 text-sm">
                        <template x-for="s in scenarios" :key="s.subs">
                            <div class="flex justify-between">
                                <span class="text-gray-600" x-text="s.subs + ' subs'"></span>
                                <span :class="s.profit >= 0 ? 'text-green-600' : 'text-red-600'" x-text="(s.profit >= 0 ? '+' : '') + '₦' + s.profit.toLocaleString()"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function financialModel() {
        return {
            expenses: <?= (int)$model['monthly_expenses'] ?>,
            price: <?= (int)$model['price_per_sub'] ?>,
            targetProfit: 100000,
            conversionRate: 30,

            get breakevenSubs() { return this.price > 0 ? Math.ceil(this.expenses / this.price) : 0; },
            get targetSubs() { return this.price > 0 ? Math.ceil((this.expenses + this.targetProfit) / this.price) : 0; },
            get trialsNeeded() { return this.conversionRate > 0 ? Math.ceil(this.targetSubs / (this.conversionRate / 100)) : 0; },
            get projectedRevenue() { return <?= $model['active_subs'] ?> * this.price; },
            get projectedMargin() {
                if (this.projectedRevenue <= 0) return 0;
                return Math.round((this.projectedRevenue - this.expenses) / this.projectedRevenue * 100);
            },
            get scenarios() {
                return [10, 25, 50, 100, 200].map(s => ({
                    subs: s,
                    profit: (s * this.price) - this.expenses
                }));
            }
        };
    }
    </script>
    <?php endif; ?>

    <?php endif; ?>

</div>
