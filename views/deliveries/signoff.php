<?php
/**
 * Nfelia — Delivery Sign-Off Page (Recipient E-Signature)
 * Standalone page: /sign/{signoff_token}
 * Mobile-first: recipient opens on their phone to confirm receipt.
 *
 * Available from PublicController:
 *   $delivery, $business, $items, $providers, $statuses
 *   $already_signed (bool) — true if delivery already signed off
 */
$primary   = $business['primary_color'] ?? '#1a1a2e';
$secondary = $business['secondary_color'] ?? '#c5a55a';
$font      = $business['font_pairing'] ?? 'Inter';
$logo_url  = !empty($business['logo_path']) ? APP_URL . '/' . $business['logo_path'] : null;
$already_signed = ($delivery['status'] === 'signed_off' && $delivery['signoff_name']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Confirm Receipt — <?= e($delivery['doc_number']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=<?= urlencode($font) ?>:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root { --brand-primary: <?= e($primary) ?>; --brand-secondary: <?= e($secondary) ?>; }
        body { font-family: '<?= e($font) ?>', sans-serif; -webkit-tap-highlight-color: transparent; }
        .brand-primary { color: var(--brand-primary); }
        .bg-brand-primary { background-color: var(--brand-primary); }

        /* Signature canvas */
        #signature-pad { touch-action: none; cursor: crosshair; }
        #signature-pad.signing { background: #fefce8; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">

    <div class="max-w-lg mx-auto px-4 py-6">

        <!-- Business Header -->
        <div class="text-center mb-6">
            <?php if ($logo_url): ?>
                <img src="<?= e($logo_url) ?>" alt="Logo" class="h-12 w-12 object-contain rounded-xl mx-auto mb-2 shadow-sm">
            <?php endif; ?>
            <h1 class="text-lg font-extrabold brand-primary"><?= e($business['business_name']) ?></h1>
            <p class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mt-1">Delivery Confirmation</p>
        </div>

        <?php if ($already_signed): ?>
        <!-- ═══ ALREADY SIGNED ═══ -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden">
            <div class="bg-green-500 text-white px-6 py-5 text-center">
                <svg class="w-10 h-10 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <h2 class="text-xl font-extrabold">Receipt Confirmed</h2>
                <p class="text-xs opacity-80 mt-1">This delivery has been signed off.</p>
            </div>
            <div class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Delivery</div>
                        <div class="text-sm font-bold text-gray-800"><?= e($delivery['doc_number']) ?></div>
                    </div>
                    <div>
                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Signed By</div>
                        <div class="text-sm font-bold text-gray-800"><?= e($delivery['signoff_name']) ?></div>
                    </div>
                    <div class="col-span-2">
                        <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest">Date & Time</div>
                        <div class="text-sm text-gray-800"><?= $delivery['signoff_timestamp'] ? fmt_date($delivery['signoff_timestamp'], 'd M Y \a\t H:i') : '—' ?></div>
                    </div>
                </div>

                <?php if ($delivery['signoff_signature_path']): ?>
                <div>
                    <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-2">Signature</div>
                    <div class="bg-gray-50 rounded-xl border border-gray-200 p-4 text-center">
                        <img src="<?= APP_URL . '/' . e($delivery['signoff_signature_path']) ?>" alt="Signature" class="max-h-24 mx-auto">
                    </div>
                </div>
                <?php endif; ?>

                <!-- Items summary -->
                <div>
                    <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-2">Items Received</div>
                    <div class="bg-gray-50 rounded-xl p-3 space-y-1.5">
                        <?php foreach ($items as $item): ?>
                        <div class="flex justify-between text-xs">
                            <span class="text-gray-700"><?= e($item['product_name'] ?: $item['description']) ?></span>
                            <span class="font-bold text-gray-500">&times;<?= number_format((float)$item['quantity'], $item['quantity'] == (int)$item['quantity'] ? 0 : 2) ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php else: ?>
        <!-- ═══ SIGN-OFF FORM ═══ -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden" id="signoff-form">

            <!-- Delivery Summary -->
            <div class="bg-brand-primary text-white px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-[10px] font-bold uppercase tracking-widest opacity-60">Delivery Note</div>
                        <div class="text-lg font-extrabold"><?= e($delivery['doc_number']) ?></div>
                    </div>
                    <div class="text-right">
                        <div class="text-[10px] font-bold uppercase tracking-widest opacity-60">To</div>
                        <div class="text-sm font-bold"><?= e($delivery['recipient_name']) ?></div>
                    </div>
                </div>
            </div>

            <!-- Items List -->
            <div class="px-6 py-4 border-b border-gray-100">
                <div class="text-[9px] font-bold text-gray-400 uppercase tracking-widest mb-3">Items to Receive (<?= count($items) ?>)</div>
                <div class="space-y-2">
                    <?php foreach ($items as $item): ?>
                    <div class="flex items-center justify-between py-1.5">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold text-gray-800 truncate"><?= e($item['product_name'] ?: $item['description']) ?></div>
                            <?php if ($item['notes']): ?>
                                <div class="text-[10px] text-gray-400 truncate"><?= e($item['notes']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="text-xs font-bold text-gray-600 ml-3 shrink-0">
                            <?= number_format((float)$item['quantity'], $item['quantity'] == (int)$item['quantity'] ? 0 : 2) ?>
                            <?= $item['unit'] ? e($item['unit']) : '' ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($delivery['special_instructions']): ?>
            <div class="px-6 py-3 bg-amber-50 border-b border-amber-100">
                <div class="text-[9px] font-bold text-amber-600 uppercase tracking-widest mb-1">Special Instructions</div>
                <div class="text-xs text-amber-800 leading-relaxed"><?= nl2br(e($delivery['special_instructions'])) ?></div>
            </div>
            <?php endif; ?>

            <!-- Signature Section -->
            <div class="px-6 py-5" x-data="signoffApp()">

                <div class="text-center mb-4">
                    <h2 class="text-sm font-extrabold text-gray-800">Confirm Receipt</h2>
                    <p class="text-[10px] text-gray-400 mt-1">Please type your name and sign below to confirm you received these items.</p>
                </div>

                <!-- Name Input -->
                <div class="mb-4">
                    <label class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Your Full Name *</label>
                    <input type="text" x-model="signoffName" required placeholder="Type your full name"
                           class="w-full px-4 py-3 text-sm font-medium bg-gray-50 border border-gray-200 rounded-xl focus:outline-none focus:border-blue-400 text-gray-800 placeholder-gray-300">
                </div>

                <!-- Signature Pad -->
                <div class="mb-4">
                    <div class="flex items-center justify-between mb-1">
                        <label class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">Your Signature *</label>
                        <button type="button" @click="clearSignature()" class="text-[10px] font-bold text-red-400 uppercase tracking-widest hover:text-red-600">Clear</button>
                    </div>
                    <div class="bg-gray-50 border-2 border-dashed border-gray-200 rounded-xl overflow-hidden relative" :class="hasSigned ? 'border-green-300 bg-green-50/30' : ''">
                        <canvas id="signature-pad" width="400" height="180"
                                class="w-full" style="height: 180px;"
                                @mousedown="startDrawing($event)"
                                @mousemove="draw($event)"
                                @mouseup="stopDrawing()"
                                @mouseleave="stopDrawing()"
                                @touchstart.prevent="startDrawing($event)"
                                @touchmove.prevent="draw($event)"
                                @touchend="stopDrawing()">
                        </canvas>
                        <div x-show="!hasSigned" class="absolute inset-0 flex items-center justify-center pointer-events-none">
                            <span class="text-xs text-gray-300 font-medium">Draw your signature here</span>
                        </div>
                    </div>
                </div>

                <!-- Error -->
                <div x-show="errorMsg" x-cloak class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 mb-4 text-xs text-red-600 font-medium" x-text="errorMsg"></div>

                <!-- Success -->
                <div x-show="success" x-cloak class="bg-green-50 border border-green-200 rounded-xl px-4 py-5 mb-4 text-center">
                    <svg class="w-10 h-10 text-green-500 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <p class="text-sm font-bold text-green-700">Receipt Confirmed!</p>
                    <p class="text-[10px] text-green-500 mt-1">Thank you. The sender has been notified.</p>
                </div>

                <!-- Submit Button -->
                <button type="button" @click="submitSignoff()" x-show="!success"
                        :disabled="submitting"
                        class="w-full py-4 rounded-xl text-sm font-extrabold uppercase tracking-widest text-white shadow-lg transition-all disabled:opacity-50"
                        :class="submitting ? 'bg-gray-400' : 'bg-brand-primary hover:opacity-90 active:scale-[0.98]'">
                    <span x-show="!submitting">Confirm Receipt</span>
                    <span x-show="submitting" x-cloak class="flex items-center justify-center gap-2">
                        <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                        Confirming...
                    </span>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div class="text-center mt-6">
            <p class="text-[10px] text-gray-400">Powered by <strong>Nfelia</strong></p>
        </div>
    </div>

    <script>
    function signoffApp() {
        return {
            signoffName: '',
            hasSigned: false,
            submitting: false,
            success: false,
            errorMsg: '',

            // Canvas state
            canvas: null,
            ctx: null,
            drawing: false,

            init() {
                this.canvas = document.getElementById('signature-pad');
                this.ctx = this.canvas.getContext('2d');
                this.setupCanvas();
            },

            setupCanvas() {
                // Scale for retina displays
                const rect = this.canvas.getBoundingClientRect();
                const dpr = window.devicePixelRatio || 1;
                this.canvas.width = rect.width * dpr;
                this.canvas.height = rect.height * dpr;
                this.ctx.scale(dpr, dpr);
                this.ctx.lineWidth = 2.5;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ctx.strokeStyle = '#1a1a2e';
            },

            getCoords(e) {
                const rect = this.canvas.getBoundingClientRect();
                if (e.touches && e.touches.length > 0) {
                    return { x: e.touches[0].clientX - rect.left, y: e.touches[0].clientY - rect.top };
                }
                return { x: e.clientX - rect.left, y: e.clientY - rect.top };
            },

            startDrawing(e) {
                this.drawing = true;
                this.hasSigned = true;
                const coords = this.getCoords(e);
                this.ctx.beginPath();
                this.ctx.moveTo(coords.x, coords.y);
            },

            draw(e) {
                if (!this.drawing) return;
                const coords = this.getCoords(e);
                this.ctx.lineTo(coords.x, coords.y);
                this.ctx.stroke();
            },

            stopDrawing() {
                this.drawing = false;
            },

            clearSignature() {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.hasSigned = false;
                this.errorMsg = '';
            },

            async submitSignoff() {
                this.errorMsg = '';

                if (!this.signoffName.trim()) {
                    this.errorMsg = 'Please enter your name.';
                    return;
                }
                if (!this.hasSigned) {
                    this.errorMsg = 'Please draw your signature.';
                    return;
                }

                this.submitting = true;

                try {
                    // Get signature as base64 PNG
                    const signatureData = this.canvas.toDataURL('image/png');

                    const formData = new FormData();
                    formData.append('signoff_name', this.signoffName.trim());
                    formData.append('signature_data', signatureData);

                    const res = await fetch('<?= APP_URL ?>/sign/<?= e($delivery['signoff_token']) ?>', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await res.json();

                    if (data.success) {
                        this.success = true;
                    } else {
                        this.errorMsg = data.error || 'Something went wrong. Please try again.';
                    }
                } catch (err) {
                    this.errorMsg = 'Network error. Please check your connection and try again.';
                } finally {
                    this.submitting = false;
                }
            }
        };
    }
    </script>
</body>
</html>