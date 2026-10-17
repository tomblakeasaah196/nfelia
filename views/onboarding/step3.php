<!-- Primary Color -->
<div>
    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Primary HEX Color</label>
    <div class="flex items-center gap-3">
        <input type="color" name="primary_color" id="primary_color_picker" value="#1A1A2E"
               class="h-12 w-12 cursor-pointer border-none p-0 bg-transparent"
               oninput="document.getElementById('primary_color_hex').value = this.value">
        <input type="text" id="primary_color_hex" value="#1A1A2E"
               class="w-full px-4 py-3 bg-brand-white border border-brand-sand font-mono text-sm uppercase rounded-none"
               oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('primary_color_picker').value = this.value"
               maxlength="7" placeholder="#1A1A2E">
    </div>
</div>

<!-- Secondary Color -->
<div>
    <label class="block text-xs font-bold text-brand-plum uppercase tracking-widest mb-2">Secondary HEX Color</label>
    <div class="flex items-center gap-3">
        <input type="color" name="secondary_color" id="secondary_color_picker" value="#C27A62"
               class="h-12 w-12 cursor-pointer border-none p-0 bg-transparent"
               oninput="document.getElementById('secondary_color_hex').value = this.value">
        <input type="text" id="secondary_color_hex" value="#C27A62"
               class="w-full px-4 py-3 bg-brand-white border border-brand-sand font-mono text-sm uppercase rounded-none"
               oninput="if(/^#[0-9A-Fa-f]{6}$/.test(this.value)) document.getElementById('secondary_color_picker').value = this.value"
               maxlength="7" placeholder="#C27A62">
    </div>
</div>