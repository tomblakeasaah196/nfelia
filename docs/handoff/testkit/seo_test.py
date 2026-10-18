"""Selling, step 5: being found. robots.txt and sitemaps, the shop directory,
news posts, and the search words and shop data set in Shop Studio. Local only.

Needs what storefront_test.py needs, with the server showing every PHP
message:
    php -d error_reporting=-1 -d display_errors=1 -S 127.0.0.1:8000 docs/handoff/testkit/router.php
    python3 docs/handoff/testkit/seo_test.py
It reseeds the database (storefront_seed.php, then bookings_seed.php).
"""
import http.client
import json
import os
import re
import subprocess
import sys
import urllib.parse
import uuid
import xml.etree.ElementTree as ET

KIT = os.path.dirname(os.path.abspath(__file__))
subprocess.run(["php", os.path.join(KIT, "storefront_seed.php")], check=True)
subprocess.run(["php", os.path.join(KIT, "bookings_seed.php")], check=True)

MAIN = "nfelia.test:8000"
PHP_NOISE = re.compile(r"(Warning|Notice|Deprecated|Fatal error|Parse error)(</b>)?:", re.I)
SM = "{http://www.sitemaps.org/schemas/sitemap/0.9}"
results = []


def check(label, ok, detail=""):
    results.append(bool(ok))
    print(("PASS " if ok else "FAIL ") + label + ("" if ok else "  -> " + str(detail)[:400]))


def sql(query):
    out = subprocess.run(["mysql", "-uroot", "-N", "smartqaq_nfelia_core", "-e", query], capture_output=True, text=True)
    if out.returncode:
        raise RuntimeError(out.stderr)
    return [line.split("\t") for line in out.stdout.strip().split("\n") if line]


def one(query):
    rows = sql(query)
    return rows[0][0] if rows else None


class Client:
    def __init__(self, host=MAIN):
        self.host = host
        self.cookies = {}
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
        for k, v in r.getheaders():
            hdrs.setdefault(k.lower(), v)
            if k.lower() == "set-cookie":
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

    def post(self, path, data):
        s, h, raw = self.req("POST", path, data)
        return s, h, raw.decode("utf-8", "replace")

    def login(self, email):
        s, h, html = self.get("/login")
        token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', html).group(1)
        self.req("POST", "/login", {"csrf_token": token, "email": email, "password": "testpass123"})
        s, h, html = self.get("/dashboard")
        m = re.search(r'name="csrf-token" content="([0-9a-f]+)"', html) or re.search(r'name="csrf_token" value="([0-9a-f]+)"', html)
        self.token = m.group(1) if m else None

    def api(self, path, payload=None):
        headers = {"Content-Type": "application/json", "Accept": "application/json", "X-CSRF-Token": self.token or ""}
        s, h, raw = self.req("POST", path, headers=headers, body=json.dumps(payload or {}))
        try:
            return s, json.loads(raw)
        except ValueError:
            return s, {"raw": raw[:300].decode("utf-8", "replace")}

    def multipart(self, path, fields, files=None):
        boundary = uuid.uuid4().hex
        parts = [f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode() for k, v in fields.items()]
        for name, (filename, data, mime) in (files or {}).items():
            parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"; filename="{filename}"\r\nContent-Type: {mime}\r\n\r\n'.encode() + data + b"\r\n")
        parts.append(f"--{boundary}--\r\n".encode())
        s, h, raw = self.req("POST", path, headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}, body=b"".join(parts))
        return s, h, raw.decode("utf-8", "replace")


def shop(label):
    return Client(f"{label}.nfelia.test:8000")


def ld_blocks(page):
    out = []
    for block in re.findall(r'<script type="application/ld\+json">(.*?)</script>', page, re.S):
        data = json.loads(block)
        out.extend(data if isinstance(data, list) else [data])
    return out


# ── Search words, directory listing and city, from Shop Studio ─────────
bisi = Client()
bisi.login("owner2@nfelia.test")
s, h, studio = bisi.get("/landing-page")
check("Studio has the Found on Google card", "Found on Google" in studio and 'data-seo="title"' in studio and 'data-seo="directory"' in studio
      and '<option value="Port Harcourt">' in studio and not PHP_NOISE.search(studio), "")
data = json.loads(re.search(r'<script type="application/json" id="st-data">(.*?)</script>', studio, re.S).group(1))
check("the city is guessed from the address", data["settings"]["seo"] == {"title": "", "description": "", "directory": False, "city": "Abeokuta"}, data["settings"].get("seo"))
check("Studio knows the words Google gets by default", data["seoDefaults"]["title"].startswith("Bisi Indigo"), data.get("seoDefaults"))
draft = data["settings"]
draft["seo"] = {"title": "Bisi Indigo — adire dresses in Abeokuta <b>", "description": "Hand-dyed adire, cut to your measure.   Delivery across Nigeria.",
                "directory": True, "city": "Abeokuta"}
s, r = bisi.api("/landing-page/save-draft", {"settings": draft})
check("search words save with the draft", s == 200 and r.get("settings", {}).get("seo", {}).get("description") == "Hand-dyed adire, cut to your measure. Delivery across Nigeria.", (s, r.get("settings", {}).get("seo")))
check("nothing is listed before publishing", one("SELECT directory_opt_in FROM businesses WHERE id = 2") == "0", "")
s, r = bisi.api("/landing-page/publish")
check("publishing copies the listing and city to the shop", sql("SELECT directory_opt_in, city, city_slug FROM businesses WHERE id = 2") == [["1", "Abeokuta", "abeokuta"]], sql("SELECT directory_opt_in, city, city_slug FROM businesses WHERE id = 2"))
s, h, page = shop("bisi-indigo").get("/")
check("the shop uses the owner's title and description", "<title>Bisi Indigo — adire dresses in Abeokuta &lt;b&gt;</title>" in page
      and '<meta name="description" content="Hand-dyed adire, cut to your measure. Delivery across Nigeria.">' in page
      and '<meta property="og:title" content="Bisi Indigo — adire dresses in Abeokuta &lt;b&gt;">' in page, re.findall(r"<title>.*?</title>", page))
biz = next(x for x in ld_blocks(page) if x.get("@type") == "LocalBusiness")
check("shop data names the city and a price range", biz.get("address", {}).get("addressLocality") == "Abeokuta" and biz.get("priceRange", "").startswith("₦"), biz.get("address"))
s, h, page = shop("glow-by-temi").get("/")
biz = next(x for x in ld_blocks(page) if x.get("@type") == "LocalBusiness")
hours = biz.get("openingHoursSpecification", [])
check("opening hours from booking hours", len(hours) == 7 and hours[0] == {"@type": "OpeningHoursSpecification", "dayOfWeek": "https://schema.org/Monday", "opens": "09:00", "closes": "19:00"}, hours[:1])
s, h, page = shop("kola-gadgets").get("/")
biz = next(x for x in ld_blocks(page) if x.get("@type") == "LocalBusiness")
check("no opening hours when none were set", "openingHoursSpecification" not in biz, "")

# ── robots.txt ─────────────────────────────────────────────────────────
s, h, txt = Client().get("/robots.txt")
check("nfelia.com/robots.txt keeps the app out and points to the sitemap", s == 200 and h.get("content-type", "").startswith("text/plain")
      and "Disallow: /dashboard" in txt and "Disallow: /login" in txt and "Allow: /shops" in txt and "Sitemap: http://nfelia.test:8000/sitemap.xml" in txt and "set-cookie" not in h, txt)
s, h, txt = shop("bisi-indigo").get("/robots.txt")
check("a paid shop lets search engines in, not into private pages", s == 200 and "Disallow: /order/" in txt and "Disallow: /booking/" in txt
      and "Disallow: /\n" not in txt and "Sitemap: http://bisi-indigo.nfelia.test:8000/sitemap.xml" in txt, txt)
s, h, txt = shop("glow-by-temi").get("/robots.txt")
check("a trial shop keeps them out", txt == "User-agent: *\nDisallow: /\n", txt)
s, h, txt = shop("nobody-here").get("/robots.txt")
check("an address with no shop keeps them out", s == 200 and txt == "User-agent: *\nDisallow: /\n", (s, txt))
sql("UPDATE subscriptions SET status = 'suspended' WHERE business_id = 11")   # a cancelled month stays open to its end
s, h, txt = shop("chinedu-co").get("/robots.txt")
check("a closed shop keeps them out", txt == "User-agent: *\nDisallow: /\n", txt)
sql("UPDATE subscriptions SET status = 'active' WHERE business_id = 11")

# ── News posts ─────────────────────────────────────────────────────────
s, h, page = bisi.get("/posts")
check("News and offers opens", s == 200 and "Write your first post" in page and not PHP_NOISE.search(page), s)
s, h, page = bisi.get("/posts/create")
csrf = re.search(r'name="csrf_token" value="([0-9a-f]+)"', page).group(1)
s, h, page = bisi.multipart("/posts/create", {"csrf_token": csrf, "title": "", "body": "x", "action": "publish"})
check("a post needs a title", "Give your post a title." in page and one("SELECT COUNT(*) FROM shop_posts") == "0", "")
png = __import__("base64").b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=")
s, h, page = bisi.multipart("/posts/create", {"csrf_token": csrf, "title": "New <adire> for December", "body": "Twelve new prints.\n\nCome <b>early</b>.", "action": "publish"},
                            {"image": ("p.png", png, "image/png")})
post_id, status, img = sql("SELECT id, status, image_path FROM shop_posts ORDER BY id DESC LIMIT 1")[0]
check("a post is published with its photo", status == "published" and img.startswith("uploads/storefront/2/post_"), (status, img))
s, h, page = bisi.multipart("/posts/create", {"csrf_token": csrf, "title": "Secret sale", "body": "Not yet.", "action": "draft"})
draft_id = one("SELECT id FROM shop_posts WHERE title = 'Secret sale'")
s, h, _ = shop("bisi-indigo").get(f"/news/{post_id}")
post_url = h.get("location", "")
check("/news/{id} is sent on to its named address", s == 301 and post_url.endswith(f"/news/{post_id}-new-adire-for-december"), (s, post_url))
s, h, page = shop("bisi-indigo").get(urllib.parse.urlparse(post_url).path)
check("the post page draws cleanly and is cacheable", s == 200 and not PHP_NOISE.search(page) and "s-maxage=300" in h.get("cache-control", "") and "set-cookie" not in h, (s, PHP_NOISE.search(page)))
check("its words are escaped", "New &lt;adire&gt; for December" in page and "Come &lt;b&gt;early&lt;/b&gt;." in page and "<adire>" not in page, "")
article = next((x for x in ld_blocks(page) if x.get("@type") == "BlogPosting"), {})
check("article data for Google", article.get("headline") == "New <adire> for December" and article.get("author", {}).get("name") == "Bisi Indigo" and article.get("image"), article)
check("shared links show it as an article with its photo", '<meta property="og:type" content="article">' in page and re.search(r'og:image" content="[^"]+/uploads/cache/og/storefront/2/post_[^"]+\.png\.jpg"', page)
      and f'<link rel="canonical" href="{post_url}">' in page, "")
s, h, page = shop("bisi-indigo").get(f"/news/{draft_id}")
check("drafts have no page", s == 404, s)
s, h, home = shop("bisi-indigo").get("/")
check("the shop shows its newest posts", 'id="sf-news"' in home and f'href="/news/{post_id}-new-adire-for-december"' in home and "Secret sale" not in home, "")
s, h, page = shop("bisi-indigo").get("/news")
check("and lists them all at /news", s == 200 and "New &lt;adire&gt; for December" in page and "Secret sale" not in page, s)
s, h, page = shop("kola-gadgets").get("/news")
check("a shop with no posts has no news page", s == 404, s)
s, h, page = shop("kola-gadgets").get("/")
check("nor a news section", 'id="sf-news"' not in page, "")
kemi = Client()
kemi.login("owner5@nfelia.test")
s, h, page = kemi.get(f"/posts/edit/{post_id}")
check("owners can't open another shop's post", "Edit post" not in page, s)

# ── Sitemaps ───────────────────────────────────────────────────────────
s, h, raw = shop("bisi-indigo").req("GET", "/sitemap.xml")
locs = [e.text for e in ET.fromstring(raw).iter(SM + "loc")]
check("a paid shop's sitemap is valid XML", s == 200 and h.get("content-type", "").startswith("application/xml") and locs[0] == "http://bisi-indigo.nfelia.test:8000", locs[:2])
dress = one("SELECT id FROM products WHERE business_id = 2 AND name = 'Olokun wrap dress'")
check("with its products and posts, not drafts", f"http://bisi-indigo.nfelia.test:8000/p/{dress}-olokun-wrap-dress" in locs and post_url in locs
      and "http://bisi-indigo.nfelia.test:8000/news" in locs and not any(f"/news/{draft_id}-" in l for l in locs), locs)
tote = one("SELECT id FROM products WHERE business_id = 2 AND name = 'Adire tote bag'")
sql(f"UPDATE products SET is_active = 0 WHERE id = {tote}")
s, h, raw = shop("bisi-indigo").req("GET", "/sitemap.xml")
check("products switched off leave it", not any(f"/p/{tote}-" in e.text for e in ET.fromstring(raw).iter(SM + "loc")), "")
sql(f"UPDATE products SET is_active = 1 WHERE id = {tote}")
s, h, _ = shop("glow-by-temi").req("GET", "/sitemap.xml")
check("a trial shop has no sitemap", s == 404, s)
s, h, raw = Client().req("GET", "/sitemap.xml")
shop_maps = [e.text for e in ET.fromstring(raw).iter(SM + "loc")]
check("nfelia.com's sitemap index lists every paid shop's sitemap", s == 200 and "http://nfelia.test:8000/sitemap-pages.xml" in shop_maps
      and "http://bisi-indigo.nfelia.test:8000/sitemap.xml" in shop_maps and "http://kola-gadgets.nfelia.test:8000/sitemap.xml" in shop_maps, shop_maps)
check("but no trial shop", not any("glow-by-temi" in m or "zainab-naturals" in m for m in shop_maps), shop_maps)
s, h, raw = Client().req("GET", "/sitemap-pages.xml")
pages = [e.text for e in ET.fromstring(raw).iter(SM + "loc")]
check("its own pages: home and the directory pages with shops", "http://nfelia.test:8000/" in pages and "http://nfelia.test:8000/shops" in pages
      and "http://nfelia.test:8000/shops/abeokuta" in pages and "http://nfelia.test:8000/shops/abeokuta/fashion" in pages, pages)

# ── The directory ──────────────────────────────────────────────────────
sql("UPDATE businesses SET directory_opt_in = 1, city = 'Lagos', city_slug = 'lagos' WHERE subdomain IN ('kola-gadgets', 'musa-provisions', 'glow-by-temi')")
s, h, page = Client().get("/shops")
check("the directory draws cleanly, sets no cookie", s == 200 and not PHP_NOISE.search(page) and "set-cookie" not in h and "<h1>Shops on Nfelia</h1>" in page, (s, h))
check("listed paid shops are in it", 'href="http://bisi-indigo.nfelia.test:8000"' in page and 'href="http://kola-gadgets.nfelia.test:8000"' in page, "")
check("shops that didn't ask, and trial shops, are not", "mama-nkechi" not in page and "glow-by-temi" not in page, "")
ld = ld_blocks(page)
check("as a list Google can read", ld and ld[0]["@type"] == "ItemList" and len(ld[0]["itemListElement"]) == 3, ld[:1])
s, h, page = Client().get("/shops/lagos")
check("by city", s == 200 and "<h1>Shops in Lagos</h1>" in page and "bisi-indigo" not in page and "kola-gadgets" in page, s)
s, h, page = Client().get("/shops/lagos/phones-and-gadgets")
check("by city and kind", s == 200 and "<h1>Phones, gadgets and repairs in Lagos</h1>" in page and "kola-gadgets" in page and "musa-provisions" not in page, s)
s, h, page = Client().get("/shops/fashion")
check("by kind", s == 200 and "<h1>Fashion and tailoring</h1>" in page and "bisi-indigo" in page and "kola-gadgets" not in page, s)
s, h, page = Client().get("/shops/timbuktu")
check("an unknown city is a 404 that search engines skip", s == 404 and "noindex" in h.get("x-robots-tag", "") and '<meta name="robots" content="noindex, follow">' in page, s)
sql("UPDATE businesses SET name = 'Kola <Gadgets>' WHERE subdomain = 'kola-gadgets'")
s, h, page = Client().get("/shops")
check("shop names are escaped in the directory", "Kola &lt;Gadgets&gt;" in page and "<Gadgets>" not in page, "")

# ── Deleting a post ────────────────────────────────────────────────────
s, h, page = bisi.get(f"/posts/edit/{post_id}")
csrf = re.search(r'name="csrf_token" value="([0-9a-f]+)"', page).group(1)
bisi.post(f"/posts/delete/{post_id}", {"csrf_token": csrf})
s, h, _ = shop("bisi-indigo").get(urllib.parse.urlparse(post_url).path)
s2, h2, raw = shop("bisi-indigo").req("GET", "/sitemap.xml")
check("a deleted post leaves the shop and the sitemap", s == 404 and post_url.encode() not in raw, s)

print()
print(f"{sum(results)} of {len(results)} passed")
sys.exit(0 if all(results) else 1)
