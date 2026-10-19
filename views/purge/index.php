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
            <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Workspace Purge Engine</h1>
        </div>
        <p class="text-xs text-brand-plum/60 font-medium mt-1 pl-11">Surgical hard deletion engine with double-lock authorization and asset cleanse.</p>
    </div>

    <!-- Security Badges -->
    <div class="flex items-center gap-2">
        <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg bg-brand-plum text-white shadow-sm flex items-center gap-1.5">
            <svg class="w-3 h-3 text-brand-gold" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a6 6 0 00-6 6v3a2 2 0 00-2 2v5a2 2 0 002 2h12a2 2 0 002-2v-5a2 2 0 00-2-2V8a6 6 0 00-6-6zm-4 6a4 4 0 118 0v3H6V8z"/></svg>
            Double-Lock Guard Active
        </span>
        <span class="data-text text-[9px] font-bold uppercase tracking-wider px-2.5 py-1 rounded-lg <?= $has_pin
            ? "bg-green-100 text-green-700"
            : "bg-amber-100 text-amber-700 animate-pulse" ?>">
            <?= $has_pin ? "PIN Configured" : "⚠️ PIN Action Required" ?>
        </span>
    </div>
</div>

<!-- Alpine Cockpit State -->
<div x-data="purgeCockpit()">

    <!-- Tab Switcher -->
    <div class="flex items-center gap-2 mb-6 border-b border-brand-sand pb-3 overflow-x-auto no-scrollbar">
        <button type="button" @click="activeTab = 'console'"
                :class="activeTab === 'console' ? 'btn-primary text-white shadow-sm' : 'bg-brand-white text-brand-plum/70 hover:text-brand-plum border border-brand-sand'"
                class="px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all shrink-0">
            ⚡ Purge Cockpit
        </button>
        <button type="button" @click="activeTab = 'logs'"
                :class="activeTab === 'logs' ? 'btn-primary text-white shadow-sm' : 'bg-brand-white text-brand-plum/70 hover:text-brand-plum border border-brand-sand'"
                class="px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all shrink-0 flex items-center gap-1.5">
            🛡️ Immutable Ledger
            <span class="bg-brand-plum/10 text-brand-plum px-1.5 py-0.5 rounded text-[8px]"><?= count(
                $purge_logs,
            ) ?></span>
        </button>
        <button type="button" @click="activeTab = 'config'"
                :class="activeTab === 'config' ? 'btn-primary text-white shadow-sm' : 'bg-brand-white text-brand-plum/70 hover:text-brand-plum border border-brand-sand'"
                class="px-5 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest transition-all shrink-0">
            🔐 Security Config
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
                    <h2 class="font-heading font-bold text-base text-brand-plum uppercase tracking-wider">Select Target Candidates</h2>
                    <p class="text-xs text-brand-plum/50 mt-0.5">Search exact document strings or query candidates across date & time ranges.</p>
                </div>
                <div class="flex bg-brand-cream p-1 rounded-xl shrink-0 border border-brand-sand">
                    <button type="button" @click="searchMode = 'exact'; candidates = [];"
                            :class="searchMode === 'exact' ? 'bg-brand-white text-brand-plum font-bold shadow-sm' : 'text-brand-plum/50 hover:text-brand-plum'"
                            class="px-4 py-2 rounded-lg text-[10px] uppercase tracking-wider transition-all">
                        🎯 Surgical Exact Search
                    </button>
                    <button type="button" @click="searchMode = 'batch'; candidates = [];"
                            :class="searchMode === 'batch' ? 'bg-brand-white text-brand-plum font-bold shadow-sm' : 'text-brand-plum/50 hover:text-brand-plum'"
                            class="px-4 py-2 rounded-lg text-[10px] uppercase tracking-wider transition-all">
                        📅 Time Batch Search
                    </button>
                </div>
            </div>

            <!-- Target Module Selector -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-5">
                <div>
                    <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1.5">Target Module</label>
                    <select x-model="targetModule" @change="candidates = []; selectedMap = {};"
                            class="w-full px-3.5 py-2.5 text-xs font-bold uppercase tracking-wider bg-brand-cream border border-brand-sand rounded-xl text-brand-plum focus:outline-none focus:border-brand-gold">
                        <option value="orders">Orders (ORD), with all their documents</option>
                        <option value="quotations">Quotations (QUO)</option>
                        <option value="invoices">Invoices (INV)</option>
                        <option value="receipts">Receipts (REC)</option>
                        <option value="deliveries">Delivery Notes (DEL)</option>
                        <option value="products">Catalog & Stock</option>
                        <option value="customers">Client Directory</option>
                    </select>
                </div>

                <!-- SURGICAL SEARCH INPUT -->
                <div x-show="searchMode === 'exact'" class="sm:col-span-3 flex gap-3">
                    <div class="flex-1">
                        <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1.5">Identifier </label>
                        <input type="text" name="purge_search_identifier" x-model="exactString" @keyup.enter="scanCandidates()"
                               autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" data-form-type="other"
                               placeholder="e.g. QUO-0001, INV-0012, ORD-0004..."
                               class="w-full px-4 py-2.5 text-xs font-bold data-text uppercase tracking-wider bg-brand-cream border border-brand-sand rounded-xl text-brand-plum focus:outline-none focus:border-brand-gold">
                    </div>
                    <div class="flex items-end">
                        <button type="button" @click="scanCandidates()" :disabled="scanning || !exactString"
                                class="btn-primary text-white px-6 py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest disabled:opacity-50 h-[42px] transition-all shadow-sm">
                            <span x-show="!scanning">Scan Database</span>
                            <span x-show="scanning">Scanning...</span>
                        </button>
                    </div>
                </div>

                <!-- BATCH TIME SEARCH INPUTS -->
                <div x-show="searchMode === 'batch'" class="sm:col-span-3 grid grid-cols-1 sm:grid-cols-4 gap-3" style="display:none;">
                    <div>
                        <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1.5">Target Date</label>
                        <input type="date" x-model="batchDate"
                               class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum">
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1.5">Start Time</label>
                        <input type="time" x-model="batchTimeStart"
                               class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum">
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1.5">End Time</label>
                        <input type="time" x-model="batchTimeEnd"
                               class="w-full px-3 py-2.5 text-xs font-semibold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum">
                    </div>
                    <div class="flex items-end">
                        <button type="button" @click="scanCandidates()" :disabled="scanning"
                                class="w-full btn-primary text-white py-2.5 rounded-xl text-[10px] font-bold uppercase tracking-widest disabled:opacity-50 h-[42px] transition-all shadow-sm">
                            <span x-show="!scanning">Find Batch</span>
                            <span x-show="scanning">Querying...</span>
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
                    <h3 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Candidates Located (<span x-text="candidates.length"></span>)</h3>
                    <p class="text-[11px] text-brand-plum/50">Check records below to assemble your impact dependency briefing.</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" @click="selectAllCandidates()" class="text-[10px] font-bold text-brand-primary uppercase tracking-wider hover:underline">Select All</button>
                    <span class="text-brand-plum/20">|</span>
                    <button type="button" @click="selectedMap = {}" class="text-[10px] font-bold text-brand-plum/50 uppercase tracking-wider hover:underline">Deselect All</button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-brand-cream/60 border-b border-brand-sand text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">
                            <th class="py-3 px-4 w-12 text-center">Purge</th>
                            <th class="py-3 px-4">Identifier</th>
                            <th class="py-3 px-4">Created Stamp</th>
                            <th class="py-3 px-4 text-right">Value (<?= $currency ?>)</th>
                            <th class="py-3 px-4 text-center">Status</th>
                            <th class="py-3 px-4 text-center">Dependencies</th>
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
                                <td class="py-3.5 px-4 data-text text-[10px] text-brand-plum/60" x-text="item.created"></td>
                                <td class="py-3.5 px-4 data-text font-bold text-brand-plum text-right" x-text="item.amount.toLocaleString(undefined, {minimumFractionDigits:2})"></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-brand-sand/60 text-brand-plum/80" x-text="item.status"></span>
                                </td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase tracking-wider bg-[#fff0f0] text-[#ff0000]"
                                          x-text="(item.children.length + item.disk_files.length) + ' collateral assets'"></span>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <div class="mt-6 pt-4 border-t border-brand-sand flex items-center justify-between">
                <span class="text-xs font-semibold text-brand-plum/60">
                    Selected <strong class="text-brand-burgundy font-bold" x-text="getSelectedIds().length"></strong> candidates for annihilation.
                </span>
                <button type="button" @click="openBriefingCard()" :disabled="getSelectedIds().length === 0"
                        class="bg-[#ff0000] hover:bg-[#cc0000] text-white px-8 py-3 rounded-xl text-xs font-extrabold uppercase tracking-widest disabled:opacity-40 transition-all shadow-md active:scale-95">
                    ⚠️ Assemble Purge Briefing Card
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
                    <span class="bg-[#ff0000] text-white text-[9px] font-extrabold uppercase tracking-widest px-3 py-1 rounded-full animate-pulse">
                        🔥 IMPACT BRIEFING
                    </span>
                    <h2 class="font-heading font-extrabold text-2xl sm:text-3xl text-white mt-3">Authorization Card</h2>
                    <p class="text-xs text-white/60 mt-1">Review target primary records and collateral cascading asset damage below.</p>
                </div>
                <button type="button" @click="showBriefing = false" class="text-white/40 hover:text-white text-sm uppercase tracking-wider font-bold">Close Card ✕</button>
            </div>

            <!-- Summary Bar -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 bg-white/5 border border-white/10 rounded-2xl p-5 mb-8">
                <div>
                    <div class="text-[9px] font-bold text-white/40 uppercase tracking-widest">Primary Rows Targeted</div>
                    <div class="text-2xl font-extrabold data-text text-[#ff4d4d]" x-text="getSelectedIds().length + ' ' + targetModule.toUpperCase()"></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-white/40 uppercase tracking-widest">Cascading Child DB Rows</div>
                    <div class="text-2xl font-extrabold data-text text-amber-400" x-text="getTotalCollateralChildren() + ' Linked Records'"></div>
                </div>
                <div>
                    <div class="text-[9px] font-bold text-white/40 uppercase tracking-widest">Physical Assets Unlinked</div>
                    <div class="text-2xl font-extrabold data-text text-blue-400" x-text="getTotalDiskFiles() + ' Disk Assets (/uploads)'"></div>
                </div>
            </div>

            <!-- DETAILED CASCADE ACCORDION -->
            <div class="space-y-4 mb-8 max-h-80 overflow-y-auto pr-2 no-scrollbar">
                <template x-for="row in getSelectedCandidates()" :key="row.id">
                    <div class="bg-white/5 border border-white/10 rounded-2xl p-5">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <span class="data-text font-extrabold text-sm text-[#ff4d4d]" x-text="row.identifier"></span>
                                <span class="text-[10px] text-white/50" x-text="'Stamp: ' + row.created"></span>
                            </div>
                            <span class="data-text font-bold text-sm text-white" x-text="'<?= $currency ?>' + row.amount.toLocaleString(undefined,{minimumFractionDigits:2})"></span>
                        </div>

                        <!-- Collateral Children List -->
                        <div x-show="row.children.length > 0 || row.disk_files.length > 0" class="mt-3 pt-3 border-t border-white/10 text-xs">
                            <p class="text-[9px] font-bold text-amber-400/80 uppercase tracking-widest mb-2">Cascading Destruction List:</p>
                            <ul class="space-y-1.5 text-white/80 pl-2">
                                <template x-for="child in row.children">
                                    <li class="flex items-center gap-2">
                                        <span class="text-amber-400">↳</span>
                                        <strong x-text="child.type + ':'"></strong>
                                        <span class="data-text font-semibold" x-text="child.name"></span>
                                        <span class="text-[10px] text-white/40" x-text="'(' + child.status + ')'"></span>
                                    </li>
                                </template>
                                <template x-for="f in row.disk_files">
                                    <li class="flex items-center gap-2 text-blue-300">
                                        <span>↳ Disk Unlink:</span>
                                        <code class="text-[10px] bg-white/10 px-2 py-0.5 rounded truncate max-w-sm" x-text="f"></code>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>
            </div>

            <!-- DOUBLE-LOCK SECRET VERIFICATION INPUTS -->
            <div class="bg-[#ff0000]/15 border-2 border-[#ff0000]/50 rounded-2xl p-6">
                <h3 class="text-xs font-extrabold text-white uppercase tracking-widest mb-4 flex items-center gap-2">
                    🔐 DOUBLE-LOCK AUTHORIZATION REQUIRED
                </h3>

                <input type="text" name="honeypot_fake_username" style="display:none;" autocomplete="username" tabindex="-1">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-5">
                    <div>
                        <label class="text-[9px] font-bold text-white/60 uppercase tracking-widest block mb-1">Account Login Password</label>
                        <input type="password" x-model="authPassword" placeholder="Your live login password"
                               class="w-full px-4 py-3 bg-black/60 border border-white/20 rounded-xl text-white text-xs font-bold focus:outline-none focus:border-[#ff0000]">
                    </div>
                    <div>
                        <label class="text-[9px] font-bold text-white/60 uppercase tracking-widest block mb-1">Purge PIN (Tab 3)</label>
                        <input type="password" x-model="authPin" placeholder="4 to 8 digit Purge PIN" maxlength="8"
                               class="w-full px-4 py-3 bg-black/60 border border-white/20 rounded-xl text-white text-xs font-bold data-text tracking-widest focus:outline-none focus:border-[#ff0000]">
                    </div>
                </div>

                <!-- Checkbox -->
                <label class="flex items-center gap-3 cursor-pointer select-none mb-6">
                    <input type="checkbox" x-model="confirmPermanent"
                           class="w-5 h-5 rounded bg-black/60 border-white/30 text-[#ff0000] focus:ring-0">
                    <span class="text-xs font-bold text-white/90">
                        I legally confirm I have audited the briefing above and authorize permanent hard deletion from MySQL + disk.
                    </span>
                </label>

                <!-- DESTROY BUTTON -->
                <button type="button" @click="executeHardPurge()" :disabled="purging || !confirmPermanent || !authPassword || !authPin"
                        class="w-full bg-[#ff0000] hover:bg-[#e60000] text-white py-4 rounded-xl text-sm font-black uppercase tracking-widest disabled:opacity-30 transition-all shadow-[0_0_30px_rgba(255,0,0,0.5)] active:scale-98">
                    <span x-show="!purging">💥 EXECUTE PERMANENT HARD PURGE</span>
                    <span x-show="purging">ANNIHILATING RECORDS...</span>
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
                <h2 class="font-heading font-bold text-base text-brand-plum uppercase tracking-wider">Immutable Purge Audit Ledger</h2>
                <p class="text-xs text-brand-plum/50 mt-0.5">Cryptographically logged non-repudiation ledger of all surgical and batch purges.</p>
            </div>

            <?php if (!empty($purge_logs)): ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-brand-cream/60 border-b border-brand-sand text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest">
                            <th class="py-3 px-4">Purge ID</th>
                            <th class="py-3 px-4">Timestamp</th>
                            <th class="py-3 px-4">Administrator</th>
                            <th class="py-3 px-4">IP Address</th>
                            <th class="py-3 px-4">Mode</th>
                            <th class="py-3 px-4">Purge Action Summary</th>
                            <th class="py-3 px-4 text-center">JSON Snapshot</th>
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
                            <td class="py-3.5 px-4 data-text text-[10px] text-brand-plum/60"><?= e(
                                $log["purged_at"],
                            ) ?></td>
                            <td class="py-3.5 px-4 font-bold text-brand-plum"><?= e(
                                $log["admin_name"],
                            ) ?></td>
                            <td class="py-3.5 px-4 data-text text-[10px] text-brand-plum/50"><?= e(
                                $log["admin_ip"],
                            ) ?></td>
                            <td class="py-3.5 px-4"><span class="px-2 py-0.5 rounded text-[9px] font-bold uppercase bg-brand-sand/60"><?= e(
                                $log["purge_mode"],
                            ) ?></span></td>
                            <td class="py-3.5 px-4 font-semibold text-brand-plum/80 max-w-sm truncate"><?= e(
                                $log["deleted_summary"],
                            ) ?></td>
                            <td class="py-3.5 px-4 text-center">
                                <button @click="expanded = !expanded" class="text-[10px] font-bold text-brand-primary uppercase tracking-wider hover:underline">
                                    <span x-show="!expanded">Inspect JSON</span>
                                    <span x-show="expanded">Hide Snapshot</span>
                                </button>
                                <div x-show="expanded" style="display:none;" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm" @click.stop>
                                    <div class="bg-brand-plum text-brand-cream p-6 rounded-2xl max-w-3xl w-full max-h-[80vh] overflow-y-auto text-left relative shadow-2xl border border-brand-sand/20">
                                        <div class="flex justify-between items-center pb-3 mb-3 border-b border-white/10">
                                            <p class="text-xs font-bold uppercase tracking-widest text-brand-gold">Raw Immutable JSON Snapshot — Ledger #<?= $log[
                                                "id"
                                            ] ?></p>
                                            <button @click="expanded = false" class="text-white/50 hover:text-white font-bold">✕</button>
                                        </div>
                                        <pre class="data-text text-[10px] leading-relaxed text-green-300 overflow-x-auto p-4 bg-black/50 rounded-xl"><?= e(
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
                <div class="text-center py-16 text-xs text-brand-plum/40 font-semibold">No hard purges recorded in this workspace yet.</div>
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
                <h2 class="font-heading font-extrabold text-xl text-brand-plum tracking-tight">Configure Purge Security PIN</h2>
                <p class="text-xs text-brand-plum/60 mt-1">Set the 4 to 8 digit double-lock PIN required whenever executing hard purges.</p>
            </div>

            <form @submit.prevent="saveSecurityPin()" class="space-y-5">
                <div>
                    <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1.5">New Security PIN (4 - 8 digits)</label>
                    <input type="password" x-model="configPin" maxlength="8" placeholder="••••••••" required
                           class="w-full px-4 py-3 text-sm font-bold data-text tracking-widest bg-brand-cream border border-brand-sand rounded-xl text-brand-plum focus:outline-none focus:border-brand-gold text-center">
                </div>
                <div>
                    <label class="text-[9px] font-bold text-brand-plum/50 uppercase tracking-widest block mb-1.5">Confirm Account Login Password</label>
                    <input type="password" x-model="configPassword" placeholder="Live login password" required
                           class="w-full px-4 py-3 text-xs font-bold bg-brand-cream border border-brand-sand rounded-xl text-brand-plum focus:outline-none focus:border-brand-gold">
                </div>

                <div x-show="configMsg" x-text="configMsg" style="display:none;"
                     class="p-3.5 rounded-xl text-xs font-bold text-center transition-all"
                     :class="configSuccess ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-[#fff0f0] text-[#ff0000] border border-[#ff0000]/30'"></div>

                <button type="submit" :disabled="configSubmitting || !configPin || !configPassword"
                        class="w-full btn-primary text-white py-4 rounded-xl text-xs font-extrabold uppercase tracking-widest disabled:opacity-40 transition-all shadow-sm">
                    <span x-show="!configSubmitting">Save Security PIN</span>
                    <span x-show="configSubmitting">Saving PIN...</span>
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
