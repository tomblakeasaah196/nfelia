#!/usr/bin/env bash
# The narrator for feature videos: Kokoro-82M (Apache-2.0), run offline.
#
# Everything comes from package registries, so it works where Hugging Face
# and GitHub downloads are blocked:
#   npm  kokoro-q8-shards      the q8 ONNX model, in 6 pieces (joined and
#                              checked against its published sha256)
#   npm  kokoro-local-runtime  the 28 English voices (only the tarball is
#                              fetched, never its dependencies)
#   PyPI kokoro-onnx           the tokenizer vocabulary and the espeak-ng
#                              phonemizer, in a virtualenv of its own
#
# With "yarngpt" it also sets up YarnGPT, a Nigerian English narrator, in a
# virtualenv of its own (yarn/): CPU PyTorch, saheedniyi/YarnGPT2b (fetched
# from Hugging Face on first use), the WavTokenizer it speaks through, and
# Whisper (base.en) to check each sentence. About 4 GB, and it needs
# huggingface.co, download.pytorch.org and openaipublic.azureedge.net.
#
# Kept in $NF_VIDEO_CACHE (default ~/.cache/nfelia-video). Safe to run again:
# whatever is already there is left as it is.
#   bash bin/feature-video/setup-voice.sh [yarngpt]
set -euo pipefail
cache="${NF_VIDEO_CACHE:-$HOME/.cache/nfelia-video}"
mkdir -p "$cache"
cd "$cache"

model_sha=fbae9257e1e05ffc727e951ef9b9c98418e6d79f1c9b6b13bd59f5c9028a1478

if [ ! -f model_quantized.onnx ]; then
    echo "[voice] fetching the Kokoro model"
    rm -rf shards && mkdir shards
    (cd shards && npm pack --silent kokoro-q8-shards@1.0.0 >/dev/null && tar xzf kokoro-q8-shards-1.0.0.tgz)
    cat shards/package/kokoro-q8.part{0,1,2,3,4,5}.bin > model_quantized.onnx.part
    echo "$model_sha  model_quantized.onnx.part" | sha256sum -c --quiet
    mv model_quantized.onnx.part model_quantized.onnx
    rm -rf shards
fi

if [ ! -d voices ]; then
    echo "[voice] fetching the voices"
    rm -rf runtime && mkdir runtime
    (cd runtime && npm pack --silent kokoro-local-runtime@0.1.0 >/dev/null && tar xzf kokoro-local-runtime-0.1.0.tgz)
    mv runtime/package/voices voices
    rm -rf runtime
fi

if [ ! -x venv/bin/python ]; then
    echo "[voice] making the Python environment"
    python3 -m venv venv
    venv/bin/pip install -q kokoro-onnx==0.6.1 soundfile
fi

if [ ! -f voices.npz ]; then
    # kokoro-onnx reads every voice from one .npz: 510 style rows of 256 each
    venv/bin/python -I - <<'EOF'
import glob, os, numpy as np
voices = {os.path.basename(p)[:-4]: np.fromfile(p, dtype=np.float32).reshape(510, 1, 256) for p in glob.glob('voices/*.bin')}
np.savez('voices.npz', **voices)
print(f'[voice] {len(voices)} voices ready')
EOF
fi
if [ "${1:-}" = yarngpt ]; then
    hf=https://huggingface.co/novateur
    mkdir -p yarngpt
    if [ ! -f yarngpt/wavtokenizer.ckpt ]; then
        echo "[voice] fetching the WavTokenizer (1.7 GB)"
        curl -sSfL -o yarngpt/wavtokenizer.yaml "$hf/WavTokenizer-medium-speech-75token/resolve/main/wavtokenizer_mediumdata_frame75_3s_nq1_code4096_dim512_kmeans200_attn.yaml"
        # The checkpoint YarnGPT was trained with: since deleted from the repo's
        # main branch (the _v2 file there garbles its speech), so fetched from
        # the commit that added it
        curl -sSfL -o yarngpt/wavtokenizer.ckpt.part "$hf/WavTokenizer-large-speech-75token/resolve/1cc9faee31025548fbae6ffe11115d7207093638/wavtokenizer_large_speech_320_24k.ckpt"
        echo "7450020c154f6aba033cb8651466cb79cb1b1cdd10ea64eaba68e7871cabcc5a  yarngpt/wavtokenizer.ckpt.part" | sha256sum -c --quiet
        mv yarngpt/wavtokenizer.ckpt.part yarngpt/wavtokenizer.ckpt
    fi
    if [ ! -x yarn/bin/python ]; then
        echo "[voice] making YarnGPT's Python environment"
        python3 -m venv yarn
        yarn/bin/pip install -q torch torchaudio --index-url https://download.pytorch.org/whl/cpu
        yarn/bin/pip install -q outetts==0.3.3 yarngpt==0.2.0 soundfile
    fi
fi
echo "[voice] ready in $cache"
