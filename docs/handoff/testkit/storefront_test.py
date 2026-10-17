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
"""
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
check("published settings draw every section", all(x in page for x in ['id="sf-announcement"', 'id="sf-featured"', 'id="sf-catalogue"', 'id="sf-quote"', 'id="sf-about"', 'id="sf-video"', 'id="sf-delivery"', 'id="sf-faq"', 'id="sf-contact"']), "")
check("photos get WebP copies with width and height", re.search(r'<source type="image/webp" srcset="http://nfelia\.test:8000/uploads/cache/320/products/2/seed_\d+\.png\.webp 320w', page) and re.search(r'<img src="[^"]+seed_\d+\.png" width="600" height="750"', page), "")
check("product photos load lazily", 'loading="lazy"' in page, "")
check("colour swatches come from the variant group", "3 colours" in page and "3 fabrics" in page, "")
check("the video waits for a tap (no iframe yet)", "youtube-nocookie" not in page and 'data-youtube="dQw4w9WgXcQ"' in page, "")
check("Shop by Nfelia shows by default", "Shop by Nfelia" in page, "")
check("WhatsApp number normalised", "wa.me/2348031234567" in page, re.findall(r"wa\.me/\d+", page)[:2])
s, h, page = shop("haven-shortlets").get("/")
check("a shop never published uses the old columns", "Furnished apartments with 24-hour power" in page and "Short stays in Lekki" in page, "")
s, h, page = shop("musa-provisions").get("/")
check("Market: price list with steppers", 'data-stepper="' in page and "Prices updated" in page, "")
check("Market: quote form asks what the old settings asked", 'name="quantity_range"' in page and 'name="company_name"' in page and 'name="budget"' not in page, "")
s, h, page = shop("kola-gadgets").get("/")
check("Volt: services sit on the repair ticket", 'class="sf-ticket"' in page and "Screen replacement" in page, "")
s, h, page = shop("chinedu-co").get("/")
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
check("Studio starts from the saved draft", draft["look"] == "atelier" and len(draft["sections"]) == 10, (draft["look"], len(draft["sections"])))

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
junk["sections"].append({"type": "reviews", "on": True})
junk["sections"].append({"type": "<script>", "on": True})
next(x for x in junk["sections"] if x["type"] == "hero")["headline"] = "x" * 500
next(x for x in junk["sections"] if x["type"] == "about")["text"] = "</script><script>alert(1)</script> <b>bold</b>"
next(x for x in junk["sections"] if x["type"] == "featured")["products"] = [1, 999999, "x", -4]
next(x for x in junk["sections"] if x["type"] == "hero")["image"] = "uploads/storefront/3/hero_x.png"
s, r = bisi.api("/landing-page/save-draft", {"settings": junk})
st = r.get("settings", {})
types = [x["type"] for x in st.get("sections", [])]
check("unknown look and font fall back", st.get("look") == "atelier" and st.get("font") == "", (st.get("look"), st.get("font")))
check("unknown and later-step sections are dropped", "reviews" not in types and "<script>" not in types and len(types) == 10, types)
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
check("hero photo uploads", s == 200 and re.match(r"^uploads/storefront/2/hero_\d+_[0-9a-f]{6}\.png$", up.get("path", "")), (s, up))
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

# ── Admin: the "Shop by Nfelia" add-on and notifications ──────────────
founder = Client()
founder.login("founder@nfelia.test")
s, h, page = founder.get("/admin/businesses")
token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', page).group(1)
check("admin lists the add-on", "Shop by Nfelia" in page, "")
s, h, _ = founder.req("POST", "/admin/shop-credit", {"csrf_token": token, "business_id": "3", "hide": "1"})
s, h, page = shop("mama-nkechi").get("/")
check("the add-on hides Shop by Nfelia", "Shop by Nfelia" not in page, "")
s, h, page = shop("kola-gadgets").get("/")
check("other shops keep it", "Shop by Nfelia" in page, "")
s, h, _ = founder.req("POST", "/admin/shop-credit", {"business_id": "3", "hide": "0"})
check("the add-on toggle needs CSRF", sql("SELECT hide_nfelia_credit FROM businesses WHERE id = 3")[0][0] == "1", "")
s, h, raw = founder.req("POST", "/admin/manage-subscription", {"csrf_token": token, "business_id": "4", "action": "suspend"})
n = sql("SELECT type, message FROM notifications WHERE business_id = 4 ORDER BY id DESC LIMIT 1")
check("admin notices with an unknown type arrive as general", n and n[0][0] == "general" and "suspended" in n[0][1], n)

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
