<?php
/**
 * Nfelia — Landing Page Settings View
 * Renders inside views/layouts/app.php
 */
$currency = $business['currency_symbol'] ?? '₦';

// Detect existing hero type from path extension
$hero_path = $business['hero_image_path'] ?? null;
$hero_url  = $hero_path ? APP_URL . '/' . $hero_path : null;

$video_exts    = ['.mp4', '.mov', '.webm', '.avi', '.wmv'];
$hero_is_video = false;

if ($hero_path) {
    $ext = strtolower(substr($hero_path, strrpos($hero_path, '.')));
    $hero_is_video = in_array($ext, $video_exts);
}
?>

<!-- Page Header -->
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
    <div>
        <h1 class="font-heading font-extrabold text-2xl sm:text-3xl text-brand-plum tracking-tight">Landing Page</h1>
        <p class="text-xs text-brand-plum/50 font-medium mt-1">Manage your public business profile that customers see</p>
    </div>
    <?php if ($public_url && $business['landing_enabled']): ?>
        <div class="flex items-center gap-2">
            <a href="<?= e($public_url) ?>" target="_blank" class="flex items-center gap-2 btn-primary text-white px-5 py-2.5 rounded-xl shadow-sm hover:shadow-md transition-all">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                <span class="text-[10px] font-bold uppercase tracking-widest">View Live Page</span>
            </a>
        </div>
    <?php endif; ?>
</div>

<!-- Share URL Bar -->
<?php if ($public_url && $business['landing_enabled']): ?>
<div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 shadow-sm" x-data="{ copied: false }">
    <div class="flex items-center gap-2 mb-2">
        <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
        <span class="text-[10px] font-bold text-green-700 uppercase tracking-widest">Your page is live</span>
    </div>
    <div class="flex gap-2">
        <input type="text" value="<?= e($public_url) ?>" readonly
               class="flex-1 px-3 py-2.5 text-xs font-medium bg-white border border-green-200 rounded-lg text-brand-plum truncate">
        <button type="button" @click="navigator.clipboard.writeText('<?= e($public_url) ?>'); copied = true; setTimeout(() => copied = false, 2000)"
                class="px-4 py-2.5 text-[10px] font-bold uppercase tracking-widest border rounded-lg transition-colors"
                :class="copied ? 'bg-green-500 text-white border-green-500' : 'bg-white text-brand-plum/60 border-green-200 hover:border-green-400'">
            <span x-show="!copied">Copy Link</span>
            <span x-show="copied" x-cloak>Copied!</span>
        </button>
    </div>
    <p class="text-[10px] text-green-600 mt-2">Share this link on WhatsApp, Instagram bio, Facebook, or anywhere.</p>
</div>
<?php endif; ?>

<!-- ═══ HERO BACKGROUND UPLOAD (AJAX — separate from main form) ═══ -->
<div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm mb-6"
     x-data="heroUploader()"
     x-init="init()">

    <h2 class="font-heading font-bold text-sm text-brand-plum mb-1 uppercase tracking-wider">Hero Background</h2>
    <p class="text-[10px] text-brand-plum/40 mb-4">
        Upload a photo, GIF, or video — it sits behind your gradient for a premium look.
        GIFs and videos are automatically compressed to MP4.
    </p>

    <!-- Current preview -->
    <template x-if="currentUrl">
        <div class="mb-4 rounded-xl overflow-hidden border border-brand-sand relative" style="max-height:220px;">

            <!-- Image preview -->
            <template x-if="currentType === 'image'">
                <img :src="currentUrl" class="w-full object-cover" style="max-height:220px;">
            </template>

            <!-- Video preview -->
            <template x-if="currentType === 'video'">
                <video :src="currentUrl" autoplay loop muted playsinline class="w-full object-cover" style="max-height:220px;"></video>
            </template>

            <!-- Gradient overlay preview -->
            <div class="absolute inset-0 flex items-end p-4"
                 style="background: linear-gradient(135deg, <?= e($business['primary_color'] ?? '#8B004A') ?>CC 0%, <?= e($business['primary_color'] ?? '#8B004A') ?>88 50%, <?= e($business['secondary_color'] ?? '#B69F5E') ?>AA 100%);">
                <span class="font-heading font-extrabold text-white text-sm drop-shadow">
                    Preview — your gradient blends over this
                </span>
            </div>

            <!-- Remove button -->
            <button type="button" @click="removeHero()"
                    class="absolute top-2 right-2 w-7 h-7 rounded-full bg-black/50 hover:bg-red-500 flex items-center justify-center transition-colors">
                <svg class="w-3.5 h-3.5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
    </template>

    <!-- Upload zone -->
    <div class="relative">
        <label class="flex flex-col items-center justify-center gap-2 p-6 border-2 border-dashed border-brand-sand rounded-xl cursor-pointer hover:border-brand-gold transition-colors"
               :class="dragging ? 'border-brand-gold bg-brand-gold/5' : ''"
               @dragover.prevent="dragging = true"
               @dragleave="dragging = false"
               @drop.prevent="onDrop($event)">

            <!-- Idle state -->
            <template x-if="!uploading">
                <div class="text-center pointer-events-none">
                    <svg class="w-8 h-8 text-brand-plum/20 mx-auto mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                    <p class="text-xs font-bold text-brand-plum/50">
                        <span x-text="currentUrl ? 'Replace background' : 'Upload background'"></span>
                    </p>
                    <p class="text-[10px] text-brand-plum/30 mt-1">JPG · PNG · WebP · GIF · MP4 · MOV · WebM — max 50MB</p>
                </div>
            </template>

            <!-- Processing state -->
            <template x-if="uploading">
                <div class="text-center pointer-events-none">
                    <div class="w-10 h-10 mx-auto mb-3 relative">
                        <svg class="w-10 h-10 text-brand-sand animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"/>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/>
                        </svg>
                    </div>
                    <p class="text-xs font-bold text-brand-plum" x-text="statusText"></p>
                    <p class="text-[10px] text-brand-plum/40 mt-1">This can take a few seconds for GIFs and videos</p>
                    <!-- Progress bar -->
                    <div class="mt-3 w-48 mx-auto h-1 bg-brand-sand rounded-full overflow-hidden">
                        <div class="h-full bg-brand-gold rounded-full transition-all duration-500"
                             :style="'width: ' + progress + '%'"></div>
                    </div>
                </div>
            </template>

            <input type="file" class="hidden"
                   accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm"
                   @change="onFileSelect($event)">
        </label>
    </div>

    <!-- Error message -->
    <template x-if="errorMsg">
        <p class="mt-2 text-xs text-red-500 font-medium" x-text="errorMsg"></p>
    </template>
</div>

<!-- ═══ MAIN SETTINGS FORM ═══ -->
<form method="POST" action="<?= APP_URL ?>/landing-page" class="space-y-6">
    <?= csrf_field() ?>

    <!-- Toggle + Slug -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider">Page Status</h2>
                <p class="text-[10px] text-brand-plum/40 mt-0.5">Turn your public page on or off</p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer">
                <input type="checkbox" name="landing_enabled" value="1" <?= $business['landing_enabled'] ? 'checked' : '' ?> class="sr-only peer">
                <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-brand-gold/30 rounded-full peer peer-checked:after:translate-x-full after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-green-500"></div>
            </label>
        </div>
        <?php if ($business['slug']): ?>
        <div class="text-[10px] text-brand-plum/40">
            Your page URL: <strong class="text-brand-plum"><?= APP_URL ?>/biz/<?= e($business['slug']) ?></strong>
        </div>
        <?php endif; ?>
    </div>

    <!-- About -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-1 uppercase tracking-wider">About Your Business</h2>
        <p class="text-[10px] text-brand-plum/40 mb-3">Tell visitors what you do. Keep it short and clear.</p>
        <textarea name="about_text" rows="4"
                  class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30 resize-none"
                  placeholder="e.g. We sell quality auto parts and accessories at the best prices in Lagos. Walk-in or order online for fast delivery."><?= e($business['about_text'] ?? '') ?></textarea>
    </div>

    <!-- YouTube Video -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-1 uppercase tracking-wider">YouTube Video (Optional)</h2>
        <p class="text-[10px] text-brand-plum/40 mb-3">Paste a YouTube link and it'll embed on your page. Great for product demos or business intros.</p>
        <input type="url" name="youtube_url" value="<?= e($business['youtube_url'] ?? '') ?>"
               class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
               placeholder="e.g. https://www.youtube.com/watch?v=dQw4w9WgXcQ">
    </div>

    <!-- Social Links -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-1 uppercase tracking-wider">Social Media Links</h2>
        <p class="text-[10px] text-brand-plum/40 mb-4">Just your handle/username — no full URLs needed.</p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Instagram</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-brand-plum/30">@</span>
                    <input type="text" name="social_instagram" value="<?= e($business['social_instagram'] ?? '') ?>"
                           class="w-full pl-7 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                           placeholder="yourpage">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">Facebook</label>
                <input type="text" name="social_facebook" value="<?= e($business['social_facebook'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                       placeholder="yourpage">
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">TikTok</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-brand-plum/30">@</span>
                    <input type="text" name="social_tiktok" value="<?= e($business['social_tiktok'] ?? '') ?>"
                           class="w-full pl-7 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                           placeholder="yourpage">
                </div>
            </div>
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-1">X (Twitter)</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] text-brand-plum/30">@</span>
                    <input type="text" name="social_twitter" value="<?= e($business['social_twitter'] ?? '') ?>"
                           class="w-full pl-7 pr-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum"
                           placeholder="yourhandle">
                </div>
            </div>
        </div>
    </div>

    <!-- Storefront Settings -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-1 uppercase tracking-wider">Storefront Settings</h2>
        <p class="text-[10px] text-brand-plum/40 mb-4">Configure checkout and quote request options for your landing page.</p>

        <div class="space-y-4">
            <!-- Delivery info -->
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest mb-1">Delivery Info (shown at checkout)</label>
                <input type="text" name="delivery_info" value="<?= e($business['delivery_info'] ?? '') ?>"
                       class="w-full px-3 py-2.5 text-xs font-medium bg-brand-cream border border-brand-sand rounded-lg focus:outline-none focus:border-brand-gold text-brand-plum placeholder-brand-plum/30"
                       placeholder="e.g. Delivery at customer expense — pickup at 123 Main St">
                <p class="text-[9px] text-brand-plum/30 mt-1">A one-liner shown to customers at checkout. Leave blank to hide.</p>
            </div>

            <!-- RFQ fields toggles -->
            <div>
                <label class="block text-[10px] font-bold text-brand-plum/50 uppercase tracking-widest mb-2">Quote Request Fields</label>
                <p class="text-[9px] text-brand-plum/30 mb-3">Choose which optional fields appear on the "Request a Quote" form. Name, phone, email, and message are always shown.</p>
                <?php
                    $rfq_current = json_decode($business['rfq_fields'] ?? '{}', true) ?: [];
                    $rfq_options = [
                        'quantity_range'    => 'Quantity / Volume Range',
                        'budget'            => 'Budget Range',
                        'timeline'          => 'Timeline / Delivery Date',
                        'delivery_location' => 'Delivery Location',
                        'company_name'      => 'Company / Organization Name',
                    ];
                ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                    <?php foreach ($rfq_options as $key => $label): ?>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-brand-sand/50 hover:bg-brand-cream/30 cursor-pointer transition-colors has-[:checked]:bg-brand-gold/5 has-[:checked]:border-brand-gold/30">
                        <input type="checkbox" name="rfq_<?= $key ?>" value="1"
                               <?= !empty($rfq_current[$key]) ? 'checked' : '' ?>
                               class="w-4 h-4 text-brand-gold border-brand-sand rounded focus:ring-brand-gold/30">
                        <span class="text-xs font-medium text-brand-plum"><?= $label ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Featured Products -->
    <div class="bg-brand-white border border-brand-sand rounded-xl p-5 shadow-sm">
        <h2 class="font-heading font-bold text-sm text-brand-plum mb-1 uppercase tracking-wider">Featured Products</h2>
        <p class="text-[10px] text-brand-plum/40 mb-4">Pick which products and services appear on your landing page. Leave all unchecked to show everything.</p>

        <?php if (empty($all_products)): ?>
            <p class="text-xs text-brand-plum/40">No products or services added yet. <a href="<?= APP_URL ?>/products/create" class="text-brand-gold font-bold">Add your first product</a></p>
        <?php else: ?>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 max-h-80 overflow-y-auto pr-2">
                <?php foreach ($all_products as $p): ?>
                    <label class="flex items-center gap-3 p-3 rounded-lg border border-brand-sand/50 hover:bg-brand-cream/30 cursor-pointer transition-colors has-[:checked]:bg-brand-gold/5 has-[:checked]:border-brand-gold/30">
                        <input type="checkbox" name="featured_products[]" value="<?= $p['id'] ?>"
                               <?= in_array($p['id'], $featured_ids) ? 'checked' : '' ?>
                               class="w-4 h-4 text-brand-gold border-brand-sand rounded focus:ring-brand-gold/30">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold text-brand-plum truncate"><?= e($p['name']) ?></div>
                            <div class="text-[10px] text-brand-plum/40">
                                <?= $currency ?><?= number_format((float)$p['sell_price'], 0) ?>
                                <?php if ($p['is_service']): ?> &middot; Service<?php endif; ?>
                                <?php if ($p['category']): ?> &middot; <?= e($p['category']) ?><?php endif; ?>
                            </div>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>
            <p class="text-[9px] text-brand-plum/30 mt-2"><?= count($all_products) ?> items available &middot; <?= count($featured_ids) ?> currently featured</p>
        <?php endif; ?>
    </div>

    <!-- Save -->
    <div class="flex items-center justify-between">
        <a href="<?= APP_URL ?>/dashboard" class="text-[10px] font-bold text-brand-plum/40 uppercase tracking-widest hover:text-brand-burgundy">Back to Dashboard</a>
        <button type="submit" class="btn-primary text-white px-8 py-3 rounded-xl text-[10px] font-bold uppercase tracking-widest shadow-sm hover:shadow-md transition-all">
            Save Changes
        </button>
    </div>
</form>
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.3/dist/cdn.min.js" defer></script>
<script src="https://unpkg.com/@ffmpeg/ffmpeg@0.11.6/dist/ffmpeg.min.js"></script>
<script>
function heroUploader() {
    return {
        uploading:   false,
        dragging:    false,
        progress:    0,
        statusText:  'Uploading...',
        errorMsg:    '',
        currentUrl:  <?= json_encode($hero_url) ?>,
        currentType: <?= json_encode($hero_is_video ? 'video' : ($hero_url ? 'image' : null)) ?>,
        csrf:        <?= json_encode(csrf_token()) ?>,
        uploadUrl:   <?= json_encode(APP_URL . '/landing-page/upload-hero') ?>,
        removeUrl:   <?= json_encode(APP_URL . '/landing-page/remove-hero') ?>,
        ticker:      null,

        init() {},

        onFileSelect(e) {
            const file = e.target.files[0];
            if (file) this.upload(file);
        },

        onDrop(e) {
            this.dragging = false;
            const file = e.dataTransfer.files[0];
            if (file) this.upload(file);
        },

        async upload(file) {
            this.errorMsg  = '';
            this.uploading = true;
            this.progress  = 0;

            const isMotion = file.type === 'image/gif' || file.type.startsWith('video/');
            const isLarge  = file.size > 3 * 1024 * 1024; // Check if > 3MB

            let finalFile = file;

            // If it's a motion file larger than 3MB, compress it first
            if (isMotion && isLarge) {
                this.statusText = 'Compressing video in browser... Please do not close the page.';
                try {
                    finalFile = await this.compressVideo(file);
                } catch (err) {
                    this.uploading = false;
                    this.errorMsg = 'Browser compression failed. Try a smaller file.';
                    console.error(err);
                    return;
                }
            } else {
                // If it's an image or under 3MB, upload directly
                this.statusText = isMotion ? 'File under 3MB. Uploading directly...' : 'Uploading image...';
                this.progress = 10;
                this.ticker = setInterval(() => {
                    if (this.progress < 85) this.progress += 5;
                }, 400);
            }

            const fd = new FormData();
            // Send the final file (either the original, or the new compressed MP4)
            fd.append('hero_media', finalFile, finalFile.name || 'compressed.mp4');
            fd.append('csrf_token', this.csrf);

            try {
                const res  = await fetch(this.uploadUrl, { method: 'POST', body: fd });
                const data = await res.json();

                if (this.ticker) clearInterval(this.ticker);

                if (data.success) {
                    this.progress = 100;
                    this.statusText = 'Done!';
                    setTimeout(() => {
                        this.uploading   = false;
                        this.progress    = 0;
                        this.currentUrl  = data.url;
                        this.currentType = data.type;
                    }, 500);
                } else {
                    this.uploading = false;
                    this.errorMsg  = data.error || 'Upload failed.';
                    this.progress  = 0;
                }
            } catch (err) {
                if (this.ticker) clearInterval(this.ticker);
                this.uploading = false;
                this.errorMsg  = 'Network error. Please check your connection.';
                this.progress  = 0;
            }
        },

        async compressVideo(file) {
            const { createFFmpeg, fetchFile } = FFmpeg;
            
            // Initialize FFmpeg once per session to save load time
            if (!window.ffmpegInstance) {
                window.ffmpegInstance = createFFmpeg({ 
                    log: true,
                    // Use standard single-thread core to ensure compatibility with shared hosting
                    corePath: 'https://unpkg.com/@ffmpeg/core@0.11.0/dist/ffmpeg-core.js',
                });
                await window.ffmpegInstance.load();
            }
            
            const ffmpeg = window.ffmpegInstance;

            // Hook up the frontend progress bar to FFmpeg's conversion ratio
            ffmpeg.setProgress(({ ratio }) => {
                this.progress = Math.round(ratio * 100);
            });

            // Clean the filename to prevent processing errors
            const inputName = 'input_' + file.name.replace(/[^a-zA-Z0-9.]/g, '');
            const outputName = 'output.mp4';

            // Write the uploaded file into FFmpeg's virtual memory
            ffmpeg.FS('writeFile', inputName, await fetchFile(file));

            // Run the exact compression command you had in PHP
            await ffmpeg.run('-i', inputName, '-vcodec', 'libx264', '-crf', '28', '-preset', 'fast', '-an', outputName);

            // Read the compressed file back out of memory
            const data = ffmpeg.FS('readFile', outputName);

            // Clean up the browser memory
            ffmpeg.FS('unlink', inputName);
            ffmpeg.FS('unlink', outputName);

            // Return the tiny, web-ready MP4 File
            return new File([data.buffer], 'compressed.mp4', { type: 'video/mp4' });
        },

        async removeHero() {
            if (!confirm('Remove the hero background?')) return;
            const fd = new FormData();
            fd.append('csrf_token', this.csrf);
            await fetch(this.removeUrl, { method: 'POST', body: fd });
            this.currentUrl  = null;
            this.currentType = null;
        }
    };
}
</script>