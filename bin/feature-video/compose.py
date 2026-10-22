"""Edit a recording into the video.

    venv/bin/python -I compose.py <work folder> <out.mp4> <scenes.json>

Reads timeline.json and the frames (record.js), v<n>.wav for each scene that
speaks (voice.py), bg.png, mask.png, cap<n>.png, card<n>.png and layout.json
(stage.js); n is the scene's place in scenes.json, from 1.

Each scene fills a slot as long as its voice line plus a breath (at least
"min" seconds, 3 by default). A filmed scene's frames, between its start and
end marks, play faster when the slot is shorter, never more than "speed"
(the scene's, else the file's, else 1.4x); a longer slot holds the last
frame. A card scene shows its card over the whole frame. The phone screen
goes into the frame with rounded corners, captions fade out and in (the
words never overlap), each voice line starts just after its cut, and the
sound is levelled to -16 LUFS. H.264 and AAC at 30 fps, for WhatsApp.
"""
import json, os, subprocess, sys
import numpy as np
import soundfile as sf

W, OUT, SCENES = sys.argv[1:4]
FPS = 30
LEAD, TAIL, XF, BREATH, SHORTEST, FASTEST, RATE = 0.12, 0.55, 0.14, 0.38, 3.0, 1.4, 24000

spec = json.load(open(SCENES))
L = json.load(open(f'{W}/layout.json'))
t = json.load(open(f'{W}/timeline.json'))
marks = {m['name']: m['ts'] for m in t['marks']}
frames = t['frames']
tss = [f['ts'] for f in frames]

slots, start, rate = [], 0.0, RATE
for i, scene in enumerate(spec['scenes'], 1):
    voice = None
    if os.path.exists(f'{W}/v{i}.wav'):
        audio, rate = sf.read(f'{W}/v{i}.wav')
        voice = audio
    spoken = len(voice) / rate if voice is not None else 0.0
    least = float(scene.get('min', SHORTEST))
    fastest = float(scene.get('speed', spec.get('speed_limit', FASTEST)))
    if scene.get('card'):
        d = max(spoken + LEAD + BREATH if spoken else 0.0, least)
        slots.append(dict(i=i, card=True, d=d, at=start, voice=voice))
    else:
        s, e = marks[f's{i}'], marks[f'e{i}']
        rec = e - s
        d = max(spoken + LEAD + BREATH if spoken else 0.0, rec / fastest, least)
        slots.append(dict(i=i, card=False, s=s, e=e, rec=rec, d=d, f=max(1.0, rec / d), at=start, voice=voice,
                          caption=os.path.exists(f'{W}/cap{i}.png')))
    start += d
total = start + TAIL

# One picture per output frame: the newest recorded frame at that moment
# (under a card, the phone keeps its last picture: the card covers it)
seq = f'{W}/seq'
os.makedirs(seq, exist_ok=True)
for old in os.listdir(seq):
    os.remove(os.path.join(seq, old))
j = 0
for k in range(int(round(total * FPS))):
    tt = k / FPS
    slot = next((x for x in slots if x['at'] <= tt < x['at'] + x['d']), slots[-1])
    if not slot['card']:
        r = slot['s'] + min((tt - slot['at']) * slot['f'], slot['rec'])
        j = max(0, int(np.searchsorted(tss, r, side='right')) - 1)
        j = max(j, int(np.searchsorted(tss, slot['s'], side='left')))   # never a frame from before the cut
        j = min(j, len(frames) - 1)
    os.link(f"{W}/{frames[j]['file']}", f'{seq}/{k:06d}.jpg')

# The voice: each line just after its cut
track = np.zeros(int(total * rate) + rate)
for slot in slots:
    if slot['voice'] is not None:
        at = int((slot['at'] + LEAD) * rate)
        track[at:at + len(slot['voice'])] += slot['voice']
sf.write(f'{W}/voice.wav', track[:int(total * rate)], rate)

# Captions and cards: each loaded only for its own slot, then moved to its place in time
inputs, filters, chain, prev = [], [], '', 'base'
first_filmed = next((x for x in slots if not x['card']), None)
for slot in slots:
    a, d = slot['at'], slot['d'] + (TAIL if slot is slots[-1] else 0)
    if slot['card']:
        src, fade_in = f"{W}/card{slot['i']}.png", a > 0
    elif slot['caption']:
        src, fade_in = f"{W}/cap{slot['i']}.png", slot is not first_filmed or a > 0
    else:
        continue
    n = 3 + len(inputs) // 8   # inputs 0-2 are the frames, background and mask; each image adds 8 arguments
    inputs += ['-loop', '1', '-framerate', str(FPS), '-t', f'{d:.3f}', '-i', src]
    fx = (f'fade=t=in:st=0:d={XF}:alpha=1,' if fade_in else '') + f'fade=t=out:st={max(0.0, d - XF):.3f}:d={XF}:alpha=1'
    filters.append(f'[{n}:v]format=rgba,{fx},setpts=PTS+{a:.3f}/TB[x{n}]')
    chain += f";[{prev}][x{n}]overlay=0:0:eof_action=pass:enable='between(t,{a:.3f},{a + d:.3f})'[o{n}]"
    prev = f'o{n}'
voice_in = 3 + len(inputs) // 8

fc = (f"[0:v]scale={L['sw']}:{L['sh']}:flags=lanczos,format=rgba[ph];"
      f"[2:v]format=gray[m];[ph][m]alphamerge[scr];"
      f"[1:v][scr]overlay={L['sx']}:{L['sy']}:shortest=1[base]"
      + (';' + ';'.join(filters) if filters else '') + chain +
      f";[{prev}]fade=t=out:st={total - 0.35:.3f}:d=0.35,format=yuv420p[v];"
      f"[{voice_in}:a]loudnorm=I=-16:TP=-1.5:LRA=7,afade=t=out:st={total - 0.35:.3f}:d=0.35,aresample=48000[a]")
cmd = ['ffmpeg', '-y', '-loglevel', 'error',
       '-framerate', str(FPS), '-i', f'{seq}/%06d.jpg',
       '-loop', '1', '-framerate', str(FPS), '-i', f'{W}/bg.png',
       '-loop', '1', '-framerate', str(FPS), '-i', f'{W}/mask.png',
       *inputs, '-i', f'{W}/voice.wav', '-filter_complex', fc, '-map', '[v]', '-map', '[a]',
       '-c:v', 'libx264', '-preset', 'slow', '-crf', '20', '-profile:v', 'high', '-r', str(FPS),
       '-c:a', 'aac', '-b:a', '128k', '-t', f'{total:.3f}', '-movflags', '+faststart', OUT]
subprocess.run(cmd, check=True)
print(json.dumps({'seconds': round(total, 2), 'scenes': [dict(n=x['i'], seconds=round(x['d'], 2), speed=round(x.get('f', 1.0), 2)) for x in slots]}))
