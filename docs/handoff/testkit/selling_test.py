"""Selling, step 3: product pages, order tracking links and reviews, checked
end to end against a local copy. Local only.

Needs what storefront_test.py needs (MariaDB with db/schema.sql and the
migrations, a .env with APP_URL=http://nfelia.test:8000 and
DB_NAME=smartqaq_nfelia_core), with the server showing every PHP message:
    php -d error_reporting=-1 -d display_errors=1 -S 127.0.0.1:8000 docs/handoff/testkit/router.php
    python3 docs/handoff/testkit/selling_test.py
It reseeds the database first (storefront_seed.php) and places test orders.
"""
import base64
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
PHP_NOISE = re.compile(r"(Warning|Notice|Deprecated|Fatal error|Parse error)(</b>)?:", re.I)
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


def checkout(label, **fields):
    data = {"channel": "cart_pop", "fulfillment_type": "pickup", "customer_name": "Ada Okafor", "customer_phone": "0803 555 0101",
            "customer_email": "", "payment_method": "none"}
    data.update(fields)
    s, h, raw = shop(label).req("POST", "/checkout", data)
    try:
        return s, json.loads(raw)
    except ValueError:
        return s, {"raw": raw[:300]}


def path_of(url):
    return urllib.parse.urlparse(url).path


BISI = one("SELECT id FROM businesses WHERE subdomain = 'bisi-indigo'")
DRESS = one(f"SELECT id FROM products WHERE business_id = {BISI} AND name = 'Olokun wrap dress'")
TOTE = one(f"SELECT id FROM products WHERE business_id = {BISI} AND name = 'Adire tote bag'")
KAFTAN = one(f"SELECT id FROM products WHERE business_id = {BISI} AND name = 'Eleko kaftan'")
KOLA_ITEM = one("SELECT p.id FROM products p JOIN businesses b ON b.id = p.business_id WHERE b.subdomain = 'kola-gadgets' ORDER BY p.id LIMIT 1")

# ── Product pages in every look ────────────────────────────────────────
for look, label in LOOKS.items():
    pid, name = sql(f"SELECT p.id, p.name FROM products p JOIN businesses b ON b.id = p.business_id WHERE b.subdomain = '{label}' "
                    "AND p.is_active = 1 AND p.deleted_at IS NULL ORDER BY p.id LIMIT 1")[0]
    s, h, _ = shop(label).get(f"/p/{pid}")
    loc = h.get("location", "")
    check(f"{look}: /p/{pid} is sent on to its named address", s == 301 and re.search(rf"/p/{pid}-[a-z0-9-]+$", loc), (s, loc))
    s, h, page = shop(label).get(path_of(loc))
    esc_name = name.replace("&", "&amp;")
    check(f"{look}: product page draws cleanly", s == 200 and not PHP_NOISE.search(page) and f'id="sf-pdp-name">{esc_name}</h1>' in page,
          (s, PHP_NOISE.search(page)))
    check(f"{look}: product page is cacheable and sets no cookie", "s-maxage=300" in h.get("cache-control", "") and "set-cookie" not in h, h)
    check(f"{look}: canonical and og:url are the product's address",
          f'<link rel="canonical" href="{loc}">' in page and f'<meta property="og:url" content="{loc}">' in page, loc)
    ld = [json.loads(m) for m in re.findall(r'<script type="application/ld\+json">(.*?)</script>', page, re.S)]
    kinds = [x.get("@type") for x in (ld[0] if ld and isinstance(ld[0], list) else ld)]
    check(f"{look}: Product (or Service) and breadcrumb data", any(k in ("Product", "Service") for k in kinds) and "BreadcrumbList" in kinds, kinds)
    check(f"{look}: buy box, share and Status buttons", 'data-buy="' + pid + '"' in page and "data-share-item" in page and "data-status-item" in page, "")

# ── Product page details (Bisi Indigo) ─────────────────────────────────
s, h, page = shop("bisi-indigo").get(f"/p/{DRESS}-olokun-wrap-dress")
check("og:type product with its price", '<meta property="og:type" content="product">' in page
      and '<meta property="product:price:amount" content="38000.00">' in page and '<meta property="product:price:currency" content="NGN">' in page, "")
og = re.search(r'<meta property="og:image" content="([^"]+)">', page).group(1)
ogw = int(re.search(r'og:image:width" content="(\d+)"', page).group(1))
ogh = int(re.search(r'og:image:height" content="(\d+)"', page).group(1))
check("link preview image is the product's JPEG copy", og.endswith(".png.jpg") and "/uploads/cache/og/products/" in og, og)
ld = json.loads(re.search(r'<script type="application/ld\+json">(.*?)</script>', page, re.S).group(1))
offer = ld[0].get("offers", {})
check("Product data has the price, currency and the shop as seller",
      offer.get("price") == "38000.00" and offer.get("priceCurrency") == "NGN" and offer.get("seller", {}).get("name") == "Bisi Indigo", offer)
check("no review stars in product data (the shop's own reviews)", "aggregateRating" not in json.dumps(ld), "")
more = page[page.find('id="sf-more-title"'):]
more_links = re.findall(r'href="(/p/[^"]+)" data-open', more)
check("more from the shop: same category first, linking to product pages",
      'id="sf-more-title">More from Bisi Indigo' in page and more_links[:1] == [f"/p/{KAFTAN}-eleko-kaftan"] and f"/p/{DRESS}-" not in "".join(more_links), more_links)
check("the name leads back to the shop", 'class="sf-brand__home" href="/"' in page, "")
check("WhatsApp ask names the product", "I%20found%20Olokun%20wrap%20dress" in page, "")
s, h, raw = Client().req("GET", path_of(og))
check("JPEG copy is made on request", s == 200 and h.get("content-type") == "image/jpeg" and raw[:3] == b"\xff\xd8\xff", (s, h.get("content-type")))
check("JPEG copy is small enough for WhatsApp", len(raw) <= 290000, len(raw))
sof = raw.find(b"\xff\xc2") if raw.find(b"\xff\xc2") != -1 else raw.find(b"\xff\xc0")
jh, jw = int.from_bytes(raw[sof + 5:sof + 7], "big"), int.from_bytes(raw[sof + 7:sof + 9], "big")
check("its size matches og:image:width and height", (jw, jh) == (ogw, ogh), ((jw, jh), (ogw, ogh)))
s, h, raw = Client().req("GET", "/uploads/cache/og/products/2/../2/seed_1.png.jpg")
check("JPEG copies refuse '..'", s == 404, s)
s, h, raw = Client().req("GET", "/uploads/cache/og/order_proofs/2/proof.png.jpg")
check("JPEG copies only for shop photos", s == 404, s)

s, h, page = shop("bisi-indigo").get(f"/p/{KOLA_ITEM}-anything")
check("another shop's product isn't here", s == 404, s)
sql(f"UPDATE products SET is_active = 0 WHERE id = {TOTE}")
s, h, page = shop("bisi-indigo").get(f"/p/{TOTE}-adire-tote-bag")
check("a product switched off has no page", s == 404 and "isn't in the shop any more" in html.unescape(page) and "no-store" in h.get("cache-control", ""), s)
sql(f"UPDATE products SET is_active = 1 WHERE id = {TOTE}")
s, h, page = shop("bisi-indigo").get("/p/abc")
check("/p/abc is not a product", s == 404, s)

glow_pid = one("SELECT p.id FROM products p JOIN businesses b ON b.id = p.business_id WHERE b.subdomain = 'glow-by-temi' ORDER BY p.id LIMIT 1")
s, h, _ = shop("glow-by-temi").get(f"/p/{glow_pid}")
s, h, page = shop("glow-by-temi").get(path_of(h.get("location", "")))
check("trial shop's product pages stay out of search", '<meta name="robots" content="noindex, nofollow">' in page and "noindex" in h.get("x-robots-tag", ""), h)
chin = one("SELECT id FROM businesses WHERE subdomain = 'chinedu-co'")
sql(f"UPDATE subscriptions SET status = 'suspended' WHERE business_id = {chin}")   # a cancelled month stays open to its end
chin_pid = one(f"SELECT id FROM products WHERE business_id = {chin} ORDER BY id LIMIT 1")
s, h, page = shop("chinedu-co").get(f"/p/{chin_pid}")
check("a closed shop's product page says closed for now", s == 200 and "closed for now" in page, (s, page[:200]))
sql(f"UPDATE subscriptions SET status = 'active' WHERE business_id = {chin}")

s, h, home = shop("bisi-indigo").get("/")
check("shop links go to product pages", f'href="/p/{DRESS}-olokun-wrap-dress" data-open="{DRESS}"' in home, "")
check("the script knows each product's address", f'"path":"/p/{DRESS}-olokun-wrap-dress"' in home and '"page":"shop"' in home, "")
shop_ld = json.loads(re.search(r'<script type="application/ld\+json">(.*?)</script>', home, re.S).group(1))
check("shop data lists product addresses", any(i.get("url", "").endswith(f"/p/{DRESS}-olokun-wrap-dress") for i in shop_ld["hasOfferCatalog"]["itemListElement"]), "")
check("no reviews section before the first review", 'id="sf-reviews"' not in home, "")
bisi = Client()
bisi.login("owner2@nfelia.test")
s, h, prev = bisi.get("/landing-page/preview")
check("Studio preview keeps the sheet links", f'href="#item-{DRESS}"' in prev and 'href="/p/' not in prev and 'id="sf-reviews"' in prev
      and "Reviews show here once customers rate" in prev, "")
s, h, studio = bisi.get("/landing-page")
check("Studio offers the reviews section", '"reviews":{"name":"Customer reviews"' in studio, re.findall(r'"reviews":\{[^}]{0,80}', studio))

# ── Checkout gives a tracking link ─────────────────────────────────────
items = json.dumps([{"product_id": int(DRESS), "quantity": 2, "variant_selection": "Size: M", "item_note": "<b>bold</b>"},
                    {"product_id": int(TOTE), "quantity": 1}])
s, j = checkout("bisi-indigo", items=items, fulfillment_type="delivery", delivery_address="4 Ake Road, Abeokuta",
                payment_method="bank_transfer", customer_email="ada@example.com")
track = j.get("order_url") or ""
check("checkout answers with the tracking link", s == 200 and j.get("success") and re.fullmatch(r"http://bisi-indigo\.nfelia\.test:8000/order/[a-f0-9]{32}", track), j)
token = track.rsplit("/", 1)[-1]
order_id = one(f"SELECT id FROM orders WHERE public_token = '{token}'")
check("the link's token is on the order", order_id is not None, token)
s, h, page = shop("bisi-indigo").get(f"/order/{token}")
check("tracking page draws cleanly", s == 200 and not PHP_NOISE.search(page) and f"Order {j['order_number']}</h1>" in page, (s, PHP_NOISE.search(page)))
check("tracking page is private", "no-store" in h.get("cache-control", "") and "noindex" in h.get("x-robots-tag", "")
      and h.get("referrer-policy") == "same-origin" and "set-cookie" not in h, h)
check("no canonical, no address to share, no shop data", 'rel="canonical"' not in page and "og:url" not in page and "application/ld+json" not in page
      and '<meta name="referrer" content="same-origin">' in page and '<meta name="robots" content="noindex, nofollow">' in page, "")
check("first steps: received, then checking the payment", "Checking your payment" in page and 'is-now" aria-current="step"' in page
      and "Order received" in page and "On its way" in page, "")
check("what was ordered, with the shopper's note escaped", "Olokun wrap dress" in page and "Size: M" in page and "&lt;b&gt;bold&lt;/b&gt;" in page
      and "<b>bold</b>" not in page and "₦88,500" in page, "")
check("delivery address and payment shown", "4 Ake Road, Abeokuta" in page and "Bank transfer" in page, "")
m = re.search(r'data-reorder="([^"]+)"', page)
again = json.loads(m.group(1).replace("&quot;", '"')) if m else []
check("order again lists what's still on sale", [a["id"] for a in again] == [int(DRESS), int(TOTE)] and again[0]["variant"] == "Size: M", again)
check("tracking page keeps the shop's header and bag", 'class="sf-brand__home" href="/"' in page and 'id="sf-bag"' in page and 'data-order="' + j["order_number"] + '"' in page, "")
s, h, _ = shop("kola-gadgets").get(f"/order/{token}")
check("another shop can't open this order", s == 404, s)
s, h, page = shop("bisi-indigo").get("/order/" + "0" * 32)
check("an unknown order link says so", s == 404 and "couldn't find this order" in html.unescape(page), s)

s, wj = checkout("bisi-indigo", items=json.dumps([{"product_id": int(TOTE), "quantity": 1}]), channel="whatsapp", customer_name="Bola Ade", customer_phone="0802 111 2222")
wtoken = (wj.get("order_url") or "/").rsplit("/", 1)[-1]
s, h, page = shop("bisi-indigo").get(f"/order/{wtoken}")
send = re.search(r'href="https://wa\.me/2348031234567\?text=([^"]+)"[^>]*><svg[^>]*>.*?</svg><span>Send it on WhatsApp', page, re.S)
check("WhatsApp order: waiting to be confirmed, with a send button", "Waiting to be confirmed" in page and send, "")
check("the WhatsApp message carries the tracking link", send and urllib.parse.unquote(send.group(1).replace("&amp;", "&")).endswith("Follow it here: " + wj["order_url"]), "")

# ── The owner sees the links, approves, delivers ───────────────────────
s, h, view = bisi.get(f"/orders/view/{order_id}")
check("order screen shows the customer's links", s == 200 and "Customer's links" in view and track in view and "wa.me/2348035550101" in view, s)
check("review not offered before delivery", "Once the delivery is signed for" in view, "")
s, h, _ = bisi.post(f"/orders/approve/{order_id}", {"csrf_token": bisi.token, "deliver_mode": "immediate"})
status, delivery_id = sql(f"SELECT status, delivery_id FROM orders WHERE id = {order_id}")[0]
check("owner approves the order", status == "approved" and delivery_id not in (None, "NULL"), (status, delivery_id))
s, h, page = shop("bisi-indigo").get(f"/order/{token}")
check("tracking: payment confirmed, being prepared", "Payment confirmed" in page and re.search(r'is-now" aria-current="step">.*?Being prepared', page, re.S), "")
sql(f"UPDATE deliveries SET status = 'dispatched' WHERE id = {delivery_id}")
s, h, page = shop("bisi-indigo").get(f"/order/{token}")
check("tracking: on its way", "Your order is on its way." in page and re.search(r'is-now" aria-current="step">.*?On its way', page, re.S), "")
signoff, note_token = sql(f"SELECT signoff_token, token FROM deliveries WHERE id = {delivery_id}")[0]
check("the delivery note has the order's lines", one(f"SELECT COUNT(*) FROM delivery_items WHERE delivery_id = {delivery_id}") == "2", "")
s, h, page = Client().get(f"/delivery/{note_token}")
check("the public delivery note opens", s == 200 and not PHP_NOISE.search(page) and "Olokun wrap dress" in page, (s, PHP_NOISE.search(page)))
s, h, page = Client().get(f"/sign/{signoff}")
check("the sign-off page opens", s == 200 and not PHP_NOISE.search(page), (s, PHP_NOISE.search(page)))
# A 1x1 PNG that image libraries can open (signatures are redrawn before saving)
png = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg=="
s, h, raw = Client().req("POST", f"/sign/{signoff}", {"signoff_name": "Ada Okafor", "signature_data": png})
sj = json.loads(raw)
review_link = sj.get("review_url") or ""
check("signing for the delivery hands over the review link", sj.get("success") and re.fullmatch(r"http://bisi-indigo\.nfelia\.test:8000/review/[a-f0-9]{32}", review_link), sj)
rtoken = review_link.rsplit("/", 1)[-1]
check("one review invitation for the order", sql(f"SELECT status, order_id FROM reviews WHERE token = '{rtoken}'") == [["invited", order_id]], "")
s, h, page = shop("bisi-indigo").get(f"/order/{token}")
check("tracking: delivered, with the review link", "Delivered. Thank you for shopping with Bisi Indigo." in page and f'href="/review/{rtoken}"' in page, "")
s, h, view = bisi.get(f"/orders/view/{order_id}")
check("order screen: waiting for the review, with a WhatsApp ask", "Waiting for your customer" in view and "Ask for a review on WhatsApp" in view, "")

# ── The review ─────────────────────────────────────────────────────────
s, h, page = shop("bisi-indigo").get(f"/review/{rtoken}")
check("review form draws cleanly and privately", s == 200 and not PHP_NOISE.search(page) and "How was your order from Bisi Indigo?" in page
      and "no-store" in h.get("cache-control", "") and "noindex" in h.get("x-robots-tag", ""), (s, h))
check("the form says how the name will show", "“Ada O.”" in page and 'name="rating" value="5"' in page, "")
s, h, page = shop("bisi-indigo").post(f"/review/{rtoken}", {"comment": "Kept me waiting"})
check("no stars: asked again, words kept", s == 422 and "Tap a star" in page and "Kept me waiting" in page, s)
s, h, _ = shop("bisi-indigo").post(f"/review/{rtoken}", {"rating": "5", "comment": "<script>alert(1)</script> Lovely fabric,\r\n\r\n\r\n\r\nfast delivery"})
check("posting goes back to the review page", s == 303 and h.get("location") == review_link, (s, h.get("location")))
rating, comment, rstatus = sql(f"SELECT rating, comment, status FROM reviews WHERE token = '{rtoken}'")[0]
check("the review is saved and published", rating == "5" and rstatus == "published" and "Lovely fabric" in comment, (rating, rstatus, comment))
s, h, _ = shop("bisi-indigo").post(f"/review/{rtoken}", {"rating": "1", "comment": "changed my mind"})
check("a review can't be posted twice", s == 303 and one(f"SELECT rating FROM reviews WHERE token = '{rtoken}'") == "5", "")
s, h, page = shop("bisi-indigo").get(f"/review/{rtoken}")
check("the link then says thank you", "Thank you</h1>" in page and "Rated 5 out of 5" in page and "&lt;script&gt;" in page, "")
note = sql(f"SELECT title, action_url FROM notifications WHERE business_id = {BISI} AND title LIKE 'New review%'")
check("the owner is told", note and note[0] == ["New review: 5 out of 5", f"orders/view/{order_id}#review"], note)
s, h, home = shop("bisi-indigo").get("/")
section = re.search(r'<section class="sf-section sf-reviews" id="sf-reviews".*?</section>', home, re.S)
section = section.group(0) if section else ""
check("the shop shows the review", "from 1 review" in section and "Ada O. · " in section and "Lovely fabric" in section, section[:300])
check("review words are escaped", "&lt;script&gt;alert(1)&lt;/script&gt;" in section and "<script>alert" not in home, "")
check("the shopper's full name never shows", "Okafor" not in section, "")
s, h, page = shop("bisi-indigo").get(f"/p/{DRESS}-olokun-wrap-dress")
check("product pages show the shop's rating", 'href="/#sf-reviews"' in page and "1 review of Bisi Indigo" in page, "")
s, h, page = shop("bisi-indigo").get(f"/order/{token}")
check("the tracking page shows the review", "Your review" in page and "Rated 5 out of 5" in page and 'href="/review/' not in page, "")

# ── Fulfilled pickup orders can be reviewed too ────────────────────────
s, pj = checkout("bisi-indigo", items=json.dumps([{"product_id": int(TOTE), "quantity": 3}]), customer_name="Femi Bello", customer_phone="0805 333 4444")
pid_order = one(f"SELECT id FROM orders WHERE order_number = '{pj['order_number']}' AND business_id = {BISI}")
bisi.post(f"/orders/approve/{pid_order}", {"csrf_token": bisi.token, "deliver_mode": "immediate"})
bisi.post(f"/orders/update-status/{pid_order}", {"csrf_token": bisi.token, "status": "processing"})
check("no invitation while it's being prepared", one(f"SELECT COUNT(*) FROM reviews WHERE order_id = {pid_order}") == "0", "")
bisi.post(f"/orders/update-status/{pid_order}", {"csrf_token": bisi.token, "status": "fulfilled"})
check("marking it fulfilled invites a review", one(f"SELECT status FROM reviews WHERE order_id = {pid_order}") == "invited", "")
s, h, page = shop("bisi-indigo").get(path_of(pj["order_url"]))
check("tracking: collected, rate it", "Collected. Thank you" in page and "Rate your order" in page, "")

# ── The Nfelia team hides a review (in Nfelia Ops) ──────────────────────
s, h, page = bisi.get("/admin/reviews")
check("owners can't open the reviews admin", s != 200 or "Reviews on shops" not in page, s)
founder = ops_client()
s, h, page = founder.get("/reviews")
founder.token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', page).group(1)
review_id = one(f"SELECT id FROM reviews WHERE token = '{rtoken}'")
check("Ops sees reviews", s == 200 and "Lovely fabric" in page and "&lt;script&gt;" in page, s)
founder.post(f"/reviews/{review_id}", {"csrf_token": founder.token, "hide": "1"})
check("hidden", one(f"SELECT status FROM reviews WHERE id = {review_id}") == "hidden", "")
s, h, home = shop("bisi-indigo").get("/")
check("a hidden review leaves the shop", 'id="sf-reviews"' not in home, "")
s, h, view = bisi.get(f"/orders/view/{order_id}")
check("the owner sees it was hidden", "Hidden from your shop by Nfelia" in view, "")
founder.post(f"/reviews/{review_id}", {"csrf_token": founder.token, "hide": "0"})
s, h, home = shop("bisi-indigo").get("/")
check("shown again", one(f"SELECT status FROM reviews WHERE id = {review_id}") == "published" and 'id="sf-reviews"' in home, "")
s, h, _ = founder.post(f"/reviews/{review_id}", {"csrf_token": "wrong", "hide": "1"})
check("hiding needs the form token", one(f"SELECT status FROM reviews WHERE id = {review_id}") == "published", s)

# ── Older orders and switched-off shops ────────────────────────────────
sql(f"UPDATE orders SET public_token = NULL WHERE id = {pid_order}")
s, h, view = bisi.get(f"/orders/view/{pid_order}")
new_token = one(f"SELECT public_token FROM orders WHERE id = {pid_order}")
check("an order from before tracking links gets one when opened", re.fullmatch(r"[a-f0-9]{32}", new_token or "") and new_token in view, new_token)
sql(f"UPDATE businesses SET landing_enabled = 0 WHERE id = {BISI}")
s, h, _ = shop("bisi-indigo").get("/")
s2, h2, page = shop("bisi-indigo").get(f"/order/{token}")
check("tracking links work when the shop page is switched off", s == 404 and s2 == 200 and "Delivered" in page, (s, s2))
sql(f"UPDATE businesses SET landing_enabled = 1 WHERE id = {BISI}")

print()
print(f"{sum(results)} of {len(results)} passed")
sys.exit(0 if all(results) else 1)
