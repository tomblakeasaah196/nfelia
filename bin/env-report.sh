#!/usr/bin/env bash
# =====================================================================
#  Nfelia environment report
#
#  Run it in the cPanel Terminal (or any shell on the server):
#      bash ~/nfelia-env-report.sh
#  It writes ~/nfelia-env-report-<date>.txt and prints the same text.
#  Secrets are masked, so the report is safe to paste into a chat.
#
#  Optional: a READ-ONLY Cloudflare token lets it read the zone too:
#      CF_API_TOKEN=xxxxxxxx bash ~/nfelia-env-report.sh
#
#  What it touches: nothing permanent. A probe file with a random name
#  sits in the docroot for a few seconds (to read the web server's real
#  PHP settings) and is removed; a structure-only copy of the database
#  schema is written to ~/nfelia-schema-live-<date>.sql in your HOME
#  folder, never under public_html.
#
#  Overrides (for a VPS or a local copy):
#      NF_DOCROOT=/var/www/nfelia NF_REPO=/srv/nfelia NF_SITE=https://nfelia.com bash bin/env-report.sh
# =====================================================================
set +e
set -u
umask 077

HOME_DIR="${HOME:-$(getent passwd "$(id -un)" | cut -d: -f6)}"
DOCROOT="${NF_DOCROOT:-$HOME_DIR/public_html/nfelia.com}"
REPO="${NF_REPO:-$HOME_DIR/repositories/nfelia}"
SITE="${NF_SITE:-https://nfelia.com}"
HOST="$(printf '%s' "$SITE" | sed -E 's#^https?://##; s#[:/].*$##')"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="$HOME_DIR/nfelia-env-report-$STAMP.txt"
SCHEMA_OUT="$HOME_DIR/nfelia-schema-live-$STAMP.sql"
TMPD="$(mktemp -d "${TMPDIR:-/tmp}/nfenv.XXXXXX")"
PROBE=""
cleanup() { [ -n "$PROBE" ] && rm -f "$PROBE"; rm -rf "$TMPD"; }
trap cleanup EXIT INT TERM

PHP_BIN=""
for p in /usr/local/bin/ea-php83 /opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/php /usr/bin/php; do
    if [ -x "$p" ]; then PHP_BIN="$p"; break; fi
done

say()  { printf '\n==================== %s ====================\n' "$*"; }
sub()  { printf -- '\n--- %s\n' "$*"; }
have() { command -v "$1" >/dev/null 2>&1; }
# Masks what looks like a secret: passwords inside URLs, Paystack and
# GitHub keys, and very long hex strings (API keys, not git commit ids).
mask() {
    sed -E \
        -e 's#(://[^/@[:space:]]+):[^@/[:space:]]+@#\1:***@#g' \
        -e 's/((sk|pk)_(live|test)_)[A-Za-z0-9]{4}[A-Za-z0-9]*/\1***/g' \
        -e 's/(ghp_|github_pat_|glpat-)[A-Za-z0-9_]+/\1***/g' \
        -e 's/\b[A-Fa-f0-9]{48,}\b/<hex-token>/g'
}
mask_mail() { sed -E 's/[A-Za-z0-9._%+-]+@([A-Za-z0-9.-]+\.[A-Za-z]{2,})/***@\1/g'; }
envval() { [ -f "$DOCROOT/.env" ] && grep -E "^$1=" "$DOCROOT/.env" | tail -1 | cut -d= -f2- | tr -d '\r' | sed -E "s/^[\"']//; s/[\"']$//"; }
code() { curl -sS -o /dev/null -w '%{http_code}' --max-time 15 "$@" 2>/dev/null || printf '000'; }

report() {
say "NFELIA ENVIRONMENT REPORT $STAMP"
echo "site=$SITE docroot=$DOCROOT repo=$REPO php=${PHP_BIN:-none} user=$(id -un) home=$HOME_DIR"

say "1. HOST"
date -Is; uname -a; hostname -f 2>/dev/null || hostname
sub "OS"; (cat /etc/redhat-release 2>/dev/null; grep -E '^(PRETTY_NAME|VERSION)=' /etc/os-release 2>/dev/null)
sub "CPU / memory / disk"; nproc; free -m | head -2; df -h "$HOME_DIR" | tail -1
sub "CloudLinux account limits (cPanel API)"
if have uapi; then uapi ResourceUsage get_usages 2>&1 | head -80; else echo "uapi not available"; fi
sub "home folder (what else lives here)"; ls -la "$HOME_DIR" | head -60

say "2. cPANEL: DOMAINS, DOCROOTS, SSL, EMAIL, CRON"
if have uapi; then
    sub "domains"; uapi DomainInfo list_domains 2>&1 | head -60
    sub "docroots"; uapi DomainInfo domains_data format=list 2>&1 | grep -E 'domain:|documentroot:|servername:|type:' | head -80
    sub "SSL installed hosts (certificate text omitted)"; uapi SSL installed_hosts 2>&1 | grep -E 'servername|not_after|not_before|organizationName|is_self_signed|docroot|fqdns|commonName' | head -140
    sub "the certificate the origin presents for $HOST (what Cloudflare connects to)"
    (timeout 10 openssl s_client -connect 127.0.0.1:443 -servername "$HOST" </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates -ext subjectAltName 2>/dev/null) || echo "(could not read)"
    sub "email accounts"; uapi Email list_pops 2>&1 | grep -E 'email:' | head -40
    sub "MX"; uapi Email list_mxs domain="$HOST" 2>&1 | head -30
    sub "SPF / DKIM / PTR as cPanel sees them"
    uapi EmailAuth validate_current_spfs domain="$HOST" 2>&1 | head -40
    uapi EmailAuth validate_current_dkims domain="$HOST" 2>&1 | head -40
    uapi EmailAuth validate_current_ptrs domain="$HOST" 2>&1 | head -30
    sub "backups the panel knows about"; uapi Backup list_backups 2>&1 | head -20
else
    echo "uapi not available (not a cPanel shell?)"
fi
sub "crontab"; crontab -l 2>&1

say "3. DNS AS SEEN FROM THIS SERVER"
for q in "A $HOST" "AAAA $HOST" "A www.$HOST" "A ops.$HOST" "A mail.$HOST" "A cpanel.$HOST" "A webmail.$HOST" "A webdisk.$HOST" "NS $HOST" "MX $HOST" "TXT $HOST" "TXT _dmarc.$HOST" "TXT default._domainkey.$HOST" "CAA $HOST" "A nfelia.lpc.cm"; do
    set -- $q
    printf '%-34s ' "$1 $2"
    if have dig; then dig +short "$2" "$1" | tr '\n' ' '; elif have host; then host -t "$1" "$2" | tr '\n' ' '; else getent hosts "$2" | tr '\n' ' '; fi
    echo
done
sub "this server's own addresses"; hostname -I 2>/dev/null; curl -s --max-time 8 https://api.ipify.org 2>/dev/null; echo

say "4. PHP (command line: ${PHP_BIN:-none})"
if [ -n "$PHP_BIN" ]; then
    "$PHP_BIN" -v | head -1
    sub "extensions"; "$PHP_BIN" -m | tr '\n' ' '; echo
    sub "opcache (CLI view; the web view is in section 5)"; "$PHP_BIN" -i 2>/dev/null | grep -iE '^(opcache\.enable|opcache\.memory_consumption|Zend OPcache)' | head -4 || true
    sub "ini (CLI)"; "$PHP_BIN" -i 2>/dev/null | grep -E '^(Loaded Configuration File|Scan this dir|memory_limit|max_execution_time|post_max_size|upload_max_filesize|disable_functions|allow_url_fopen|date\.timezone|error_log|log_errors|display_errors|session\.save_path|session\.gc_maxlifetime|opcache\.enable|opcache\.enable_cli|opcache\.memory_consumption|opcache\.max_accelerated_files|opcache\.validate_timestamps|opcache\.revalidate_freq|opcache\.jit|opcache\.jit_buffer_size|opcache\.interned_strings_buffer|realpath_cache_size|max_input_vars|open_basedir|sendmail_path) ' | head -40
    sub "other PHP binaries"; ls -la /usr/local/bin/php /usr/local/bin/ea-php8* /opt/cpanel/ea-php8*/root/usr/bin/php 2>/dev/null
else
    echo "no PHP binary found in the usual places"
fi
sub "tools"; for b in ffmpeg rsync git node npm mysql mysqldump dig jq python3 openssl; do printf '%-10s %s\n' "$b" "$(command -v $b 2>/dev/null || echo missing)"; done
[ -f "$HOME_DIR/composer.phar" ] && [ -n "$PHP_BIN" ] && "$PHP_BIN" "$HOME_DIR/composer.phar" --version 2>/dev/null | head -1

say "5. PHP AS THE WEB SERVER RUNS IT (probe through $SITE)"
if [ -d "$DOCROOT" ]; then
    T="$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')"
    PROBE="$DOCROOT/nf-env-probe-$T.php"
    cat > "$PROBE" <<'PHPEOF'
<?php
header('Content-Type: text/plain'); header('Cache-Control: no-store');
echo "sapi=", PHP_SAPI, " php=", PHP_VERSION, " server=", $_SERVER['SERVER_SOFTWARE'] ?? '', "\n";
echo "remote_addr=", $_SERVER['REMOTE_ADDR'] ?? '', " cf_connecting_ip=", isset($_SERVER['HTTP_CF_CONNECTING_IP']) ? 'present' : 'absent', " cf_ray=", isset($_SERVER['HTTP_CF_RAY']) ? 'present' : 'absent', " x_nfelia_edge=", isset($_SERVER['HTTP_X_NFELIA_EDGE']) ? 'present' : 'absent', " https=", $_SERVER['HTTPS'] ?? '', "\n";
echo "(remote_addr above is the address of the machine running this report: on this server LiteSpeed puts the visitor's own address there, not Cloudflare's)\n";
foreach (['memory_limit','max_execution_time','max_input_time','post_max_size','upload_max_filesize','max_input_vars','disable_functions','allow_url_fopen','date.timezone','error_log','log_errors','display_errors','session.save_path','session.gc_maxlifetime','session.gc_probability','session.gc_divisor','session.cookie_secure','session.cookie_samesite','opcache.enable','opcache.memory_consumption','opcache.max_accelerated_files','opcache.validate_timestamps','opcache.revalidate_freq','opcache.jit','opcache.jit_buffer_size','opcache.interned_strings_buffer','realpath_cache_size','realpath_cache_ttl','open_basedir','upload_tmp_dir','sys_temp_dir','output_buffering','zlib.output_compression','expose_php'] as $k) echo $k, "=", ini_get($k), "\n";
echo "loaded_ini=", php_ini_loaded_file(), " scanned=", php_ini_scanned_files(), "\n";
echo "tmp=", sys_get_temp_dir(), " tz=", date_default_timezone_get(), " now=", date('c'), "\n";
foreach (['litespeed_finish_request','fastcgi_finish_request','imagewebp','imagecreatefromwebp','exec','popen','shell_exec','proc_open','mail','curl_init','transliterator_transliterate','sodium_crypto_secretbox','finfo_open','random_bytes','apcu_fetch'] as $f) echo $f, "=", function_exists($f) ? 'yes' : 'no', " ";
echo "\n";
if (function_exists('opcache_get_status') && ($s = @opcache_get_status(false))) { echo "opcache: enabled=", json_encode($s['opcache_enabled']), " cache_full=", json_encode($s['cache_full']), " scripts=", $s['opcache_statistics']['num_cached_scripts'] ?? '?', " hit_rate=", round($s['opcache_statistics']['opcache_hit_rate'] ?? 0, 1), " mem_used_mb=", round(($s['memory_usage']['used_memory'] ?? 0) / 1048576), " mem_free_mb=", round(($s['memory_usage']['free_memory'] ?? 0) / 1048576), " jit=", json_encode($s['jit']['enabled'] ?? null), "\n"; } else echo "opcache: no status (disabled or restricted)\n";
echo "extensions=", implode(',', get_loaded_extensions()), "\n";
echo "gd=", json_encode(function_exists('gd_info') ? array_filter(gd_info(), 'is_bool') : null), "\n";
echo "uid=", getmyuid(), " user=", get_current_user(), " umask=", sprintf('%04o', umask()), "\n";
$sp = ini_get('session.save_path') ?: sys_get_temp_dir(); $n = @count(glob(rtrim($sp, '/') . '/sess_*') ?: []); echo "session_files_in_save_path=", $n, " (", $sp, ")\n";
PHPEOF
    chmod 644 "$PROBE"
    curl -sS --max-time 20 "$SITE/nf-env-probe-$T.php" 2>&1 | grep -v "^/opt/cpanel/.*\.ini,$" | head -90
    rm -f "$PROBE"; PROBE=""
else
    echo "docroot $DOCROOT not found"
fi

say "6. WEB: RESPONSE HEADERS, TIMING, EXPOSURE"
sub "home page headers (through Cloudflare)"; curl -sS -o /dev/null -D - --max-time 20 "$SITE/" 2>&1 | head -40
sub "login page headers"; curl -sS -o /dev/null -D - --max-time 20 "$SITE/login" 2>&1 | grep -iE '^(HTTP|cache-control|set-cookie|strict|content-security|x-frame|x-content|referrer|permissions|server|cf-cache|age|x-powered):'
sub "asset headers (app.css)"; curl -sS -o /dev/null -D - --max-time 20 "$SITE/assets/css/app.css" 2>&1 | grep -iE '^(HTTP|cache-control|expires|etag|last-modified|cf-cache-status|age|content-encoding|vary):'
sub "ops host"; curl -sS -o /dev/null -D - --max-time 20 "https://ops.$HOST/" 2>&1 | grep -iE '^(HTTP|cache-control|x-frame|content-security|cf-cache-status|location):'
sub "time to first byte, 3 tries"; for i in 1 2 3; do curl -sS -o /dev/null -w 'login: %{http_code} ttfb=%{time_starttransfer}s total=%{time_total}s\n' --max-time 20 "$SITE/login"; done
sub "paths that must NOT be readable (expect 403/404/405; a 200 is a problem)"
for p in /.env /.git/HEAD /composer.json /composer.lock /storage/release /db/schema.sql /README.md /DEPLOY.md /.deployignore /vendor/autoload.php /tests/ /docs/ /config/app.php /cron/planner.php /error_log /api/ajax/error_log /api/error_log /webhook/error_log /cron/error_log /storage/logs/php_errors.log /smartqaq_nfelia_core.sql /uploads/ /phpinfo.php /info.php /.user.ini /php.ini /webhook/deploy.php; do
    printf '%-34s %s\n' "$p" "$(code "$SITE$p")"
done
sub "paths that MUST be readable (expect 200)"
for p in /sw.js /site.webmanifest /offline.html /views/404.php /assets/css/app.css /robots.txt; do printf '%-34s %s\n' "$p" "$(code "$SITE$p")"; done
sub "does the origin answer traffic that did NOT come through Cloudflare? (200/302 = yes)"
for ip in $(hostname -I 2>/dev/null); do
    printf 'https direct to %-16s -> %s\n' "$ip" "$(code -k --resolve "$HOST:443:$ip" "https://$HOST/login")"
    printf 'http  direct to %-16s -> %s\n' "$ip" "$(code -H "Host: $HOST" "http://$ip/login")"
done

sub "origin lock (docs/ops/SECURITY.md §5): state of the deployed .htaccess"
if grep -q 'nf-edge-lock-begin' "$DOCROOT/.htaccess" 2>/dev/null; then
    if grep -q '^RewriteCond armed \^armed\$' "$DOCROOT/.htaccess"; then
        echo "ARMED: requests without Cloudflare's X-Nfelia-Edge header are refused (section 5 above must say x_nfelia_edge=present, or the site is unreachable)"
    elif grep -q '__EDGE_ARMED__' "$DOCROOT/.htaccess"; then
        echo "present but inert: the placeholders were never substituted (the file was copied without bin/deploy.sh); the lock is off"
    else
        echo "block present in an unexpected state: read the lines between the nf-edge-lock markers"
    fi
else
    echo "off: no lock block in the deployed .htaccess (no EDGE_TOKEN in .env at the last deploy, or an older file)"
fi

say "7. REPO CHECKOUT, DEPLOY, DOCROOT"
if [ -d "$REPO/.git" ]; then
    sub "remote / head"; git -C "$REPO" remote -v | mask; git -C "$REPO" rev-parse --abbrev-ref HEAD; git -C "$REPO" log -1 --format='%h %ci %s'; git -C "$REPO" status --short | head -10
    sub "credential helper"; git -C "$REPO" config --get credential.helper; git -C "$REPO" config --get remote.origin.url | mask
    sub "stored git credential (masked)"; [ -f "$HOME_DIR/.git-credentials" ] && sed -E 's#(://[^:/@]+:)[^@]{4}[^@]*@#\1***@#' "$HOME_DIR/.git-credentials" || echo "(none)"
    sub "can that credential read the repository through the API?"
    GH_TOKEN_LINE="$(grep -m1 'github.com' "$HOME_DIR/.git-credentials" 2>/dev/null || true)"
    if [ -n "$GH_TOKEN_LINE" ]; then
        GH_TOKEN="$(printf '%s' "$GH_TOKEN_LINE" | sed -E 's#.*://[^:/@]+:([^@]+)@.*#\1#')"
        curl -sS --max-time 15 -o /dev/null -D - -H "Authorization: token $GH_TOKEN" https://api.github.com/repos/tomblakeasaah196/nfelia 2>/dev/null | grep -iE '^(HTTP|x-oauth-scopes|x-accepted-github-permissions|github-authentication-token-expiration)' || echo "(no answer)"
        unset GH_TOKEN
    else
        echo "(no github.com credential stored)"
    fi
else
    echo "repo checkout not found at $REPO"
fi
sub "last deploy log lines"; tail -25 "$HOME_DIR/deploy-nfelia.log" 2>/dev/null | mask
sub "release file"; cat "$DOCROOT/storage/release" 2>/dev/null
sub "docroot root listing"; ls -la "$DOCROOT" 2>/dev/null | head -80
sub "entries in the docroot that the repo does not have (lingering from old deploys)"
if [ -d "$REPO/.git" ] && [ -d "$DOCROOT" ]; then
    git -C "$REPO" ls-tree --name-only HEAD | sort > "$TMPD/repo_top"
    printf '%s\n' .env .env.example .htaccess .user.ini php.ini error_log uploads storage vendor cgi-bin .well-known >> "$TMPD/repo_top"
    sort -u "$TMPD/repo_top" -o "$TMPD/repo_top"
    ls -A "$DOCROOT" | sort > "$TMPD/doc_top"
    comm -13 "$TMPD/repo_top" "$TMPD/doc_top"
fi
sub "permissions of the sensitive files"; ls -lad "$DOCROOT/.env" "$DOCROOT/.htaccess" "$DOCROOT/storage" "$DOCROOT/storage/private" "$DOCROOT/storage/private/seal.key" "$DOCROOT/storage/sessions" "$DOCROOT/uploads" 2>&1 | head -20
sub "cPanel-made ini files in the docroot"; for f in .user.ini php.ini; do echo "[$f]"; cat "$DOCROOT/$f" 2>/dev/null || echo "(none)"; done
sub ".htaccess files under the docroot"; find "$DOCROOT" -maxdepth 3 -name .htaccess -not -path '*/vendor/*' 2>/dev/null
sub "deployed .htaccess vs repo .htaccess (differences; none is good; the origin-lock block is left out of the comparison since the deploy rewrites it)"
diff <(sed '/# nf-edge-lock-begin/,/# nf-edge-lock-end/d' "$REPO/.htaccess") <(sed '/# nf-edge-lock-begin/,/# nf-edge-lock-end/d' "$DOCROOT/.htaccess") 2>&1 | head -40
sub "uploads/.htaccess"; cat "$DOCROOT/uploads/.htaccess" 2>/dev/null || echo "(none)"
sub "error_log files cPanel may have written (public if present at the root!)"; find "$DOCROOT" -maxdepth 3 -name error_log -not -path '*/vendor/*' -exec ls -la {} \; 2>/dev/null

say "8. .ENV (secret values masked)"
if [ -f "$DOCROOT/.env" ]; then
    while IFS= read -r line || [ -n "$line" ]; do
        case "$line" in ''|'#'*) continue;; esac
        k="${line%%=*}"; v="${line#*=}"; k="$(printf '%s' "$k" | tr -d '[:space:]')"
        case "$k" in
            PAYSTACK_SECRET_KEY|PAYSTACK_WEBHOOK_SECRET) printf '%s=%s*** (%d chars)\n' "$k" "${v:0:8}" "${#v}" ;;
            DB_PASS|MAIL_PASSWORD|APP_KEY|SEAL_KEY|DEPLOY_WEBHOOK_SECRET|VAPID_PRIVATE_KEY|CLOUDFLARE_API_TOKEN|*SECRET*|*PASSWORD*|*PRIVATE*|*TOKEN*) printf '%s=<set, %d chars>\n' "$k" "${#v}" ;;
            *) printf '%s=%s\n' "$k" "$v" ;;
        esac
    done < "$DOCROOT/.env"
    if [ -f "$REPO/.env.example" ]; then
        grep -oE '^[A-Z_]+=' "$REPO/.env.example" | tr -d = | sort -u > "$TMPD/ex"; grep -oE '^[A-Z_]+=' "$DOCROOT/.env" | tr -d = | sort -u > "$TMPD/env"
        sub "keys in .env.example that .env lacks"; comm -23 "$TMPD/ex" "$TMPD/env"
        sub "keys in .env that .env.example does not list"; comm -13 "$TMPD/ex" "$TMPD/env"
    fi
else
    echo ".env not found at $DOCROOT/.env"
fi

say "9. STORAGE"
du -sh "$DOCROOT" "$DOCROOT/uploads" "$DOCROOT/storage" "$DOCROOT/vendor" "$DOCROOT/assets" "$HOME_DIR/momo_receipts" "$(envval MOMO_RECEIPT_DIR)" 2>/dev/null | sort -u
sub "storage subfolders"; du -sh "$DOCROOT"/storage/* 2>/dev/null
sub "uploads subfolders"; du -sh "$DOCROOT"/uploads/* 2>/dev/null | sort -rh | head -20
sub "counts"; printf 'upload files=%s  session files=%s  og-cache=%s  variants=%s  private=%s\n' "$(find "$DOCROOT/uploads" -type f 2>/dev/null | wc -l)" "$(find "$DOCROOT/storage/sessions" -type f 2>/dev/null | wc -l)" "$(find "$DOCROOT/storage/og-cache" -type f 2>/dev/null | wc -l)" "$(find "$DOCROOT/uploads/cache" -type f 2>/dev/null | wc -l)" "$(find "$DOCROOT/storage/private" -type f 2>/dev/null | wc -l)"
sub "logs"; ls -la "$DOCROOT/storage/logs" 2>/dev/null; for f in "$DOCROOT/storage/logs/php_errors.log" "$DOCROOT/error_log"; do [ -f "$f" ] && { echo "[$f] lines=$(wc -l < "$f")"; tail -5 "$f" | mask | mask_mail | cut -c1-200; }; done

say "10. DATABASE"
DB_HOST="$(envval DB_HOST)"; DB_PORT="$(envval DB_PORT)"; DB_NAME="$(envval DB_NAME)"; DB_USER="$(envval DB_USER)"; DB_PASS="$(envval DB_PASS)"
echo "DB_HOST=${DB_HOST:-localhost}  DB_NAME=$DB_NAME  DB_USER=$DB_USER"
# What the panel knows needs no database password: users, privileges, schema.
if have uapi; then
    sub "database users and their databases (panel)"; uapi Mysql list_users 2>&1 | sed -n '/^  data:/,/^  errors:/p' | grep -v '^  errors:' | head -60
    sub "privileges of the app user on the database (panel)"; uapi Mysql get_privileges_on_database user="$DB_USER" database="$DB_NAME" 2>&1 | sed -n '/^  data:/,/^  errors:/p' | grep -v '^  errors:' | head -30
fi
sub "structure-only schema copy -> $SCHEMA_OUT (kept in HOME, not printed)"
done_dump=no
if have uapi && uapi --output=jsonpretty Mysql dump_database_schema dbname="$DB_NAME" > "$TMPD/schema.json" 2>"$TMPD/dumperr"; then
    if have perl && perl -MJSON::PP -e1 2>/dev/null; then
        perl -MJSON::PP -0777 -ne 'my $j = decode_json($_); die "uapi: " . join("; ", @{$j->{result}{errors} || []}) . "\n" unless $j->{result}{status}; print $j->{result}{data}' "$TMPD/schema.json" > "$SCHEMA_OUT" 2>"$TMPD/dumperr" && done_dump=yes
    elif have node; then
        node -e 'const j = JSON.parse(require("fs").readFileSync(process.argv[1], "utf8")); if (!j.result.status) { console.error("uapi: " + (j.result.errors || []).join("; ")); process.exit(1); } process.stdout.write(j.result.data);' "$TMPD/schema.json" > "$SCHEMA_OUT" 2>"$TMPD/dumperr" && done_dump=yes
    else
        echo "uapi answered, but neither perl (JSON::PP) nor node is here to unpack it" > "$TMPD/dumperr"
    fi
    [ "$done_dump" = yes ] && echo "(through the panel API: uapi Mysql dump_database_schema)"
    # Run 3 on the server got 34 lines back with no table in them: check, and
    # if so keep that answer aside for a look and let mysqldump have its turn.
    if [ "$done_dump" = yes ] && [ "$(grep -c 'CREATE TABLE' "$SCHEMA_OUT")" = 0 ]; then
        echo "...but with no CREATE TABLE in it ($(wc -c < "$SCHEMA_OUT") bytes). Its first lines, for the record:"
        head -c 600 "$SCHEMA_OUT" | tr '\n' ' ' | cut -c1-600; echo
        mv "$SCHEMA_OUT" "$TMPD/uapi-schema.txt"; done_dump=no
    fi
fi
if have mysql && [ -n "$DB_USER" ]; then
    export MYSQL_PWD="$DB_PASS"
    BASEOPTS="-u $DB_USER"; [ -n "$DB_PORT" ] && BASEOPTS="$BASEOPTS -P $DB_PORT"
    # Try the host .env names (from inside CageFS a public IP may not be
    # reachable), then TCP on loopback, then the local socket. Say which one
    # answered and how each other one failed: the host part of the grant
    # decides, and that is what DB_HOST=localhost depends on.
    WAY=""; MY=""
    for way in "-h ${DB_HOST:-localhost} --protocol=tcp" "-h 127.0.0.1 --protocol=tcp" "--protocol=socket"; do
        if out=$(mysql $BASEOPTS $way -N -e "SELECT CONCAT('signed in as ', CURRENT_USER())" "$DB_NAME" 2>&1); then
            echo "$out  ($way)"; WAY="$way"; MY="mysql --default-character-set=utf8mb4 $BASEOPTS $WAY $DB_NAME"; break
        else
            echo "no  ($way): $(echo "$out" | head -1 | cut -c1-150)"
        fi
    done
    if [ -z "$MY" ]; then
        cat <<EOT
The shell cannot sign in as $DB_USER with the password in .env by any route,
while the site itself does (so the account exists for the host the web
server connects from, DB_HOST=${DB_HOST:-localhost}). Usual causes: no 'localhost'
entry for this user, or a different password on it. Look at cPanel ->
MySQL Databases -> Current Users (is $DB_USER there?) and cPanel ->
Remote MySQL (which hosts are added?). To confirm by hand, typing the
.env password when asked:
    mysql --protocol=socket -u $DB_USER -p $DB_NAME -e 'SELECT CURRENT_USER()'
Resetting the user's password in cPanel (and putting the new one in .env
in the same minute) sets it on every host entry; DB_HOST=localhost works
after that.
EOT
        db_skip() { echo "(skipped: no connection)"; }; MY=db_skip
    fi
    sub "which hosts the app user may connect from"; $MY -N -e "SELECT CONCAT(user, '@', host) FROM mysql.user WHERE user = '$DB_USER';" 2>/dev/null || echo "(mysql.user not readable by this user; SHOW GRANTS below names the host of this connection)"
    sub "server"; $MY -t -e "SELECT VERSION() v, @@version_comment c, @@hostname h;" 2>&1
    sub "variables that matter"; $MY -t -e "SHOW VARIABLES WHERE Variable_name IN ('innodb_buffer_pool_size','innodb_log_file_size','innodb_flush_log_at_trx_commit','innodb_file_per_table','max_connections','max_user_connections','wait_timeout','interactive_timeout','max_allowed_packet','tmp_table_size','max_heap_table_size','sort_buffer_size','join_buffer_size','table_open_cache','thread_cache_size','character_set_server','collation_server','character_set_database','collation_database','time_zone','system_time_zone','sql_mode','slow_query_log','long_query_time','log_queries_not_using_indexes','performance_schema','query_cache_type','query_cache_size','max_statement_time','lock_wait_timeout','innodb_lock_wait_timeout','event_scheduler');" 2>&1
    sub "status"; $MY -t -e "SHOW GLOBAL STATUS WHERE Variable_name IN ('Uptime','Threads_connected','Max_used_connections','Connections','Slow_queries','Questions','Innodb_buffer_pool_reads','Innodb_buffer_pool_read_requests','Created_tmp_disk_tables','Created_tmp_tables','Select_full_join','Select_scan','Table_locks_waited','Aborted_connects');" 2>&1
    sub "grants of the app user"; $MY -e "SHOW GRANTS;" 2>&1 | mask
    sub "tables: rows, size, engine, collation, columns, indexes"
    $MY -t -e "SELECT t.table_name, t.engine, t.table_rows, ROUND((t.data_length+t.index_length)/1048576,2) mb, t.table_collation, (SELECT COUNT(*) FROM information_schema.columns c WHERE c.table_schema=t.table_schema AND c.table_name=t.table_name) cols, (SELECT COUNT(DISTINCT s.index_name) FROM information_schema.statistics s WHERE s.table_schema=t.table_schema AND s.table_name=t.table_name) idx, t.auto_increment FROM information_schema.tables t WHERE t.table_schema=DATABASE() ORDER BY t.table_name;" 2>&1
    sub "routines, triggers, events, views"; $MY -t -e "SELECT routine_name, routine_type, created FROM information_schema.routines WHERE routine_schema=DATABASE(); SELECT trigger_name, event_object_table FROM information_schema.triggers WHERE trigger_schema=DATABASE(); SELECT event_name, status FROM information_schema.events WHERE event_schema=DATABASE(); SELECT table_name FROM information_schema.views WHERE table_schema=DATABASE();" 2>&1
    sub "applied migrations"; $MY -t -e "SELECT version, applied_at FROM schema_migrations ORDER BY version;" 2>&1 | tail -45
    sub "business figures"; $MY -t -e "SELECT (SELECT COUNT(*) FROM businesses) businesses, (SELECT COUNT(*) FROM users WHERE is_active=1) active_users, (SELECT COUNT(*) FROM invoices) invoices, (SELECT COUNT(*) FROM orders) orders, (SELECT COUNT(*) FROM customers) customers, (SELECT COUNT(*) FROM products) products, (SELECT COUNT(*) FROM notifications) notifications, (SELECT COUNT(*) FROM notify_outbox) outbox, (SELECT COUNT(*) FROM error_events) error_events, (SELECT COUNT(*) FROM auth_throttle) throttle_rows, (SELECT COUNT(*) FROM sessions) remember_rows, (SELECT COUNT(*) FROM sessions WHERE expires_at < NOW()) remember_expired, (SELECT COUNT(*) FROM push_subscriptions) push_devices, (SELECT COUNT(*) FROM records_books) records_books;" 2>&1
    sub "subscriptions by status"; $MY -t -e "SELECT status, COUNT(*) n FROM subscriptions GROUP BY status;" 2>&1
    sub "shops (names only)"; $MY -t -e "SELECT id, subdomain, subdomain_status, landing_enabled FROM businesses ORDER BY id;" 2>&1
    sub "outbox by status"; $MY -t -e "SELECT status, channel, COUNT(*) n FROM notify_outbox GROUP BY status, channel;" 2>&1
    if [ "$done_dump" != yes ]; then
        sub "schema copy, second try (mysqldump as the app user; tables, views, triggers, events)"
        # Not --routines: the stored procedure belongs to the panel user, and
        # SHOW CREATE PROCEDURE is refused to anyone else, which fails the whole
        # dump. Its text is in the repository (db/migrations/*numbers_right.sql).
        if have mysqldump && [ -n "$WAY" ]; then
            if mysqldump --no-data --skip-comments --skip-add-drop-table --triggers --events $BASEOPTS $WAY "$DB_NAME" > "$SCHEMA_OUT" 2>"$TMPD/dumperr"; then done_dump=yes; else echo "mysqldump failed: $(head -2 "$TMPD/dumperr")"; fi
        else
            echo "not possible: $(head -2 "$TMPD/dumperr" 2>/dev/null)"
        fi
    fi
    unset MYSQL_PWD
else
    echo "mysql client not found or DB_USER empty"
fi
if [ "$done_dump" = yes ]; then
    chmod 600 "$SCHEMA_OUT"; echo "schema copy written: $(wc -l < "$SCHEMA_OUT") lines, $(grep -c 'CREATE TABLE' "$SCHEMA_OUT") tables, $(grep -c -E 'CREATE[^;]*(PROCEDURE|FUNCTION)' "$SCHEMA_OUT") routines -> $SCHEMA_OUT"
else
    rm -f "$SCHEMA_OUT"; echo "NO schema copy this run (the reasons are above); nothing left at $SCHEMA_OUT"
fi

say "11. MAIL SERVER"
MH="$(envval MAIL_HOST)"; MP="$(envval MAIL_PORT)"
sub "SMTP certificate on ${MH:-?}:${MP:-?}"
if [ -n "$MH" ] && [ -n "$MP" ]; then (timeout 10 openssl s_client -connect "$MH:$MP" -servername "$HOST" </dev/null 2>/dev/null | openssl x509 -noout -subject -issuer -dates 2>/dev/null) || echo "(could not read)"; fi
sub "exim queue (if visible)"; exim -bpc 2>/dev/null || echo "(not visible to this user)"

say "12. CLOUDFLARE"
CF_ZONE="${CF_ZONE_ID:-$(envval CLOUDFLARE_ZONE_ID)}"
if [ -n "${CF_API_TOKEN:-}" ] && [ -n "$CF_ZONE" ]; then
    cf() { curl -sS --max-time 20 -H "Authorization: Bearer $CF_API_TOKEN" -H 'Content-Type: application/json' "https://api.cloudflare.com/client/v4/zones/$CF_ZONE$1"; }
    pp() { if have python3; then python3 -c '
import json,sys
d=json.load(sys.stdin)
if not d.get("success", True): print("ERROR", d.get("errors")); sys.exit()
r=d.get("result")
keep=("id","name","type","content","proxied","ttl","value","status","plan","name_servers","enabled","description","expression","action","action_parameters","phase","targets","actions","priority","mode","configuration","notes","modified_on","last_updated","version","certificate_authority","validation_method","hosts","primary_certificate")
def one(x):
    return {k:x[k] for k in x if k in keep} if isinstance(x,dict) else x
if isinstance(r,list):
    for x in r: print(json.dumps(one(x), default=str))
elif isinstance(r,dict) and "rules" in r:
    print("ruleset", r.get("name"), r.get("phase"), "rules:", len(r.get("rules",[])))
    for x in r["rules"]: print(json.dumps(one(x), default=str))
else:
    print(json.dumps(one(r), default=str, indent=1)[:5000])
'; else head -c 5000; echo; fi; }
    sub "zone"; cf "" | pp
    sub "settings"; cf "/settings" | pp
    sub "HSTS"; cf "/settings/security_header" | pp
    sub "DNS records"; cf "/dns_records?per_page=200" | pp
    for ph in http_request_cache_settings http_ratelimit http_request_firewall_custom http_request_firewall_managed http_request_dynamic_redirect http_config_settings http_response_headers_transform; do
        sub "ruleset phase: $ph"; cf "/rulesets/phases/$ph/entrypoint" | pp
    done
    sub "page rules"; cf "/pagerules" | pp
    sub "IP access rules"; cf "/firewall/access_rules/rules?per_page=100" | pp
    sub "authenticated origin pulls"; cf "/origin_tls_client_auth/settings" | pp
    sub "edge certificates"; cf "/ssl/certificate_packs?status=all" | pp
else
    echo "No CF_API_TOKEN given (or no zone id): skipped. To include Cloudflare, create a READ-ONLY token at"
    echo "dash.cloudflare.com -> My Profile -> API Tokens -> Create Token -> Custom token, permissions:"
    echo "  Zone:Read, Zone Settings:Read, DNS:Read, Firewall Services:Read, Zone WAF:Read, Cache Rules:Read,"
    echo "  Page Rules:Read, SSL and Certificates:Read   (Zone Resources: Include -> Specific zone -> $HOST)"
    echo "then run:  CF_API_TOKEN=<token> bash $0"
fi

say "DONE. Report saved to $OUT (secrets masked). Schema copy (if made): $SCHEMA_OUT"
}

report 2>&1 | tee "$OUT"
chmod 600 "$OUT" 2>/dev/null
