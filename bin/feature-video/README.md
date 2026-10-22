# Feature videos

A short narrated video of new features, filmed in the real app at phone size,
to send a business on WhatsApp: vertical 1080×1920, under 20 seconds, H.264
and AAC. Gold circles show each tap, a gold outline shows what changed, and
captions sit above the phone in Nfelia's wine and gold.

```
bash bin/feature-video/make.sh bin/feature-video/videos/2026-10-late-sales
# → bin/feature-video/videos/2026-10-late-sales/out/2026-10-late-sales.mp4
```

## What it needs

- A local or test copy of the app, served at `APP_URL`. **The seed wipes the
  database** the checkout's `.env` points at, so `make.sh` refuses unless
  `APP_ENV` is `local` or `testing`. The same setup as the end-to-end tests
  (`tests/notifications/setup.sh`), then:
  `php -S 127.0.0.1:8099 -t . tests/notifications/router.php`
- `npm ci` (the captions use Plus Jakarta Sans from `node_modules`).
- Playwright with Chromium: `npm install --no-save playwright@1.56.1` and
  `npx playwright install chromium`.
- `ffmpeg` with libx264, and Python 3.
- The narrator downloads itself once (`setup-voice.sh`, about 110 MB, kept in
  `~/.cache/nfelia-video` or `$NF_VIDEO_CACHE`).

## How it works

| Step | File | What it does |
|---|---|---|
| Seed | `videos/<name>/seed.php` | Builds the demo data on the test fixture and prints the ids the scenes open |
| Speak | `voice.py` | Each scene's line, spoken by Kokoro, offline |
| Film | `record.js` | Plays the scenes in Chromium at 384×832 (a Samsung screen), frame by frame from Chrome's screencast, marking where each scene starts and ends |
| Frame | `stage.js` | The background, the phone and one caption card per scene |
| Edit | `compose.py` | Fits each scene to its voice line (at most 1.4× faster), rounds the screen into the phone, fades the captions, levels the sound |

Page loads never show: each scene is cut from its own start and end marks.

## A new video

Copy `videos/2026-10-late-sales`, then change the two files:

- **`seed.php`**: the demo data. Use made-up businesses and clients, never a
  real one. Print one line of JSON with the ids the scenes need.
- **`scenes.json`**: per scene, the page to `open` (`{key}` takes an id from
  the seed), a `caption` (one line: `stage.js` warns when it wraps), a `sub`
  line, what to `say`, and the `steps`. Steps under `before` run unfilmed,
  for example to scroll.

| Step | Example |
|---|---|
| wait | `["wait", 400]` |
| tap, with its circle | `["tap", "button[data-open=\"nf-more\"]"]` |
| fill a field at once | `["fill", "#nf-sale-day", "2026-06-12"]` |
| type, key by key | `["type", "input[name=\"amount\"]", "50,000", 70]` |
| fire a change event | `["change", "#nf-sale-day"]` |
| outline what changed | `["spot", ".nf-dated", 4]` |
| scroll an element to near the top | `["scroll", "button:has-text(\"Kept with you\")", 330]` |

Selectors are Playwright's, so `:has-text()` works. Keep each line short: a
scene lasts as long as its line plus a breath, three seconds at least, and
five scenes come to about 18 seconds. `voice` and `speed` set the narrator
(`af_heart` at 1.15; British voices start `bf_` and `bm_`).

## The voice

Kokoro-82M (Apache-2.0), run on the CPU. Its weights and voices come from npm
and its tokenizer from PyPI (`kokoro-onnx`), so it works where Hugging Face
is blocked. The joined model is checked against its published sha256.
