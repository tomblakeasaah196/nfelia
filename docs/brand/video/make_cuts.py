#!/usr/bin/env python3
"""Rebuild every cut of the Nfelia brand intro from the source clip.

    python3 docs/brand/video/make_cuts.py docs/brand/video/source/nfelia-intro-source.mp4 WORKDIR

Writes to WORKDIR:
    nfelia-intro-16x9.mp4, nfelia-intro-1x1.mp4, nfelia-intro-9x16.mp4   social cuts, with sound
    web/nfelia-intro.mp4, web/intro-start.webp, web/intro-end.webp       for the landing page
Needs ffmpeg, cwebp, and Python 3 with Pillow and NumPy.

The edit (frame numbers are the source's, at 24 fps):
  - Part 1 is frames 48-159 (2.0s to 6.6s): the gold thread draws the mark and
    the name rises. Its first 5 frames fade in from a clean background.
  - Frames 160-187 are skipped: the letters break apart there.
  - Part 2 is frames 188-235 (7.8s to 9.8s): the name with the tagline. It
    starts scaled x1.22 and moved (-141, -36)px, which lines its name up with
    the last frame of part 1 (found by a least-squares search around the
    name), then eases back to full frame, so the cut reads as one move.
    One 50/50 frame bridges the cut.
  - The website copy is tightened to end inside 5 seconds (see make_web).
"""
import math
import os
import subprocess
import sys

import numpy as np
from PIL import Image

FPS = 24
A_FRAMES = range(48, 160)
B_FRAMES = range(188, 236)
SCALE, TX, TY = 1.22, -141.0, -36.0    # lines part 2's name up with part 1's last frame
EASE = 1.4                             # seconds for part 2 to settle (cubic ease-out)


def run(*args):
    subprocess.run(args, check=True)


def rgb(path):
    return np.asarray(Image.open(path).convert("RGB")).astype(np.float32)


def save(a, path, size=None):
    im = Image.fromarray(np.clip(a + 0.5, 0, 255).astype(np.uint8))
    (im.resize(size, Image.LANCZOS) if size else im).save(path)


def extract(src, work):
    for name, frames in (("A", A_FRAMES), ("B", B_FRAMES), ("BG", range(0, 24))):
        os.makedirs(f"{work}/{name}", exist_ok=True)
        run("ffmpeg", "-v", "error", "-y", "-i", src,
            "-vf", f"select='between(n\\,{frames.start}\\,{frames.stop - 1})'", "-vsync", "0",
            "-start_number", str(frames.start), f"{work}/{name}/f_%04d.png")
    # A clean background: the per-pixel median of the first second removes
    # most of the moving thread.
    stack = np.stack([rgb(f"{work}/BG/f_{i:04d}.png") for i in range(24)])
    return np.median(stack, axis=0)


def part2(work, k, ease_power, ease_secs):
    """Part 2's frame k, eased from the matched zoom back to full frame."""
    p = 1 - (1 - min(k / FPS / ease_secs, 1.0)) ** ease_power
    z, ox, oy = 1 + (SCALE - 1) * (1 - p), TX * (1 - p), TY * (1 - p)
    f = Image.open(f"{work}/B/f_{B_FRAMES.start + k:04d}.png").convert("RGB")
    if p < 1:
        f = f.transform(f.size, Image.AFFINE, (1 / z, 0, -ox / z, 0, 1 / z, -oy / z), resample=Image.BICUBIC)
    return np.asarray(f).astype(np.float32)


def make_edit(work, plate):
    """The social edit, 160 frames (6.7s), as PNGs in WORKDIR/out."""
    os.makedirs(f"{work}/out", exist_ok=True)
    frames = [rgb(f"{work}/A/f_{i:04d}.png") for i in A_FRAMES]
    for k in range(5):
        frames[k] = plate + (frames[k] - plate) * (k + 1) / 6
    last_a = frames[-1]
    for k in range(len(B_FRAMES)):
        f = part2(work, k, 3, EASE)
        frames.append((f + last_a) / 2 if k == 0 else f)
    for n, a in enumerate(frames):
        save(a, f"{work}/out/o_{n:04d}.png")
    return len(frames)


def make_audio(src, work):
    """Sound follows the same two parts, with short fades at the joins."""
    a1, a2 = A_FRAMES.start / FPS, B_FRAMES.start / FPS
    run("ffmpeg", "-v", "error", "-y", "-ss", f"{a1:.4f}", "-t", f"{len(A_FRAMES) / FPS:.4f}", "-i", src, "-vn",
        "-af", f"afade=t=out:st={len(A_FRAMES) / FPS - 0.03:.4f}:d=0.03", "-ar", "48000", f"{work}/a1.wav")
    run("ffmpeg", "-v", "error", "-y", "-ss", f"{a2:.4f}", "-t", f"{len(B_FRAMES) / FPS:.4f}", "-i", src, "-vn",
        "-af", "afade=t=in:st=0:d=0.03,afade=t=out:st=1.7:d=0.3", "-ar", "48000", f"{work}/a2.wav")
    run("ffmpeg", "-v", "error", "-y", "-i", f"{work}/a1.wav", "-i", f"{work}/a2.wav",
        "-filter_complex", "[0][1]concat=n=2:v=0:a=1,afade=t=in:st=0:d=0.2", f"{work}/audio.wav")


def make_social(work, plate, n):
    h264 = ["-c:v", "libx264", "-preset", "slow", "-crf", "18", "-pix_fmt", "yuv420p", "-movflags", "+faststart"]
    wide = f"{work}/nfelia-intro-16x9.mp4"
    run("ffmpeg", "-v", "error", "-y", "-framerate", str(FPS), "-i", f"{work}/out/o_%04d.png", "-i", f"{work}/audio.wav",
        *h264, "-profile:v", "high", "-c:a", "aac", "-b:a", "160k", "-shortest", wide)
    # Square and tall: a full-height band of the wide frame, as wide as the
    # canvas, scaled down so the line keeps clear of the sides (and, in the
    # tall cut, of the buttons Reels and TikTok put down the right). Above
    # and below it, a backdrop continues the clip's own radial falloff, and
    # the band's top and bottom edges are feathered into it.
    g = plate.mean(axis=2)
    h, w = g.shape
    yy, xx = np.mgrid[0:h, 0:w]
    r = np.hypot(xx - w / 2, yy - h / 2)
    bins = np.arange(0, r.max() + 8, 8)
    idx = np.digitize(r.ravel(), bins)
    prof = np.array([np.median(g.ravel()[idx == i]) if np.any(idx == i) else np.nan for i in range(1, len(bins))])
    centres = bins[:-1] + 4
    ok = ~np.isnan(prof)

    def framed(name, cw, ch, scale, top):
        bw, bh = round(cw / scale), round(720 * scale / 2) * 2     # band: source width, canvas height
        Y, X = np.mgrid[0:ch, 0:cw]
        back = np.interp(np.hypot(X - cw / 2, Y - top - bh / 2) / scale, centres[ok], prof[ok], right=prof[ok][-1])
        save(np.stack([back] * 3, -1), f"{work}/{name}-bg.png")
        f = bh // 8
        ramp = np.ones(bh)
        ramp[:f] = np.linspace(0, 1, f) ** 1.5
        ramp[-f:] = ramp[:f][::-1]
        Image.fromarray(np.repeat((ramp * 255 + 0.5).astype(np.uint8)[:, None], cw, axis=1)).save(f"{work}/{name}-mask.png")
        secs = f"{n / FPS:.3f}"
        run("ffmpeg", "-v", "error", "-y", "-i", wide,
            "-loop", "1", "-framerate", str(FPS), "-t", secs, "-i", f"{work}/{name}-bg.png",
            "-loop", "1", "-framerate", str(FPS), "-t", secs, "-i", f"{work}/{name}-mask.png",
            "-filter_complex", f"[0:v]crop={bw}:720:{637 - bw // 2}:0,scale={cw}:{bh}:flags=lanczos,format=rgba[sq];[2:v]format=gray[m];"
                               f"[sq][m]alphamerge[sqa];[1:v]format=rgba[bg];[bg][sqa]overlay=0:{top}:shortest=1,format=yuv420p[v]",
            "-map", "[v]", "-map", "0:a", "-frames:v", str(n), *h264, "-c:a", "copy", f"{work}/{name}.mp4")

    framed("nfelia-intro-1x1", 1080, 1080, 1.275, 81)    # the line ends about 105px from each side
    framed("nfelia-intro-9x16", 1080, 1920, 1.15, 480)   # the line spans x 150-930, the logo y 34-56%


def warm(a):
    # Shift the near-white background toward the landing page's cream
    # (#F7F3EA), weighted by brightness so the burgundy mark keeps its colour.
    w = (a.mean(axis=2, keepdims=True) / 255.0) ** 2
    return a * (1 - w * (1 - np.array([1.0, 0.985, 0.95], dtype=np.float32)))


def make_web(work, plate):
    """The landing page copy: same edit, all motion inside 5 seconds, no sound.

    - The slow shine (edit frames 36-86) runs on a smooth 1x -> 2.1x -> 1x ramp.
    - Part 2 settles on a quadratic ease with the same starting speed as the
      cubic one (so the cut is unchanged) and lands in 0.93s instead of 1.4s.
    - No end hold: the page keeps the last frame on screen.
    - Graded warm to sit on the page's cream.
    """
    web = f"{work}/web"
    os.makedirs(f"{web}/frames", exist_ok=True)

    def edit_at(t):
        lo = int(math.floor(t))
        a = rgb(f"{work}/out/o_{lo:04d}.png")
        f = t - lo
        return a if f < 1e-6 else a * (1 - f) + rgb(f"{work}/out/o_{lo + 1:04d}.png") * f

    frames = [edit_at(i) for i in range(36)]
    s0, s1, n2 = 36.0, 86.0, 32
    amp = 2 * ((s1 - s0) / n2 - 1)
    for j in range(n2):
        u = j / n2
        frames.append(edit_at(s0 + n2 * (u + amp / 2 * (u - math.sin(2 * math.pi * u) / (2 * math.pi)))))
    frames += [edit_at(i) for i in range(86, len(A_FRAMES))]
    last_a = frames[-1]
    for k in range(24):
        f = part2(work, k, 2, 2 * EASE / 3)
        frames.append((f + last_a) / 2 if k == 0 else f)
    for n, a in enumerate(frames):
        save(warm(a), f"{web}/frames/w_{n:04d}.png", (960, 540))

    run("ffmpeg", "-v", "error", "-y", "-framerate", str(FPS), "-i", f"{web}/frames/w_%04d.png", "-frames:v", str(len(frames)),
        "-c:v", "libx264", "-preset", "veryslow", "-crf", "28", "-pix_fmt", "yuv420p", "-profile:v", "main", "-level:v", "3.1",
        "-movflags", "+faststart", "-an", f"{web}/nfelia-intro.mp4")
    save(warm(plate), f"{web}/start.png", (960, 540))
    run("cwebp", "-quiet", "-q", "90", "-m", "6", f"{web}/start.png", "-o", f"{web}/intro-start.webp")
    run("cwebp", "-quiet", "-q", "80", "-sharp_yuv", "-m", "6", f"{web}/frames/w_{len(frames) - 1:04d}.png", "-o", f"{web}/intro-end.webp")
    return len(frames)


if __name__ == "__main__":
    if len(sys.argv) != 3:
        sys.exit(__doc__)
    src, work = sys.argv[1], sys.argv[2]
    os.makedirs(work, exist_ok=True)
    plate = extract(src, work)
    n = make_edit(work, plate)
    make_audio(src, work)
    make_social(work, plate, n)
    w = make_web(work, plate)
    print(f"social cuts: {n} frames ({n / FPS:.2f}s); web cut: {w} frames ({w / FPS:.2f}s); in {work}")
