<?php
/**
 * Nfelia — Interactive Client Portal (Quotation)
 * Standalone Mobile-First View
 */
$currency = $business['currency_symbol'] ?? '₦';
$primary  = $business['primary_color'] ?? '#0F172A'; // Defaulting to dark navy
$font_heading = 'Inter';
$font_body    = 'Inter';
$font_mono    = 'Roboto Mono';

$is_active = in_array($quotation['status'], ['draft', 'sent', 'in_negotiation']);

$unread_count = 0;
if (!empty($activities)) {
    foreach ($activities as $act) {
        if ($act['actor_type'] === 'business' && empty($act['is_client_read'])) {
            $unread_count++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?= errors_script_tag() ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Proposal <?= e($quotation['doc_number']) ?> — <?= e($business['name']) ?></title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=Roboto+Mono:wght@500;700&display=swap" rel="stylesheet">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js"></script>
    
    <script>
        tailwind.config = { 
            theme: { 
                extend: { 
                    fontFamily: {
                        sans: ['"<?= $font_body ?>"', 'sans-serif'],
                        heading: ['"<?= $font_heading ?>"', 'sans-serif'],
                        mono: ['"<?= $font_mono ?>"', 'monospace'],
                    },
                    colors: { brand: '<?= e($primary) ?>' }
                }
            }
        }
    </script>
    <style>
        body { background-color: #F8FAFC; -webkit-tap-highlight-color: transparent; }
        .hide-scrollbar::-webkit-scrollbar { display: none; }
        .hide-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
        /* Signature Canvas specific rules to prevent mobile scrolling while signing */
        #sig-canvas { touch-action: none; cursor: crosshair; }
    </style>
</head>
<body class="text-slate-800 antialiased selection:bg-brand selection:text-white" x-data="portalController()">

    <div class="max-w-2xl mx-auto bg-white min-h-screen relative shadow-2xl border-x border-slate-200 flex flex-col pb-24">
        
        <header class="p-5 sm:p-8 border-b-4 border-brand">
            <div class="flex justify-between items-start mb-6">
                <div>
                    <?php if ($business['logo_path']): ?>
                        <img src="<?= APP_URL ?>/<?= e($business['logo_path']) ?>" class="h-12 w-auto mb-3" alt="Logo">
                    <?php endif; ?>
                    <h1 class="font-heading font-extrabold text-2xl tracking-tight text-slate-900"><?= e($business['name']) ?></h1>
                    <p class="text-sm font-medium text-slate-500">Proposal Ref: <span class="font-mono text-slate-900"><?= e($quotation['doc_number']) ?></span></p>
                </div>
                
                <div class="text-right flex flex-col items-end gap-3">
                    <span class="inline-flex px-3 py-1 text-[10px] font-bold uppercase tracking-widest rounded-sm border-2 
                        <?= $quotation['status'] === 'accepted' ? 'border-green-600 text-green-700 bg-green-50' : 
                           ($quotation['status'] === 'declined' ? 'border-red-600 text-red-700 bg-red-50' : 
                           'border-slate-800 text-slate-800 bg-slate-50') ?>">
                        <?= str_replace('_', ' ', $quotation['status']) ?>
                    </span>
                    
                    <a href="<?= APP_URL ?>/doc/<?= e($quotation['token']) ?>?format=pdf" target="_blank" 
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 text-slate-700 rounded text-[10px] font-bold uppercase tracking-widest border border-slate-200 hover:bg-slate-200 hover:text-slate-900 transition-colors shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                        Download PDF
                    </a>
                </div>
            </div>

            <?php if ($customer): ?>
                <div class="bg-slate-50 p-4 border border-slate-200 rounded-lg">
                    <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Prepared For</p>
                    <p class="font-bold text-slate-900"><?= e($customer['full_name']) ?></p>
                    <?php if ($customer['business_name']): ?><p class="text-sm text-slate-600"><?= e($customer['business_name']) ?></p><?php endif; ?>
                </div>
            <?php endif; ?>
        </header>

        <nav class="flex border-b border-slate-200 sticky top-0 bg-white/90 backdrop-blur z-20">
            <template x-for="t in ['offer', 'terms', 'discuss']" :key="t">
                <button @click="switchTab(t)" 
                        class="flex-1 py-4 text-xs font-bold uppercase tracking-widest border-b-2 transition-colors flex items-center justify-center gap-2"
                        :class="tab === t ? 'border-brand text-brand' : 'border-transparent text-slate-400 hover:text-slate-600'">
                    <span x-text="t === 'discuss' ? 'Discussion' : t"></span>
                    
                    <!-- Notification Badge -->
                    <template x-if="t === 'discuss' && unreadCount > 0">
                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-white text-[9px] font-bold animate-pulse" 
                              x-text="unreadCount"></span>
                    </template>
                </button>
            </template>
        </nav>

        <main class="flex-1 overflow-y-auto p-5 sm:p-8">
            
            <div x-show="tab === 'offer'" x-transition.opacity>
                <div class="space-y-4 mb-8">
                    <?php foreach ($items as $idx => $it): ?>
                        <div class="p-4 border border-slate-200 rounded-lg bg-white shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div>
                                <div class="font-bold text-slate-900 text-sm"><?= e($it['description']) ?></div>
                                <div class="text-xs font-medium text-slate-500 mt-1">
                                    Qty: <span class="font-mono text-slate-800"><?= number_format((float)$it['quantity'], 2) ?></span> 
                                    × <span class="font-mono"><?= money((float)$it['unit_price'], $currency) ?></span>
                                    <?php if ((float)$it['discount_percent'] > 0): ?>
                                        <span class="text-red-500 ml-2">(-<?= number_format((float)$it['discount_percent'], 1) ?>%)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="font-mono font-bold text-lg text-slate-900 text-right">
                                <?= money((float)$it['subtotal'], $currency) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="border-2 border-slate-900 p-5 rounded-lg bg-slate-50 space-y-2">
                    <div class="flex justify-between text-sm font-medium text-slate-600">
                        <span>Subtotal</span><span class="font-mono"><?= money((float)$quotation['subtotal'], $currency) ?></span>
                    </div>
                    <?php if ((float)$quotation['discount_amount'] > 0): ?>
                        <div class="flex justify-between text-sm font-medium text-red-600">
                            <span>Discount</span><span class="font-mono">-<?= money((float)$quotation['discount_amount'], $currency) ?></span>
                        </div>
                    <?php endif; ?>
                    <?php if ((float)$quotation['tax_rate'] > 0): ?>
                        <div class="flex justify-between text-sm font-medium text-slate-600">
                            <span>Tax (<?= number_format((float)$quotation['tax_rate'], 1) ?>%)</span><span class="font-mono"><?= money((float)$quotation['tax_amount'], $currency) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="pt-4 mt-2 border-t-2 border-slate-200 flex justify-between items-center">
                        <span class="font-heading font-extrabold uppercase tracking-widest text-slate-900">Total</span>
                        <span class="font-mono font-bold text-2xl text-brand"><?= money((float)$quotation['total'], $currency) ?></span>
                    </div>
                </div>
            </div>

            <div x-show="tab === 'terms'" x-cloak x-transition.opacity class="space-y-8">
                <?php if ($quotation['notes']): ?>
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Terms & Conditions</h3>
                        <div class="p-5 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-700 whitespace-pre-line leading-relaxed">
                            <?= e($quotation['notes']) ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($bank): ?>
                    <div>
                        <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3">Payment Details</h3>
                        <div class="p-5 border-2 border-slate-900 rounded-lg bg-white">
                            <p class="text-sm font-medium text-slate-500 mb-1">Bank: <span class="font-bold text-slate-900"><?= e($bank['bank_name']) ?></span></p>
                            <p class="text-sm font-medium text-slate-500 mb-1">Name: <span class="font-bold text-slate-900"><?= e($bank['account_name']) ?></span></p>
                            <p class="text-sm font-medium text-slate-500">Account: <span class="font-mono font-bold text-lg text-brand"><?= e($bank['account_number']) ?></span></p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php if ($quotation['expiry_date']): ?>
                    <div class="text-center p-4 bg-amber-50 border border-amber-200 rounded-lg">
                        <p class="text-xs font-bold text-amber-800 uppercase tracking-widest">Valid Until</p>
                        <p class="font-mono font-bold text-amber-900 mt-1"><?= fmt_date($quotation['expiry_date'], 'd M Y') ?></p>
                    </div>
                <?php endif; ?>
            </div>

            <div x-show="tab === 'discuss'" x-cloak x-transition.opacity class="flex flex-col h-full">
                <div class="flex-1 space-y-6 mb-6">
                    <?php if (empty($activities)): ?>
                        <p class="text-sm text-slate-400 text-center py-10 font-medium">No activity yet. Leave a comment to negotiate terms.</p>
                    <?php else: ?>
                        <?php foreach ($activities as $act): ?>
                            <?php $is_client = $act['actor_type'] === 'client'; ?>
                            <div class="flex flex-col <?= $is_client ? 'items-end' : 'items-start' ?>">
                                <span class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-1 mx-1">
                                    <?= $is_client ? e($customer['full_name'] ?? 'You') : e($business['name']) ?> • <?= date('M d, H:i', strtotime($act['created_at'])) ?>
                                </span>
                                <?php if ($act['action'] === 'comment_added'): ?>
                                    <div class="px-4 py-3 rounded-2xl max-w-[85%] text-sm <?= $is_client ? 'bg-brand text-white rounded-tr-sm' : 'bg-slate-100 text-slate-800 border border-slate-200 rounded-tl-sm' ?>">
                                        <?= nl2br(e($act['message'])) ?>
                                    </div>
                                <?php else: ?>
                                    <div class="px-3 py-1.5 bg-slate-50 border border-slate-200 rounded-md text-xs font-medium text-slate-500">
                                        System: <?= e($act['message']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <?php if ($is_active): ?>
                    <div class="mt-auto pt-4 border-t border-slate-200">
                        <textarea x-model="commentText" rows="2" class="w-full p-3 border-2 border-slate-200 rounded-lg focus:border-brand focus:ring-0 text-sm resize-none mb-2 font-medium" placeholder="Ask a question or request a revision..."></textarea>
                        <button @click="submitAction('comment')" :disabled="!commentText.trim() || isSubmitting" 
                                class="w-full py-3 bg-slate-900 text-white text-xs font-bold uppercase tracking-widest rounded-lg disabled:opacity-50 transition-opacity">
                            Send Message
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <?php if ($is_active): ?>
            <div class="fixed bottom-0 left-0 right-0 z-30 max-w-2xl mx-auto bg-white border-t border-slate-200 p-4 flex gap-3 shadow-[0_-10px_20px_-10px_rgba(0,0,0,0.1)]">
                <button @click="modals.decline = true" class="flex-1 py-3.5 border-2 border-slate-200 text-slate-600 text-xs font-bold uppercase tracking-widest rounded-lg hover:bg-slate-50 transition-colors">
                    Decline
                </button>
                <button @click="openSignatureModal()" class="flex-[2] py-3.5 bg-brand text-white text-xs font-bold uppercase tracking-widest rounded-lg shadow-lg hover:shadow-xl transition-all">
                    Sign & Accept
                </button>
            </div>
        <?php endif; ?>
        
        <?php if ($quotation['owner_signature_data'] || ($quotation['status'] === 'accepted' && $quotation['signature_data'])): ?>
            <div class="grid grid-cols-2 gap-4 p-6 bg-slate-50 border-t border-slate-200 mt-auto">
                
                <div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-3">Authorized By</p>
                    <?php if ($quotation['owner_signature_data']): ?>
                        <img src="<?= e($quotation['owner_signature_data']) ?>" class="max-h-16 object-contain mb-2 border-b border-slate-200 pb-2">
                        <p class="text-sm font-bold text-slate-900"><?= e($quotation['owner_signed_by_name']) ?></p>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">Date: <?= date('d M Y, H:i', strtotime($quotation['owner_signed_at'])) ?></p>
                    <?php else: ?>
                        <p class="text-xs text-slate-400 italic mt-6">Pending internal authorization...</p>
                    <?php endif; ?>
                </div>

                <div>
                    <p class="text-[9px] font-bold text-slate-400 uppercase tracking-widest mb-3">Client Acceptance</p>
                    <?php if ($quotation['status'] === 'accepted' && $quotation['signature_data']): ?>
                        <img src="<?= e($quotation['signature_data']) ?>" class="max-h-16 object-contain mb-2 border-b border-slate-200 pb-2">
                        <p class="text-sm font-bold text-slate-900"><?= e($quotation['signed_by_name']) ?></p>
                        <p class="text-[9px] text-slate-500 font-mono mt-0.5">Date: <?= date('d M Y, H:i', strtotime($quotation['signed_at'])) ?></p>
                    <?php else: ?>
                        <p class="text-xs text-slate-400 italic mt-6">Pending client signature...</p>
                    <?php endif; ?>
                </div>

            </div>
        <?php endif; ?>

        <div x-show="modals.decline" x-cloak class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-slate-900/50 backdrop-blur-sm p-4">
            <div @click.away="modals.decline = false" x-transition.translate.y.bottom class="bg-white w-full max-w-md rounded-2xl p-6 shadow-2xl">
                <h3 class="font-heading font-bold text-lg text-slate-900 mb-2">Decline Proposal</h3>
                <p class="text-xs text-slate-500 mb-4">Please let us know why so we can improve.</p>
                <textarea x-model="declineReason" rows="3" class="w-full p-3 border-2 border-slate-200 rounded-lg mb-4 text-sm focus:border-brand outline-none resize-none"></textarea>
                <div class="flex gap-3">
                    <button @click="modals.decline = false" class="flex-1 py-3 text-xs font-bold text-slate-600 bg-slate-100 rounded-lg uppercase tracking-widest">Cancel</button>
                    <button @click="submitAction('decline')" :disabled="isSubmitting" class="flex-1 py-3 text-xs font-bold text-white bg-red-600 rounded-lg uppercase tracking-widest disabled:opacity-50">Confirm</button>
                </div>
            </div>
        </div>

        <div x-show="modals.sign" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/80 backdrop-blur-sm p-4 sm:p-6">
            <div @click.away="modals.sign = false" x-transition.scale class="bg-white w-full max-w-lg rounded-2xl shadow-2xl overflow-hidden flex flex-col max-h-[90vh]">
                
                <div class="p-5 border-b border-slate-100 bg-slate-50">
                    <h3 class="font-heading font-bold text-lg text-slate-900">Sign & Accept</h3>
                    <p class="text-xs text-slate-500">By signing, you agree to the Terms & Conditions.</p>
                </div>
                
                <div class="p-5 overflow-y-auto">
                    <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-2">Your Full Name</label>
                    <input type="text" x-model="signName" class="w-full p-3 border-2 border-slate-200 rounded-lg mb-5 text-sm font-bold text-slate-900 focus:border-brand outline-none" placeholder="John Doe">
                    
                    <div class="flex justify-between items-end mb-2">
                        <label class="block text-[10px] font-bold text-slate-400 uppercase tracking-widest">Draw Signature</label>
                        <button @click="clearSignature()" type="button" class="text-[10px] font-bold text-brand uppercase tracking-widest hover:underline">Clear</button>
                    </div>
                    <div class="border-2 border-slate-900 rounded-xl bg-slate-50 overflow-hidden relative" style="height: 200px;">
                        <canvas id="sig-canvas" width="600" height="200" class="w-full h-full absolute inset-0"></canvas>
                    </div>
                </div>

                <div class="p-5 border-t border-slate-100 flex gap-3 bg-white">
                    <button @click="modals.sign = false" class="py-3.5 px-6 text-xs font-bold text-slate-600 border-2 border-slate-200 rounded-lg uppercase tracking-widest hover:bg-slate-50">Cancel</button>
                    <button @click="submitSignature()" :disabled="!signName.trim() || isSubmitting" class="flex-1 py-3.5 text-xs font-bold text-white bg-brand rounded-lg uppercase tracking-widest shadow-md disabled:opacity-50">
                        <span x-text="isSubmitting ? 'Processing...' : 'Accept Proposal'"></span>
                    </button>
                </div>
            </div>
        </div>

    </div>

    <script>
        function portalController() {
            return {
                tab: 'offer',
                unreadCount: <?= $unread_count ?>,
                commentText: '',
                declineReason: '',
                signName: '<?= e($customer['contact_name'] ?? $customer['full_name'] ?? '') ?>',
                modals: { sign: false, decline: false },
                isSubmitting: false,
                ctx: null,
                canvas: null,
                isDrawing: false,
                hasDrawn: false,

                switchTab(t) {
                    this.tab = t;
                    // If they click discuss and have unread messages, clear badge and update DB
                    if (t === 'discuss' && this.unreadCount > 0) {
                        this.unreadCount = 0;
                        fetch('<?= APP_URL ?>/quotations/mark_comments_read/<?= e($quotation['token']) ?>', {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest' }
                        });
                    }
                },

                init() {
                    this.canvas = document.getElementById('sig-canvas');
                    this.ctx = this.canvas.getContext('2d');
                    
                    // Canvas Setup
                    this.ctx.lineWidth = 3;
                    this.ctx.lineCap = 'round';
                    this.ctx.strokeStyle = '#0F172A';

                    // Mouse Events
                    this.canvas.addEventListener('mousedown', this.startDrawing.bind(this));
                    this.canvas.addEventListener('mousemove', this.draw.bind(this));
                    this.canvas.addEventListener('mouseup', this.stopDrawing.bind(this));
                    this.canvas.addEventListener('mouseout', this.stopDrawing.bind(this));

                    // Touch Events (mobile support)
                    this.canvas.addEventListener('touchstart', (e) => { e.preventDefault(); this.startDrawing(e.touches[0]); }, { passive: false });
                    this.canvas.addEventListener('touchmove', (e) => { e.preventDefault(); this.draw(e.touches[0]); }, { passive: false });
                    this.canvas.addEventListener('touchend', this.stopDrawing.bind(this));
                },

                openSignatureModal() {
                    this.modals.sign = true;
                    // Slight delay to ensure modal is visible before clearing/focusing
                    setTimeout(() => { this.clearSignature(); }, 100);
                },

                getMousePos(e) {
                    const rect = this.canvas.getBoundingClientRect();
                    // Calculate scale to map CSS size to internal canvas resolution (600x200)
                    const scaleX = this.canvas.width / rect.width;
                    const scaleY = this.canvas.height / rect.height;
                    return {
                        x: (e.clientX - rect.left) * scaleX,
                        y: (e.clientY - rect.top) * scaleY
                    };
                },

                startDrawing(e) {
                    this.isDrawing = true;
                    this.hasDrawn = true;
                    const pos = this.getMousePos(e);
                    this.ctx.beginPath();
                    this.ctx.moveTo(pos.x, pos.y);
                },

                draw(e) {
                    if (!this.isDrawing) return;
                    const pos = this.getMousePos(e);
                    this.ctx.lineTo(pos.x, pos.y);
                    this.ctx.stroke();
                },

                stopDrawing() {
                    this.isDrawing = false;
                },

                clearSignature() {
                    this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                    this.hasDrawn = false;
                    this.ctx.beginPath(); // Reset paths
                },

                async submitSignature() {
                    if (!this.hasDrawn) {
                        alert("Please draw your signature to legally accept this document.");
                        return;
                    }
                    const dataUrl = this.canvas.toDataURL('image/png');
                    
                    const fd = new FormData();
                    fd.append('action', 'sign');
                    fd.append('name', this.signName);
                    fd.append('signature', dataUrl);
                    
                    await this.postData(fd);
                },

                async submitAction(action) {
                    const fd = new FormData();
                    fd.append('action', action);
                    
                    if (action === 'comment') {
                        fd.append('message', this.commentText);
                    } else if (action === 'decline') {
                        fd.append('reason', this.declineReason);
                    }
                    
                    await this.postData(fd);
                },

                async postData(formData) {
                    this.isSubmitting = true;
                    try {
                        const res = await fetch(window.location.href, { method: 'POST', body: formData });
                        const json = await res.json();
                        
                        if (json.success) {
                            window.location.reload(); // Reload to show new state/comments
                        } else {
                            alert(json.error || "An error occurred.");
                        }
                    } catch (e) {
                        alert("Network error. Please try again.");
                    }
                    this.isSubmitting = false;
                }
            }
        }
    </script>
</body>
</html>