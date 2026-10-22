"""Edit a recording into the feature video.

    venv/bin/python -I compose.py <work folder> <out.mp4>

Reads timeline.json and the frames (record.js), v1.wav ... (voice.py),
bg.png, mask.png, cap1.png ... and layout.json (stage.js).

Each scene's frames, between its start and end marks, fill a slot as long as
its voice line plus a breath (3 seconds at least). A slot shorter than the
recording plays it faster, never more than 1.4x; a longer one holds the last
frame. The phone screen goes into the frame with rounded corners, captions
fade out and in (the words never overlap), each voice line starts just after
its cut, and the sound is levelled to -16 LUFS. H.264 and AAC at 30 fps, for
WhatsApp.
"""
import json, os, subprocess, sys
import numpy as np
import soundfile as sf

W, OUT = sys.argv[1:3]
FPS = 30
LEAD, TAIL, XF, BREATH, SHORTEST, FASTEST = 0.12, 0.55, 0.14, 0.38, 3.0, 1.4

L = json.load(open(f'{W}/layout.json'))
t = json.load(open(f'{W}/timeline.json'))
marks = {m['name']: m['ts'] for m in t['marks']}
frames = t['frames']
n = sum(1 for k in marks if k.startswith('s'))
voices = [sf.read(f'{W}/v{i}.wav') for i in range(1, n + 1)]
rate = voices[0][1]

slots, start = [], 0.0
for i in range(1, n + 1):
    s, e = marks[f's{i}'], marks[f'e{i}']
    rec = e - s
    spoken = len(voices[i - 1][0]) / rate
    d = max(spoken + LEAD + BREATH, rec / FASTEST, SHORTEST)
    slots.append(dict(s=s, e=e, rec=rec, d=d, f=max(1.0, rec / d), at=start, voice=spoken))
    start += d
total = start + TAIL

# One picture per output frame: the newest recorded frame at that moment
seq = f'{W}/seq'
os.makedirs(seq, exist_ok=True)
for old in os.listdir(seq):
    os.remove(os.path.join(seq, old))
tss = [f['ts'] for f in frames]
for k in range(int(round(total * FPS))):
    tt = k / FPS
    slot = next((x for x in slots if x['at'] <= tt < x['at'] + x['d']), slots[-1])
    r = slot['s'] + min((tt - slot['at']) * slot['f'], slot['rec'])
    j = max(0, int(np.searchsorted(tss, r, side='right')) - 1)
    j = max(j, int(np.searchsorted(tss, slot['s'], side='left')))   # never a frame from before the cut
    os.link(f"{W}/{frames[j]['file']}", f'{seq}/{k:05d}.jpg')

# The voice: each line just after its cut
track = np.zeros(int(total * rate) + rate)
for slot, (audio, _) in zip(slots, voices):
    at = int((slot['at'] + LEAD) * rate)
    track[at:at + len(audio)] += audio
sf.write(f'{W}/voice.wav', track[:int(total * rate)], rate)

# Captions: each shown for its slot, fading out before the next fades in
caps, chain, prev = [], '', 'base'
for i, slot in enumerate(slots):
    a = slot['at']
    b = slot['at'] + slot['d'] + (0 if i < n - 1 else TAIL)
    fade_in = f'fade=t=in:st={a:.3f}:d={XF}:alpha=1,' if i else ''
    caps.append(f'[{3 + i}:v]format=rgba,{fade_in}fade=t=out:st={b - XF:.3f}:d={XF}:alpha=1[c{i}]')
    chain += f";[{prev}][c{i}]overlay=0:0:enable='between(t,{a:.3f},{b:.3f})'[o{i}]"
    prev = f'o{i}'

fc = (f"[0:v]scale={L['sw']}:{L['sh']}:flags=lanczos,format=rgba[ph];"
      f"[2:v]format=gray[m];[ph][m]alphamerge[scr];"
      f"[1:v][scr]overlay={L['sx']}:{L['sy']}:shortest=1[base];"
      + ';'.join(caps) + chain +
      f";[{prev}]fade=t=out:st={total - 0.35:.3f}:d=0.35,format=yuv420p[v];"
      f"[{3 + n}:a]loudnorm=I=-16:TP=-1.5:LRA=7,afade=t=out:st={total - 0.35:.3f}:d=0.35,aresample=48000[a]")
cmd = ['ffmpeg', '-y', '-loglevel', 'error',
       '-framerate', str(FPS), '-i', f'{seq}/%05d.jpg',
       '-loop', '1', '-framerate', str(FPS), '-i', f'{W}/bg.png',
       '-loop', '1', '-framerate', str(FPS), '-i', f'{W}/mask.png']
for i in range(1, n + 1):
    cmd += ['-loop', '1', '-framerate', str(FPS), '-t', f'{total:.3f}', '-i', f'{W}/cap{i}.png']
cmd += ['-i', f'{W}/voice.wav', '-filter_complex', fc, '-map', '[v]', '-map', '[a]',
        '-c:v', 'libx264', '-preset', 'slow', '-crf', '19', '-profile:v', 'high', '-r', str(FPS),
        '-c:a', 'aac', '-b:a', '128k', '-t', f'{total:.3f}', '-movflags', '+faststart', OUT]
subprocess.run(cmd, check=True)
print(json.dumps({'seconds': round(total, 2), 'scenes': [dict(seconds=round(x['d'], 2), speed=round(x['f'], 2)) for x in slots]}))
