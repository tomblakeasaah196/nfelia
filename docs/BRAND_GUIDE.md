# NFELIA — BRAND GUIDE
**Version 1.0 | Extracted and Formalized 2026-06-03**

---

## 1. Brand Identity

**Brand Name:** Nfelia *(always capital N, lowercase the rest — never NFELIA or nfelia)*
**Tagline:** Track everything. Carry nothing.
**Brand Voice:** Military-corporate. Structured. Decisive. "Director," not "user." "Deploy," not "set up." "Workspace," not "account." The system speaks like a highly competent senior colleague — never cheerful, never casual, never apologetic.
**Author:** JBS Praxis

---

## 2. Color Palette

All colors defined as CSS variables in `views/layouts/app.php`. Reference them via class names — never hardcode hex in new components.

| Token | Hex | Usage |
|---|---|---|
| `brand-burgundy` | `#8B004A` | Primary CTA buttons, active nav, error accents |
| `brand-gold` | `#B69F5E` | Secondary accents, borders, hover states, pricing tags |
| `brand-plum` | `#1B0A11` | Body text, headings, dark backgrounds |
| `brand-sand` | `#E8E4DA` | Borders, dividers, subtle backgrounds |
| `brand-cream` | `#F2EFE7` | Page background, card backgrounds |
| `brand-white` | `#FFFFFF` | Card surfaces, input fields, modal backgrounds |

**CSS Variable usage (in PHP views):**
```css
background-color: var(--brand-primary);   /* set per-business from DB */
color: var(--brand-secondary);            /* set per-business from DB */
```

**Per-business branding:** Primary and secondary colors are stored per business in `businesses.primary_color` and `businesses.secondary_color`. The layout injects them as CSS variables. Default fallbacks: `#8B004A` / `#B69F5E`.

---

## 3. Typography

| Role | Font | Weight | Usage |
|---|---|---|---|
| Headings | Plus Jakarta Sans | 600, 700, 800 | `font-heading`, `h1`–`h3`, module titles |
| Body | Montserrat (login) / Per-business dynamic (app) | 400, 500, 600 | Body text, labels, descriptions |
| Data / Mono | Roboto Mono | 400, 600, 700 | `.data-text`, numbers, doc refs, amounts, codes |

**Dynamic font pairings** (configurable per business in Settings):
- `modern_sans` — Inter + DM Sans *(default)*
- `corporate_serif` — EB Garamond + Questrial
- `bold_display` — Syne + Outfit
- `classic_tech` — Roboto Mono + Roboto
- `elegant_serif` — Playfair Display + Lato

**Rule:** Never use `font-family: '"Plus Jakarta Sans"'` (with inner quotes). Always: `font-family: 'Plus Jakarta Sans', sans-serif`.

---

## 4. Component Patterns

### Buttons
```html
<!-- Primary CTA -->
<button class="btn-primary text-white px-6 py-3 text-xs font-bold uppercase tracking-widest rounded-xl">
    Action Label
</button>

<!-- Secondary / Ghost -->
<button class="bg-brand-cream text-brand-plum border border-brand-sand px-6 py-3 text-xs font-bold uppercase tracking-widest rounded-xl hover:border-brand-gold">
    Cancel
</button>

<!-- Danger -->
<button class="bg-red-50 text-red-600 border border-red-200 px-5 py-2.5 text-xs font-bold uppercase tracking-widest rounded-xl hover:bg-red-100">
    Delete
</button>
```

### Cards
```html
<div class="bg-white border border-brand-sand rounded-2xl p-6 shadow-sm">
    <h2 class="font-heading font-bold text-sm text-brand-plum uppercase tracking-wider mb-4">Card Title</h2>
    <!-- content -->
</div>
```

### Form Fields
```html
<div>
    <label class="block text-[10px] font-bold text-brand-plum/60 uppercase tracking-widest mb-2">Field Label</label>
    <input type="text" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-brand-gold outline-none">
</div>
```

### Flash / Toast Messages
The `flash()` helper in `app/helpers/functions.php` sets messages. They auto-display in `views/layouts/app.php`.
- `flash('success', '...')` — green
- `flash('error', '...')` — red
- `flash('warning', '...')` — amber

**Never use `$_SESSION['flash_error']` or `$_SESSION['flash_success']` directly.**

### Data Display (numbers, refs)
Always wrap monetary values and document references in `.data-text` class:
```html
<span class="data-text font-bold text-brand-plum">₦10,000</span>
<span class="data-text text-[10px] text-brand-plum/50">NFA-INV-001</span>
```

---

## 5. Copy & Voice Guidelines

| Context | Wrong | Right |
|---|---|---|
| Login button | "Login" | "Authenticate" |
| Register button | "Sign Up" | "Initialize Setup Wizard" / "Start Pilot" |
| Error generic | "Something went wrong" | "System error. Please try again or contact support." |
| Session expired | "Session expired" | "Workspace session closed. Please re-authenticate." |
| Subscription expired | "Your plan expired" | "Your trial period has ended. Subscribe to continue." |
| Staff removed | "User deleted" | "[Name] removed from your team." |
| Payment success | "Payment successful" | "Payment confirmed. Your subscription is now active." |
| Empty state | "No data" | "No [records] logged yet. Create your first one." |
| Loading | "Please wait..." | "Processing..." |

**Avoid:** casual language, exclamation marks in error states, passive voice, first-person ("I", "My").
**Use:** second-person ("Your workspace", "Your team"), technical-professional vocabulary.

---

## 6. Naming Conventions

| Term | Nfelia Equivalent |
|---|---|
| Account | Workspace |
| Users | Team / Staff / Director |
| Login page | The landing page / authentication portal |
| Customers | Clients |
| Products | Inventory / Stock |
| Signup | Deployment / Pilot initialization |
| Dashboard | Command Center |

---

## 7. Document Number Format

`{PREFIX}-{TYPE}-{NUMBER}`

Examples: `NFA-INV-001`, `TID-DEL-003`, `LPC-QUO-012`

- Prefix: derived from business name (3 chars, uppercase), set at registration
- Type: `QUO` (Quotation), `INV` (Invoice), `REC` (Receipt), `DEL` (Delivery)
- Number: zero-padded to 3 digits minimum, auto-incremented via `generate_doc_number()`

---

## 8. Email Template Standard

All emails sent via `send_mail()` in `app/helpers/mail.php`.

**Required structure:**
```
Subject: [Action/Event] — Nfelia
Body:
  Hello [Full Name],

  [Main message in plain, professional language.]

  [Supporting detail or next step.]

  [Link if applicable]

  The Nfelia Team
```

- Plain text only currently (no HTML email templates — future enhancement)
- No emojis in email body
- Always sign as "The Nfelia Team"
- Support email: support@nfelia.com

---

## 9. Favicon & Meta Standards

**Page title format:** `[Page Name] — [Business Name] | Nfelia`
*(Defined in `views/layouts/app.php`)*

**Login/landing page title:** `Nfelia — Track Everything. Carry Nothing.`

**Favicon files (root):**
- `favicon.ico`
- `favicon-16x16.png`
- `favicon-32x32.png`
- `apple-touch-icon.png`
- `android-chrome-192x192.png`
- `android-chrome-512x512.png`

**OG Image:** `/assets/img/nfelia-og-banner.png` (1200×630)

---

## 10. Brand Intro Video

A short logo animation: a gold thread draws the mark, the name rises, and it ends on "The simple way to look big and track everything." Cuts for WhatsApp Status, Reels, TikTok, feed posts and YouTube, and the muted copy on the landing page, are in `docs/brand/video/`, with a note on where to post each.
