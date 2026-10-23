"""Shop Studio and the storefront, step 2: end-to-end checks against a local
copy. Local only.

Needs what shop_links_test.py needs (MariaDB with db/schema.sql and the
migrations, and a .env with APP_URL=http://nfelia.test:8000 and
DB_NAME=smartqaq_nfelia_core), with the server showing every PHP message:
    php -d error_reporting=-1 -d display_errors=1 -S 127.0.0.1:8000 docs/handoff/testkit/router.php
    python3 docs/handoff/testkit/storefront_test.py
It reseeds the database first (storefront_seed.php: one shop per look) and
leaves test photos under uploads/. Cloudflare variables must be unset: the
purge is checked as a no-op.

The map checks need the Geoapify stand-in (geoapify_mock.php, started here
if it isn't running) and these in .env; without them they are skipped:
    GEOAPIFY_API_KEY=test-key
    GEOAPIFY_API_URL=http://127.0.0.1:8099
    GEOAPIFY_MAPS_URL=http://127.0.0.1:8099
"""
import html
import http.client
import json
import os
import re
import subprocess
import sys
import urllib.parse

KIT = os.path.dirname(os.path.abspath(__file__))
ROOT = os.path.dirname(os.path.dirname(os.path.dirname(KIT)))
subprocess.run(["php", os.path.join(KIT, "storefront_seed.php")], check=True)

MAIN = "nfelia.test:8000"
LOOKS = {"atelier": "bisi-indigo", "buka": "mama-nkechi", "glow": "glow-by-temi", "volt": "kola-gadgets",
         "market": "musa-provisions", "stage": "lola-events", "harvest": "ayomide-farms", "shea": "zainab-naturals",
         "haven": "haven-shortlets", "desk": "chinedu-co"}
results = []


def check(label, ok, detail=""):
    results.append(bool(ok))
    print(("PASS " if ok else "FAIL ") + label + ("" if ok else "  -> " + str(detail)[:400]))


def sql(query):
    out = subprocess.run(["mysql", "-uroot", "-N", "smartqaq_nfelia_core", "-e", query], capture_output=True, text=True)
    if out.returncode:
        raise RuntimeError(out.stderr)
    return [line.split("\t") for line in out.stdout.strip().split("\n") if line]


def php(code):
    out = subprocess.run(["php", "-r", code], capture_output=True, text=True, cwd=ROOT)
    return out.stdout.strip()


class Client:
    def __init__(self, host=MAIN):
        self.host = host
        self.cookies = {}
        self.set_cookie_headers = []
        self.token = None

    def req(self, method, path, data=None, headers=None, body=None):
        conn = http.client.HTTPConnection("127.0.0.1", 8000, timeout=60)
        h = {"Host": self.host}
        if self.cookies:
            h["Cookie"] = "; ".join(f"{k}={v}" for k, v in self.cookies.items())
        if data is not None:
            body = urllib.parse.urlencode(data)
            h["Content-Type"] = "application/x-www-form-urlencoded"
        if headers:
            h.update(headers)
        conn.request(method, path, body=body, headers=h)
        r = conn.getresponse()
        raw = r.read()
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
        return r.status, hdrs, raw

    def get(self, path):
        s, h, raw = self.req("GET", path)
        return s, h, raw.decode("utf-8", "replace")

    def login(self, email, remember=False):
        s, h, html = self.get("/login")
        token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', html).group(1)
        data = {"csrf_token": token, "email": email, "password": "testpass123"}
        if remember:
            data["remember_me"] = "1"
        s, h, _ = self.req("POST", "/login", data)
        self.studio_token()
        return s, h

    def studio_token(self):
        s, h, html = self.get("/dashboard")
        m = re.search(r'name="csrf-token" content="([0-9a-f]+)"', html) or re.search(r'name="csrf_token" value="([0-9a-f]+)"', html)
        self.token = m.group(1) if m else None
        return self.token

    def api(self, path, payload=None, token=True):
        headers = {"Content-Type": "application/json", "Accept": "application/json"}
        if token:
            headers["X-CSRF-Token"] = self.token or ""
        s, h, raw = self.req("POST", path, headers=headers, body=json.dumps(payload or {}))
        try:
            return s, json.loads(raw)
        except Exception:
            return s, {"raw": raw[:300].decode("utf-8", "replace")}


# The founder's admin moved to Nfelia Ops (ops.<host>): an Ops person signs
# in there with the same test password
def ops_client():
    sql("DELETE FROM ops_staff WHERE email = 'ops@nfelia.test'")
    sql("INSERT INTO ops_staff (full_name, email, password_hash, role) "
        "SELECT 'Ops Admin', 'ops@nfelia.test', password_hash, 'admin' FROM users WHERE email = 'founder@nfelia.test'")
    c = Client("ops." + MAIN)
    s, h, raw = c.req("GET", "/signin")
    token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', raw.decode("utf-8", "replace")).group(1)
    c.req("POST", "/signin", {"csrf_token": token, "email": "ops@nfelia.test", "password": "testpass123"})
    return c


def ops_token(c, path):
    s, h, raw = c.req("GET", path)
    m = re.search(r'name="csrf_token" value="([0-9a-f]+)"', raw.decode("utf-8", "replace"))
    return m.group(1) if m else ""


def shop(label):
    return Client(f"{label}.nfelia.test:8000")


PHP_NOISE = re.compile(r"(Warning|Notice|Deprecated|Fatal error|Parse error)(</b>)?:", re.I)


def luminance(h):
    out = 0
    for i, w in enumerate((0.2126, 0.7152, 0.0722)):
        c = int(h[1 + 2 * i:3 + 2 * i], 16) / 255
        c = c / 12.92 if c <= 0.03928 else ((c + 0.055) / 1.055) ** 2.4
        out += w * c
    return out


def contrast(a, b):
    la, lb = luminance(a), luminance(b)
    return (max(la, lb) + 0.05) / (min(la, lb) + 0.05)


# ── Ten looks ─────────────────────────────────────────────────────────
for look, label in LOOKS.items():
    c = shop(label)
    s, h, page = c.get("/")
    check(f"{look}: renders", s == 200 and f"look-{look}" in page, (s, page[:200]))
    check(f"{look}: no PHP warnings", not PHP_NOISE.search(page), PHP_NOISE.search(page) and page[PHP_NOISE.search(page).start() - 100:PHP_NOISE.search(page).end() + 200])
    check(f"{look}: cacheable for five minutes", h.get("cache-control") == "public, max-age=0, s-maxage=300", h.get("cache-control"))
    check(f"{look}: sets no cookie", not c.set_cookie_headers, c.set_cookie_headers)
    fonts = re.findall(r'href="(https://fonts\.googleapis\.com/[^"]+)"', page)
    check(f"{look}: loads only its own font pair", len(fonts) == 1, fonts)
    check(f"{look}: no Tailwind on the shop", "tailwindcss" not in page, "")
    check(f"{look}: one stylesheet and one script of its own", page.count("assets/css/storefront.css") == 1 and page.count("assets/js/storefront.js") == 1, "")

s, h, page = shop("bisi-indigo").get("/")
s, h, list_page = shop("bisi-indigo").get("/shop")
s, h, contact_page = shop("bisi-indigo").get("/contact")
check("published settings draw every home section", all(x in page for x in ['id="sf-announcement"', 'id="sf-featured"', 'id="sf-catalogue"', 'id="sf-quote"', 'id="sf-about"', 'id="sf-video"', 'id="sf-contact"']), "")
check("the home page keeps a taste of the shop and a button to the request form",
      'sf-catalogue-home' in page and 'href="/shop"' in page and 'href="/contact#sf-quote">Start your request</a>' in page, "")
check("the full list, delivery and questions are off the home page", 'sf-page__title' not in page and 'id="sf-delivery"' not in page and 'id="sf-faq"' not in page, "")
check("/shop has the full list and delivery", 'class="sf-h1 sf-page__title" id="sf-catalogue-title"' in list_page and 'id="sf-delivery"' in list_page, "")
check("/contact has the request form and the questions", 'id="sf-quote"' in contact_page and 'name="message"' in contact_page and 'id="sf-faq"' in contact_page, "")
check("photos get WebP copies with width and height", re.search(r'<source type="image/webp" srcset="http://nfelia\.test:8000/uploads/cache/320/products/2/seed_\d+\.png\.webp 320w', page) and re.search(r'<img src="[^"]+seed_\d+\.png" width="600" height="750"', page), "")
check("product photos load lazily", 'loading="lazy"' in page, "")
check("colour swatches come from the variant group", "3 colours" in list_page and "3 fabrics" in list_page, "")
check("the video waits for a tap (no iframe yet)", "youtube-nocookie" not in page and 'data-youtube="dQw4w9WgXcQ"' in page, "")
check("Shop by Nfelia shows by default", "Shop by Nfelia" in page, "")
check("WhatsApp number normalised", "wa.me/2348031234567" in page, re.findall(r"wa\.me/\d+", page)[:2])
s, h, page = shop("haven-shortlets").get("/")
check("a shop never published uses the old columns", "Furnished apartments with 24-hour power" in page and "Short stays in Lekki" in page, "")
s, h, page = shop("musa-provisions").get("/shop")
check("Market: price list with steppers", 'data-stepper="' in page and "Prices updated" in page, "")
s, h, page = shop("musa-provisions").get("/contact")
check("Market: quote form asks what the old settings asked", 'name="quantity_range"' in page and 'name="company_name"' in page and 'name="budget"' not in page, "")
s, h, page = shop("kola-gadgets").get("/shop")
check("Volt: services sit on the repair ticket", 'class="sf-ticket"' in page and "Screen replacement" in page, "")
s, h, page = shop("chinedu-co").get("/shop")
check("Desk: what's included comes from description lines", "Gas level checked" in page and "sf-checks" in page, "")

s, h, page = shop("no-such-shop").get("/")
check("notices are not cached", s == 404 and h.get("cache-control") == "no-store", h.get("cache-control"))

# ── Studio ────────────────────────────────────────────────────────────
staff = Client()
staff.login("staff2@nfelia.test")
s, h, _ = staff.get("/landing-page")
check("staff can't open Shop Studio", s == 302 and h.get("location", "").endswith("/dashboard"), (s, h.get("location")))
s, r = staff.api("/landing-page/save-draft", {"settings": {}})
check("staff can't save a draft", s == 403, (s, r))

bisi = Client()
bisi.login("owner2@nfelia.test")
s, h, studio = bisi.get("/landing-page")
check("Studio opens", s == 200 and "<h1>Shop Studio</h1>" in studio and 'src="http://nfelia.test:8000/landing-page/preview"' in studio, (s, studio[:200]))
check("Studio is private", "no-store" in h.get("cache-control", ""), h.get("cache-control"))
check("Studio has no PHP warnings", not PHP_NOISE.search(studio), "")
check("Studio has no all-caps labels", "uppercase" not in studio and "text-transform" not in studio, "")
check("nav says Shop Studio", "Shop Studio" in bisi.get("/dashboard")[2], "")
data = json.loads(re.search(r'<script type="application/json" id="st-data">(.*?)</script>', studio, re.S).group(1))
draft = data["settings"]
check("Studio starts from the saved draft", draft["look"] == "atelier" and len(draft["sections"]) == 14, (draft["look"], len(draft["sections"])))

s, h, prev = bisi.get("/landing-page/preview")
check("preview draws the draft privately", s == 200 and "no-store" in h.get("cache-control", "") and "noindex" in h.get("x-robots-tag", "") and '"mode":"preview"' in prev, (s, h))

s, r = bisi.api("/landing-page/save-draft", {"settings": draft}, token=False)
check("saving without the CSRF token is refused", s == 419, (s, r))

hero = next(x for x in draft["sections"] if x["type"] == "hero")
hero["headline"] = "Draft headline only the owner sees"
s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
check("draft saves", s == 200 and r.get("ok") and r.get("unpublished") is True, (s, r))
s, h, live = shop("bisi-indigo").get("/")
check("the draft doesn't reach shoppers", "Draft headline only the owner sees" not in live, "")
s, h, prev = bisi.get("/landing-page/preview")
check("the preview shows the draft", "Draft headline only the owner sees" in prev, "")

versions_before = int(sql("SELECT COUNT(*) FROM storefront_versions WHERE business_id = 2")[0][0])
s, r = bisi.api("/landing-page/publish")
check("publish", s == 200 and r.get("ok") and len(r.get("versions", [])) == versions_before + 1, (s, r))
s, h, live = shop("bisi-indigo").get("/")
check("published headline reaches shoppers", "Draft headline only the owner sees" in live, "")
for i in range(11):
    hero["headline"] = f"Version {i}"
    bisi.api("/landing-page/save-draft", {"settings": draft})
    bisi.api("/landing-page/publish")
count = int(sql("SELECT COUNT(*) FROM storefront_versions WHERE business_id = 2")[0][0])
check("only the last ten publishes are kept", count == 10, count)
s, h, raw = bisi.req("GET", "/landing-page/versions", headers={"Accept": "application/json"})
versions = json.loads(raw)["versions"]
check("versions list, newest first", len(versions) == 10 and versions[0]["id"] > versions[-1]["id"], [v["id"] for v in versions])
oldest = versions[-1]
s, r = bisi.api("/landing-page/restore-version", {"id": oldest["id"]})
restored = next(x for x in r.get("settings", {}).get("sections", []) if x["type"] == "hero")
check("an older version comes back into the draft", s == 200 and restored["headline"] == "Version 1", (s, restored.get("headline")))
s, h, live = shop("bisi-indigo").get("/")
check("restoring doesn't publish", "Version 10" in live and "Version 1<" not in live, re.findall(r'id="sf-hero-title">([^<]+)', live))
other = sql("SELECT id FROM storefront_versions WHERE business_id <> 2 LIMIT 1")
if other:
    s, r = bisi.api("/landing-page/restore-version", {"id": int(other[0][0])})
    check("another shop's version can't be restored", s == 404, (s, r))

# Contrast: the owner's own colour is fixed until text on it reads
for picked, expect in (("#FFD23F", "stays yellow, with dark text"), ("#F97316", "is darkened under white text"), ("#FFFFFF", "is moved off the ground")):
    draft["colours"] = {"set": "custom", "base": "look", "accent": picked}
    s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
    v = r.get("theme", {}).get("vars", {})
    ok = (contrast(v["--sf-on"], v["--sf-accent"]) >= 4.5 and contrast(v["--sf-accent-text"], v["--sf-ground"]) >= 4.5
          and contrast(v["--sf-ink"], v["--sf-ground"]) >= 4.5 and contrast(v["--sf-muted"], v["--sf-ground"]) >= 4.5)
    check(f"own colour {picked}: every text pairing reads at 4.5:1", ok, v)
    if picked == "#FFD23F":
        good = luminance(v["--sf-accent"]) > 0.4 and luminance(v["--sf-on"]) < 0.1
    elif picked == "#F97316":
        good = v["--sf-accent"] != picked and v["--sf-on"] == "#FFFFFF" and luminance(v["--sf-accent"]) < luminance(picked)
    else:
        good = contrast(v["--sf-accent"], v["--sf-ground"]) >= 1.5
    check(f"own colour {picked} {expect}", good, (v["--sf-accent"], v["--sf-on"]))
for set_name in ("night", "earth"):
    draft["colours"] = {"set": set_name}
    s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
    v = r["theme"]["vars"]
    check(f"colour set {set_name} reads at 4.5:1", contrast(v["--sf-on"], v["--sf-accent"]) >= 4.5 and contrast(v["--sf-ink"], v["--sf-ground"]) >= 7, v)
draft["colours"] = {"set": "look"}

# Junk in, clean settings out
junk = json.loads(json.dumps(draft))
junk["look"] = "nonsense"
junk["font"] = "comic"
junk["sections"].append({"type": "gallery", "on": True})
junk["sections"].append({"type": "<script>", "on": True})
next(x for x in junk["sections"] if x["type"] == "hero")["headline"] = "x" * 500
next(x for x in junk["sections"] if x["type"] == "about")["text"] = "</script><script>alert(1)</script> <b>bold</b>"
next(x for x in junk["sections"] if x["type"] == "featured")["products"] = [1, 999999, "x", -4]
next(x for x in junk["sections"] if x["type"] == "hero")["image"] = "uploads/storefront/3/hero_x.png"
s, r = bisi.api("/landing-page/save-draft", {"settings": junk})
st = r.get("settings", {})
types = [x["type"] for x in st.get("sections", [])]
check("unknown look and font fall back", st.get("look") == "atelier" and st.get("font") == "", (st.get("look"), st.get("font")))
check("unknown sections are dropped", "gallery" not in types and "<script>" not in types and len(types) == 14, types)
check("long text is cut", len(next(x for x in st["sections"] if x["type"] == "hero")["headline"]) == 90, "")
check("another shop's photo can't be used", next(x for x in st["sections"] if x["type"] == "hero")["image"] == "", "")
own_ids = {int(x[0]) for x in sql("SELECT id FROM products WHERE business_id = 2")}
picked = next(x for x in st["sections"] if x["type"] == "featured")["products"]
check("product picks keep only this shop's products", all(p in own_ids for p in picked), picked)
s, h, prev = bisi.get("/landing-page/preview")
check("owner text is escaped in the shop", "<script>alert(1)" not in prev and "&lt;/script&gt;&lt;script&gt;alert(1)" in prev, "")
s, r = bisi.api("/landing-page/save-draft", {"settings": draft})

# Hero photo upload goes to the draft only
png = subprocess.run(["php", "-r", "$i=imagecreatetruecolor(1600,900);imagefill($i,0,0,imagecolorallocate($i,40,60,160));ob_start();imagepng($i);echo base64_encode(ob_get_clean());"], capture_output=True, text=True).stdout
boundary = "----nfeliakit"
body = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"csrf_token\"\r\n\r\n{bisi.token}\r\n"
        f"--{boundary}\r\nContent-Disposition: form-data; name=\"media\"; filename=\"hero.png\"\r\nContent-Type: image/png\r\n\r\n").encode() \
    + __import__("base64").b64decode(png) + f"\r\n--{boundary}--\r\n".encode()
s, h, raw = bisi.req("POST", "/landing-page/upload-image", headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}, body=body)
up = json.loads(raw)
check("hero photo uploads", s == 200 and re.match(r"^uploads/storefront/2/hero_\d+_[0-9a-f]{6}\.jpg$", up.get("path", "")), (s, up))
next(x for x in draft["sections"] if x["type"] == "hero")["image"] = up.get("path", "")
s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
s, h, prev = bisi.get("/landing-page/preview")
check("the draft shows the new photo", up.get("path", "~") in prev and "uploads/cache/1280/storefront/2/" in prev, "")
s, h, live = shop("bisi-indigo").get("/")
check("the live shop keeps its old photo until publish", up.get("path", "~") not in live, "")

# Shop on and off
s, r = bisi.api("/landing-page/visibility", {"on": False})
s2, h, page = shop("bisi-indigo").get("/")
check("switching the shop off hides it", s == 200 and r.get("on") is False and s2 == 404, (s, r, s2))
bisi.api("/landing-page/visibility", {"on": True})
s, h, page = shop("bisi-indigo").get("/")
check("switching it back on", s == 200, s)

# Look previews on the setup screen
s, h, page = bisi.get("/shop-setup/preview?look=market")
check("setup previews a look with the owner's products", s == 200 and "look-market" in page and "Olokun wrap dress" in page and "no-store" in h.get("cache-control", ""), (s, h.get("cache-control")))
s, h, page = bisi.get("/shop-setup/preview?look=nonsense")
check("setup preview of an unknown look is a 404", s == 404, s)
s, h, page = bisi.get("/shop-setup")
check("setup offers full-size previews", page.count("data-look-preview=") == 10, page.count("data-look-preview="))

# ── Pages of their own: the full list at /shop, the form at /contact ──
for look, label in LOOKS.items():
    c = shop(label)
    s0, h0, home = c.get("/")
    s1, h1, lst = c.get("/shop")
    s2, h2, con = c.get("/contact")
    check(f"{look}: /shop and /contact render", s1 == 200 and s2 == 200 and f"look-{look}" in lst and f"look-{look}" in con, (s1, s2))
    check(f"{look}: no PHP warnings on /shop or /contact", not PHP_NOISE.search(lst + con), "")
    check(f"{look}: /shop and /contact are cached like the home page",
          h1.get("cache-control") == h2.get("cache-control") == "public, max-age=0, s-maxage=300", (h1.get("cache-control"), h2.get("cache-control")))
    check(f"{look}: each page has its own address for search engines",
          f'<link rel="canonical" href="http://{label}.nfelia.test:8000/shop">' in lst
          and f'<link rel="canonical" href="http://{label}.nfelia.test:8000/contact">' in con, "")
    check(f"{look}: the menu reaches both pages", 'id="sf-menu"' in home and 'href="/shop"' in home and 'href="/contact"' in home, "")
    check(f"{look}: the full list is on /shop, not the home page", "sf-page__title" in lst and "sf-page__title" not in home, "")
s, h, sitemap = shop("bisi-indigo").get("/sitemap.xml")
check("the sitemap lists /shop and /contact", "<loc>http://bisi-indigo.nfelia.test:8000/shop</loc>" in sitemap
      and "<loc>http://bisi-indigo.nfelia.test:8000/contact</loc>" in sitemap, sitemap[:300])
catalogue = next(x for x in draft["sections"] if x["type"] == "catalogue")
catalogue["on"] = False
bisi.api("/landing-page/save-draft", {"settings": draft})
bisi.api("/landing-page/publish")
s, h, _ = shop("bisi-indigo").get("/shop")
check("with the list switched off, /shop goes to the home page", s == 302 and h.get("location", "").rstrip("/") == "http://bisi-indigo.nfelia.test:8000", (s, h.get("location")))
catalogue["on"] = True

# ── Brand fonts and colours: Settings leads, Studio can override ─────
s, h, settings_page = bisi.get("/settings")
brand_data = json.loads(re.search(r'<script type="application/json" id="nf-brand-data">(.*?)</script>', settings_page, re.S).group(1))
heads = [f for f in brand_data["fonts"] if f["head"] is not None]
bodies = [f for f in brand_data["fonts"] if f["body"]]
check("Settings offers at least 20 primary and 20 secondary fonts", len(heads) >= 20 and len(bodies) >= 20, (len(heads), len(bodies)))
check("each font's name is drawn in that font", brand_data["samples"].startswith("https://fonts.googleapis.com/css2?") and "&text=" in brand_data["samples"], brand_data["samples"][:200])
head_only = next(f["key"] for f in heads if not f["body"])


def save_settings(**fields):
    s, h, page = bisi.get("/settings")
    token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', page).group(1)
    data = {"csrf_token": token, "business_name": "Bisi Indigo", "tagline": "Adire, cut to your measure.", "phone": "0803 123 4567",
            "email": "owner2@nfelia.test", "address": "12 Lafenwa Road\nAbeokuta, Ogun State", "currency_symbol": "₦",
            "primary_color": "#A56203", "secondary_color": "#252018", "brand_font_head": "eb-garamond", "brand_font_body": "questrial"}
    data.update(fields)
    return bisi.req("POST", "/settings/update-business", data)


save_settings(brand_font_head="comic-sans", brand_font_body=head_only)
row = sql("SELECT IFNULL(brand_font_head, '-'), IFNULL(brand_font_body, '-') FROM businesses WHERE id = 2")[0]
check("Settings refuses a font outside the library, and a headline-only font for reading", row == ["-", "-"], row)
save_settings()
row = sql("SELECT brand_font_head, brand_font_body, primary_color, secondary_color FROM businesses WHERE id = 2")[0]
check("Settings saves the brand fonts and colours", row == ["eb-garamond", "questrial", "#A56203", "#252018"], row)


def reads(v):
    return (contrast(v["--sf-ink"], v["--sf-ground"]) >= 7 and contrast(v["--sf-on"], v["--sf-accent"]) >= 4.5
            and contrast(v["--sf-accent-text"], v["--sf-ground"]) >= 4.5 and contrast(v["--sf-muted"], v["--sf-ground"]) >= 4.5)


draft["font"] = ""
for mode in ("dark", "light"):
    draft["colours"] = {"set": "brand", "mode": mode}
    s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
    t = r.get("theme", {})
    v = t.get("vars", {})
    check(f"brand, {mode}: the shop takes the brand fonts", t.get("pair") == "brand" and "EB+Garamond" in t.get("fonts_url", "") and "Questrial" in t.get("fonts_url", ""), (t.get("pair"), t.get("fonts_url")))
    check(f"brand, {mode}: the secondary colour is the " + ("ground" if mode == "dark" else "words"),
          (v.get("--sf-ground") if mode == "dark" else v.get("--sf-ink")) == "#252018" and t.get("dark") is (mode == "dark"), (v.get("--sf-ground"), v.get("--sf-ink")))
    check(f"brand, {mode}: every text pairing reads", reads(v), v)
draft["font"] = "bodoni"
s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
check("a font pair picked in Studio wins, on the shop alone", r["theme"]["pair"] == "bodoni" and "EB+Garamond" not in r["theme"]["fonts_url"], r["theme"]["pair"])
draft["font"] = ""
draft["colours"] = {"set": "brand", "mode": "dark"}
bisi.api("/landing-page/save-draft", {"settings": draft})
bisi.api("/landing-page/publish")
s, h, live = shop("bisi-indigo").get("/")
fonts = re.findall(r'href="(https://fonts\.googleapis\.com/[^"]+)"', live)
check("the live shop loads one font link, with both brand fonts", len(fonts) == 1 and "EB+Garamond" in fonts[0] and "Questrial" in fonts[0], fonts)
sql("INSERT INTO customers (business_id, full_name, phone) VALUES (2, 'Kit Brand Customer', '08030000000')")
customer = sql("SELECT MAX(id) FROM customers WHERE business_id = 2")[0][0]
sql(f"INSERT INTO invoices (business_id, customer_id, user_id, doc_number, status, issue_date, subtotal, total, amount_paid, balance_due, token) "
    f"VALUES (2, {customer}, 2, 'S2-INV-KIT', 'sent', CURDATE(), 1000, 1000, 0, 1000, 'kitbrandfonts000000000000000000000000000000000000000000000000001')")
s, h, doc = Client().get("/doc/kitbrandfonts000000000000000000000000000000000000000000000000001")
check("the invoice link the customer opens uses the brand fonts", s == 200 and re.search(r'fonts\.googleapis\.com/css2\?[^"]*EB\+Garamond[^"]*Questrial', doc)
      and "--font-heading: 'EB Garamond'" in doc and "--font-body: 'Questrial'" in doc, (s, re.findall(r"--font-[a-z]+: [^;]+", doc)[:3]))

# Settings saved before brand fonts move to the brand, once
old_draft = {"look": "atelier", "colours": {"set": "look"}, "font": "bodoni", "sections": [{"type": "hero", "on": True, "headline": "Saved long ago"}]}
sql("UPDATE businesses SET storefront_draft = '" + json.dumps(old_draft) + "' WHERE id = 2")
s, h, studio = bisi.get("/landing-page")
moved = json.loads(re.search(r'<script type="application/json" id="st-data">(.*?)</script>', studio, re.S).group(1))["settings"]
check("old settings move to the brand fonts and colours", moved.get("v") == 2 and moved["font"] == "" and moved["colours"].get("set") == "brand"
      and moved["colours"].get("mode") in ("light", "dark"), (moved.get("v"), moved["font"], moved["colours"]))
check("and keep what the owner wrote", next(x for x in moved["sections"] if x["type"] == "hero")["headline"] == "Saved long ago", "")
moved["font"] = "bodoni"
s, r = bisi.api("/landing-page/save-draft", {"settings": moved})
check("a font picked after the move stays picked", r["settings"]["font"] == "bodoni", r["settings"]["font"])
bisi.api("/landing-page/save-draft", {"settings": draft})

# ── Announcement: an end date ────────────────────────────────────────
import datetime
announcement = next(x for x in draft["sections"] if x["type"] == "announcement")
announcement.update({"on": True, "text": "Fittings this Saturday", "until": "2020-01-01"})
s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
check("the end date is kept", next(x for x in r["settings"]["sections"] if x["type"] == "announcement")["until"] == "2020-01-01", "")
bisi.api("/landing-page/publish")
s, h, live = shop("bisi-indigo").get("/")
check("an ended announcement leaves the shop", 'id="sf-announcement"' not in live, "")
s, h, prev = bisi.get("/landing-page/preview")
check("the preview still shows it, marked as ended", 'id="sf-announcement"' in prev and "Ended on 1 January. Shoppers no longer see it." in prev, "")
announcement["until"] = (datetime.date.today() + datetime.timedelta(days=7)).isoformat()
bisi.api("/landing-page/save-draft", {"settings": draft})
bisi.api("/landing-page/publish")
s, h, live = shop("bisi-indigo").get("/shop")
check("it shows on every page until its end date", 'id="sf-announcement"' in live and "Fittings this Saturday" in live, "")
announcement["until"] = "31/12/2026"
s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
check("a date that isn't one is dropped", next(x for x in r["settings"]["sections"] if x["type"] == "announcement")["until"] == "", "")
announcement["until"] = ""

# ── Hero: up to five photos in turn, and the page background ─────────
def upload(rgb):
    img = subprocess.run(["php", "-r", f"$i=imagecreatetruecolor(1200,1500);imagefill($i,0,0,imagecolorallocate($i,{rgb}));ob_start();imagejpeg($i);echo base64_encode(ob_get_clean());"],
                         capture_output=True, text=True).stdout
    form = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"csrf_token\"\r\n\r\n{bisi.token}\r\n"
            f"--{boundary}\r\nContent-Disposition: form-data; name=\"media\"; filename=\"slide.jpg\"\r\nContent-Type: image/jpeg\r\n\r\n").encode() \
        + __import__("base64").b64decode(img) + f"\r\n--{boundary}--\r\n".encode()
    s, h, raw = bisi.req("POST", "/landing-page/upload-image", headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}, body=form)
    return json.loads(raw).get("path", "")


slides = [upload(f"{40 + 30 * i},{70 + 20 * i},120") for i in range(6)]
check("six photos upload", all(re.match(r"^uploads/storefront/2/hero_\d+_[0-9a-f]{6}\.jpg$", x) for x in slides), slides)
hero = next(x for x in draft["sections"] if x["type"] == "hero")
hero.update({"slides": slides + ["uploads/storefront/3/hero_x.jpg"], "background": ""})
s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
kept = next(x for x in r["settings"]["sections"] if x["type"] == "hero")
check("five photos are kept, in order, none from another shop", kept["slides"] == slides[:5] and kept["image"] == slides[0], kept["slides"])
hero["slides"] = kept["slides"]
bisi.api("/landing-page/publish")
s, h, live = shop("bisi-indigo").get("/")
check("Atelier plays them in turn, with a bar for each", live.count('class="sf-cover__slide') == 5 and live.count('class="sf-cover__bar"') == 5 and 'data-interval="6500"' in live, live.count('class="sf-cover__slide'))
check("only the first photo loads at once", live.count('data-src="') + live.count('data-srcset="') >= 4, "")
backdrop = re.search(r'<div class="sf-backdrop" aria-hidden="true">(.*?)</div>', live, re.S)
check("the first photo is the page background by default", backdrop and slides[0].split("/")[-1] in backdrop.group(1), backdrop and backdrop.group(1)[:200])
for value, expect in ((slides[2], slides[2]), ("none", None)):
    hero["background"] = value
    bisi.api("/landing-page/save-draft", {"settings": draft})
    s, h, prev = bisi.get("/landing-page/preview")
    backdrop = re.search(r'<div class="sf-backdrop" aria-hidden="true">(.*?)</div>', prev, re.S)
    if expect:
        check("another photo can be the page background", backdrop and expect.split("/")[-1] in backdrop.group(1), "")
    else:
        check("or none at all", backdrop is None, "")
hero["background"] = ""
hero["slides"] = slides[:1]
bisi.api("/landing-page/save-draft", {"settings": draft})
s, h, prev = bisi.get("/landing-page/preview")
check("one photo stays put: no bars, no timer", prev.count('class="sf-cover__slide') == 1 and "sf-cover__bar" not in prev and "data-interval" not in prev, "")
draft["look"] = "stage"
hero["slides"] = slides[:3]
bisi.api("/landing-page/save-draft", {"settings": draft})
s, h, prev = bisi.get("/landing-page/preview")
check("other looks show the first photo", "sf-cover__slide" not in prev and slides[0].split("/")[-1] in prev and slides[1].split("/")[-1] not in prev, "")
draft["look"] = "glow"
bisi.api("/landing-page/save-draft", {"settings": draft})
s, h, prev = bisi.get("/landing-page/preview")
intro = re.search(r'<article class="sf-story sf-story--intro[^"]*".*?</article>', prev, re.S)
check("Glow opens its stories with them, the first with the headline",
      intro and slides[0].split("/")[-1] in intro.group(0) and 'id="sf-hero-title"' in intro.group(0)
      and prev.count("sf-story sf-story--photo") == 2 and slides[2].split("/")[-1] in prev, intro and intro.group(0)[:300])
draft["look"] = "atelier"
bisi.api("/landing-page/save-draft", {"settings": draft})

# ── Buka, Glow and Shea: the turntable, the stories, the skin finder ──
def pid(biz, name):
    return int(sql(f"SELECT id FROM products WHERE business_id = {biz} AND name = '{name}'")[0][0])


def hero_of(settings):
    return next(x for x in settings["sections"] if x["type"] == "hero")


def studio_data_of(client):
    s, h, page = client.get("/landing-page")
    return json.loads(re.search(r'<script type="application/json" id="st-data">(.*?)</script>', page, re.S).group(1))


s, h, page = shop("mama-nkechi").get("/")
check("Buka opens on the turntable, a plate and the words for each dish",
      'data-turntable data-count="6"' in page and page.count('class="sf-turntable__plate') == 6 and page.count('data-turn-card="') == 6
      and page.count('class="sf-turntable__dot"') == 6 and 'id="sf-hero-title"' in page, page.count('class="sf-turntable__plate'))
first = re.search(r'data-turn-card="0">\s*<h2 class="sf-turntable__name"><a [^>]*>([^<]+)</a>', page)
check("Buka: dishes with photos come round first", first and first.group(1) in ("Jollof rice and chicken", "Egusi soup, 1 litre"), first and first.group(1))
check("Buka: only the dish in front is open to a tap; the plates are pictures", page.count('class="sf-turntable__plate is-front"') == 1
      and re.search(r'class="sf-turntable__food" tabindex="-1" aria-hidden="true"', page), "")
check("Buka: every dish has its price and Add", page.count('class="sf-btn sf-turntable__add" data-add="') == 6 and "per plate" in page, "")
check("Buka: no open sign without opening hours", "data-hours=" not in page, "")
sql("UPDATE businesses SET booking_settings = '{\"hours\":{\"mon\":{\"open\":\"08:00\",\"close\":\"21:00\"},\"tue\":{\"open\":\"08:00\",\"close\":\"21:00\"},"
    "\"wed\":{\"open\":\"08:00\",\"close\":\"21:00\"},\"thu\":{\"open\":\"08:00\",\"close\":\"21:00\"},\"fri\":{\"open\":\"08:00\",\"close\":\"21:00\"},"
    "\"sat\":{\"open\":\"10:30\",\"close\":\"18:00\"},\"sun\":null}}' WHERE id = 3")
s, h, page = shop("mama-nkechi").get("/")
today = datetime.date.today().weekday()
said = "Closed today" if today == 6 else ("Open today, 10:30am to 6pm" if today == 5 else "Open today, 8am to 9pm")
check("Buka: its booking hours make an open sign, today's hours in words", 'data-hours="{&quot;mon&quot;:{&quot;open&quot;:&quot;08:00&quot;' in page and said in page, re.findall(r'data-open-text>([^<]*)<', page))
sql("UPDATE businesses SET booking_settings = NULL WHERE id = 3")

nkechi = Client()
nkechi.login("owner3@nfelia.test")
buka_draft = studio_data_of(nkechi)["settings"]
egusi, zobo, olokun = pid(3, "Egusi soup, 1 litre"), pid(3, "Zobo, 1 litre"), pid(2, "Olokun wrap dress")
hero_of(buka_draft)["picks"] = [zobo, olokun, egusi]
s, r = nkechi.api("/landing-page/save-draft", {"settings": buka_draft})
check("the dishes on the table are the owner's own, in the owner's order", hero_of(r["settings"])["picks"] == [zobo, egusi], hero_of(r["settings"])["picks"])
s, h, prev = nkechi.get("/landing-page/preview")
first = re.search(r'data-turn-card="0">\s*<h2 class="sf-turntable__name"><a [^>]*>([^<]+)</a>', prev)
check("two dishes go round the table three times, the first picked in front",
      'data-count="2" style="--slots: 6;' in prev and prev.count('class="sf-turntable__plate') == 6 and first and first.group(1) == "Zobo, 1 litre", first and first.group(1))
hero_of(buka_draft)["picks"] = [egusi]
nkechi.api("/landing-page/save-draft", {"settings": buka_draft})
s, h, prev = nkechi.get("/landing-page/preview")
check("one dish sits still: no arrows, no dots", 'data-count="1"' in prev and "sf-turntable__controls" not in prev and "sf-turntable__dot" not in prev, "")
hero_of(buka_draft)["picks"] = []
nkechi.api("/landing-page/save-draft", {"settings": buka_draft})

s, h, page = shop("glow-by-temi").get("/")
check("Glow opens on its stories, the headline first",
      'data-stories data-interval="5500"' in page and re.search(r'<article class="sf-story sf-story--intro[^"]*is-on"', page)
      and 'class="sf-h1 sf-story__title" id="sf-hero-title"' in page, "")
check("Glow: a story for each service, with a bar each", page.count('data-story-item="') == 5 and page.count('class="sf-stories__bar"') == 6, page.count('data-story-item="'))
check("Glow: stories after the first wait out of reach", page.count(' inert>') == 5, page.count(' inert>'))
check("Glow: a ring per category, and one to the prices", page.count('class="sf-ring"') == 3 and 'sf-ring sf-ring--all' in page, page.count('class="sf-ring"'))
check("Glow: its home page lists prices instead of category tiles again", "sf-cats" not in page and "sf-item--pricerow" in page, "")
check("Glow: the header sits over the stories", "sf-header--over" in page, "")
s, h, list_page = shop("glow-by-temi").get("/shop")
check("Glow: but not over its other pages", "sf-header--over" not in list_page and "data-stories" not in list_page, "")
lash = pid(4, "Classic lash set")
sql(f"UPDATE products SET booking_mode = 'slot', booking_minutes = 90 WHERE id = {lash}")
s, h, page = shop("glow-by-temi").get("/")
story = re.search(rf'<article [^>]*data-story-item="{lash}".*?</article>', page, re.S)
check("Glow: a service people book says how long and books from the story",
      story and "1 hour 30 minutes" in story.group(0) and f'data-book="{lash}"' in story.group(0) and "Book a slot" in story.group(0) and "data-add=" not in story.group(0),
      story and story.group(0)[:400])
sql(f"UPDATE products SET booking_mode = 'none' WHERE id = {lash}")

s, h, page = shop("zainab-naturals").get("/")
finder_html = page[page.find("data-finder"):page.find("</section>", page.find("data-finder"))]
skins = re.findall(r'data-skin="\d">\s*<span><span class="sf-skin__icon">.*?</span>([^<]+)</span>', finder_html, re.S)
check("Shea opens on the skin finder, its skin types from its categories", "What's your skin like?" in page and skins == ["Dry skin", "Oily skin", "Sensitive"], skins)
check("Shea: before a pick, the steps wait as empty places", finder_html.count("sf-rstep sf-rstep--ghost") == 3, finder_html.count("sf-rstep--ghost"))
dry = re.search(r'data-routine="0".*?data-add-routine.*?</button>', finder_html, re.S)
dry = dry.group(0) if dry else ""
steps = re.findall(r'class="sf-rstep__name">([^<]+)</a>', dry)
check("Shea: a routine per skin, in the order the steps are used", steps == ["African black soap, 200g", "Hibiscus face toner, 100ml", "Whipped shea butter"]
      and finder_html.count('data-routine="') == 3, steps)
shea, oil, toner, soap = pid(9, "Whipped shea butter"), pid(9, "Calming body oil"), pid(9, "Hibiscus face toner, 100ml"), pid(9, "African black soap, 200g")
check("Shea: what the words say goes first, the other that fits can be swapped in", f'data-rstep-items="{shea},{oil}"' in dry and "data-swap" in dry, "")
check("Shea: the routine adds up its steps, as each comes unpicked", "data-routine-total>₦15,000<" in dry and "250g, Unscented" in dry, re.findall(r"data-routine-total>([^<]*)<", dry))
check("Shea: perfume stays out of routines", "Oud perfume oil" not in finder_html, "")
css = open(os.path.join(ROOT, "assets/css/storefront.css")).read()
check("no look restyles the checkout's steps (.sf-step is the bag's alone)", len(re.findall(r"\.sf-step(?![\w-])", css)) == 1, re.findall(r"[^\n]*\.sf-step(?![\w-])[^\n]*", css)[:4])

zainab = Client()
zainab.login("owner9@nfelia.test")
studio_data = studio_data_of(zainab)
check("Studio knows the finder's skin types and its guesses", studio_data["finder"]["skins"] == ["Dry skin", "Oily skin", "Sensitive"]
      and studio_data["finder"]["guess"].get(str(soap)) == "cleanse" and studio_data["finder"]["guess"].get(str(pid(9, "Oud perfume oil, 12ml"))) == "none", studio_data.get("finder"))
shea_draft = studio_data["settings"]
hero_of(shea_draft).update({"skins": ["Dry skin", "Oily skin", "oily SKIN", "Sensitive", "Dark spots and uneven tone, all over", "Acne", "Mature", "Eczema"],
                            "routine": [{"id": toner, "step": "tone", "skins": ["Oily skin", "Not offered"]}, {"id": oil, "step": "none", "skins": []},
                                        {"id": soap, "step": "exfoliate", "skins": []}, {"id": olokun, "step": "treat", "skins": []}]})
s, r = zainab.api("/landing-page/save-draft", {"settings": shea_draft})
kept = hero_of(r["settings"])
check("skin types are cleaned: each once, short, six at most", kept["skins"] == ["Dry skin", "Oily skin", "Sensitive", "Dark spots and uneven to", "Acne", "Mature"], kept["skins"])
check("the owner's word on products is kept, cleaned, and only for their own",
      kept["routine"] == [{"id": toner, "step": "tone", "skins": ["Oily skin"]}, {"id": oil, "step": "none", "skins": []}, {"id": soap, "step": "none", "skins": []}], kept["routine"])
hero_of(shea_draft)["routine"] = [{"id": toner, "step": "tone", "skins": ["Oily skin"]}, {"id": oil, "step": "none", "skins": []}]
zainab.api("/landing-page/save-draft", {"settings": shea_draft})
s, h, prev = zainab.get("/landing-page/preview")
routines = dict(re.findall(r'<h2 class="sf-routine__title">Your routine for ([^<]+)</h2>(.*?)data-add-routine', prev, re.S))
names = {k: re.findall(r'class="sf-rstep__name">([^<]+)</a>', v) for k, v in routines.items()}
check("a product the owner placed is only for the skins they ticked", "Hibiscus face toner, 100ml" in names.get("oily skin", [])
      and "Hibiscus face toner, 100ml" not in names.get("dry skin", ["x"]) and all("Calming body oil" not in n for n in names.values()), names)
check("every skin the owner added gets a routine", len(routines) == 6 and "dark spots and uneven to" in routines, list(routines))
hero_of(shea_draft)["routine"] = [{"id": x, "step": "none", "skins": []} for x in (soap, toner, oil)]
zainab.api("/landing-page/save-draft", {"settings": shea_draft})
s, h, prev = zainab.get("/landing-page/preview")
check("with fewer than two products in routines, the shelf shows instead", "sf-hero--shelf" in prev and "data-finder" not in prev, "")
hero_of(shea_draft).update({"skins": [], "routine": []})
zainab.api("/landing-page/save-draft", {"settings": shea_draft})

# ── Market and Volt: the price board, the spotlight ──────────────────
def checkout(label, lines, phone="08031110000"):
    sql("DELETE FROM auth_throttle WHERE bucket LIKE 'order%' OR bucket = 'form_email'")
    s, h, raw = shop(label).req("POST", "/checkout", {"customer_name": "Ada Obi", "customer_phone": phone, "fulfillment_type": "pickup", "channel": "whatsapp",
                                                      "items": json.dumps([{"product_id": i, "quantity": n, "item_note": f"line {k}"} for k, (i, n) in enumerate(lines)])})
    try:
        return s, json.loads(raw)
    except Exception:
        return s, {"raw": raw[:300].decode("utf-8", "replace")}


def last_order_lines(biz):
    return [(float(p), float(n)) for p, n in sql(f"SELECT i.unit_price, i.quantity FROM order_items i JOIN orders o ON o.id = i.order_id "
                                                 f"WHERE o.business_id = {biz} AND o.id = (SELECT MAX(id) FROM orders WHERE business_id = {biz}) ORDER BY i.id")]


s, h, page = shop("musa-provisions").get("/")
board = page[page.find("data-board"):page.find("</section>", page.find("data-board"))]
check("Market opens on its price board, the shop's name painted across it",
      'class="sf-board__title" id="sf-hero-title"' in board and 'class="sf-board__shop" aria-hidden="true"><span>★</span>Musa &amp; Sons Provisions' in board
      and "sf-header--slim" in page, "")
check("Market: every product on the board, by category, each with Add and a stepper", board.count('class="sf-board__row') == 7
      and board.count('class="sf-board__cat"') == 3 and board.count('data-stepper="') == 6 and "Macaroni 500g" in board, (board.count('class="sf-board__row'), board.count('data-stepper="')))
check("Market: a product with choices opens its sheet instead of the calculator", re.search(r'data-add="\d+" aria-label="Add Macaroni 500g to your bag"', board), "")
check("Market: the board says when its prices changed, and has a tab for a list", "Prices updated today" in board and 'data-board-tab="list"' in board and "data-mylist-text" in board, "")
check("Market: no category tiles under the board", "sf-cats" not in page, "")
check("Market: the calculator and the order book come with the bag", 'id="sf-calc"' in page and "data-calc-input" in page and "sf-book__tear" in page and "Send this order" in page, "")
check("Market: no buy-more prices, smallest order or free delivery until the owner sets them", "sf-buymore\"><svg" not in board and "Smallest order" not in board and "Free delivery from" not in board, "")
check("Market: the sign takes a colour readable on the board, its shadows only where they stand apart",
      re.search(r"--sf-sign: ?#[0-9A-Fa-f]{6}", page) and re.search(r"--sf-sign-shade: ?(#[0-9A-Fa-f]{6}|transparent);", page)
      and re.search(r"--sf-price-shade: ?(#[0-9A-Fa-f]{6}|transparent);", page), "")

musa = Client()
musa.login("owner6@nfelia.test")
market_draft = studio_data_of(musa)["settings"]
noodles, rice, spaghetti = pid(6, "Instant noodles, chicken 70g"), pid(6, "Rice, long grain"), pid(6, "Spaghetti 500g")
hero_of(market_draft).update({"min_order": "50000", "free_from": 200000, "badge": "Wholesale prices daily", "breaks": [
    {"id": noodles, "from": 20, "price": 9000}, {"id": noodles, "from": 5, "price": 9300}, {"id": noodles, "from": 5, "price": 9100},
    {"id": noodles, "from": 50, "price": 8800}, {"id": noodles, "from": 100, "price": 8500},
    {"id": rice, "from": 1, "price": 70000}, {"id": rice, "from": 5, "price": "76500"}, {"id": spaghetti, "from": 10, "price": 25000},
    {"id": olokun, "from": 5, "price": 100}, {"id": rice, "from": 10, "price": 0}]})
s, r = musa.api("/landing-page/save-draft", {"settings": market_draft})
kept = hero_of(r["settings"])
check("prices for buying more are cleaned: three steps a product, fewest first, the shop's own products only",
      kept["breaks"] == [{"id": noodles, "from": 5, "price": 9300}, {"id": noodles, "from": 20, "price": 9000}, {"id": noodles, "from": 50, "price": 8800},
                         {"id": rice, "from": 5, "price": 76500}, {"id": spaghetti, "from": 10, "price": 25000}]
      and kept["min_order"] == 50000 and kept["free_from"] == 200000, kept["breaks"])
musa.api("/landing-page/publish")
s, h, page = shop("musa-provisions").get("/")
board = page[page.find("data-board"):page.find("</section>", page.find("data-board"))]
row = lambda pid_: (re.search(rf'<div [^>]*data-item="{pid_}"[^>]*class="sf-board__row.*?</div>\s*</div>', board, re.S)
                    or re.search(rf'<div class="sf-board__row[^"]*"[^>]*data-item="{pid_}".*?class="sf-board__act"', board, re.S))
noodle_row = row(noodles)
noodle_row = noodle_row.group(0) if noodle_row else ""
check("Market: the board shows each step under its product", "5+ <strong>₦9,300</strong>" in noodle_row and "20+ <strong>₦9,000</strong>" in noodle_row
      and "50+ <strong>₦8,800</strong>" in noodle_row and '<span class="sf-sr"> each</span>' in noodle_row, noodle_row[:500])
spag_row = row(spaghetti)
check("Market: a step that isn't cheaper than the price is never shown", spag_row and "sf-buymore" not in spag_row.group(0) and "₦25,000" not in board, "")
check("Market: the smallest order and free delivery are on the sign", "Smallest order ₦50,000" in board and "Free delivery from ₦200,000" in board and "Wholesale prices daily" in board, "")
data = json.loads(re.search(r'<script type="application/json" id="sf-data">(.*?)</script>', page, re.S).group(1))
by_id = {i["id"]: i for i in data["items"].values()}
check("Market: the page's script knows the steps and the thresholds", by_id[noodles]["breaks"] == [{"from": 5, "price": 9300}, {"from": 20, "price": 9000}, {"from": 50, "price": 8800}]
      and by_id[spaghetti]["breaks"] == [] and data["order"] == {"min": 50000, "free": 200000}, (by_id[noodles].get("breaks"), data.get("order")))

s, r = checkout("musa-provisions", [(noodles, 3)])
check("checkout refuses an order under the smallest, and says how much more", s == 422 and r.get("error") == "The smallest order Musa & Sons Provisions takes is ₦50,000. Add ₦21,200 more and send it again.", (s, r))
s, r = checkout("musa-provisions", [(noodles, 2), (noodles, 4)])
check("checkout charges the step reached by all the lines of a product together", s == 200 and r.get("total") == 55800 and last_order_lines(6) == [(9300.0, 2.0), (9300.0, 4.0)], (s, r, last_order_lines(6)))
s, r = checkout("musa-provisions", [(noodles, 25), (rice, 1)])
check("each product gets its own step; one bag of rice pays the rice price", s == 200 and last_order_lines(6) == [(9000.0, 25.0), (78000.0, 1.0)], last_order_lines(6))
s, r = checkout("musa-provisions", [(spaghetti, 10), (rice, 1)])
check("a step dearer than the price never raises it", s == 200 and last_order_lines(6)[0] == (21500.0, 10.0), last_order_lines(6))
market_draft = studio_data_of(musa)["settings"]
market_draft["look"] = "desk"
musa.api("/landing-page/save-draft", {"settings": market_draft})
musa.api("/landing-page/publish")
s, r = checkout("musa-provisions", [(noodles, 3)])
check("a shop that leaves Market charges its own prices and takes any order", s == 200 and last_order_lines(6) == [(9600.0, 3.0)], (s, r, last_order_lines(6)))
s, h, page = shop("musa-provisions").get("/")
check("and shows no steps or thresholds", "sf-buymore\"><svg" not in page and "Smallest order" not in page and '"order":{"min":0,"free":0}' in page, "")
market_draft["look"] = "market"
musa.api("/landing-page/save-draft", {"settings": market_draft})
musa.api("/landing-page/publish")
hero_of(market_draft)["headline"] = "Musa & Sons provisions"
musa.api("/landing-page/save-draft", {"settings": market_draft})
s, h, prev = musa.get("/landing-page/preview")
check("Market: a headline that is only the shop's name is painted once", '<h1 class="sf-board__shop" id="sf-hero-title">' in prev and "sf-board__title" not in prev, "")
hero_of(market_draft)["headline"] = ""
musa.api("/landing-page/save-draft", {"settings": market_draft})
s, h, page = shop("musa-provisions").get("/shop")
check("Market's list page sells with the calculator and shows the steps too", 'data-calc-open="' in page and "5+ <strong>₦9,300</strong>" in page and 'data-stepper="' in page, "")

s, h, page = shop("kola-gadgets").get("/")
spot = page[page.find("data-spotlight"):page.find("</section>", page.find("data-spotlight"))]
figures = spot.split('<figure class="sf-spot__device')[1:]
cards = spot[:spot.find('class="sf-spot__repairs"')].split('data-spot-card="')[1:]
check("Volt opens on the spotlight: each device with a card, the first lit", 'data-spotlight data-count="5"' in spot and len(figures) == 5 and len(cards) == 5
      and spot.count('class="sf-spot__device is-on"') == 1 and spot.count('class="sf-spot__dot"') == 5 and 'class="sf-h1 sf-spot__title" id="sf-hero-title"' in spot, len(figures))
check("Volt: services stay out of the spotlight", not any("Screen replacement" in c for c in cards), "")
card_of = {re.search(r'<h2 class="sf-spot__name"><a [^>]*>([^<]+)</a>', c).group(1): (c, figures[i]) for i, c in enumerate(cards)}
iphone_card, iphone_fig = card_of.get("iPhone 12, 128GB", ("", ""))
chips = lambda fig: [re.sub(r"<[^>]+>", "", x) for x in re.findall(r'class="sf-callout__chip">(.*?)</span>\s*</li>', fig, re.S)]
check("Volt: what a buyer checks first is pointed out round the device, from its name, options and words",
      chips(iphone_fig) == ["128GB", "2 colours", "Battery 86%", "Face ID works"] and 'class="sf-battery sf-battery--sm" style="--level: 86%"' in iphone_fig, chips(iphone_fig))
check("Volt: the card says its condition and battery health", '<p class="sf-grade sf-grade--abroad">UK used</p>' in iphone_card
      and 'sf-batterymeter sf-batterymeter--strong' in iphone_card and "Battery health <strong>86%</strong>" in iphone_card and "₦385,000" in iphone_card, iphone_card[:500])
hp_card, hp_fig = card_of.get("HP EliteBook 840 G6", ("", ""))
check("Volt: a laptop's specs come from its options; no condition or battery it doesn't state", chips(hp_fig) == ["16GB RAM", "512GB SSD", "Core i5"]
      and "sf-grade" not in hp_card and "sf-batterymeter" not in hp_card, chips(hp_fig))
check("Volt: a sealed box is brand new; two storage sizes are one callout", '<p class="sf-grade sf-grade--new">Brand new</p>' in card_of.get("Samsung Galaxy A15, 128GB", ("", ""))[0]
      and chips(card_of.get("Samsung Galaxy A15, 128GB", ("", ""))[1]) == ["128GB or 256GB"], chips(card_of.get("Samsung Galaxy A15, 128GB", ("", ""))[1]))
check("Volt: devices with photos come first", list(card_of)[:2] in (["Power bank, 20,000mAh", "iPhone 12, 128GB"], ["iPhone 12, 128GB", "Power bank, 20,000mAh"]), list(card_of))
check("Volt: a strip to the repair desk, from the cheapest repair", 'class="sf-spot__repairs" href="/shop#sf-services"' in spot and "Repairs from ₦12,000" in spot, "")
check("Volt: no stamp without a sticker", "sf-spot__stamp" not in spot, "")
s, h, list_page = shop("kola-gadgets").get("/shop")
check("Volt: the list shows condition and battery too", '<p class="sf-grade sf-grade--abroad">UK used</p>' in list_page and "Battery health <strong>86%</strong>" in list_page, "")

kola = Client()
kola.login("owner5@nfelia.test")
volt_draft = studio_data_of(kola)["settings"]
hp, screen = pid(5, "HP EliteBook 840 G6"), pid(5, "Screen replacement")
hero_of(volt_draft).update({"picks": [hp, screen, olokun], "badge": "Tested before you pay"})
s, r = kola.api("/landing-page/save-draft", {"settings": volt_draft})
check("the devices in the spotlight are the owner's own", hero_of(r["settings"])["picks"] == [hp, screen], hero_of(r["settings"])["picks"])
s, h, prev = kola.get("/landing-page/preview")
check("one device stands still: no arrows, no dots; the sticker is a stamp", 'data-spotlight data-count="1"' in prev and "data-spot-step" not in prev and "sf-spot__dot" not in prev
      and '<p class="sf-spot__stamp"><span>Tested before you pay</span>' in prev and "HP EliteBook 840 G6" in prev, "")
hero_of(volt_draft)["picks"] = [screen]
kola.api("/landing-page/save-draft", {"settings": volt_draft})
s, h, prev = kola.get("/landing-page/preview")
check("with no device to show, Volt keeps its spec sheet", "data-spotlight" not in prev and "sf-hero--spec" in prev, "")
hero_of(volt_draft).update({"picks": [], "badge": ""})
kola.api("/landing-page/save-draft", {"settings": volt_draft})

# ── Haven and Stage: dates first, plan your event ─────────────────────
import shutil

one, studio_apt, duplex = pid(10, "One-bedroom apartment"), pid(10, "Studio"), pid(10, "Three-bedroom duplex")
sql(f"UPDATE products SET booking_mode = 'nights', deposit_type = 'percent', deposit_value = 30, caution_fee = 50000, stay_rates = '[[7,75000],[28,65000],[3,90000]]' WHERE id = {one}")
sql(f"UPDATE products SET booking_mode = 'nights' WHERE id IN ({studio_apt}, {duplex})")
sql("UPDATE businesses SET booking_settings = '{\"rules\":[\"No parties or events\",\"No smoking indoors\"]}' WHERE id = 10")
sql("DELETE FROM bookings WHERE business_id = 10")
s, h, page = shop("haven-shortlets").get("/")
stays_html = page[page.find("data-stays"):page.find("</section>", page.find("data-stays"))]
check("Haven opens on its dates: When are you coming?, every place below", "When are you coming?" in stays_html and stays_html.count('<li class="sf-stay" data-stay="') == 3
      and 'class="sf-h1 sf-stays__title" id="sf-hero-title"' in stays_html and 'id="sf-dates"' in page, stays_html.count('class="sf-stay"'))
one_card = re.search(rf'<li class="sf-stay" data-stay="{one}".*?data-stay-book>', stays_html, re.S)
one_card = one_card.group(0) if one_card else ""
check("Haven: what it sleeps and its amenities, power first", 'Sleeps 2' in one_card and re.search(r'<li class="sf-amenity sf-amenity--power">.*?24-hour power</li>\s*<li class="sf-amenity">.*?Wi-Fi</li>', one_card, re.S), one_card[:600])
check("Haven: a place's longer-stay prices and refundable caution fee, only the ones that are cheaper",
      "7+ nights <strong>₦75,000</strong>" in one_card and "28+ nights <strong>₦65,000</strong>" in one_card and "₦90,000" not in one_card
      and "₦50,000 refundable caution fee" in one_card, one_card[:800])
check("Haven: the house rules, with the check-in times", "House rules" in stays_html and "Check-in from 2:00pm" in stays_html and "No parties or events" in stays_html, "")
check("Haven: its places aren't listed again under the dates", "sf-cats" not in page and "sf-item--listing" not in page, "")
first = (datetime.date.today() + datetime.timedelta(days=10)).isoformat()
week = (datetime.date.today() + datetime.timedelta(days=17)).isoformat()
s, h, raw = shop("haven-shortlets").req("GET", f"/stays/free?checkin={first}&checkout={week}")
free = {a["id"]: a for a in json.loads(raw)["stays"]}
check("Haven: a week is priced as the booking will charge it", s == 200 and h.get("cache-control") == "no-store"
      and free[one] == {"id": one, "free": True, "nights": 7, "each": 75000, "total": 525000, "deposit": 157500, "caution": 50000}
      and free[studio_apt]["total"] == 420000 and free[studio_apt]["caution"] == 0, (s, free.get(one)))
s, h, raw = shop("haven-shortlets").req("GET", "/stays/free?month=" + first[:7])
days = json.loads(raw)["days"]
check("Haven: each night says how many places are free", days.get(first) == 3 and min(days.values()) >= -1, list(days.items())[:3])
sql("DELETE FROM auth_throttle WHERE bucket LIKE 'booking%' OR bucket = 'form_email'")
s, h, raw = shop("haven-shortlets").req("POST", f"/book/{one}", {"customer_name": "Ada Obi", "customer_phone": "08031112222", "checkin": first, "checkout": week, "channel": "whatsapp"})
booked = json.loads(raw)
row = sql(f"SELECT units, unit_price, total, deposit_due, caution_fee FROM bookings WHERE business_id = 10 ORDER BY id DESC LIMIT 1")
check("a week's stay books at the longer-stay price, with the deposit and the caution fee", s == 200 and booked.get("caution") == 50000
      and row == [["7", "75000.00", "525000.00", "157500.00", "50000.00"]], (s, booked, row))
s, h, raw = shop("haven-shortlets").req("GET", f"/stays/free?checkin={first}&checkout={week}")
check("Haven: a place booked for those nights says it's taken", {a["id"]: a for a in json.loads(raw)["stays"]}[one] == {"id": one, "free": False, "why": "taken"}, raw[:200])
s, h, raw = shop("haven-shortlets").req("GET", f"/stays/free?checkin={first}&checkout=" + (datetime.date.today() + datetime.timedelta(days=50)).isoformat())
check("Haven: a stay that's too long says why", "Stays can be up to 30 nights" in raw.decode(), raw[:200])
s, h, page = shop("haven-shortlets").get(f"/book/{one}-one-bedroom-apartment")
check("the booking page asks for the deposit and the caution fee, after the house rules", "Pay the deposit and the caution fee" in page and "Caution fee, refundable</span><strong>₦50,000" in page
      and "data-book-now" in page and "No smoking indoors" in page and "7+ nights <strong>₦75,000</strong>" in page, "")
token = sql(f"SELECT public_token FROM bookings WHERE business_id = 10 ORDER BY id DESC LIMIT 1")[0][0]
s, h, page = shop("haven-shortlets").get(f"/booking/{token}")
check("the guest's booking page shows the caution fee and that it comes back", "Caution fee, refundable</span><strong>₦50,000" in page and "comes back to you after you check out" in page
      and "7 nights × ₦75,000" in page, "")

bid = sql(f"SELECT id FROM bookings WHERE business_id = 10 ORDER BY id DESC LIMIT 1")[0][0]
host = Client()
host.login("owner10@nfelia.test")
sql(f"UPDATE bookings SET status = 'confirmed' WHERE id = {bid}")
host.req("POST", f"/bookings/caution/{bid}", {"csrf_token": host.token})
check("a caution fee can't be marked given back before the guest checks out", sql(f"SELECT caution_refunded_at IS NULL FROM bookings WHERE id = {bid}") == [["1"]], "")
sql(f"UPDATE bookings SET status = 'confirmed', starts_at = NOW() - INTERVAL 8 DAY, ends_at = CONCAT(CURDATE() - INTERVAL 1 DAY, ' 12:00:00') WHERE id = {bid}")
if shutil.which("faketime"):
    out = subprocess.run(f"TZ=Africa/Lagos faketime '{datetime.date.today().isoformat()} 10:00:00' php cron/planner.php", shell=True, capture_output=True, text=True, cwd=ROOT).stdout
    told = sql(f"SELECT title FROM notifications WHERE business_id = 10 AND type = 'booking.caution'")
    check("the morning after a stay, the owner hears to give back the caution fee", "cautions=1" in out and told and told[0][0].startswith("Give back a caution fee"), (out[-300:], told))
else:
    print("SKIP the caution reminder: needs faketime for the cron's clock")
s, h, page = host.get(f"/bookings/view/{bid}")
check("the owner's booking says to give back the caution fee once the stay is over", "Give back the ₦50,000 caution fee" in page and f'/bookings/caution/{bid}' in page, "")
s, h, page = host.get("/bookings?show=past")
check("and the list says so too", "Caution ₦50,000 to give back" in page, "")
s, h, _ = host.req("POST", f"/bookings/caution/{bid}", {"csrf_token": host.token})
check("marking it given back stamps the booking", sql(f"SELECT caution_refunded_at IS NOT NULL FROM bookings WHERE id = {bid}") == [["1"]], (s, h.get("location")))
s, h, page = host.get(f"/bookings/view/{bid}")
check("then the booking shows it given back, with no reminder", "Given back" in page and "Give back the ₦50,000" not in page, "")
s, h, page = shop("haven-shortlets").get(f"/booking/{token}")
check("and the guest's page says when", "Haven Shortlets gave it back on" in page, "")
host.studio_token()
s, h, _ = host.req("POST", "/bookings/settings", {"csrf_token": host.token, "checkin": "15:00", "checkout": "11:00", "rules": "- No parties\n2. No smoking indoors\n\nNo parties\n" + "x" * 200})
rules = json.loads(sql("SELECT booking_settings FROM businesses WHERE id = 10")[0][0])["rules"]
check("house rules are cleaned: a line each, numbering and repeats dropped, each kept short", rules == ["No parties", "No smoking indoors", "x" * 120], rules)
s, h, page = host.get(f"/products/edit/{duplex}")
check("the product form asks a stay for its caution fee and longer-stay prices", 'name="caution_fee"' in page and 'name="stay_from[]"' in page and "Cheaper for longer stays" in page, "")
form = [("csrf_token", host.token), ("name", "Three-bedroom duplex"), ("auto_sku", "1"), ("category", "Victoria Island"), ("description", "Victoria Island, sleeps 6."),
        ("sell_price", "250000"), ("unit", "night"), ("is_service", "1"), ("booking_mode", "nights"), ("deposit_type", "none"), ("caution_fee", "100000"),
        ("stay_from[]", "14"), ("stay_price[]", "200000"), ("stay_from[]", "5"), ("stay_price[]", "230000"), ("stay_from[]", "1"), ("stay_price[]", "1")]
host.req("POST", f"/products/edit/{duplex}", body=urllib.parse.urlencode(form), headers={"Content-Type": "application/x-www-form-urlencoded"})
saved = sql(f"SELECT caution_fee, stay_rates FROM products WHERE id = {duplex}")
check("the product form keeps the caution fee and the steps, fewest nights first", saved == [["100000.00", "[[5,230000],[14,200000]]"]], saved)

s, h, page = shop("lola-events").get("/")
plan_json = re.search(r'<div class="sf-plan" data-plan="([^"]+)"', page)
plan_data = json.loads(html.unescape(plan_json.group(1))) if plan_json else {}
check("Stage opens on its planner, the packages smallest first with the guests they hold",
      [(p["name"], p["guests"], p["open"]) for p in plan_data.get("packages", [])] == [("Intimate", 80, False), ("Classic", 250, False), ("Grand", 500, True)]
      and 'class="sf-h1 sf-plan__title" id="sf-hero-title"' in page and "How many guests?" in page, plan_data.get("packages"))
check("Stage: extras say how many guests each covers", [(x["name"], x["per"]) for x in plan_data.get("extras", [])] == [("Small chops tray", 50)], plan_data.get("extras"))
check("Stage: the colours to pick from, and the hall to see them on", page.count('name="sf-theme"') == 8 and 'data-decor' in page and "sf-decor__arch" in page, page.count('name="sf-theme"'))
check("Stage: no package is held online until the owner books it by the day", all(p["book"] is None for p in plan_data.get("packages", [])), "")
check("Stage: its packages and extras aren't listed again under the planner", "sf-item--package" not in page and "sf-cats" not in page, "")
classic = pid(7, "Classic")
sql(f"UPDATE products SET booking_mode = 'day', deposit_type = 'percent', deposit_value = 30 WHERE id = {classic}")
lola = Client()
lola.login("owner7@nfelia.test")
stage_draft = studio_data_of(lola)["settings"]
hero_of(stage_draft).update({"guests": [{"id": classic, "guests": 300}, {"id": olokun, "guests": 5}, {"id": classic, "guests": 10}], "themes": ["black-gold", "nope", "gold-emerald"]})
s, r = lola.api("/landing-page/save-draft", {"settings": stage_draft})
kept = hero_of(r["settings"])
check("the owner's guest numbers are kept for their own packages, once each; themes only known ones", kept["guests"] == [{"id": classic, "guests": 300}]
      and kept["themes"] == ["gold-emerald", "black-gold"], (kept["guests"], kept["themes"]))
s, h, prev = lola.get("/landing-page/preview")
plan_json = re.search(r'<div class="sf-plan" data-plan="([^"]+)"', prev)
plan_data = json.loads(html.unescape(plan_json.group(1))) if plan_json else {}
booked_pack = [p for p in plan_data.get("packages", []) if p["name"] == "Classic"]
check("a package booked by the day can be held, with its deposit and free days", booked_pack and booked_pack[0]["guests"] == 300
      and booked_pack[0]["book"] == {"url": f"/book/{classic}-classic", "free": f"/book/{classic}/free", "type": "percent", "value": 30}, booked_pack)
check("the planner offers only the owner's themes", prev.count('name="sf-theme"') == 2 and "Black and gold" in prev and "Royal blue" not in prev, prev.count('name="sf-theme"'))
sql(f"UPDATE products SET description = 'Decor and lighting.' WHERE business_id = 7 AND is_service = 1")
hero_of(stage_draft).update({"guests": [], "themes": []})
lola.api("/landing-page/save-draft", {"settings": stage_draft})
s, h, prev = lola.get("/landing-page/preview")
check("without packages that say how many guests, Stage keeps its arch", "data-plan" not in prev and "sf-hero--arch" in prev, "")

# ── Map and directions ───────────────────────────────────────────────
env = open(os.path.join(ROOT, ".env")).read()
if "GEOAPIFY_API_URL=http://127.0.0.1:8099" not in env or "GEOAPIFY_API_KEY=test-key" not in env:
    print("SKIP map checks: point .env at the Geoapify stand-in (see geoapify_mock.php)")
else:
    import socket
    import time
    try:
        socket.create_connection(("127.0.0.1", 8099), timeout=1).close()
    except OSError:
        subprocess.Popen(["php", "-S", "127.0.0.1:8099", os.path.join(KIT, "geoapify_mock.php")], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        time.sleep(1)
    calls = "/tmp/geoapify_mock/calls.log"
    if os.path.exists(calls):
        os.remove(calls)
    __import__("shutil").rmtree(os.path.join(ROOT, "uploads/maps/2"), ignore_errors=True)
    location = next(x for x in draft["sections"] if x["type"] == "location")
    s, h, live = shop("bisi-indigo").get("/")
    check("the map waits for its pin", location["lat"] is None and 'id="sf-location"' not in live, location)
    s, r = bisi.api("/landing-page/location-find", {"address": "12 Lafenwa Road\nAbeokuta, Ogun State"})
    check("the shop's address is found on the map", s == 200 and r.get("places") and abs(r["places"][0]["lat"] - 7.1651187) < 1e-6 and r.get("saved") is False, (s, r))
    check("the search stays in the shop's country", os.path.exists(calls) and re.search(r"countrycode(%3A|:)ng", open(calls).read()), "")
    s, r = bisi.api("/landing-page/location-find", {"address": "Admiralty Way, Lekki"})
    check("an address that could be two places offers both", s == 200 and len(r.get("places", [])) == 2, (s, r))
    check("a new address becomes the shop's address too", r.get("saved") is True and sql("SELECT address FROM businesses WHERE id = 2")[0][0] == "Admiralty Way, Lekki", r.get("saved"))
    bisi.api("/landing-page/location-find", {"address": "12 Lafenwa Road\nAbeokuta, Ogun State"})
    s, r = bisi.api("/landing-page/location-find", {"address": "Somewhere nobody knows"})
    check("an address that isn't found says what to do", s == 404 and "Google Maps link" in r.get("error", ""), (s, r))
    s, r = staff.api("/landing-page/location-find", {"address": "12 Lafenwa Road"})
    check("staff can't look up the map", s == 403, (s, r))
    s, r = bisi.api("/landing-page/location-find", {"address": "12 Lafenwa Road"}, token=False)
    check("the map lookup needs the form token", s == 419, (s, r))
    for link, lat, lng in (("https://www.google.com/maps/place/Bisi+Indigo/@7.1651187,3.3359874,17z/data=!4m6", 7.165119, 3.335987),
                           ("7.1651, 3.3359", 7.1651, 3.3359)):
        s, r = bisi.api("/landing-page/location-link", {"link": link})
        pin = r.get("pin") or {}
        check(f"a pin is read from {link[:32]}", s == 200 and abs(pin.get("lat", 0) - lat) < 1e-6 and abs(pin.get("lng", 0) - lng) < 1e-6, (s, r))
    s, r = bisi.api("/landing-page/location-link", {"link": "https://example.com/somewhere"})
    check("a link without a pin is refused", s == 422 and "Copy link" in r.get("error", ""), (s, r))
    for link in ("https://google.xyz/maps/abc", "https://maps.app.goo.gl:8443/abc", "http://maps.app.goo.gl/abc"):
        s, r = bisi.api("/landing-page/location-link", {"link": link})
        check(f"only Google's own hosts are asked: {link[:34]}", s == 422, (s, r))
    location.update({"on": True, "lat": 7.1651187, "lng": 3.3359874, "zoom": 16, "note": "Ring the bell at the blue door."})
    bisi.api("/landing-page/save-draft", {"settings": draft})
    bisi.api("/landing-page/publish")
    s, h, live = shop("bisi-indigo").get("/")
    s, h, contact_page = shop("bisi-indigo").get("/contact")
    picture = re.search(r'<img src="http://nfelia\.test:8000/(uploads/maps/2/map-[0-9a-f]{16}-1280\.webp)" srcset="[^"]+-640\.webp 640w, [^"]+-1280\.webp 1280w"', live)
    check("the map shows at the bottom of the home page, once pinned", 'id="sf-location"' in live and picture, re.findall(r'uploads/maps/[^"]+', live)[:2])
    check("and on the contact page", 'id="sf-location"' in contact_page, "")
    check("Get directions opens Google Maps at the pin", "https://www.google.com/maps/dir/?api=1&amp;destination=7.165119%2C3.335987" in live, "")
    check("the map credits OpenStreetMap and Geoapify", "© OpenStreetMap contributors" in live and "Powered by Geoapify" in live, "")
    if picture:
        path = os.path.join(ROOT, picture.group(1))
        raw = open(path, "rb").read() if os.path.exists(path) else b""
        check("the map picture is kept as WebP", raw[:4] == b"RIFF" and raw[8:12] == b"WEBP", path)
    staticmaps = [x for x in map(json.loads, open(calls).read().splitlines()) if x["path"] == "/v1/staticmap"]
    check("the map is drawn once, with a pin in the shop's colour", len(staticmaps) == 1 and "color:%23" in staticmaps[0]["query"] and "dark-matter-brown" in staticmaps[0]["query"], [x["query"][:160] for x in staticmaps])
    s, h, live = shop("bisi-indigo").get("/")
    staticmaps = [x for x in map(json.loads, open(calls).read().splitlines()) if x["path"] == "/v1/staticmap"]
    check("later visits use the kept picture", len(staticmaps) == 1, len(staticmaps))
    ld = re.search(r'"geo":\{"@type":"GeoCoordinates","latitude":7\.16511\d*,"longitude":3\.33598\d*\}', live)
    check("search engines get the pin", ld, re.findall(r'"geo":\{[^}]+\}', live))
    location["on"] = False
    bisi.api("/landing-page/save-draft", {"settings": draft})
    bisi.api("/landing-page/publish")
    s, h, live = shop("bisi-indigo").get("/")
    check("the map can be switched off", 'id="sf-location"' not in live and '"geo"' not in live, "")

# ── Photo copies ──────────────────────────────────────────────────────
rel = sql("SELECT image_path FROM products WHERE business_id = 2 AND image_path IS NOT NULL LIMIT 1")[0][0]
target = os.path.join(ROOT, "uploads/cache/320", rel[len("uploads/"):] + ".webp")
if os.path.exists(target):
    os.remove(target)
s, h, raw = Client().req("GET", "/uploads/cache/320/" + rel[len("uploads/"):] + ".webp")
check("a WebP copy is made on first request", s == 200 and h.get("content-type") == "image/webp" and raw[:4] == b"RIFF" and raw[8:12] == b"WEBP", (s, h.get("content-type")))
check("the copy is kept on disk for the web server", os.path.exists(target), target)
out = subprocess.run(["php", "-r", f"$i=getimagesize('{target}'); echo $i[0];"], capture_output=True, text=True).stdout
check("the copy is 320px wide", out.strip() == "320", out)
for bad in ["/uploads/cache/500/" + rel[len("uploads/"):] + ".webp", "/uploads/cache/320/order_proofs/2/x.png.webp",
            "/uploads/cache/320/products/2/../../../config/app.php.webp", "/uploads/cache/320/products/2/missing.png.webp"]:
    s, h, raw = Client().req("GET", bad)
    check(f"no copy for {bad.split('/cache/')[1][:40]}", s == 404, s)

# ── Purge ─────────────────────────────────────────────────────────────
out = php("define('BASE_PATH', getcwd()); require 'app/helpers/cloudflare.php'; var_export(cloudflare_purge_hosts(['bisi-indigo.nfelia.com']));")
check("purge is a no-op without Cloudflare settings", out == "false", out)

# ── Old names are held for 90 days ────────────────────────────────────
s, h, page = bisi.get("/landing-page")
token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', page).group(1)
s, h, _ = bisi.req("POST", "/shop-setup/rename", {"csrf_token": token, "shop_name": "bisi-adire"})
check("rename", sql("SELECT subdomain FROM businesses WHERE id = 2")[0][0] == "bisi-adire", "")
check("the old name is held", sql("SELECT business_id, DATEDIFF(held_until, NOW()) FROM shop_name_holds WHERE name = 'bisi-indigo'") == [["2", "90"]], sql("SELECT * FROM shop_name_holds"))
s, h, _ = shop("bisi-indigo").get("/")
check("the old link sends people to the new one", s == 302 and h.get("location") == "http://bisi-adire.nfelia.test:8000", (s, h.get("location")))
s, h, raw = shop("bisi-indigo").req("POST", "/checkout", {"customer_name": "A", "customer_phone": "08055556666", "items": "[]"})
check("an order sent to the old address is told to reload", s == 410 and b"new address" in raw, (s, raw[:200]))
kemi = Client()
kemi.login("owner3@nfelia.test")
s, h, raw = kemi.req("GET", "/shop-setup/check?name=bisi-indigo")
check("a held name is taken for other shops", json.loads(raw).get("state") == "taken", raw)
s, h, page = bisi.get("/landing-page")
check("Studio says to contact support after the one rename", "Contact support" in page and "Change link" not in page, "")

# ── Nfelia Ops: the "Shop by Nfelia" add-on and suspending ────────────
ops = ops_client()
s, h, raw = ops.req("GET", "/clients/3")
page = raw.decode("utf-8", "replace")
token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', page).group(1)
check("Ops shows the add-on", "Shop by Nfelia" in page, s)
ops.req("POST", "/clients/3/credit", {"csrf_token": token, "hide": "1"})
s, h, page = shop("mama-nkechi").get("/")
check("the add-on hides Shop by Nfelia", "Shop by Nfelia" not in page, "")
s, h, page = shop("kola-gadgets").get("/")
check("other shops keep it", "Shop by Nfelia" in page, "")
ops.req("POST", "/clients/3/credit", {"hide": "0"})
check("the add-on toggle needs CSRF", sql("SELECT hide_nfelia_credit FROM businesses WHERE id = 3")[0][0] == "1", "")
ops.req("POST", "/clients/4/plan", {"csrf_token": ops_token(ops, "/clients/4"), "action": "suspend", "reason": "Kit check: suspended for testing"})
n = sql("SELECT title FROM notifications WHERE business_id = 4 ORDER BY id DESC LIMIT 1")
check("suspending from Ops tells the owner", sql("SELECT status FROM subscriptions WHERE business_id = 4")[0][0] == "suspended"
      and n and n[0][0] == "Your account is on hold", n)

# ── Remember me ───────────────────────────────────────────────────────
c = Client()
c.login("owner5@nfelia.test", remember=True)
s, h, _ = c.get("/login")
rem = [x for x in c.cookies if "remember" in x]
check("keep me signed in uses the __Host- cookie", rem == ["__Host-nfelia_remember"], list(c.cookies))
fresh = Client()
fresh.cookies = {"__Host-nfelia_remember": c.cookies.get("__Host-nfelia_remember", "")}
s, h, _ = fresh.get("/dashboard")
check("the __Host- cookie signs back in", s == 200, (s, h.get("location")))
old = Client()
old.cookies = {"nfelia_remember": c.cookies.get("__Host-nfelia_remember", "")}
s, h, _ = old.get("/dashboard")
check("the old cookie is not trusted and is cleared", s == 302 and any(x.startswith("nfelia_remember=") for x in old.set_cookie_headers), (s, old.set_cookie_headers))

# ── Fix-ups ───────────────────────────────────────────────────────────
s, h, page = bisi.get("/dashboard")
check("no stray --> line before the feedback modal", not re.search(r"(^|\n)[ \t]*-->[ \t]*\r?\n[ \t]*<div x-data=\"feedbackModal", page), "")
check("views/quotations/business.php is gone", not os.path.exists(os.path.join(ROOT, "views/quotations/business.php")), "")

fails = results.count(False)
print(f"\n{len(results) - fails}/{len(results)} passed")
sys.exit(1 if fails else 0)
