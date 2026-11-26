<?php
/**
 * The photo screen (app/helpers/product_ai.php), for a business with AI on:
 * the first thing Add item shows, and what "Fill from a photo" in the form
 * opens. They choose English or French, then take or pick a photo; it goes
 * at once, and its name, description and category land in the form below,
 * with the photo in Photos. Nothing to approve: the form is theirs to change.
 *
 * The phone shrinks the photo to 1024 px first, so it goes quickly and the
 * same small photo is the one saved with the item. While Gemini reads it the
 * screen says what is happening; a problem says what to do next.
 *
 * How many photos are left this week shows in green from 70% of the week's,
 * yellow from 40%, red under that. With none left the screen says when more
 * come and offers to type it in.
 *
 * Renders inside views/products/form.php:
 *   $ai        product_ai_state()
 *   $ai_first  open it first (a new item, not one sent back with a problem)
 *   $editing, $product_images   an item being edited can use a photo it has
 */
$own = array_slice(array_map(fn($i) => ['id' => (int)$i['id'], 'src' => APP_URL . '/' . $i['image_path']],
                             $editing ? ($product_images ?? []) : []), 0, 4);
$cfg = ['first' => !empty($ai_first), 'state' => $ai, 'own' => $own];
?>
<style>
.nf-ai-screen { gap: 14px; }
.nf-ai-screen > header { display: grid; gap: 4px; }
.nf-ai-screen h2 { display: flex; align-items: center; gap: 8px; margin: 0; font: 600 1.25rem/1.25 var(--font-heading); outline: none; }
.nf-ai-screen h2 svg { width: 22px; height: 22px; color: var(--accent-text); flex: none; }
.nf-ai-screen header p { margin: 0; font: 400 .9375rem/1.45 var(--font-body); color: var(--ink-2); }
.nf-ai-q { margin: 0 0 6px; font: 600 .875rem/1.3 var(--font-body); color: var(--ink-2); }
.nf-ai-pick { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
.nf-ai-pick .nf-file { min-height: 88px; flex-direction: column; justify-content: center; gap: 6px; padding: 12px 8px; text-align: center;
  font: 600 .9375rem/1.25 var(--font-body); color: var(--accent-text); }
.nf-ai-pick .nf-file > svg { width: 28px; height: 28px; }
.nf-ai-pick.is-off { opacity: .5; pointer-events: none; }
/* The form greys out disabled boxes (.nf-iform input:disabled); a file box stays unseen under its button */
.nf-ai .nf-ai-pick .nf-file input[type="file"] { opacity: 0; }
.nf-ai-own { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
.nf-ai-own button { width: 56px; height: 56px; padding: 0; border: 0; border-radius: 12px; overflow: hidden; cursor: pointer; background: var(--bg);
  box-shadow: inset 0 0 0 1px var(--line); }
.nf-ai-own button:focus-visible { outline: none; box-shadow: var(--focus-ring); }
.nf-ai-own img { display: block; width: 100%; height: 100%; object-fit: cover; }
/* The photo being read, with what is happening over it */
.nf-ai-photo { position: relative; display: grid; place-items: center; min-height: 220px; max-height: 340px; border-radius: 16px; overflow: hidden; background: var(--bg);
  box-shadow: inset 0 0 0 1px var(--line); }
.nf-ai-photo img { display: block; width: 100%; height: 100%; max-height: 340px; object-fit: contain; }
.nf-ai-photo.is-reading img { opacity: .55; }
.nf-ai-status { position: absolute; left: 12px; right: 12px; bottom: 12px; display: flex; align-items: center; gap: 10px; padding: 10px 12px; border-radius: 12px;
  background: rgb(255 255 255 / .94); box-shadow: 0 1px 3px rgb(31 10 20 / .16); font: 600 .9375rem/1.3 var(--font-body); color: var(--ink); }
.nf-ai-spin { width: 18px; height: 18px; flex: none; border-radius: 50%; border: 2.5px solid var(--accent-soft); border-top-color: var(--accent); animation: nf-ai-spin .8s linear infinite; }
@keyframes nf-ai-spin { to { transform: rotate(360deg); } }
.nf-ai-error { margin: 0; padding: 12px 14px; border-radius: 14px; background: var(--owed-soft); color: var(--ink); font: 500 .9375rem/1.45 var(--font-body); }
.nf-ai-do { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
.nf-ai-do .nf-btn { flex: none; }
/* Photos left this week: green, yellow, red */
.nf-ai-left { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 10px; margin: 0; }
.nf-ai-left b { display: inline-flex; align-items: center; gap: 8px; min-height: 30px; padding: 0 12px; border-radius: 999px; font: 600 .875rem/1 var(--font-body); font-variant-numeric: tabular-nums; }
.nf-ai-left b::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: currentColor; }
.nf-ai-left.is-good b { background: var(--in-soft); color: var(--in); }
.nf-ai-left.is-mid b { background: var(--warn-soft); color: var(--warn); }
.nf-ai-left.is-low b { background: var(--owed-soft); color: var(--owed); }
.nf-ai-left small { font: 400 .8125rem/1.3 var(--font-body); color: var(--ink-2); }
.nf-ai-none { display: grid; gap: 10px; justify-items: start; padding: 12px 14px; border-radius: 14px; background: var(--owed-soft); }
.nf-ai-none p { margin: 0; font: 500 .9375rem/1.45 var(--font-body); }
.nf-ai-none .nf-btn { flex: none; }
/* After: one line on top of the form, and the boxes it filled lit for a moment */
.nf-ai-done { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 10px; padding: 12px 14px; border-radius: 14px; background: var(--in-soft);
  font: 500 .9375rem/1.45 var(--font-body); color: var(--ink); outline: none; }
.nf-ai-done > svg { width: 20px; height: 20px; flex: none; margin-top: 1px; color: var(--in); }
.nf-ai-done .nf-link { min-height: 0; padding: 0; margin-left: 4px; }
.nf-ai-lit { animation: nf-ai-lit 2.4s ease-out; }
@keyframes nf-ai-lit { from { background-color: var(--accent-soft); } to { background-color: var(--surface); } }
.nf-ai-fill { display: flex; align-items: center; gap: 12px; width: 100%; min-height: 60px; padding: 10px 14px; border: 0; border-radius: 14px; cursor: pointer;
  background: var(--accent-soft); color: var(--ink); text-align: left; font: inherit; }
.nf-ai-fill > svg { width: 22px; height: 22px; flex: none; color: var(--accent-text); }
.nf-ai-fill > span { display: grid; gap: 2px; }
.nf-ai-fill b { font: 600 .9375rem/1.3 var(--font-body); color: var(--accent-text); }
.nf-ai-fill small { font: 400 .8125rem/1.35 var(--font-body); color: var(--ink-2); }
.nf-ai-fill:focus-visible { outline: none; box-shadow: var(--focus-ring); }
@media (prefers-reduced-motion: reduce) { .nf-ai-spin { animation-duration: 2.4s; } .nf-ai-lit { animation: none; } }
</style>
<?php /* Without scripts there is no photo screen: the form shows as it always did */ ?>
<noscript><style>#catalogue-form[hidden] { display: grid !important; } .nf-ai { display: none; }</style></noscript>

<script>
/* The photo, at most 1024 px, as a plain JPEG: quick to send, and no
   location or camera details. Null when this browser can't open it. */
window.nfAiShrink = window.nfAiShrink || async function (file) {
    var src = null;
    if (window.createImageBitmap) {
        try { src = await createImageBitmap(file, { imageOrientation: 'from-image' }); } catch (e) { src = null; }
    }
    if (!src) {
        try {
            src = await new Promise(function (ok, no) {
                var img = new Image();
                img.onload = function () { ok(img); };
                img.onerror = no;
                img.src = URL.createObjectURL(file);
            });
        } catch (e) { return null; }
    }
    var w = src.naturalWidth || src.width, h = src.naturalHeight || src.height;
    if (!w || !h) return null;
    var scale = Math.min(1, 1024 / Math.max(w, h));
    var c = document.createElement('canvas');
    c.width = Math.max(1, Math.round(w * scale));
    c.height = Math.max(1, Math.round(h * scale));
    var ctx = c.getContext('2d');
    ctx.fillStyle = '#FFFFFF';
    ctx.fillRect(0, 0, c.width, c.height);
    ctx.drawImage(src, 0, 0, c.width, c.height);
    var blob = await new Promise(function (ok) { c.toBlob(ok, 'image/jpeg', 0.86); });
    return blob ? new File([blob], 'nfelia-ai-photo.jpg', { type: 'image/jpeg' }) : null;
};

window.nfAiPhoto = window.nfAiPhoto || function (cfg) {
    var AI_NAME = 'nfelia-ai-photo.jpg';
    return {
        open: cfg.first, step: 'pick', lang: 'en', state: cfg.state, own: cfg.own,
        file: null, imageId: 0, preview: '', error: '', words: '', timers: [],
        done: null, before: null,

        init: function () {
            try { var l = localStorage.getItem('nf-ai-lang'); if (l === 'en' || l === 'fr') this.lang = l; } catch (e) {}
            this.$watch('lang', function (v) { try { localStorage.setItem('nf-ai-lang', v); } catch (e) {} });
            this.$watch('open', function (v) { var f = document.getElementById('catalogue-form'); if (f) f.hidden = v; });
        },
        get noneLeft() { return this.state.left <= 0; },
        get leftText() { return this.state.left + ' of ' + this.state.limit + ' left this week'; },

        show: function () {
            this.open = true; this.step = 'pick'; this.error = '';
            window.scrollTo(0, 0);
            var self = this;
            this.$nextTick(function () { self.$refs.heading.focus({ preventScroll: true }); });
        },
        typeIt: function () {
            this.stopTimers();
            this.open = false; this.step = 'pick';
            window.scrollTo(0, 0);
        },
        picked: async function (ev) {
            var f = ev.target.files && ev.target.files[0];
            ev.target.value = '';
            if (!f) return;
            this.imageId = 0; this.error = ''; this.step = 'reading'; this.say('Getting your photo ready…');
            var photo = await window.nfAiShrink(f);
            if (!photo) {
                if (/^image\/(jpeg|png|webp)$/.test(f.type) && f.size < 10 * 1024 * 1024) {
                    photo = new File([f], AI_NAME, { type: f.type });
                } else {
                    this.preview = '';
                    this.fail('This photo can’t be opened here. Pick a JPG or PNG photo.');
                    return;
                }
            }
            this.file = photo;
            if (this.preview && this.preview.indexOf('blob:') === 0) URL.revokeObjectURL(this.preview);
            this.preview = URL.createObjectURL(photo);
            this.send();
        },
        useOwn: function (img) {
            this.file = null; this.imageId = img.id; this.preview = img.src;
            this.send();
        },
        say: function (words) { this.words = words; },
        stopTimers: function () { this.timers.forEach(clearTimeout); this.timers = []; },
        send: async function () {
            var self = this;
            this.step = 'reading'; this.error = '';
            this.stopTimers();
            this.say('Looking at your photo…');
            this.timers.push(setTimeout(function () { self.say('Writing its name and description…'); }, 2500));
            this.timers.push(setTimeout(function () { self.say('Still working. A few seconds more…'); }, 9000));

            var fd = new FormData();
            fd.append('csrf_token', window.Nfelia.csrf);
            fd.append('lang', this.lang);
            if (this.imageId) fd.append('image_id', this.imageId); else fd.append('photo', this.file, 'photo.jpg');
            var res = null, status = 0;
            try {
                var r = await fetch(window.Nfelia.url + '/products/ai-describe', {
                    method: 'POST', body: fd, credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                status = r.status;
                res = await r.json();
            } catch (e) { res = null; }
            this.stopTimers();
            if (!this.open) return;   // they chose to type it in meanwhile
            if (res && res.state) this.state = res.state;
            if (!res) {
                this.fail(status ? 'You were signed out. Reload the page, sign in, and try again.' : 'No connection. Check your internet and try again.');
                return;
            }
            if (!res.success) { this.fail(res.message); return; }
            this.fill(res);
        },
        fail: function (message) { this.step = 'error'; this.error = message; },
        again: function () { if (this.file || this.imageId) this.send(); else this.step = 'pick'; },
        another: function () { this.step = 'pick'; this.error = ''; this.preview = ''; this.file = null; this.imageId = 0; },

        fill: function (res) {
            var form = document.getElementById('catalogue-form');
            var before = {};
            ['name', 'description', 'category'].forEach(function (k) {
                var el = form.elements[k];
                if (!el) return;
                before[k] = el.value;
                el.value = res[k] || '';
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el.classList.remove('nf-ai-lit');
                void el.offsetWidth;
                el.classList.add('nf-ai-lit');
            });
            var added = this.file ? this.setPhoto(form, this.file) : false;
            this.before = before;
            this.done = { lang: this.lang === 'fr' ? 'French' : 'English', photo: added, lost: !!this.file && !added };
            this.open = false; this.step = 'pick';
            window.scrollTo(0, 0);
            var self = this;
            this.$nextTick(function () { if (self.$refs.done) self.$refs.done.focus({ preventScroll: true }); });
        },
        /* The photo goes first in Photos (the cover of a new item), before
           any others picked there; a photo from an earlier try is replaced. */
        setPhoto: function (form, file) {
            var input = form.querySelector('input[name="images[]"]');
            if (!input || typeof DataTransfer === 'undefined') return false;
            try {
                var dt = new DataTransfer();
                if (file) dt.items.add(new File([file], AI_NAME, { type: file.type || 'image/jpeg' }));
                Array.prototype.forEach.call(input.files, function (f) { if (f.name !== AI_NAME) dt.items.add(f); });
                input.files = dt.files;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                return true;
            } catch (e) { return false; }
        },
        undo: function () {
            var form = document.getElementById('catalogue-form');
            var before = this.before || {};
            Object.keys(before).forEach(function (k) {
                var el = form.elements[k];
                if (el) { el.value = before[k]; el.dispatchEvent(new Event('input', { bubbles: true })); }
            });
            if (this.done && this.done.photo) this.setPhoto(form, null);
            this.done = null; this.before = null;
        }
    };
};
</script>

<div class="nf-ai" x-data="nfAiPhoto(<?= e(json_encode($cfg, JSON_HEX_APOS | JSON_HEX_QUOT)) ?>)" @nf-ai-open.window="show()">
    <section class="nf-sec nf-ai-screen" x-show="open"<?= $cfg['first'] ? '' : ' x-cloak' ?> aria-labelledby="nf-ai-h">
        <header>
            <h2 id="nf-ai-h" x-ref="heading" tabindex="-1"><?= nav_icon('sparkle') ?><?= $editing ? 'Fill it in from a photo' : 'Add an item from a photo' ?></h2>
            <p>Take a photo of the item, or pick one. Nfelia writes its name, a description for your shop and a category, and puts them in the form for you.</p>
        </header>

        <div>
            <p class="nf-ai-q" id="nf-ai-lang">Write it in</p>
            <div class="nf-seg is-2" role="radiogroup" aria-labelledby="nf-ai-lang">
                <?php foreach (PRODUCT_AI_LANGS as $code => $label): ?>
                <label><input type="radio" name="nf-ai-lang" value="<?= e($code) ?>" x-model="lang" :disabled="step === 'reading'"><span class="opt"><?= e($label) ?></span></label>
                <?php endforeach; ?>
            </div>
        </div>

        <template x-if="step === 'pick'">
            <div style="display:grid;gap:12px">
                <div class="nf-ai-pick" :class="noneLeft && 'is-off'">
                    <label class="nf-file"><?= nav_icon('camera') ?>Take a photo
                        <input type="file" accept="image/*" capture="environment" @change="picked($event)" :disabled="noneLeft"></label>
                    <label class="nf-file"><?= nav_icon('image') ?>Choose a photo
                        <input type="file" accept="image/jpeg,image/png,image/webp,image/heic,image/heif" @change="picked($event)" :disabled="noneLeft"></label>
                </div>
                <template x-if="own.length && !noneLeft">
                    <div>
                        <p class="nf-ai-q">Or use a photo it has</p>
                        <div class="nf-ai-own">
                            <template x-for="img in own" :key="img.id">
                                <button type="button" @click="useOwn(img)" aria-label="Use this photo"><img :src="img.src" alt="" loading="lazy"></button>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <template x-if="step !== 'pick'">
            <div style="display:grid;gap:12px">
                <div class="nf-ai-photo" :class="step === 'reading' && 'is-reading'">
                    <template x-if="preview"><img :src="preview" alt="Your photo"></template>
                    <p class="nf-ai-status" x-show="step === 'reading'" role="status" aria-live="polite"><span class="nf-ai-spin" aria-hidden="true"></span><span x-text="words"></span></p>
                </div>
                <template x-if="step === 'error'">
                    <div style="display:grid;gap:10px">
                        <p class="nf-ai-error" role="alert" x-text="error"></p>
                        <div class="nf-ai-do">
                            <button type="button" class="nf-btn nf-btn-primary" x-show="(file || imageId) && !noneLeft" @click="again()">Try again</button>
                            <button type="button" class="nf-btn nf-btn-ghost" x-show="!noneLeft" @click="another()">Pick another photo</button>
                        </div>
                    </div>
                </template>
            </div>
        </template>

        <div class="nf-ai-none" x-show="noneLeft && step === 'pick'" x-cloak>
            <p>You’ve used all <span x-text="state.limit"></span> photos for this week. You get more on <?= e(product_ai_resets_text($ai)) ?>.</p>
            <?php if (!$editing): ?><button type="button" class="nf-btn nf-btn-primary" @click="typeIt()">Type it in</button><?php endif; ?>
        </div>
        <p class="nf-ai-left" :class="'is-' + state.level"><b x-text="leftText"><?= (int)$ai['left'] ?> of <?= (int)$ai['limit'] ?> left this week</b>
            <small>Starts again on <?= e(product_ai_resets_text($ai)) ?></small></p>

        <div class="nf-ai-do">
            <button type="button" class="nf-link" @click="typeIt()"><?= $editing ? 'Back to the form' : 'Skip, I’ll type it in' ?></button>
        </div>
    </section>

    <div class="nf-ai-done" x-show="!open && done" x-cloak x-ref="done" tabindex="-1" role="status">
        <?= nav_icon('check') ?>
        <span>Filled in from your photo, in <span x-text="done && done.lang"></span><span x-show="done && done.photo">, and the photo is in Photos</span>.
            <span x-show="done && done.lost">Add the photo again under Photos.</span>
            <?= $editing ? 'Check it, then save your changes.' : 'Add the price, check the rest, then save.' ?>
            <button type="button" class="nf-link" @click="undo()">Undo</button></span>
    </div>
</div>
