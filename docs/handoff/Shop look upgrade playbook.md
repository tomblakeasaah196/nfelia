# Shop look upgrade playbook

How Atelier was upgraded (step 6), written so the same can be done for the other nine looks, one at
a time. Read [Shop upgrade step 6.md](Shop%20upgrade%20step%206.md) first for what changed. Buka, Glow
and Shea followed in step 7 ([Shop looks step 7.md](Shop%20looks%20step%207.md)), Market and Volt in
step 8 ([Shop looks step 8.md](Shop%20looks%20step%208.md)), Haven and Stage in step 9
([Shop looks step 9.md](Shop%20looks%20step%209.md)).

## What every look already has

These came with step 6 for all ten looks; an upgrade doesn't need to redo them:

- the owner's brand colours and fonts from Settings, with Studio overrides (`storefront_resolve()`);
- the page split: a light home page, the full list at `/shop`, the request form at `/contact`;
- the top bar with a menu sheet on phones and links on wider screens; the footer links;
- the announcement bar with an end date; tap-to-edit in Studio's preview;
- the map section (`location`), with its own card style.

What an upgrade adds is the look's own design: its first screen, how its sections sit on the page,
its type, its one memorable thing.

## The steps

### 1. Brief the look in one paragraph

Name the trade, the shopper, the phone they hold, and what they came to do. Then choose **one**
memorable thing and keep everything else quiet. For Atelier: fashion; shoppers browsing on a phone,
often from Instagram; they came to look at clothes, so the photo is everything: full-screen photos
that take turns, and one of them kept faintly behind every page.

Ideas for the others (to be confirmed with the owner, one look at a time):

| Look | Trade | A memorable thing that would suit it |
| --- | --- | --- |
| Buka | Food | Done in step 7: the dishes on a table that turns |
| Glow | Beauty | Done in step 7: shoppable stories |
| Volt | Gadgets and repairs | Done in step 8: each device under a spotlight, with spec callouts, its condition and battery health |
| Market | Wholesale | Done in step 8: the price board as a painted signboard, with a calculator, prices for buying more and an order book |
| Stage | Events | Done in step 9: plan your event (guests, the day, colours, extras), with photos and reviews |
| Harvest | Farm produce | This week's harvest and the pickup day, with real farm photos |
| Shea | Skincare | Done in step 7: the skin finder, which builds a routine |
| Haven | Short stays | Done in step 9: dates first, then what's free and what the stay costs |
| Desk | Services | What's included, as a checklist, and "Book a visit" |

### 2. Set the look's data

In `app/helpers/storefront.php`, `storefront_looks()`:

- `hero`: `'cover'` gives Atelier's full-screen photos (`sections/hero.php`, case `cover`);
  `'turntable'`, `'stories'` and `'finder'` are Buka's, Glow's and Shea's, each in its own partial
  (`sections/hero_turntable.php` and so on); other values are the looks' existing heroes.
- `slides`: how many photos the cover plays (1 for a still photo). Studio's photo shelf follows it.
- `backdrop`: `true` keeps a photo faintly behind every page (`$backdrop` in `shell.php`); Studio
  then shows the "Page background" choice.
- `shape`: corner radii for hero, tiles, buttons, cards, chips and fields.
- `labels`: the words the look uses ("All pieces", "Visit us"…).
- `colours` and `pair` are the look's own, used only when the owner has no brand or picks them.

### 3. Style it, mobile first, from the tokens

All look CSS lives in `assets/css/storefront.css`, scoped under `.look-<name>`, in one block per
look. Design at 390 px first, then 600, 720, 960 and 1080.

Use the tokens; never write a colour or a font name into a look's CSS, or the owner's brand can't
reach it:

| Token | What it is |
| --- | --- |
| `--sf-ground`, `--sf-ink`, `--sf-muted` | page background, text, quieter text |
| `--sf-accent`, `--sf-on`, `--sf-accent-text` | buttons, the text on them, accent-coloured text |
| `--sf-surface`, `--sf-card`, `--sf-line`, `--sf-field` | raised areas, cards, rules, inputs |
| `--sf-band`, `--sf-band-ink`, `--sf-motif`, `--sf-tint` | the look's second colour and its text, pattern colour, soft fill |
| `--sf-scrim`, `--sf-paper` | the palette's dark and light, for fades over photos and the words on them |
| `--sf-head`, `--sf-body`, `--sf-head-weight`, `--sf-head-stretch`, `--sf-scale` | the font pair |
| `--sf-r-hero`, `--sf-r-tile`, `--sf-r-btn`, `--sf-r-card`, `--sf-r-chip`, `--sf-r-field` | radii |

Every pairing is already checked to 4.5:1 on the server (`sf_palette()`); a look's CSS should only
put text on the pairings that were checked (ink on ground, on on accent, accent-text on ground, paper
on scrim). Text over a photo always sits on a fade made from `--sf-scrim`.

### 4. Motion, glass and their fallbacks

- One moment of motion that nobody asked for, at most (Atelier's cover). Everything else answers a
  tap. Anything that moves by itself has a pause button, holds while focused, off screen or in a
  hidden tab, and never runs under `prefers-reduced-motion` or Save-Data (`storefront.js`, the cover).
- Glass only over a photo, where it has something to frost. Give it fallbacks:
  `@supports not (backdrop-filter: …)` and `prefers-reduced-transparency: reduce` make it solid.
- Photos beyond the first load late (`data-src`), and the next one only while the current one shows.

### 5. Studio

Studio reads the look's data (`views/studio/index.php`, `looks`), so `slides` and `backdrop` turn
their editors on without more code. A new section type needs its fields in `storefront_section_types()`
and an editor case in `assets/js/studio.js` (`renderEditor`).

### 6. Test and look

- Add checks to `docs/handoff/testkit/storefront_test.py` for anything the look does differently
  (markup that must be there, what must not be on the home page).
- Run every suite in the test kit and compare failures with the run before the change: nothing new.
- Screenshots at 390, 800 and 1280, brand light and dark, home, `/shop` and `/contact`. Use
  viewport shots for anything fixed or frosted (see the gotchas).
- Write any new exception to `.ux-profile.md`, and a handoff note.

## Gotchas found on Atelier

- **A panel that is also the page's wrap** (`.sf-wrap.sf-about__inner`) runs edge to edge on a phone
  once it has a background. Inset it: `width: calc(100% - 40px)` (64 px from 720 wide).
- **Category tiles**: a fixed three-column grid leaves one tile alone on a row with four
  categories. The grid now has `.sf-cats--N` classes that fill every row; keep them when restyling.
- **Header over the cover**: `.sf-header--over` pulls the cover up under the header with a negative
  margin equal to the header's height (61 px, 73 px from 720 wide). Change both together.
- **The cover's height** is `100svh` minus the announcement bar, which `storefront.js` measures into
  `--sf-top`.
- **Look-alike icons**: a new menu button next to Market's list-shaped order button read as two
  menus; Market's order is now a clipboard. Check the header icons of each look.
- **Headless screenshots**: full-page captures in Chromium don't reliably paint fixed backgrounds or
  areas under `backdrop-filter`, so the page photo and the map looked missing. Check those with a
  viewport screenshot scrolled to the spot.
- **Google Fonts**: ask for the exact weights a family has; a family with one weight is asked for by
  name only; Bodoni Moda needs its optical size axis. Unknown families answer 400, which is how the
  library was checked.
- **The Studio preview runs on the main host**: links in previews go through `sf_page_href()`, which
  knows whether it is drawing the live shop, the preview or a look sample.
