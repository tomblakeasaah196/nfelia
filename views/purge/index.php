<?php
/**
 * Nfelia — Data Purge Engine UI Cockpit
 * Rendered inside views/layouts/app.php
 */
$currency = $business["currency_symbol"] ?? "₦"; ?>

<!-- Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <div class="flex items-center gap-2.5">
            <div class="w-9 h-9 rounded-xl bg-[#fff0f0] border border-[#ff0000]/20 flex items-center justify-center text-[#ff0000] shadow-sm">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
            </div>
            <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Purge data</h1>
        </div>
        <p class="text-xs text-brand-plum/60 font-medium mt-1 pl-11">Delete records for good. Your password and a PIN confirm every purge.</p>
    </div>

    <!-- Security Badges -->
    <div class="flex items-center gap-2">
        <span class="data-text text-sm font-semibold px-2.5 py-1 rounded-lg bg-brand-plum text-white shadow-sm flex items-center gap-1.5">
            <svg class="w-3 h-3 text-accent-soft" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3a2 2 0 00-2 2v5a2 2 0 002 2h12a2 2 0 002-2v-5a2 2 0 00-2-2V8a6 6 0 00-6-6zm-4 6a4 4 0 118 0v3H6V8z"/></svg>
            Password and PIN lock on
        </span>
        <span class="data-text text-sm font-semibold px-2.5 py-1 rounded-lg <?= $has_pin
            ? "bg-green-100 text-green-700"
            : "bg-amber-100 text-amber-700 animate-pulse" ?>">
            <?= $has_pin ? "PIN set" : "⚠️ Set your PIN" ?>
        </span>
    </div>
</div>

<!-- Alpine Cockpit State -->
<div x-data="purgeCockpit()">

    <!-- Tab Switcher -->
    <div class="flex items-center gap-2 mb-6 border-b border-brand-sand pb-3 overflow-x-auto no-scrollbar">
        <button type="button" @click="activeTab = 'console'"
                :class="activeTab === 'console' ? 'btn-primary text-white shadow-sm' : 'bg-brand-white text-brand-plum/70 hover:text-brand-plum border border-brand-sand'"
                class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all shrink-0">
            ⚡ Purge
        </button>
        <button type="button" @click="activeTab = 'logs'"
                :class="activeTab === 'logs' ? 'btn-primary text-white shadow-sm' : 'bg-brand-white text-brand-plum/70 hover:text-brand-plum border border-brand-sand'"
                class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all shrink-0 flex items-center gap-1.5">
            🛡️ Purge log
            <span class="bg-brand-plum/10 text-brand-plum px-1.5 py-0.5 rounded text-xs"><?= count(
                $purge_logs,
            ) ?></span>
        </button>
        <button type="button" @click="activeTab = 'config'"
                :class="activeTab === 'config' ? 'btn-primary text-white shadow-sm' : 'bg-brand-white text-brand-plum/70 hover:text-brand-plum border border-brand-sand'"
                class="px-5 py-2.5 rounded-xl text-sm font-semibold transition-all shrink-0">
            🔐 Purge PIN
        </button>
    </div>

    <!-- ==========================================
         TAB 1: PURGE COCKPIT CONSOLE
         ========================================== -->
    <div x-show="activeTab === 'console'" x-transition:enter="transition ease-out duration-200">

        <!-- Mode Toggle Block -->
        <div class="bg-brand-white border border-brand-sand rounded-2xl p-6 shadow-sm mb-6">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-brand-sand/60 pb-5 mb-5">
                <div>
                    <h2 class="font-heading font-semibold text-base text-brand-plum">Find what to delete</h2>
                    <p class="text-xs text-brand-plum/50 mt-0.5">Search by document number, or by a date and time.</p>
                </div>
                <div class="flex bg-brand-cream p-1 rounded-xl shrink-0 border border-brand-sand">
                    <button type="button" @click="searchMode = 'exact'; candidates = [];"
                            :class="searchMode === 'exact' ? 'bg-brand-white text-brand-plum font-bold shadow-sm' : 'text-brand-plum/50 hover:text-brand-plum'"
                            class="px-4 py-2 rounded-lg text-sm transition-all">
                        🎯 By number
                    </button>
                    <button type="button" @click="searchMode = 'batch'; candidates = [];"
                            :class="searchMode === 'batch' ? 'bg-brand-white text-brand-plum font-bold shadow-sm' : 'text-brand-plum/50 hover:text-brand-plum'"
                            class="px-4 py-2 rounded-lg text-sm transition-all">
                        📅 By date and time
                    </button>
                </div>
            </div>

            <!-- Target Module Selector -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-5">
                <div>
                    <label class="text-sm font-semibold text-brand-plum block mb-1.5">What to delete</label>
                    <select x-model="targetModule" @change="candidates = []; selectedMap = {};"
                            class="w-full px-3.5 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum focus:outline-none focus:border-accent-text">
                        <option value="orders">Orders (ORD), with all their documents</option>
                        <option value="quotations">Quotations (QUO)</option>
                        <option value="invoices">Invoices (INV)</option>
                        <option value="receipts">Receipts (REC)</option>
                        <option value="deliveries">Delivery notes (DEL)</option>
                        <option value="products">Catalog and stock</option>
                        <option value="customers">Clients</option>
                    </select>
                </div>

                <!-- SURGICAL SEARCH INPUT -->
                <div x-show="searchMode === 'exact'" class="sm:col-span-3 flex gap-3">
                    <div class="flex-1">
                        <label class="text-sm font-semibold text-brand-plum block mb-1.5">Document number</label>
                        <input type="text" name="purge_search_identifier" x-model="exactString" @keyup.enter="scanCandidates()"
                               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-form-type="other"
                               placeholder="e.g. QUO-0001, INV-0012, ORD-0004..."
                               class="w-full px-4 py-2.5 text-xs font-semibold data-text bg-brand-cream border border-brand-sand rounded-xl text-brand-plum focus:outline-none focus:border-accent-text">
                    </div>
                    <div class="flex items-end">
                        <button type="button" @click="scanCandidates()" :disabled="scanning || !exactString"
                                class="btn-primary text-white px-6 py-2.5 rounded-xl text-sm font-semibold disabled:opacity-50 h-[42px] transition-all shadow-sm">
                            <span x-show="!scanning">Find it</span>
                            <span x-show="scanning">Scanning...</span>
                        </button>
                    </div>
                </div>

                <!-- BATCH TIME SEARCH INPUTS -->
                <div x-show="searchMode === 'batch'" class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-4 gap-3" style="display:none;">
                    <div>
                        <label class="text-sm font-semibold text-brand-plum block mb-1.5">Date</label>
                        <input type="date" x-model="batchDate"
                               class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-brand-plum block mb-1.5">From</label>
                        <input type="time" x-model="batchTimeStart"
                               class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-brand-plum block mb-1.5">To</label>
                        <input type="time" x-model="batchTimeEnd"
                               class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum">
                    </div>
                    <div class="flex items-end">
                        <button type="button" @click="scanCandidates()" :disabled="scanning"
                                class="w-full btn-primary text-white py-2.5 rounded-xl text-sm font-semibold disabled:opacity-50 h-[42px] transition-all shadow-sm">
                            <span x-show="!scanning">Find records</span>
                            <span x-show="scanning">Searching…</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- SCAN ERROR ALERTS -->
            <div x-show="scanError" x-text="scanError" style="display:none;"
                 class="p-3 bg-[#fff0f0] border border-[#ff0000]/30 text-[#ff0000] rounded-xl text-xs font-bold text-center mt-4"></div>
        </div>

        <!-- CANDIDATES TABLE GRID -->
        <div x-show="candidates.length > 0" style="display:none;" class="bg-brand-white border border-brand-sand rounded-2xl p-6 shadow-sm mb-6">
            <div class="flex items-center justify-between mb-4 pb-3 border-b border-brand-sand">
                <div>
                    <h3 class="font-heading font-semibold text-base text-brand-plum">Found (<span x-text="candidates.length"></span>)</h3>
                    <p class="text-xs text-brand-plum/50">Tick the ones to delete, then check what goes with them.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="selectAllCandidates()" class="text-sm font-semibold text-brand-primary hover:underline">Select all</button>
                    <span class="text-brand-plum/20">|</span>
                    <button type="button" @click="selectedMap = {}" class="text-sm font-semibold text-brand-plum/60 hover:underline">Clear selection</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-brand-cream/60 border-b border-brand-sand text-[13px] font-semibold text-brand-plum/60">
                            <th class="py-3 px-4 w-12 text-center">Purge</th>
                            <th class="py-3 px-4">Number</th>
                            <th class="py-3 px-4">Created</th>
                            <th class="py-3 px-4 text-right">Value (<?= $currency ?>)</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Linked</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-sand/60 text-xs">
                        <template x-for="item in candidates" :key="item.id">
                            <tr class="hover:bg-brand-cream/30 transition-colors cursor-pointer" @click="toggleSelect(item.id)">
                                <td class="py-3.5 px-4 text-center" @click.stop>
                                    <input type="checkbox" :value="item.id" x-model="selectedMap[item.id]"
                                           class="w-4 h-4 rounded border-brand-sand text-brand-primary focus:ring-0 cursor-pointer">
                                </td>
                                <td class="py-3.5 px-4 font-bold data-text text-brand-plum" x-text="item.identifier"></td>
                                <td class="py-3.5 px-4 data-text text-xs text-brand-plum/60" x-text="item.created"></td>
                                <td class="py-3.5 px-4 data-text font-bold text-brand-plum text-right" x-text="item.amount.toLocaleString(undefined, {minimumFractionDigits:2})"></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="nf-tag" x-text="String(item.status).replace(/_/g, ' ').replace(/^./, c => c.toUpperCase())"></span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="nf-tag is-owed"
                                          x-text="(item.children.length + item.disk_files.length) + ' linked records and files'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 pt-4 border-t border-brand-sand flex items-center justify-between">
                <span class="text-xs font-semibold text-brand-plum/60">
                    <strong class="text-accent-text font-bold" x-text="getSelectedIds().length"></strong> selected to delete.
                </span>
                <button type="button" @click="openBriefingCard()" :disabled="getSelectedIds().length === 0"
                        class="bg-[#ff0000] hover:bg-[#cc0000] text-white px-8 py-3 rounded-xl text-sm font-semibold disabled:opacity-40 transition-all shadow-md active:scale-95">
                    ⚠️ Check what will be deleted
                </button>
            </div>
        </div>

        <!-- ==========================================
             THE RED AWARENESS BRIEFING CARD
             ========================================== -->
        <div x-show="showBriefing" style="display:none;" x-transition:enter="transition ease-out duration-300 transform"
             class="bg-[#1a050b] text-white border-2 border-[#ff0000] rounded-3xl p-8 shadow-2xl relative overflow-hidden mb-12">

            <div class="absolute -right-20 -top-20 w-80 h-80 bg-[#ff0000]/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex items-start justify-between border-b border-white/10 pb-6 mb-6">
                <div>
                    <span class="bg-[#ff0000] text-white text-sm font-semibold px-3 py-1 rounded-full animate-pulse">
                        🔥 Check before you delete
                    </span>
                    <h2 class="font-heading font-extrabold text-2xl sm:text-3xl text-white mt-3">Confirm the purge</h2>
                    <p class="text-xs text-white/60 mt-1">These records, and everything linked to them, will be deleted for good.</p>
                </div>
                <button type="button" @click="showBriefing = false" class="text-white/40 hover:text-white text-sm font-semibold">Close ✕</button>
            </div>

            <!-- Summary Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-white/5 border border-white/10 rounded-2xl p-5 mb-8">
                <div>
                    <div class="text-sm font-semibold text-white/60">Records to delete</div>
                    <div class="text-2xl font-extrabold data-text text-[#ff4d4d]" x-text="getSelectedIds().length + ' ' + targetModule"></div>
                </div>
                <div>
                    <div class="text-sm font-semibold text-white/60">Linked records</div>
                    <div class="text-2xl font-extrabold data-text text-amber-400" x-text="getTotalCollateralChildren() + ' linked records'"></div>
                </div>
                <div>
                    <div class="text-sm font-semibold text-white/60">Files removed</div>
                    <div class="text-2xl font-extrabold data-text text-blue-400" x-text="getTotalDiskFiles() + ' files'"></div>
                </div>
            </div>

            <!-- DETAILED CASCADE ACCORDION -->
            <div class="space-y-4 mb-8 max-h-80 overflow-y-auto pr-2 no-scrollbar">
                <template x-for="row in getSelectedCandidates()" :key="row.id">
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="data-text font-extrabold text-sm text-[#ff4d4d]" x-text="row.identifier"></span>
                                <span class="text-xs text-white/50" x-text="'Stamp: ' + row.created"></span>
                            </div>
                            <span class="data-text font-bold text-sm text-white" x-text="'<?= $currency ?>' + row.amount.toLocaleString(undefined,{minimumFractionDigits:2})"></span>
                        </div>

                        <!-- Collateral Children List -->
                        <div x-show="row.children.length > 0 || row.disk_files.length > 0" class="mt-3 pt-3 border-t border-white/10 text-xs">
                            <p class="text-sm font-semibold text-amber-400/80 mb-2">Also deleted:</p>
                            <ul class="space-y-1.5 text-white/80 pl-2">
                                <template x-for="child in row.children">
                                    <li class="flex items-center gap-2">
                                        <span class="text-amber-400">↳</span>
                                        <strong x-text="child.type + ':'"></strong>
                                        <span class="data-text font-semibold" x-text="child.name"></span>
                                        <span class="text-xs text-white/40" x-text="'(' + child.status + ')'"></span>
                                    </li>
                                </template>
                                <template x-for="f in row.disk_files">
                                    <li class="flex items-center gap-2 text-blue-300">
                                        <span>↳ File:</span>
                                        <code class="text-xs bg-white/10 px-2 py-0.5 rounded truncate max-w-sm" x-text="f"></code>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>
            </div>

            <!-- DOUBLE-LOCK SECRET VERIFICATION INPUTS -->
            <div class="bg-[#ff0000]/15 border-2 border-[#ff0000]/50 rounded-2xl p-6">
                <h3 class="text-sm font-semibold text-white mb-4 flex items-center gap-2">
                    🔐 Your password and PIN
                </h3>

                <input type="text" name="honeypot_fake_username" style="display:none;" autocomplete="username" tabindex="-1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="text-sm font-semibold text-white/80 block mb-1">Your password</label>
                        <input type="password" x-model="authPassword" placeholder="The password you sign in with"
                               class="w-full px-4 py-3 bg-black/60 border border-white/20 rounded-xl text-white text-xs font-bold focus:outline-none focus:border-[#ff0000]">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-white/80 block mb-1">Purge PIN</label>
                        <input type="password" x-model="authPin" placeholder="4 to 8 digits" maxlength="8"
                               class="w-full px-4 py-3 bg-black/60 border border-white/20 rounded-xl text-white text-xs font-bold data-text tracking-widest focus:outline-none focus:border-[#ff0000]">
                    </div>
                </div>

                <!-- Checkbox -->
                <label class="flex items-center gap-3 cursor-pointer select-none mb-6">
                    <input type="checkbox" x-model="confirmPermanent"
                           class="w-5 h-5 rounded bg-black/60 border-white/30 text-[#ff0000] focus:ring-0">
                    <span class="text-xs font-bold text-white/90">
                        I have checked the list above. Delete these records and their files for good.
                    </span>
                </label>

                <!-- DESTROY BUTTON -->
                <button type="button" @click="executeHardPurge()" :disabled="purging || !confirmPermanent || !authPassword || !authPin"
                        class="w-full bg-[#ff0000] hover:bg-[#e60000] text-white py-4 rounded-xl text-sm font-semibold disabled:opacity-30 transition-all shadow-[0_0_30px_rgba(255,0,0,0.5)] active:scale-98">
                    <span x-show="!purging">💥 Delete for good</span>
                    <span x-show="purging">Deleting…</span>
                </button>
            </div>

        </div>

    </div>

    <!-- ==========================================
         TAB 2: IMMUTABLE PURGE LEDGER
         ========================================== -->
    <div x-show="activeTab === 'logs'" style="display:none;" x-transition:enter="transition ease-out duration-200">
        <div class="bg-brand-white border border-brand-sand rounded-2xl p-6 shadow-sm">
            <div class="border-b border-brand-sand pb-4 mb-5">
                <h2 class="font-heading font-semibold text-base text-brand-plum">Purge log</h2>
                <p class="text-xs text-brand-plum/50 mt-0.5">Every purge: when, who, and what was deleted. It can't be edited.</p>
            </div>

            <?php if (!empty($purge_logs)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-brand-cream/60 border-b border-brand-sand text-[13px] font-semibold text-brand-plum/60">
                            <th class="py-3 px-4">Purge ID</th>
                            <th class="py-3 px-4">When</th>
                            <th class="py-3 px-4">Who</th>
                            <th class="py-3 px-4">IP address</th>
                            <th class="py-3 px-4">Mode</th>
                            <th class="py-3 px-4">What was deleted</th>
                            <th class="py-3 px-4 text-center">Snapshot</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-brand-sand/60 text-xs">
                        <?php foreach ($purge_logs as $log): ?>
                        <tr class="hover:bg-brand-cream/30" x-data="{ expanded: false }">
                            <td class="py-3.5 px-4 font-bold data-text text-brand-plum">LEDGER-<?= str_pad(
                                $log["id"],
                                4,
                                "0",
                                STR_PAD_LEFT,
                            ) ?></td>
                            <td class="py-3.5 px-4 data-text text-xs text-brand-plum/60"><?= e(
                                $log["purged_at"],
                            ) ?></td>
                            <td class="py-3.5 px-4 font-bold text-brand-plum"><?= e(
                                $log["admin_name"],
                            ) ?></td>
                            <td class="py-3.5 px-4 data-text text-xs text-brand-plum/50"><?= e(
                                $log["admin_ip"],
                            ) ?></td>
                            <td class="py-3.5 px-4"><span class="nf-tag"><?= e(
                                ucfirst(strtolower((string)$log["purge_mode"])),
                            ) ?></span></td>
                            <td class="py-3.5 px-4 font-semibold text-brand-plum/80 max-w-sm truncate"><?= e(
                                $log["deleted_summary"],
                            ) ?></td>
                            <td class="py-3.5 px-4 text-center">
                                <button @click="expanded = !expanded" class="text-sm font-semibold text-brand-primary hover:underline">
                                    <span x-show="!expanded">View snapshot</span>
                                    <span x-show="expanded">Hide snapshot</span>
                                </button>
                                <div x-show="expanded" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.stop>
                                    <div class="bg-brand-plum text-brand-cream p-6 rounded-2xl max-w-3xl w-full max-h-[80vh] overflow-y-auto text-left relative shadow-2xl border border-brand-sand/20">
                                        <div class="flex justify-between items-center pb-3 mb-3 border-b border-white/10">
                                            <p class="text-xs font-semibold text-accent-soft">Snapshot of purge #<?= $log[
                                                "id"
                                            ] ?></p>
                                            <button @click="expanded = false" class="text-white/50 hover:text-white font-bold">✕</button>
                                        </div>
                                        <pre class="data-text text-xs leading-relaxed text-green-300 overflow-x-auto p-4 bg-black/50 rounded-xl"><?= e(
                                            json_encode(
                                                json_decode(
                                                    $log["snapshot_json"],
                                                    true,
                                                ),
                                                JSON_PRETTY_PRINT,
                                            ),
                                        ) ?></pre>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <div class="text-center py-16 text-xs text-brand-plum/40 font-semibold">No purges yet.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ==========================================
         TAB 3: SECURITY CONFIGURATION
         ========================================== -->
    <div x-show="activeTab === 'config'" style="display:none;" x-transition:enter="transition ease-out duration-200">
        <div class="max-w-xl mx-auto bg-brand-white border border-brand-sand rounded-2xl p-8 shadow-sm">
            <div class="border-b border-brand-sand pb-5 mb-6 text-center">
                <div class="w-12 h-12 bg-brand-cream rounded-full mx-auto flex items-center justify-center mb-3 border border-brand-sand">
                    <span class="text-2xl">🔐</span>
                </div>
                <h2 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight">Set your purge PIN</h2>
                <p class="text-xs text-brand-plum/60 mt-1">4 to 8 digits, asked for with your password every time you purge.</p>
            </div>

            <form @submit.prevent="saveSecurityPin()" class="space-y-5">
                <div>
                    <label class="text-sm font-semibold text-brand-plum block mb-1.5">New PIN (4 to 8 digits)</label>
                    <input type="password" x-model="configPin" maxlength="8" placeholder="••••••••" required
                           class="w-full px-4 py-3 text-sm font-bold data-text tracking-widest bg-brand-cream border border-brand-sand rounded-xl text-brand-plum focus:outline-none focus:border-accent-text text-center">
                </div>
                <div>
                    <label class="text-sm font-semibold text-brand-plum block mb-1.5">Your password</label>
                    <input type="password" x-model="configPassword" placeholder="The password you sign in with" required
                           class="w-full px-4 py-3 text-xs font-bold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum focus:outline-none focus:border-accent-text">
                </div>

                <div x-show="configMsg" x-text="configMsg" style="display:none;"
                     class="p-3.5 rounded-xl text-xs font-bold text-center transition-all"
                     :class="configSuccess ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-[#fff0f0] text-[#ff0000] border border-[#ff0000]/30'"></div>

                <button type="submit" :disabled="configSubmitting || !configPin || !configPassword"
                        class="w-full btn-primary text-white py-4 rounded-xl text-sm font-semibold disabled:opacity-40 transition-all shadow-sm">
                    <span x-show="!configSubmitting">Save PIN</span>
                    <span x-show="configSubmitting">Saving PIN…</span>
                </button>
            </form>
        </div>
    </div>

</div>

<!-- Cockpit Alpine Controller -->
<script>
function purgeCockpit() {
    return {
        activeTab: '<?= $has_pin ? "console" : "config" ?>',
        searchMode: 'exact',
        targetModule: 'orders',
        exactString: '',
        batchDate: '<?= date("Y-m-d") ?>',
        batchTimeStart: '00:00',
        batchTimeEnd: '23:59',

        scanning: false,
        scanError: '',
        candidates: [],
        selectedMap: {},
        showBriefing: false,

        authPassword: '',
        authPin: '',
        confirmPermanent: false,
        purging: false,

        configPin: '',
        configPassword: '',
        configSubmitting: false,
        configMsg: '',
        configSuccess: false,

        async scanCandidates() {
            this.scanning = true;
            this.scanError = '';
            this.showBriefing = false;

            let url = window.Nfelia.url + `/purge/scan?module=${encodeURIComponent(this.targetModule)}&mode=${this.searchMode}`;
            if (this.searchMode === 'exact') {
                url += `&q=${encodeURIComponent(this.exactString)}`;
            } else {
                url += `&date=${this.batchDate}&time_start=${this.batchTimeStart}&time_end=${this.batchTimeEnd}`;
            }

            try {
                const res = await fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                const data = await res.json();
                if (data.success) {
                    this.candidates = data.candidates || [];
                    if (this.candidates.length === 0) {
                        this.scanError = 'No target candidates located matching your query.';
                    } else if (this.searchMode === 'exact' && this.candidates.length === 1) {
                        this.selectedMap[this.candidates[0].id] = true;
                    }
                } else {
                    this.scanError = data.error || 'Scan failed';
                }
            } catch (e) {
                this.scanError = 'Network scan error.';
            }
            this.scanning = false;
        },

        toggleSelect(id) {
            this.selectedMap[id] = !this.selectedMap[id];
        },
        selectAllCandidates() {
            this.candidates.forEach(c => { this.selectedMap[c.id] = true; });
        },
        getSelectedIds() {
            return Object.keys(this.selectedMap).filter(k => this.selectedMap[k]).map(Number);
        },
        getSelectedCandidates() {
            const ids = this.getSelectedIds();
            return this.candidates.filter(c => ids.includes(c.id));
        },
        openBriefingCard() {
            this.authPassword = '';
            this.authPin = '';
            this.confirmPermanent = false;
            this.showBriefing = true;
            setTimeout(() => { window.scrollTo({ top: document.body.scrollHeight, behavior: 'smooth' }); }, 100);
        },
        getTotalCollateralChildren() {
            let count = 0;
            this.getSelectedCandidates().forEach(c => { count += c.children.length; });
            return count;
        },
        getTotalDiskFiles() {
            let count = 0;
            this.getSelectedCandidates().forEach(c => { count += c.disk_files.length; });
            return count;
        },

        async executeHardPurge() {
            if (!this.confirmPermanent || !this.authPassword || !this.authPin) return;
            this.purging = true;

            const fd = new FormData();
            fd.append('module', this.targetModule);
            fd.append('ids', JSON.stringify(this.getSelectedIds()));
            fd.append('password', this.authPassword);
            fd.append('pin', this.authPin);

            try {
                const res = await fetch(window.Nfelia.url + '/purge/destroy', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert(data.error || 'Purge authorization failed.');
                }
            } catch (e) {
                alert('Network execution error.');
            }
            this.purging = false;
        },

        async saveSecurityPin() {
            this.configSubmitting = true;
            this.configMsg = '';

            const fd = new FormData();
            fd.append('pin', this.configPin);
            fd.append('password', this.configPassword);

            try {
                const res = await fetch(window.Nfelia.url + '/purge/save_pin', {
                    method: 'POST',
                    body: fd,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await res.json();
                this.configSuccess = !!data.success;
                this.configMsg = data.message || data.error || 'Pin update failed';
                if (data.success) {
                    this.configPin = '';
                    this.configPassword = '';
                    setTimeout(() => location.reload(), 1500);
                }
            } catch (e) {
                this.configSuccess = false;
                this.configMsg = 'Network error saving PIN.';
            }
            this.configSubmitting = false;
        }
    };
}
</script>
