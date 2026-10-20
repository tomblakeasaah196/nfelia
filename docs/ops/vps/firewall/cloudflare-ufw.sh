#!/usr/bin/env bash
# Allow 80 and 443 only from Cloudflare's published ranges (ufw), replacing
# the previous set. Run weekly by cloudflare-ufw.timer. If the lists cannot
# be fetched, the existing rules are left alone.
set -euo pipefail
v4="$(curl -fsS --max-time 20 https://www.cloudflare.com/ips-v4)" || exit 0
v6="$(curl -fsS --max-time 20 https://www.cloudflare.com/ips-v6)" || exit 0
[ "$(printf '%s\n' "$v4" | wc -l)" -ge 10 ] || exit 0
# drop old Cloudflare rules (tagged by comment), then add the current set
ufw status numbered | grep -E "Cloudflare" | awk -F'[][]' '{print $2}' | sort -rn | while read -r n; do yes | ufw delete "$n" >/dev/null; done
for ip in $v4 $v6; do ufw allow proto tcp from "$ip" to any port 80,443 comment 'Cloudflare' >/dev/null; done
ufw reload >/dev/null
echo "cloudflare-ufw: $(printf '%s\n' "$v4" "$v6" | wc -l) ranges allowed"
