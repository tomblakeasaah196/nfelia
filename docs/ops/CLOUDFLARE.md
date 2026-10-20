# Cloudflare: the zone as it is, what to change, and the prompts

The zone `nfelia.com` (id `1a0e9a750a9786c0d9f236c168507e15`, Free plan)
was read in full on 20 October 2026 with Cloudflare's own assistant: the
chat in the dashboard, which calls the API with a token you create for it.
This page keeps what it found, what to change, and the prompts to paste.
[INFRASTRUCTURE.md](INFRASTRUCTURE.md) §4 to §6 carries the same facts in
the inventory; [SECURITY.md](SECURITY.md) says why each change matters.

---

## 1. As found on 20 October 2026

| Area | Value |
|---|---|
| DNS | 9 records: `nfelia.com` A and `www` A → the server, proxied; `*.nfelia.com` CNAME → `nfelia.com`, proxied; `mail` and `webmail` A, DNS only; MX; SPF; DKIM; DMARC `p=none`. DNSSEC **off**. |
| TLS | Full (strict); Universal SSL (Google Trust Services, ECDSA, to 2027-01-04); Always Use HTTPS on; HSTS **off**; minimum TLS **1.0**; TLS 1.3 on; 0-RTT off |
| Rules | no WAF custom rules, no rate limiting, no Page Rules; one Cache Rule "Cache shop pages" (follows the origin's headers) |
| Security | level medium; Browser Integrity Check on; Bot Fight Mode off; no IP, user-agent or lockdown rules; managed free ruleset and DDoS protection on |
| Speed | Brotli on, HTTP/3 on, **Early Hints off**, Rocket Loader off, minify off |
| Caching | level aggressive; browser TTL 4 h; 3.0 % of the week's requests served from cache (app pages are dynamic by design) |
| Account | two members, **2FA off on both**, "enforce 2FA" off; no Workers, Pages, Tunnels, Access or Load Balancers; Email Routing not enabled; no notification policies |
| Traffic, 14 to 20 October | 25,503 requests; 3,403 page views; 1,226 unique visitors; 264 blocked by the managed rules; 161 MB |

Not readable with the token used (not necessarily absent): transform,
origin, redirect and configuration rules; Argo; API tokens; notification
policies. A token with "Zone: Read" on everything shows them.

---

## 2. What to change

In this order. "You" means by hand in the dashboard; "assistant" means
paste the prompt from §3.

| # | Change | Who | Why | State |
|---|---|---|---|---|
| C-1 | 2FA on both members, then Enforce 2FA for the account | you: My Profile → Authentication; Manage Account → Members | the account controls the DNS, and so the site, the mail and every password reset | **open, first** |
| C-2 | Minimum TLS 1.2 | assistant, prompt B (or SSL/TLS → Edge Certificates) | TLS 1.0 and 1.1 are broken protocols; no browser of this decade needs them | open |
| C-3 | Early Hints on | assistant, prompt B (or Speed → Optimization) | free; browsers fetch the stylesheet while PHP is still working | open |
| C-4 | HSTS, 6 months, no `includeSubDomains`, no preload | assistant, prompt C | SECURITY.md §4 | decision |
| C-5 | Rate limit on sign-in POSTs | assistant, prompt C | SECURITY.md §5 | decision |
| C-6 | WAF rule: the deploy webhook answers GitHub Actions only | assistant, prompt C | takes the brute-force surface off `webhook/deploy.php` | decision |
| C-7 | WAF rule: challenge `ops.nfelia.com` outside the countries you sign in from | assistant, prompt C, needs the country list | the back office is used by two people | decision |
| C-8 | DNSSEC on, then the DS record at the registrar | you: DNS → Settings → Enable DNSSEC; then the registrar | SECURITY.md L-12 | open |
| C-9 | DMARC `p=quarantine` | you: DNS → `_dmarc` | after two clean weeks of reports at dmarc@nfelia.com | from 3 November |
| C-10 | Notifications: Universal SSL expiry, DDoS Attack L7, Origin Error Rate | you: Notifications → Add | nobody is told of anything today | open |
| C-11 | Lock the origin to Cloudflare | code (`.htaccess`), SECURITY.md §5; it happens at the origin, not here | decision |

Undo for anything the assistant creates: ask it to **disable** the rule by
name (not delete), or Security → WAF → the rule's switch; HSTS is turned
off where it was turned on (browsers forget it after `max-age`).

---

## 3. Prompts for the assistant

Open the assistant in the dashboard. It works with a token you create
(My Profile → API Tokens): prompt A needs read permissions, prompts B and
C need, on the zone `nfelia.com` only, **Zone Settings: Edit**, **Zone
WAF: Edit**, **SSL and Certificates: Edit**. Paste one prompt at a time,
read its plan, say yes only to what the prompt asks. Each prompt ends by
reading the settings back; keep that output with the report.

### Prompt A: read everything (the run of 20 October)

```
Run a complete read-only configuration audit of the nfelia.com zone. Cover all 11 sections: Zone, DNS, SSL/TLS, Rules, Security, Speed, Caching, Network, Email Routing/Workers/Pages/Tunnels/Access/Load Balancers, Account (tokens, members, notifications), and Traffic analytics for the last 7 days. For every setting, read the actual value from the API — do not guess. Where a setting is at its default, say "default". Where you cannot read a value, say "cannot read".
```

### Prompt B: the two safe changes (no decision needed)

```
On the zone nfelia.com, make exactly these two changes and nothing else, then read each setting back and show me the value before and after:
1. SSL/TLS → Edge Certificates: set the Minimum TLS Version to 1.2.
2. Speed → Optimization: turn Early Hints on.
Do not touch HSTS, DNS, caching, WAF, rate limiting or anything else. If a call fails, stop and show me the error instead of trying something different.
```

### Prompt C: after the decisions

Delete the numbered items that were decided against, and fill in the
country list in item 4 before pasting.

```
On the zone nfelia.com, apply the following, one item at a time, reading each back to confirm it. Do nothing that is not listed. If the plan does not allow a value, use the nearest value it allows and tell me what you used.

1. HSTS: enabled, max-age 15552000 seconds (180 days), includeSubDomains OFF, preload OFF, no-sniff header ON.

2. One rate limiting rule named "Sign-in bursts": requests whose method is POST and whose URI path is exactly "/login" or exactly "/api/ajax/auth"; counted per IP address; more than 10 requests in 10 seconds; action Block, for the shortest mitigation timeout the plan allows. (On the Free plan the period and the timeout are both 10 seconds; the application has its own limits of 10 per account and 50 per address over 15 minutes behind this.)

3. A WAF custom rule named "Deploy webhook: GitHub Actions only", placed first: requests whose URI path is exactly "/webhook/deploy.php" and whose User-Agent does not contain "github-actions-nfelia"; action Block.

4. A WAF custom rule named "Ops back office: expected countries only": requests whose host is exactly "ops.nfelia.com" and whose country is not one of {CM, NG}; action Managed Challenge.

When done, list every WAF custom rule, every rate limiting rule and the HSTS setting as they now are.
```

---

## 4. Checking afterwards, from any machine

```bash
curl -sI https://nfelia.com/login | grep -i strict-transport      # HSTS header present (C-4)
openssl s_client -tls1_1 -connect nfelia.com:443 </dev/null 2>&1 | grep -iE 'alert|error' | head -1   # refused (C-2)
curl -s -o /dev/null -w '%{http_code}\n' -X POST https://nfelia.com/webhook/deploy.php     # 403 from the WAF (C-6); it was 405 from PHP
for i in $(seq 1 12); do curl -s -o /dev/null -w '%{http_code} ' -X POST https://nfelia.com/login; done; echo   # the last ones 429 (C-5)
dig +dnssec nfelia.com A | grep -c RRSIG                             # 1 or more once DNSSEC is live (C-8)
```

From the server, `CF_API_TOKEN=<read-only token> bash ~/nfelia-env-report.sh`
prints section 12 with the same facts in plain text; the permissions the
token needs are printed at the top of that section.
