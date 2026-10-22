"""Each scene's line, spoken: v<n>.wav in the work folder (n: the scene's
place in scenes.json, from 1). Scenes with no "say" stay silent.

    <python> -I voice.py <scenes.json> <work folder> <cache folder>

The narrator comes from the scenes file:
  "voice": "af_heart", "speed": 1.15            Kokoro, as before
  "voice": {"engine": "kokoro", "name": "bf_emma", "speed": 1.0, "tempo": 0.92}
  "voice": {"engine": "yarngpt", "name": "idera", "tempo": 0.94, "pause": 0.42}
"tempo" below 1 slows the finished line without lowering the voice (ffmpeg's
atempo), for tutorials spoken slowly.

YarnGPT (Nigerian English, saheedniyi/YarnGPT2b) runs in its own virtualenv
(setup-voice.sh yarngpt; make.sh picks it). It reads letters only, so write
"say" lines in words: "forty-five thousand naira", not "N45,000". It hears no
punctuation either, so each sentence is spoken on its own and joined with a
"pause" of silence. Every sentence is checked by Whisper against its words and
made again (up to 5 tries, keeping the best) when it doesn't match. Sentences
are kept in <cache>/lines, so only new or changed ones are made again.

Prints each line's length in seconds.
"""
import hashlib, json, os, re, subprocess, sys
import numpy as np
import soundfile as sf

scenes_file, work, cache = sys.argv[1:4]
spec = json.load(open(scenes_file))
v = spec.get('voice', 'af_heart')
v = v if isinstance(v, dict) else {'engine': 'kokoro', 'name': v, 'speed': spec.get('speed', 1.15)}
engine, name = v.get('engine', 'kokoro'), v.get('name', 'af_heart')
speed, tempo = float(v.get('speed', 1.0)), float(v.get('tempo', 1.0))
RATE = 24000


def words(text):
    """The words of a line as the checks compare them: lower case, letters only."""
    return re.sub(r"[^a-z' ]", ' ', text.lower().replace('-', ' ')).replace("'", '').split()


if engine == 'kokoro':
    from kokoro_onnx import Kokoro
    kokoro = Kokoro(f'{cache}/model_quantized.onnx', f'{cache}/voices.npz')

    def speak(text):
        audio, rate = kokoro.create(text, voice=name, speed=speed, lang='en-us')
        return audio, rate

elif engine == 'yarngpt':
    import difflib, torch, whisper
    from transformers import AutoModelForCausalLM
    from audiotokenizer import AudioTokenizerV2

    torch.set_num_threads(os.cpu_count() or 4)
    yarn = f'{cache}/yarngpt'
    tok = AudioTokenizerV2('saheedniyi/YarnGPT2b', f'{yarn}/wavtokenizer.ckpt', f'{yarn}/wavtokenizer.yaml')
    model = AutoModelForCausalLM.from_pretrained('saheedniyi/YarnGPT2b', torch_dtype='auto').to(tok.device).eval()
    ear = whisper.load_model('base.en', download_root=f'{cache}/whisper')
    pause = float(v.get('pause', 0.42))
    os.makedirs(f'{cache}/lines', exist_ok=True)

    def trim(audio, floor=0.012, pad=0.06):
        """Cut the silence (and the odd breath of noise) before and after a sentence."""
        loud = np.flatnonzero(np.abs(audio) > floor)
        if not len(loud):
            return audio
        a, b = max(0, loud[0] - int(pad * RATE)), min(len(audio), loud[-1] + int(pad * RATE))
        return audio[a:b]

    def heard(audio):
        """What Whisper hears in the audio."""
        return ear.transcribe(audio.astype(np.float32), language='en', fp16=False, temperature=0)['text']

    def match(meant, got):
        """How closely what was heard matches the words meant, 0 to 1."""
        return difflib.SequenceMatcher(None, words(meant), words(got)).ratio()

    def sentence(text):
        key = hashlib.sha1(json.dumps(['yarngpt2b', name, text]).encode()).hexdigest()[:20]
        path = f'{cache}/lines/{key}.wav'
        if os.path.exists(path):
            return sf.read(path)[0]
        best, best_score, best_got = None, -1.0, ''
        for attempt in range(5):
            torch.manual_seed(1000 + attempt)
            ids = tok.tokenize_prompt(tok.create_prompt(text, lang='english', speaker_name=name))
            with torch.inference_mode():
                out = model.generate(input_ids=ids, repetition_penalty=1.1, max_length=4000,
                                     do_sample=attempt > 0, temperature=0.1 if attempt else None, top_p=None, top_k=None)
            codes = tok.get_codes(out)
            if len(codes) < 10:
                continue
            audio = trim(tok.get_audio(codes).squeeze().numpy())
            # Too long for its words means the model ran on (a sentence is never this slow)
            if len(audio) / RATE > 1.2 + 0.75 * len(words(text)):
                continue
            got = heard(audio)
            score = match(text, got)
            if score > best_score:
                best, best_score, best_got = audio, score, got
            if score >= 0.9:
                break
        if best is None:
            raise SystemExit(f'YarnGPT could not say: "{text}"')
        if best_score < 0.9:
            print(f'[voice] check by ear ({best_score:.2f}): "{text}" was heard as "{best_got.strip()}"', file=sys.stderr)
        sf.write(path, best, RATE)
        return best

    def speak(text):
        # One sentence at a time (YarnGPT hears no punctuation), joined by a pause
        parts = [p.strip() for p in re.split(r'(?<=[.!?])\s+', text.strip()) if p.strip()]
        gap = np.zeros(int(pause * RATE))
        out = []
        for k, p in enumerate(parts):
            out += ([gap] if k else []) + [sentence(p)]
        return np.concatenate(out), RATE

else:
    raise SystemExit(f'Unknown voice engine "{engine}".')

lengths = {}
for i, scene in enumerate(spec['scenes'], 1):
    path = f'{work}/v{i}.wav'
    if os.path.exists(path):
        os.remove(path)
    if not scene.get('say'):
        continue
    audio, rate = speak(scene['say'])
    sf.write(path, audio, rate)
    if abs(tempo - 1.0) > 0.001:
        subprocess.run(['ffmpeg', '-y', '-loglevel', 'error', '-i', path, '-filter:a', f'atempo={tempo}', path + '.tmp.wav'], check=True)
        os.replace(path + '.tmp.wav', path)
    info = sf.info(path)
    lengths[i] = round(info.frames / info.samplerate, 2)
    print(f'[voice] {i}: {lengths[i]}s', file=sys.stderr, flush=True)
print(json.dumps({'voice': name, 'lengths': lengths, 'seconds': round(sum(lengths.values()), 1)}))
