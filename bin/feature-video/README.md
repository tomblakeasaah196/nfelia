# Feature videos and tutorials

Narrated videos filmed in the real app at phone size, to send a business on
WhatsApp: vertical 1080×1920, H.264 and AAC. Gold circles show each tap, a
gold outline shows what changed, and captions sit above the phone in
Nfelia's wine and gold. The same tool makes a 15-second "what's new" clip and
a 5-minute tutorial with title cards.

```
bash bin/feature-video/make.sh bin/feature-video/videos/2026-10-late-sales
# → bin/feature-video/videos/2026-10-late-sales/out/2026-10-late-sales.mp4

NF_VIDEO_DOMAIN=nfelia.com NF_MAIL_DIR=/path/to/caught/mail \
  bash bin/feature-video/make.sh bin/feature-video/videos/tutorials/1-start
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
- `ffmpeg` with libx264, `openssl`, and Python 3.
- The narrator downloads itself once (`setup-voice.sh`, kept in
  `~/.cache/nfelia-video` or `$NF_VIDEO_CACHE`): Kokoro is about 110 MB,
  YarnGPT about 4 GB.
- A video that reads Nfelia's emails (a `mail` step) needs a mail catcher:
  `tests/notifications/smtp_sink.py` on the port `.env`'s `MAIL_PORT` names,
  with its folder in `NF_MAIL_DIR`.

## How it works

| Step | File | What it does |
|---|---|---|
| Seed | `videos/<name>/seed.php` | Builds the demo data on the test fixture and prints the ids the scenes open |
| Speak | `voice.py` | Each scene's line, spoken by Kokoro or YarnGPT |
| Film | `record.js` | Plays the scenes in Chromium at 384×832 (a Samsung screen), frame by frame from Chrome's screencast, marking where each scene starts and ends; a second phone for the customer |
| Frame | `stage.js` | The background, the phone, one caption per filmed scene and a full-screen card per card scene |
| Edit | `compose.py` | Fits each scene to its voice line, rounds the screen into the phone, fades the captions, levels the sound |

Page loads never show: each scene is cut from its own start and end marks.
Frames go straight to disk, so a five-minute film never sits in memory.

## Filming as nfelia.com

With `NF_VIDEO_DOMAIN=nfelia.com`, links and shop addresses on screen read as
they do for real (`adannaluxehair.nfelia.com`, not `localhost:8099`):
`make.sh` points `.env`'s `APP_URL` at `https://nfelia.com` while it films
(and puts it back after, even on failure), `tls-proxy.js` answers for the
domain and its shops on this machine's port 443, and the filming browser maps
the domain to this machine and skips any web proxy for it.

**The real site is never filmed.** `tls-proxy.js` marks every answer, and
`record.js` refuses to start unless the domain's first page carries that
mark. Without it, a machine behind a web proxy would send the video's
sign-ups and orders to the live site.

## A new video

Copy a folder under `videos/`, then change the two files:

- **`seed.php`**: the demo data. Use made-up businesses and clients, never a
  real one. Print one line of JSON with the ids the scenes need.
- **`scenes.json`**: per scene, the page to `open`, a `caption` (one line:
  `stage.js` warns when it wraps), a `sub` line, what to `say`, and the
  `steps`. Steps under `before` run unfilmed.

| Scene field | |
|---|---|
| `open` | A path (`/orders/view/{order}`: `{key}` takes an id from the seed or a `keep` step), a full URL, or `shop:name/path` for a business's shop at `name.<the app's host>`. Left out, the scene carries on where the last one ended on that phone. |
| `as` | `owner` (default) or `customer`: a second phone, signed out |
| `card` | `{kicker, title, sub}` instead of filming: a full-screen title card |
| `chapter` | The small line over the caption in a long video (seven filmed scenes or fewer get numbered dots instead) |
| `min`, `speed` | The scene's shortest length (3 s) and the fastest its film may play (spec `speed_limit`, else 1.4×) |

| Step | Example |
|---|---|
| wait | `["wait", 400]` |
| tap, with its circle | `["tap", "button[data-open=\"nf-more\"]"]`; add `{"nav": true}` when it opens a page |
| type, key by key | `["type", "input[name=\"amount\"]", "50,000", 70]` |
| fill, select, check, press | `["fill", "#day", "2026-06-12"]`, `["select", "select[name=unit]", "bundle"]`, `["press", "Escape"]` |
| pick a file | `["upload", "input[name=logo]", "../assets/logo.jpg"]`, or `["choose", "button:has-text('Upload')", "../assets/banner.jpg"]` for a button that opens the file picker |
| outline, then clear | `["spot", ".nf-dated", 4]`, `["unspot"]` |
| scroll | `["scroll", "#nf-attn-h", 200]` (to near the top), `["scrollBy", 400, 900]` |
| an email Nfelia sent | `["mail", "Verify"]` (the newest whose subject has the text, under a sender and subject bar) |
| remember for later | `["keep", "signoff", "#signoff-url", "value"]`, `["keep", "order", "url"]` |
| change data off camera | `["run", "php more.php"]` (in `before`) |

Selectors are Playwright's: `:has-text()` and `:visible` work. Prefer
`:visible` when a page has the same control in a hidden sheet.

## The voice

`scenes.json`'s `voice` picks the narrator:

```
"voice": "af_heart", "speed": 1.15
"voice": {"engine": "yarngpt", "name": "chinenye", "tempo": 0.9, "pause": 0.45,
          "say_as": {"Nfelia": "En-feh-lee-ya"}}
```

- **Kokoro-82M** (Apache-2.0), American and British voices, from npm and
  PyPI so it works where Hugging Face is blocked.
- **YarnGPT** (`saheedniyi/YarnGPT2b`, Apache-2.0), Nigerian English; the
  tutorials use Chinenye. It needs Hugging Face, and runs in its own
  virtualenv (`setup-voice.sh yarngpt`; `make.sh` picks it). Write `say` in
  words ("forty-five thousand naira"); `say_as` respells names for the voice
  only. Each sentence is made eight at a time, checked by Whisper against its
  words and made again when it doesn't match, then kept in
  `<cache>/lines`, so editing a script only speaks the changed sentences.
  Allow about half an hour per five-minute video on four CPU cores.

## The tutorials

`videos/tutorials/` holds the five tutorials for Adanna Luxe Hair, a made-up
hair vendor in Lekki: `1-start` (sign-up to a ready shop), `2-products`,
`3-orders`, `4-quotes` and `5-money`. `adanna.php` builds the business for
each seed; `assets/` holds her logo, banner and product photos and an
unbranded transfer receipt.
