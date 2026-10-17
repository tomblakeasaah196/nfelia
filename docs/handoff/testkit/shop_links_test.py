"""Shop links, step 1: end-to-end checks against a local copy. Local only.

Needs MariaDB with db/schema.sql and the migrations applied, and a .env with
    APP_URL=http://nfelia.test:8000
    DB_NAME=smartqaq_nfelia_core
Then, from the repo root:
    php -S 127.0.0.1:8000 docs/handoff/testkit/router.php
    python3 docs/handoff/testkit/shop_links_test.py
It reseeds the database first (shop_links_seed.php). Requests set the Host
header, so name.nfelia.test needs no DNS. The __Host- session cookie can't be
stored over plain http, so cookies are kept by hand here.
"""
import http.client
import json
import os
import re
import subprocess
import sys
import urllib.parse

subprocess.run(["php", os.path.join(os.path.dirname(os.path.abspath(__file__)), "shop_links_seed.php")], check=True)

MAIN = "nfelia.test:8000"
results = []


def check(label, ok, detail=""):
    results.append((label, bool(ok)))
    print(("PASS " if ok else "FAIL ") + label + ("" if ok else "  -> " + str(detail)[:400]))


def sql(query):
    out = subprocess.run(["mysql", "-uroot", "-N", "smartqaq_nfelia_core", "-e", query],
                         capture_output=True, text=True)
    if out.returncode:
        raise RuntimeError(out.stderr)
    return [line.split("\t") for line in out.stdout.strip().split("\n") if line]


class Client:
    def __init__(self, host=MAIN):
        self.host = host
        self.cookies = {}
        self.set_cookie_headers = []

    def req(self, method, path, data=None, headers=None):
        conn = http.client.HTTPConnection("127.0.0.1", 8000, timeout=30)
        h = {"Host": self.host}
        if self.cookies:
            h["Cookie"] = "; ".join(f"{k}={v}" for k, v in self.cookies.items())
        body = None
        if data is not None:
            body = urllib.parse.urlencode(data)
            h["Content-Type"] = "application/x-www-form-urlencoded"
        if headers:
            h.update(headers)
        conn.request(method, path, body=body, headers=h)
        r = conn.getresponse()
        text = r.read().decode("utf-8", "replace")
        hdrs = {}
        self.set_cookie_headers = []
        for k, v in r.getheaders():
            hdrs.setdefault(k.lower(), v)
            if k.lower() == "set-cookie":
                self.set_cookie_headers.append(v)
                name, _, rest = v.partition("=")
                val = rest.split(";")[0]
                if "01 Jan 1970" in v or "Max-Age=0" in v or val in ("deleted", ""):
                    self.cookies.pop(name, None)
                else:
                    self.cookies[name] = val
        return r.status, hdrs, text

    def csrf(self, path="/login"):
        status, _, html = self.req("GET", path)
        m = re.search(r'name="csrf_token" value="([0-9a-f]+)"', html)
        return m.group(1) if m else None

    def login(self, email):
        token = self.csrf("/login")
        status, hdrs, _ = self.req("POST", "/login", {"csrf_token": token, "email": email, "password": "testpass123"})
        return status, hdrs.get("location", "")


def shop(label):
    return Client(f"{label}.nfelia.test:8000")


# ── Routing without a session ──────────────────────────────────────────
c = shop("bisi-indigo")
s, h, t = c.req("GET", "/")
check("unknown shop is a friendly 404", s == 404 and "no shop at this address" in t, (s, t[:200]))
check("unknown shop sets no cookie", not c.set_cookie_headers, c.set_cookie_headers)
check("unknown shop is noindex", "noindex" in h.get("x-robots-tag", ""), h)
check("unknown shop links to Nfelia", 'href="http://nfelia.test:8000"' in t, t[:300])

s, h, t = Client().req("GET", "/biz/bisi-indigo")
check("/biz/{slug} says the link no longer works", s == 404 and "no longer works" in t and "yourshop.nfelia.test" in t, (s, t[:300]))

s, h, t = Client().req("POST", "/public/cart-checkout", {"items": "[]"})
check("old checkout endpoint is 410", s == 410 and "moved" in t, (s, t))
s, h, t = Client().req("POST", "/public/quote-request", {"name": "x"})
check("old quote endpoint is 410", s == 410, (s, t))

s, h, t = shop("mail").req("GET", "/")
check("mail.nfelia.test is not a shop", s == 404 and "no shop" in t, s)
s, h, t = Client("a.b.nfelia.test:8000").req("GET", "/")
check("nested subdomain is not a shop", s == 404 and "no shop" in t, s)
s, h, t = Client("www.nfelia.test:8000").req("GET", "/")
check("www stays the main site", s == 200 and "no shop" not in t, s)

s, h, t = Client().req("GET", "/doc/does-not-exist")
check("missing document uses the notice page", s == 404 and "find this document" in t, (s, t[:200]))

# ── Session cookie ─────────────────────────────────────────────────────
c = Client()
c.req("GET", "/login")
sc = " | ".join(c.set_cookie_headers)
check("session cookie is __Host- prefixed, Secure, no Domain",
      "__Host-nfelia_session=" in sc and "secure" in sc.lower() and "domain" not in sc.lower() and "path=/" in sc.lower(), sc)

# ── Staff are not sent to setup ────────────────────────────────────────
staff = Client()
s, loc = staff.login("tunde@nfelia.test")
s, h, t = staff.req("GET", "/dashboard")
check("staff carry on to the dashboard", s == 200, (s, h.get("location")))
s, h, t = staff.req("GET", "/shop-setup/check?name=anything")
check("staff can't use the name check", s == 403, (s, t))
s, h, t = staff.req("GET", "/shop-setup")
check("staff visiting setup go to the dashboard", s == 302 and h.get("location", "").endswith("/dashboard"), (s, h.get("location")))

# ── Owner setup ────────────────────────────────────────────────────────
bisi = Client()
s, loc = bisi.login("bisi@nfelia.test")
check("owner signs in", s == 302 and loc.endswith("/dashboard"), (s, loc))
s, h, t = bisi.req("GET", "/dashboard")
check("owner without a link goes to setup", s == 302 and h.get("location", "").endswith("/shop-setup"), (s, h.get("location")))
s, h, t = bisi.req("GET", "/contact-support")
check("support stays reachable during setup", s == 200, s)

s, h, setup_html = bisi.req("GET", "/shop-setup")
check("setup screen renders", s == 200 and "Set up your shop" in setup_html, (s, setup_html[:300]))
check("setup preselects Atelier for fashion", re.search(r'value="atelier"[^>]*checked', setup_html), "")
check("setup offers suggestions", "bisiindigofashion" in setup_html, "")
check("setup shows ten looks", setup_html.count('name="look"') == 10, setup_html.count('name="look"'))
view_part = setup_html.split('max-w-3xl mx-auto')[1].split('shop-link.js')[0]
check("setup has no all-caps labels", "uppercase" not in view_part, "")

def name_check(client, name):
    s, h, t = client.req("GET", "/shop-setup/check?name=" + urllib.parse.quote(name))
    try:
        return json.loads(t)
    except Exception:
        return {"raw": t[:200], "status": s}

r = name_check(bisi, "Bisi Indigo")
check("check: 'Bisi Indigo' -> ok bisi-indigo", r.get("state") == "ok" and r.get("name") == "bisi-indigo", r)
for name, state in [("www", "reserved"), ("admin", "reserved"), ("ab", "invalid"), ("-abc", "invalid"),
                    ("abc-", "invalid"), ("a--bc", "invalid"), ("ab!c", "invalid"), ("x" * 31, "invalid"),
                    ("gtbank-promo", "review"), ("mtn-deals", "review"), ("glowbytemi", "ok"), ("harmony", "ok"),
                    ("bankole", "ok"), ("my.shop_name", "ok"), ("nfelia-support", "review")]:
    r = name_check(bisi, name)
    check(f"check: {name!r} -> {state}", r.get("state") == state, r)
r = name_check(bisi, "Ṣadé Ọlá")
check("check: tone marks come off ('Ṣadé Ọlá' -> sade-ola)", r.get("state") == "ok" and r.get("name") == "sade-ola", r)
r = name_check(bisi, "www")
check("check: refused names come with suggestions", isinstance(r.get("suggestions"), list) and len(r["suggestions"]) <= 3, r)

token = bisi.csrf("/shop-setup")
s, h, t = bisi.req("POST", "/shop-setup/save", {"shop_name": "bisi-indigo", "look": "atelier"})
check("save without CSRF is refused", s == 302 and h.get("location", "").endswith("/login"), (s, h.get("location")))
check("nothing saved without CSRF", sql("SELECT subdomain IS NULL FROM businesses WHERE id = 2")[0][0] == "1", "")

s, h, t = bisi.req("POST", "/shop-setup/save", {"csrf_token": token, "shop_name": "bisi-indigo", "look": "nonsense"})
check("save with a bad look goes back to setup", s == 302 and h.get("location", "").endswith("/shop-setup"), (s, h))
s, h, t = bisi.req("GET", "/shop-setup")
check("bad look shows a plain error", "Choose one of the ten looks." in t, "")

s, h, t = bisi.req("POST", "/shop-setup/save", {"csrf_token": token, "shop_name": "www", "look": "atelier"})
s, h, t = bisi.req("GET", "/shop-setup")
check("reserved name is refused on save", "kept for Nfelia" in t, "")

s, h, t = bisi.req("POST", "/shop-setup/save", {"csrf_token": token, "shop_name": "Bisi Indigo", "look": "atelier"})
check("good save goes to the dashboard", s == 302 and h.get("location", "").endswith("/dashboard"), (s, h.get("location")))
row = sql("SELECT subdomain, subdomain_status, storefront_look, subdomain_renames FROM businesses WHERE id = 2")[0]
check("link and look are stored", row == ["bisi-indigo", "active", "atelier", "0"], row)
s, h, t = bisi.req("GET", "/dashboard")
check("owner with link and look reaches the dashboard", s == 200, (s, h.get("location")))

# ── The shop page ─────────────────────────────────────────────────────
c = shop("bisi-indigo")
s, h, page = c.req("GET", "/")
check("shop page renders at its own address", s == 200 and "Bisi Indigo Fashion" in page, (s, page[:300]))
check("shop page sets no cookie", not c.set_cookie_headers, c.set_cookie_headers)
check("trial shop: X-Robots-Tag noindex", "noindex" in h.get("x-robots-tag", ""), h)
check("trial shop: meta robots noindex", '<meta name="robots" content="noindex, nofollow">' in page, "")
check("canonical is the shop link", '<link rel="canonical" href="http://bisi-indigo.nfelia.test:8000">' in page, re.findall(r'<link rel="canonical"[^>]*>', page))
check("og:url is the shop link", re.search(r'og:url" content="http://bisi-indigo\.nfelia\.test:8000"', page), re.findall(r'og:url[^>]*>', page))
check("owner text can't end a script block", "</script><script>alert(1)" not in page, "")
check("JSON-LD priceCurrency NGN", '"priceCurrency":"NGN"' in page, re.findall(r'priceCurrency[^,]*', page)[:2])
check("WhatsApp number normalised to 234", "wa.me/2348031234567" in page, re.findall(r"wa\.me/\d+", page)[:3])
check("checkout posts to the shop's own address", "fetch('/checkout'" in page and "fetch('/quote-request'" in page, "")
check("no Tailwind tag with a broken src", 'cdn.tailwindcss.com>' not in page, "")
s, h, t = shop("BISI-INDIGO").req("GET", "/")
check("host is case-insensitive", s == 200, s)
s, h, t = shop("bisi-indigo").req("GET", "/login")
check("other paths on a shop are a 404 that links home", s == 404 and "isn&#039;t here" in t and "http://bisi-indigo.nfelia.test:8000" in t, (s, t[:200]))
s, h, t = shop("bisi-indigo").req("GET", "/checkout")
check("GET /checkout is not an order", s == 404, s)

def order(items, **extra):
    data = {"customer_name": "Ada Shopper", "customer_phone": "08055556666", "channel": "whatsapp",
            "items": json.dumps(items)}
    data.update(extra)
    s, h, t = shop("bisi-indigo").req("POST", "/checkout", data)
    try:
        return s, json.loads(t)
    except Exception:
        return s, {"raw": t[:300]}

s, r = order([{"product_id": 1, "quantity": 2, "unit_price": 1, "product_name": "Free"},
              {"product_id": 3, "quantity": 1}, {"product_id": 4, "quantity": 1}, {"product_id": 999, "quantity": 1},
              {"product_id": "x"}, "junk"])
check("tampered cart still orders", s == 200 and r.get("success"), (s, r))
check("total comes from the catalogue", r.get("total") in (50000, 50000.0), r)
order_row = sql("SELECT o.id, o.currency_code, o.subtotal FROM orders o WHERE o.business_id = 2 ORDER BY o.id DESC LIMIT 1")[0]
items = sql(f"SELECT product_id, product_name, unit_price, quantity, line_total FROM order_items WHERE order_id = {order_row[0]}")
check("hidden, foreign and unknown products are dropped", len(items) == 1 and items[0][0] == "1", items)
check("name and price are the real ones", items and items[0][1] == "Adire kaftan" and items[0][2] == "25000.00", items)
check("order currency is NGN", order_row[1] == "NGN", order_row)

s, r = order([{"product_id": 2, "quantity": 5000}])
q = sql("SELECT quantity FROM order_items WHERE product_id = 2 ORDER BY id DESC LIMIT 1")
check("quantity is capped at 999", s == 200 and q and q[0][0].startswith("999"), (s, r, q))
s, r = order([{"product_id": 2, "quantity": -4}])
q = sql("SELECT quantity FROM order_items WHERE product_id = 2 ORDER BY id DESC LIMIT 1")
check("quantity is at least 1", s == 200 and q and q[0][0].startswith("1"), (s, r, q))
s, r = order([])
check("empty cart is refused", s == 422 and "empty" in r.get("error", "").lower(), (s, r))
s, r = order([{"product_id": 3, "quantity": 1}, {"product_id": 4, "quantity": 1}])
check("cart of unavailable items is refused", s == 422 and "no longer available" in r.get("error", ""), (s, r))
s, r = order([{"product_id": 1, "quantity": 1}], customer_phone="12")
check("unusable phone is refused", s == 422, (s, r))

s, h, t = shop("bisi-indigo").req("POST", "/quote-request", {"name": "Ada", "phone": "08055556666", "message": "50 kaftans please", "business_id": "4"})
qr = sql("SELECT business_id FROM quote_requests ORDER BY id DESC LIMIT 1")
check("quote request lands with the shop from the link", s == 200 and qr and qr[0][0] == "2", (s, t, qr))

# ── Closed shop ───────────────────────────────────────────────────────
sql("UPDATE businesses SET subdomain = 'volt', subdomain_status = 'active', storefront_look = 'volt' WHERE id = 3")
s, h, t = shop("volt").req("GET", "/")
check("expired trial shows closed for now", s == 200 and "closed for now" in t, (s, t[:200]))
check("closed shop is noindex", "noindex" in h.get("x-robots-tag", ""), h)
check("closed shop has its WhatsApp button", "wa.me/2348099998888" in t, re.findall(r"wa\.me/\d+", t))
s, h, t = shop("volt").req("POST", "/checkout", {"customer_name": "A", "customer_phone": "08055556666", "items": "[]"})
check("closed shop takes no orders", s == 403, (s, t))
for status, extra in [("suspended", ""), ("cancelled", ""),
                      ("past_due", ", grace_ends_at = DATE_SUB(NOW(), INTERVAL 1 DAY)")]:
    sql(f"UPDATE subscriptions SET status = '{status}'{extra} WHERE business_id = 3")
    s, h, t = shop("volt").req("GET", "/")
    check(f"{status} shop is closed", "closed for now" in t, s)
sql("UPDATE subscriptions SET status = 'past_due', grace_ends_at = DATE_ADD(NOW(), INTERVAL 1 DAY) WHERE business_id = 3")
s, h, t = shop("volt").req("GET", "/")
check("past_due inside grace stays open and indexed", s == 200 and "closed for now" not in t and "x-robots-tag" not in h, (s, h))
sql("UPDATE subscriptions SET status = 'trial', grace_ends_at = NULL WHERE business_id = 3")
sql("UPDATE businesses SET landing_enabled = 0 WHERE id = 3")
s, h, t = shop("volt").req("GET", "/")
check("shop switched off looks like no shop", s == 404 and "no shop" in t, s)
sql("UPDATE businesses SET landing_enabled = 1 WHERE id = 3")

# ── Lookalike names wait for review ───────────────────────────────────
kemi = Client()
kemi.login("kemi@nfelia.test")
token = kemi.csrf("/shop-setup")
s, h, setup_html = kemi.req("GET", "/shop-setup")
check("food business starts on Buka", re.search(r'value="buka"[^>]*checked', setup_html), "")
s, h, t = kemi.req("POST", "/shop-setup/save", {"csrf_token": token, "shop_name": "kemi-promo", "look": "buka"})
row = sql("SELECT subdomain IS NULL, subdomain_requested, subdomain_status, storefront_look FROM businesses WHERE id = 4")[0]
check("lookalike name waits for review", row == ["1", "kemi-promo", "pending", "buka"], row)
s, h, t = kemi.req("GET", "/dashboard")
check("a pending name counts as chosen", s == 200, (s, h.get("location")))
check("dashboard says we're checking", "checking kemi-promo" in t, "")
n = sql("SELECT title, action_url FROM notifications WHERE business_id = 1 ORDER BY id DESC LIMIT 1")
check("founder is notified", n and n[0] == ["Shop name to review", "admin/shop-names"], n)
s, h, t = shop("kemi-promo").req("GET", "/")
check("pending name is not live", s == 404, s)
r = name_check(bisi, "kemi-promo")
check("a pending name is taken for others", r.get("state") == "taken", r)

founder = Client()
founder.login("founder@nfelia.test")
s, h, t = founder.req("GET", "/dashboard")
check("founder is also sent to setup", s == 302 and h.get("location", "").endswith("/shop-setup"), (s, h.get("location")))
s, h, admin_html = founder.req("GET", "/admin/shop-names")
check("admin queue lists the name", s == 200 and "kemi-promo.nfelia.test" in admin_html, (s, admin_html[:200]))
token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', admin_html).group(1)
s, h, t = founder.req("POST", "/admin/review-shop-name", {"business_id": "4", "name": "kemi-promo", "decision": "approve"})
check("approve without CSRF is refused", s == 302 and h.get("location", "").endswith("/login"), (s, h.get("location")))
s, h, t = founder.req("POST", "/admin/review-shop-name", {"csrf_token": token, "business_id": "4", "name": "kemi-promo", "decision": "approve"})
row = sql("SELECT subdomain, subdomain_requested IS NULL, subdomain_status, subdomain_renames, subdomain_reviewed_by FROM businesses WHERE id = 4")[0]
check("approval makes the link live", row == ["kemi-promo", "1", "active", "0", "1"], row)
n = sql("SELECT title, action_url FROM notifications WHERE business_id = 4 ORDER BY id DESC LIMIT 1")
check("owner is told it's live", n and n[0][0] == "Your shop link is live", n)
s, h, t = shop("kemi-promo").req("GET", "/")
check("approved paying shop is indexed", s == 200 and "x-robots-tag" not in h and '<meta name="robots" content="index, follow">' in t, (s, h.get("x-robots-tag")))
s, h, t = founder.req("POST", "/admin/review-shop-name", {"csrf_token": token, "business_id": "4", "name": "kemi-promo", "decision": "approve"})
check("a stale approval is handled", s == 302, s)

# Founder's own first claim turned down, then back to setup
token = founder.csrf("/shop-setup")
founder.req("POST", "/shop-setup/save", {"csrf_token": token, "shop_name": "nfelia-official", "look": "desk"})
check("founder's lookalike name pends", sql("SELECT subdomain_status FROM businesses WHERE id = 1")[0][0] == "pending", "")
s, h, t = founder.req("POST", "/admin/review-shop-name", {"csrf_token": token, "business_id": "1", "name": "nfelia-official", "decision": "reject"})
row = sql("SELECT subdomain IS NULL, subdomain_requested IS NULL, subdomain_status FROM businesses WHERE id = 1")[0]
check("turned-down first claim is rejected", row == ["1", "1", "rejected"], row)
s, h, t = founder.req("GET", "/dashboard")
check("rejected owner goes back to setup", s == 302 and h.get("location", "").endswith("/shop-setup"), (s, h.get("location")))
s, h, t = founder.req("GET", "/shop-setup")
check("setup says we couldn't approve", "couldn't approve the last name" in t, "")
r = name_check(bisi, "nfelia-official")
check("a turned-down name is free again", r.get("state") == "review", r)

# ── Rename (once) ─────────────────────────────────────────────────────
token = bisi.csrf("/landing-page")
s, h, lp = bisi.req("GET", "/landing-page")
check("Landing Page shows the link", "bisi-indigo.nfelia.test" in lp and "Change link" in lp, "")
check("Landing Page has no /biz link", "/biz/" not in lp, "")
s, h, t = bisi.req("POST", "/shop-setup/rename", {"csrf_token": token, "shop_name": "bisi-indigo"})
check("renaming to the same name is a no-op", sql("SELECT subdomain_renames FROM businesses WHERE id = 2")[0][0] == "0", "")
s, h, t = bisi.req("POST", "/shop-setup/rename", {"csrf_token": token, "shop_name": "kemi-promo"})
check("renaming to a taken name is refused", sql("SELECT subdomain FROM businesses WHERE id = 2")[0][0] == "bisi-indigo", "")
s, h, t = bisi.req("POST", "/shop-setup/rename", {"csrf_token": token, "shop_name": "bisi-adire"})
row = sql("SELECT subdomain, subdomain_renames FROM businesses WHERE id = 2")[0]
check("rename moves the link", row == ["bisi-adire", "1"], row)
s, h, t = shop("bisi-indigo").req("GET", "/")
check("old name stops working at once", s == 404, s)
s, h, t = shop("bisi-adire").req("GET", "/")
check("new name works", s == 200, s)
r = name_check(kemi, "bisi-indigo")
check("old name is free for others", r.get("state") == "ok", r)
s, h, t = bisi.req("POST", "/shop-setup/rename", {"csrf_token": token, "shop_name": "bisi-again"})
check("second rename is refused", sql("SELECT subdomain FROM businesses WHERE id = 2")[0][0] == "bisi-adire", "")
s, h, lp = bisi.req("GET", "/landing-page")
check("Landing Page then points to support", "Contact support" in lp and "Change link" not in lp, "")

# Rename into review keeps the live link, turned down keeps it
token = kemi.csrf("/landing-page")
kemi.req("POST", "/shop-setup/rename", {"csrf_token": token, "shop_name": "kemi-giveaway"})
row = sql("SELECT subdomain, subdomain_requested, subdomain_status FROM businesses WHERE id = 4")[0]
check("rename to a lookalike waits, old link stays", row == ["kemi-promo", "kemi-giveaway", "pending"], row)
s, h, t = shop("kemi-promo").req("GET", "/")
check("current link keeps working while the rename waits", s == 200, s)
token = founder.csrf("/admin/shop-names")
founder.req("POST", "/admin/review-shop-name", {"csrf_token": token, "business_id": "4", "name": "kemi-giveaway", "decision": "reject"})
row = sql("SELECT subdomain, subdomain_requested IS NULL, subdomain_status, subdomain_renames FROM businesses WHERE id = 4")[0]
check("turned-down rename keeps the old link", row == ["kemi-promo", "1", "active", "0"], row)
token = kemi.csrf("/landing-page")
kemi.req("POST", "/shop-setup/rename", {"csrf_token": token, "shop_name": "kemi-giveaway"})
token = founder.csrf("/admin/shop-names")
founder.req("POST", "/admin/review-shop-name", {"csrf_token": token, "business_id": "4", "name": "kemi-giveaway", "decision": "approve"})
row = sql("SELECT subdomain, subdomain_renames FROM businesses WHERE id = 4")[0]
check("approved rename counts as the one rename", row == ["kemi-giveaway", "1"], row)

# Change the look from Landing Page
token = bisi.csrf("/shop-setup")
s, h, t = bisi.req("GET", "/shop-setup")
check("setup with a live link only asks for the look", "Your shop&#039;s look" in t and 'name="shop_name"' not in t, "")
s, h, t = bisi.req("POST", "/shop-setup/save", {"csrf_token": token, "look": "glow"})
check("look change returns to Landing Page", s == 302 and h.get("location", "").endswith("/landing-page"), (s, h.get("location")))
check("look is changed", sql("SELECT storefront_look FROM businesses WHERE id = 2")[0][0] == "glow", "")

fails = [label for label, ok in results if not ok]
print(f"\n{len(results) - len(fails)}/{len(results)} passed")
sys.exit(1 if fails else 0)
