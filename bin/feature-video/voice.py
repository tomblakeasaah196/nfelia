"""Each scene's line, spoken: v<n>.wav in the work folder (n: the scene's
place in scenes.json, from 1). Scenes with no "say" stay silent.

    <python> -I voice.py <scenes.json> <work folder> <cache folder>

The narrator comes from the scenes file:
  "voice": "af_heart", "speed": 1.15            Kokoro, as before
  "voice": {"engine": "kokoro", "name": "bf_emma", "speed": 1.0, "tempo": 0.92}
  "voice": {"engine": "yarngpt", "name": "chinenye", "tempo": 0.9, "pause": 0.45,
            "say_as": {"Nfelia": "En-feh-lee-ya"}}
"say_as" respells words for the narrator only (captions keep the real spelling).
"tempo" below 1 slows the finished line without lowering the voice (ffmpeg's
atempo), for tutorials spoken slowly.

YarnGPT (saheedniyi/YarnGPT2b, Nigerian English) runs in its own virtualenv
(setup-voice.sh yarngpt; make.sh picks it). It reads letters only, so write
"say" lines in words: "forty-five thousand naira", not "N45,000". It hears no
punctuation, so a line is spoken a sentence at a time and joined with a
"pause" of silence; a sentence under 7 words rides with its neighbour, since
the model says short ones badly. Whisper (medium.en) listens to every
sentence and compares it with the words meant; one that doesn't match is made
again, up to 4 times in all, and the best kept (named on stderr if still
below the bar; the last try takes any take the model finishes). Words in "loose" (names the listener can't know, like
"Nfelia") are left out of the comparison. Sentences are kept in
<cache>/lines, so only new or changed ones are made again.

Prints each line's length in seconds.
"""
import difflib, hashlib, json, os, re, subprocess, sys
import numpy as np
import soundfile as sf

scenes_file, work, cache = sys.argv[1:4]
spec = json.load(open(scenes_file))
v = spec.get('voice', 'af_heart')
v = v if isinstance(v, dict) else {'engine': 'kokoro', 'name': v, 'speed': spec.get('speed', 1.15)}
engine, name = v.get('engine', 'kokoro'), v.get('name', 'af_heart')
speed, tempo = float(v.get('speed', 1.0)), float(v.get('tempo', 1.0))
RATE = 24000
say_as = v.get('say_as', {})


def spoken(text):
    """A line as the narrator says it: say_as respellings, whole words only."""
    for word, sound in say_as.items():
        text = re.sub(r'\b' + re.escape(word) + r'\b', sound, text, flags=re.I)
    return text


def words(text):
    """The words of a line as the checks compare them: lower case, letters only."""
    return re.sub(r"[^a-z' ]", ' ', text.lower().replace('-', ' ')).replace("'", '').split()


if engine == 'kokoro':
    from kokoro_onnx import Kokoro
    kokoro = Kokoro(f'{cache}/model_quantized.onnx', f'{cache}/voices.npz')

    def speak(text):
        return kokoro.create(text, voice=name, speed=speed, lang='en-us')

elif engine == 'yarngpt':
    import torch, torchaudio, whisper
    from transformers import AutoModelForCausalLM
    from audiotokenizer import AudioTokenizerV2

    torch.set_num_threads(os.cpu_count() or 4)
    # 0.85: Whisper (trained on other accents) mishears a few Nigerian vowels,
    # "tap" as "top", and that costs a sentence about one word in ten
    GOOD, TRIES, BATCH, SHORT = 0.85, 4, 8, 7
    pause = float(v.get('pause', 0.45))
    loose = set(w.lower() for w in v.get('loose', ['nfelia'])) | set(w for s in say_as.values() for w in words(s))
    os.makedirs(f'{cache}/lines', exist_ok=True)

    def sentences(text):
        """A line in the pieces it is spoken in: sentences, the short ones joined to a neighbour."""
        parts = [p.strip() for p in re.split(r'(?<=[.!?])\s+', text.strip()) if p.strip()]
        out = []
        for p in parts:
            if out and (len(words(p)) < SHORT or len(words(out[-1])) < SHORT):
                out[-1] += ' ' + p
            else:
                out.append(p)
        return out

    def kept(text):
        return f"{cache}/lines/{hashlib.sha1(json.dumps(['yarngpt2b', name, text]).encode()).hexdigest()[:20]}.wav"

    needed = []
    for scene in spec['scenes']:
        for s in sentences(spoken(scene.get('say') or '')):
            if s not in needed and not os.path.exists(kept(s)):
                needed.append(s)

    if needed:
        yarn = f'{cache}/yarngpt'
        tok = AudioTokenizerV2('saheedniyi/YarnGPT2b', f'{yarn}/wavtokenizer.ckpt', f'{yarn}/wavtokenizer.yaml')
        model = AutoModelForCausalLM.from_pretrained('saheedniyi/YarnGPT2b', dtype=torch.float32).eval()
        tk = tok.tokenizer
        tk.padding_side = 'left'
        if tk.pad_token is None:
            tk.pad_token = tk.eos_token
        ear = whisper.load_model(v.get('judge', 'medium.en'), download_root=f'{cache}/whisper')

        def trim(audio, floor=0.012, pad=0.06):
            """Cut the silence (and any breath of noise) before and after."""
            loud = np.flatnonzero(np.abs(audio) > floor)
            if not len(loud):
                return audio
            return audio[max(0, loud[0] - int(pad * RATE)):min(len(audio), loud[-1] + int(pad * RATE))]

        def score(meant, audio):
            """What Whisper hears, and how closely it matches the words meant (0 to 1)."""
            a16 = torchaudio.functional.resample(torch.from_numpy(audio.astype(np.float32)), RATE, 16000).numpy()
            got = ear.transcribe(a16, language='en', fp16=False, temperature=0)['text'].strip()
            m = [w for w in words(meant) if w not in loose]
            return difflib.SequenceMatcher(None, m, words(got)).ratio(), got

        def make(batch, attempt):
            """One take of each sentence in the batch: greedy first, then sampled."""
            torch.manual_seed(1000 + attempt)
            prompts = [tok.create_prompt(s, lang='english', speaker_name=name) for s in batch]
            enc = tk(prompts, add_special_tokens=False, return_tensors='pt', padding=True)
            most = max(len(words(s)) for s in batch)
            extra = dict(do_sample=True, temperature=0.4, top_p=0.95) if attempt else dict(do_sample=False)
            with torch.inference_mode():
                out = model.generate(**enc, repetition_penalty=1.1, max_new_tokens=min(3000, 150 + 90 * most),
                                     pad_token_id=tk.pad_token_id, **extra)
            takes = []
            for i, s in enumerate(batch):
                codes = tok.extract_integers(tk.decode(out[i][enc['input_ids'].shape[1]:]))
                n = len(words(s))
                if len(codes) < 20:
                    takes.append(None)
                    continue
                audio = trim(tok.get_audio(codes).squeeze().numpy())
                secs = len(audio) / RATE
                # Ran on, or skipped words: no sentence is spoken this slow or this
                # fast (the last try takes what it gets, for Whisper to judge)
                takes.append(audio if attempt == TRIES - 1 or 0.2 * n < secs < 1.2 + 0.75 * n else None)
            return takes

        best = {s: (-1.0, None, '') for s in needed}
        for attempt in range(TRIES):
            todo = [s for s in needed if best[s][0] < GOOD]
            if not todo:
                break
            print(f'[voice] take {attempt + 1}: {len(todo)} sentences', file=sys.stderr, flush=True)
            for k in range(0, len(todo), BATCH):
                batch = todo[k:k + BATCH]
                for s, audio in zip(batch, make(batch, attempt)):
                    if audio is None:
                        continue
                    sc, got = score(s, audio)
                    if sc > best[s][0]:
                        best[s] = (sc, audio, got)
                        if sc >= GOOD:   # kept at once, so a stopped run loses nothing that passed
                            sf.write(kept(s), audio, RATE)
        for s, (sc, audio, got) in best.items():
            if sc >= GOOD:
                continue
            if audio is None:
                raise SystemExit(f'YarnGPT could not say: "{s}"')
            print(f'[voice] check by ear ({sc:.2f}): "{s}" was heard as "{got}"', file=sys.stderr)
            sf.write(kept(s), audio, RATE)

    def speak(text):
        gap = np.zeros(int(pause * RATE))
        out = []
        for k, s in enumerate(sentences(spoken(text))):
            out += ([gap] if k else []) + [sf.read(kept(s))[0]]
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
print(json.dumps({'voice': name, 'lengths': lengths, 'seconds': round(sum(lengths.values()), 1)}))
