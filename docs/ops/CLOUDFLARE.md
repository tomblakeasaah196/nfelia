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
| C-4 | HSTS, 6 months, no `includeSubDomains`, no preload | assistant, prompt C | SECURITY.md §4 | **decided 20 Oct** |
| C-5 | Rate limit on sign-in POSTs | assistant, prompt C | SECURITY.md §5 | **decided 20 Oct** |
| C-6 | WAF rule: the deploy webhook answers GitHub Actions only | assistant, prompt C | takes the brute-force surface off `webhook/deploy.php` | **decided 20 Oct** |
| C-7 | WAF rule: challenge `ops.nfelia.com` outside the countries you sign in from | assistant, needs the country list | the back office is used by two people | not chosen |
| C-12 | Bot Fight Mode | you: Security → Bots | on the Free plan it cannot be bypassed by rules, so it can challenge Paystack's webhooks and GitHub's deploy call | **decided 20 Oct: off**; prompt D kept for the day it is reconsidered |
| C-8 | DNSSEC on, then the DS record at the registrar | you: DNS → Settings → Enable DNSSEC; then the registrar | SECURITY.md L-12 | open |
| C-9 | DMARC `p=quarantine` | you: DNS → `_dmarc` (RUNBOOK §5 has the record) | after two weeks of reports at dmarc@nfelia.com | **decided: 3 November** |
| C-10 | Notifications: Universal SSL expiry, DDoS Attack L7, Origin Error Rate | you: Notifications → Add | nobody is told of anything today | open |
| C-11 | Lock the origin to Cloudflare | a Transform Rule that adds the secret header (prompt E, or by hand), then the token in `.env` and a deploy; RUNBOOK §4.4 in that order | **decided 20 Oct**; the address-list version failed the server test and was replaced by the header |

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

### Prompt C: the decisions of 20 October (HSTS and the two rules)

```
On the zone nfelia.com, apply the following, one item at a time, reading each back to confirm it. Do nothing that is not listed. If the plan does not allow a value, use the nearest value it allows and tell me what you used.

1. HSTS: enabled, max-age 15552000 seconds (180 days), includeSubDomains OFF, preload OFF, no-sniff header ON.

2. One rate limiting rule named "Sign-in bursts": requests whose method is POST and whose URI path is exactly "/login" or exactly "/api/ajax/auth"; counted per IP address; more than 10 requests in 10 seconds; action Block, for the shortest mitigation timeout the plan allows. (On the Free plan the period and the timeout are both 10 seconds; the application has its own limits of 10 per account and 50 per address over 15 minutes behind this.)

3. A WAF custom rule named "Deploy webhook: GitHub Actions only", placed first: requests whose URI path is exactly "/webhook/deploy.php" and whose User-Agent does not contain "github-actions-nfelia"; action Block.

When done, list every WAF custom rule, every rate limiting rule and the HSTS setting as they now are.
```

### Prompt D: Bot Fight Mode (asked for; read this first)

Bot Fight Mode on the Free plan challenges requests it judges to be
automated, and **no rule can exempt a caller from it** (that needs Super
Bot Fight Mode on a paid plan). Two callers Nfelia depends on are
automated: Paystack, which posts payment webhooks to `/api/webhooks`, and
GitHub Actions, which posts to `/webhook/deploy.php` after every merge. If
either is challenged, payments stop being recorded or deploys stop,
silently. The recommendation is to leave it off: the managed rules, the
rate limit and the deploy rule above cover what it would add. If it is
wanted anyway, turn it on in Security → Bots and test both callers the
same hour:

1. Merge or re-run the deploy workflow in GitHub → Actions; the deploy job
   must end green and `~/deploy-nfelia.log` must show the new run.
2. Paystack dashboard → Settings → Webhooks → send a test event, or make a
   small real payment; Ops → Health must show the webhook received.

If either fails, turn Bot Fight Mode off again; nothing else needs undoing.

### Prompt E: the origin lock's header (step 2 of RUNBOOK §4.4)

Make the token first (`openssl rand -hex 24` on the server) and put it in
place of `PASTE_TOKEN`. Needs **Transform Rules: Edit** on the zone.

```
On the zone nfelia.com, create exactly one Transform Rule of the kind "Modify Request Header", named "Origin lock: edge token": for all incoming requests, set a static request header named X-Nfelia-Edge with the value PASTE_TOKEN. Do nothing else. Read the rule back and show me its expression, its action and the header it sets.
```

Visitors never see this header; it travels only from Cloudflare to the
server. Changing the value later means changing it here and in the
server's `.env` in the order RUNBOOK §4.4 gives.

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
