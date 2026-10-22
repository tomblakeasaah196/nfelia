#!/usr/bin/env bash
# A short, narrated video of new features, filmed in the real app at phone
# size, to send a business on WhatsApp. See README.md beside this file.
#
#   bash bin/feature-video/make.sh bin/feature-video/videos/<name> [out.mp4]
#
# The folder holds scenes.json (what to film and say) and seed.php (the demo
# data it films). The seed WIPES the database the checkout's .env points at,
# so like the tests it only runs with APP_ENV local or testing; the app must
# be served at APP_URL. A video that reads Nfelia's emails ("mail" steps)
# needs the mail catcher's folder in NF_MAIL_DIR (see README.md).
set -euo pipefail
here="$(cd "$(dirname "$0")" && pwd)"
root="$(cd "$here/../.." && pwd)"
video="$(cd "${1:?Name the video folder, like bin/feature-video/videos/2026-10-late-sales}" && pwd)"
out="${2:-$video/out/$(basename "$video").mp4}"
cache="${NF_VIDEO_CACHE:-$HOME/.cache/nfelia-video}"
env_of() { grep -E "^$1=" "$root/.env" | tail -1 | cut -d= -f2- || true; }

case "$(env_of APP_ENV)" in local|testing) ;; *) echo "Refusing: the seed wipes the database, and APP_ENV in .env is not local or testing." >&2; exit 2;; esac
app_url="$(env_of APP_URL)"
curl -sf -o /dev/null "$app_url/login" || { echo "The app isn't answering at $app_url. Serve it first (see README.md)." >&2; exit 2; }
command -v ffmpeg >/dev/null || { echo "ffmpeg is needed (apt install ffmpeg)." >&2; exit 2; }
# Playwright: the project's, else a global install
export NODE_PATH="$root/node_modules:$(npm root -g)"
node -e "require('playwright')" 2>/dev/null || { echo "Playwright is needed: npm install --no-save playwright@1.56.1 (and npx playwright install chromium)." >&2; exit 2; }

# The narrator's engine (scenes.json "voice"): Kokoro unless it names YarnGPT
engine="$(node -e "const v = require(process.argv[1]).voice; console.log((v && v.engine) || 'kokoro')" "$video/scenes.json")"
bash "$here/setup-voice.sh" "$engine"
speaker="$cache/venv/bin/python"
[ "$engine" = yarngpt ] && speaker="$cache/yarn/bin/python"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT
mkdir -p "$(dirname "$out")"

echo "[video] seeding the demo data"
ids="$(cd "$root" && php "$video/seed.php" | tail -1)"
echo "[video] speaking"
"$speaker" -I "$here/voice.py" "$video/scenes.json" "$work" "$cache"
echo "[video] filming"
(cd "$root" && APP_URL="$app_url" NF_MAIL_DIR="${NF_MAIL_DIR:-}" node "$here/record.js" "$video/scenes.json" "$ids" "$work")
echo "[video] drawing the frame and captions"
(cd "$root" && node "$here/stage.js" "$video/scenes.json" "$work")
echo "[video] editing"
"$cache/venv/bin/python" -I "$here/compose.py" "$work" "$out" "$video/scenes.json"
echo "[video] $out"
