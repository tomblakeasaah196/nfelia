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

Inside the app, in Shop Studio, on a shop's own pages and in the emails a business sends, colour is **the business's**, never Nfelia's. `app/helpers/theme.php` works it out from `businesses.primary_color` (Settings › Business › Branding) in four parts, set as CSS variables by `views/layouts/app.php` (and by Shop Studio, which stands outside the layout):

| Tailwind colour | CSS variable | Usage |
|---|---|---|
| `accent` | `--accent` | Fills: main buttons, the selected tab, today on the calendar, progress |
| `on-accent` | `--on-accent` | Text and icons on an `accent` fill (white or ink, whichever reads) |
| `accent-text` | `--accent-text` | Text, links, icons, selected borders and rings on white (4.5:1) |
| `accent-soft` | `--accent-soft` | The pale tint behind a selected chip |

A filled button is `bg-accent text-on-accent`, never `text-white`: a light brand colour gets ink. A business that never chose a colour (its `primary_color` is still the column's default, `#1A1A2E`) gets Nfelia's wine through the same variables.

Documents (the invoice, receipt, quotation, delivery and deed pages customers open, their PDFs, and the app's own view of them) take their two colours from `brand_doc_colours()` in `app/helpers/brand.php`: the brand colours, else Nfelia's wine and gold. No page reads `primary_color` itself.

The neutrals are the same for everyone:

| Token | Hex | Usage |
|---|---|---|
| `brand-plum` | `#1B0A11` | Body text, headings, dark backgrounds |
| `brand-sand` | `#E8E4DA` | Borders, dividers, subtle backgrounds |
| `brand-cream` | `#F2EFE7` | Page background, card backgrounds |
| `brand-white` | `#FFFFFF` | Card surfaces, input fields, modal backgrounds |

**Nfelia's own colours**, wine `#8B004A` and gold `#B69F5E`, belong to Nfelia's own pages: signing in, onboarding, the website, Ops and Nfelia's own emails. `brand-burgundy` and `brand-gold` are left only in `tailwind.public.config.js`, for Nfelia's own pages built with it; the app's build has none, and `tests/theme/colours_test.php` fails if a fixed wine or gold comes back where the business's colour belongs, customer documents included.

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
<button class="bg-accent text-on-accent px-6 py-3 text-xs font-bold uppercase tracking-widest rounded-xl">
    Action Label
</button>

<!-- Secondary / Ghost -->
<button class="bg-brand-cream text-brand-plum border border-brand-sand px-6 py-3 text-xs font-bold uppercase tracking-widest rounded-xl hover:border-accent">
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
    <input type="text" class="w-full px-4 py-3 border border-brand-sand rounded-xl text-sm focus:border-accent-text outline-none">
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
