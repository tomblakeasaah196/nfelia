# Nfelia brand intro

A short logo animation. A gold thread draws the Nfelia mark, the name rises, and it ends on the line "The simple way to look big and track everything."

The original is a 10-second clip generated with Gemini, kept in `source/`. Between 6.7 and 7.7 seconds its letters break apart, so every cut here jumps past that moment.

Nothing in `docs/` is deployed. The website uses its own small copy, described below.

## Which file to post where

| File | Shape | Length | Sound | Post it on |
|---|---|---|---|---|
| `nfelia-intro-9x16.mp4` | Tall, 1080 × 1920 | 6.7 s | Yes | WhatsApp Status, Instagram and Facebook Stories, Reels, TikTok |
| `nfelia-intro-1x1.mp4` | Square, 1080 × 1080 | 6.7 s | Yes | Instagram and Facebook posts, WhatsApp chats and groups |
| `nfelia-intro-16x9.mp4` | Wide, 1280 × 720 | 6.7 s | Yes | YouTube and Facebook, the start of the setup video, presentations |

In the tall cut the logo and line sit around the middle (34% to 56% of the height), at least 150 px in from each side, so the buttons and captions that Stories, Reels and TikTok lay over the video don't cover them. In the square, the line ends about 120 px from each side.

## On the website

`assets/video/nfelia-intro.mp4` (960 × 540, 4.9 s, no sound, 123 KB) plays in the "Start in three steps" section, where the setup video will go. It plays once, the first time that section is on screen, and stops on the logo. Phones set to reduce motion or save data, slow 2G connections and browsers that can't play it see a still of the last frame instead (`assets/img/home/intro-end.webp`), and so does anyone whose copy fails or is slow to start. `docs/handoff/testkit/intro_test.js` checks each of these cases in a browser.

It is shorter than the social cuts on purpose. Motion that starts by itself and lasts more than 5 seconds needs a pause button (WCAG 2.2.2), so the slow shine in the middle runs faster and the zoom settles sooner. Its colours are also warmed a little to sit on the page's cream.

When the setup video is on YouTube, set `$onboarding_video_id` in `views/home/index.php` and the YouTube player replaces this intro.

## Making the cuts again

`make_cuts.py` rebuilds every file from the source. It needs ffmpeg, cwebp, and Python 3 with Pillow and NumPy, and takes about a minute and a half:

    python3 docs/brand/video/make_cuts.py docs/brand/video/source/nfelia-intro-source.mp4 /tmp/nfelia-cuts

The output folder holds the three social cuts, and `web/` holds the website's clip and its two stills. The top of the script explains the edit: the frame numbers, how the two parts are lined up across the cut, and what the website copy changes.
