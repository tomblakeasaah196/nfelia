"""Each scene's line, spoken: v1.wav, v2.wav ... in the work folder.

    venv/bin/python -I voice.py <scenes.json> <work folder> <cache folder>

The voice and its speed come from the scenes file ("voice", "speed");
af_heart at 1.15 by default. Prints each line's length in seconds.
"""
import json, sys
import soundfile as sf
from kokoro_onnx import Kokoro

scenes_file, work, cache = sys.argv[1:4]
spec = json.load(open(scenes_file))
kokoro = Kokoro(f'{cache}/model_quantized.onnx', f'{cache}/voices.npz')
voice, speed = spec.get('voice', 'af_heart'), float(spec.get('speed', 1.15))
lengths = []
for i, scene in enumerate(spec['scenes'], 1):
    audio, rate = kokoro.create(scene['say'], voice=voice, speed=speed, lang='en-us')
    sf.write(f'{work}/v{i}.wav', audio, rate)
    lengths.append(round(len(audio) / rate, 2))
print(json.dumps({'voice': voice, 'lengths': lengths}))
