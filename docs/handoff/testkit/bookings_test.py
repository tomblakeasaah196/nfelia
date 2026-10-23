"""Selling, step 4: bookings with deposits, checked end to end against a local
copy. Local only.

Needs what storefront_test.py needs, with the server showing every PHP
message:
    php -d error_reporting=-1 -d display_errors=1 -S 127.0.0.1:8000 docs/handoff/testkit/router.php
    python3 docs/handoff/testkit/bookings_test.py
It reseeds the database (storefront_seed.php, then bookings_seed.php).
Dates are worked out from today in Lagos time.
"""
import datetime
import http.client
import json
import os
import re
import subprocess
import sys
import urllib.parse
import uuid

KIT = os.path.dirname(os.path.abspath(__file__))
subprocess.run(["php", os.path.join(KIT, "storefront_seed.php")], check=True)
subprocess.run(["php", os.path.join(KIT, "bookings_seed.php")], check=True)

MAIN = "nfelia.test:8000"
PHP_NOISE = re.compile(r"(Warning|Notice|Deprecated|Fatal error|Parse error)(</b>)?:", re.I)
LAGOS = datetime.timezone(datetime.timedelta(hours=1))
TODAY = datetime.datetime.now(LAGOS).date()
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


def day(n):
    return (TODAY + datetime.timedelta(days=n)).isoformat()


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

    def json(self, method, path, data=None):
        s, h, raw = self.req(method, path, data)
        try:
            return s, json.loads(raw)
        except ValueError:
            return s, {"raw": raw[:300].decode("utf-8", "replace")}

    def login(self, email):
        s, h, html = self.get("/login")
        token = re.search(r'name="csrf_token" value="([0-9a-f]+)"', html).group(1)
        self.req("POST", "/login", {"csrf_token": token, "email": email, "password": "testpass123"})
        s, h, html = self.get("/dashboard")
        m = re.search(r'name="csrf-token" content="([0-9a-f]+)"', html) or re.search(r'name="csrf_token" value="([0-9a-f]+)"', html)
        self.token = m.group(1) if m else None


def shop(label):
    return Client(f"{label}.nfelia.test:8000")


def book(label, pid, **fields):
    sql("DELETE FROM auth_throttle")   # these checks are about bookings, not the spam limits (lockdown_test.js)
    data = {"customer_name": "Ada Okafor", "customer_phone": "0803 555 0101", "channel": "site"}
    data.update(fields)
    return shop(label).json("POST", f"/book/{pid}", data)


def pid(biz, name):
    return one(f"SELECT id FROM products WHERE business_id = {biz} AND name = '{name}'")


BRAIDS, LASH, GEL = pid(4, "Knotless braids, medium, waist length"), pid(4, "Classic lash set"), pid(4, "Gel polish, hands")
INTIMATE, CLASSIC = pid(7, "Intimate"), pid(7, "Classic")
ONEBED, STUDIO = pid(10, "One-bedroom apartment"), pid(10, "Studio")
AC = pid(11, "AC servicing")
GLOW_BANK = one("SELECT id FROM bank_accounts WHERE business_id = 4 LIMIT 1")
HAVEN_BANK = one("SELECT id FROM bank_accounts WHERE business_id = 10 LIMIT 1")

# ── On the shop ────────────────────────────────────────────────────────
s, h, page = shop("glow-by-temi").get("/")
check("bookable services get Book buttons to their booking page", f'href="/book/{BRAIDS}-knotless-braids-medium-waist-length" data-book="{BRAIDS}"' in page and not PHP_NOISE.search(page), "")
check("the bookings section lists them", 'id="sf-booking"' in page and "3 hours · 30% deposit to book" in page and "1 hour · ₦5,000 deposit to book" in page, "")
check("the sheet knows how to book them", '"book":{"url":"/book/' + BRAIDS + '-knotless-braids-medium-waist-length"' in page and "Pick a time and book" in page, "")
s, h, page = shop("bisi-indigo").get("/")
check("shops without bookable products show no bookings section", 'id="sf-booking"' not in page, "")
s, h, page = shop("haven-shortlets").get("/")
check("stays are titled for stays", 'id="sf-booking-title">Book your stay' in page and '<span class="sf-unit"> a night</span>' in page and "<span>50% deposit to book</span>" in page, "")
s, h, page = shop("glow-by-temi").get(f"/p/{BRAIDS}-knotless-braids-medium-waist-length")
check("a bookable product's page offers booking instead of the bag", "Pick a time and book" in page and "data-buy=" not in page and f'href="/book/{BRAIDS}-' in page, "")

# ── The booking page ───────────────────────────────────────────────────
s, h, _ = shop("glow-by-temi").get(f"/book/{BRAIDS}")
check("/book/{id} is sent on to its named address", s == 301 and h.get("location", "").endswith(f"/book/{BRAIDS}-knotless-braids-medium-waist-length"), (s, h.get("location")))
s, h, page = shop("glow-by-temi").get(f"/book/{BRAIDS}-knotless-braids-medium-waist-length")
check("booking page draws cleanly", s == 200 and not PHP_NOISE.search(page) and 'data-bookform' in page and "Pay the deposit" in page, (s, PHP_NOISE.search(page)))
check("booking page is cacheable, not indexed, and points to the product", "s-maxage=300" in h.get("cache-control", "")
      and '<meta name="robots" content="noindex, nofollow">' in page  # Glow is on trial
      and f'<link rel="canonical" href="http://glow-by-temi.nfelia.test:8000/p/{BRAIDS}-knotless-braids-medium-waist-length">' in page, h)
s, h, page = shop("haven-shortlets").get(f"/book/{ONEBED}-one-bedroom-apartment")
check("a live shop's booking page may be followed but not indexed", '<meta name="robots" content="noindex, follow">' in page and "Check-in from 2:00pm, check-out by 12:00pm" in page, "")
data = json.loads(re.search(r'<script type="application/json" id="sf-data">(.*?)</script>', page, re.S).group(1))
check("the page hands the script the booking rules", data["booking"]["mode"] == "nights" and data["booking"]["deposit"] == {"type": "percent", "value": 50} and data["booking"]["urls"]["free"] == f"/book/{ONEBED}/free", data.get("booking"))
s, h, _ = shop("glow-by-temi").get(f"/book/{pid(4, 'Cornrows with extensions')}")
check("a product that isn't booked has no booking page", s == 404, s)
s, h, _ = shop("glow-by-temi").get(f"/book/{ONEBED}")
check("another shop's product can't be booked here", s == 404, s)

# ── Free days and times ────────────────────────────────────────────────
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?month={day(2)[:7]}")
check("free days come a month at a time", s == 200 and j["days"].get(day(2)) == "open", j)
s, h, _ = shop("glow-by-temi").req("GET", f"/book/{LASH}/free?month={day(2)[:7]}")
check("free days are never cached", "no-store" in h.get("cache-control", ""), h)
if TODAY.day > 1:
    check("past days are closed", j["days"].get(day(-1)) == "closed", j["days"].get(day(-1)))
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?date={day(2)}")
times = [t["t"] for t in j.get("times", [])]
check("free times run from opening to the last start that fits", times[:3] == ["09:00", "09:30", "10:00"] and times[-1] == "18:00", times)
check("times come with words", j["times"][0]["label"] == "9:00am", j["times"][:1])
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?date=2020-01-01")
check("a past day has no times", j.get("times") == [], j)
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?date={day(90)}")
check("beyond how far ahead people can book, no times", j.get("times") == [], j)

# ── Time slots ─────────────────────────────────────────────────────────
s, j = book("glow-by-temi", BRAIDS, date=day(2), time="10:00", bank_id=GLOW_BANK, customer_email="ada@example.com", customer_note="<b>long</b> hair please")
# Booking numbers carry the business's prefix since the orders hub (ADA-BKG-001; the seed leaves the counters running)
BKG1 = j.get("booking_number") or ""
check("a time slot is booked", s == 200 and j.get("success") and re.fullmatch(r"[A-Z0-9]+-BKG-\d{3,}", BKG1) and j["total"] == 45000 and j["deposit"] == 13500, j)
check("with its own private link", re.fullmatch(r"http://glow-by-temi\.nfelia\.test:8000/booking/[a-f0-9]{32}", j.get("booking_url") or ""), j)
braids_link = j["booking_url"]
row = sql(f"SELECT kind, starts_at, ends_at, status, payment_method, deposit_due, customer_id IS NOT NULL FROM bookings WHERE booking_number = '{BKG1}' AND business_id = 4")[0]
check("held as a request, 3 hours long, by transfer", row == ["slot", f"{day(2)} 10:00:00", f"{day(2)} 13:00:00", "pending", "bank_transfer", "13500.00", "1"], row)
told = sql("SELECT title, message, action_url FROM notifications WHERE business_id = 4 AND title LIKE 'New booking%'")
check("the owner is told", told and BKG1 in told[0][1] and told[0][2].startswith("bookings/view/"), told)
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?date={day(2)}")
times = [t["t"] for t in j.get("times", [])]
check("a request takes its time away from everyone", "09:00" in times and "09:30" not in times and "12:30" not in times and "13:00" in times, times)
s, j = book("glow-by-temi", LASH, date=day(2), time="11:00", customer_phone="0803 555 0102")
check("an overlapping time is refused", s == 409 and "just taken" in j.get("error", ""), (s, j))
s, j = book("glow-by-temi", LASH, date=day(2), time="11:15", customer_phone="0803 555 0102")
check("a time that isn't a start time is refused", s == 409, (s, j))
s, j = book("glow-by-temi", GEL, date=day(2), time="13:00", customer_phone="0803 555 0103")
check("the next free time can be booked, no deposit", s == 200 and j.get("deposit") == 0 and j.get("total") == 8000, j)
s, j = book("glow-by-temi", LASH, date=day(2), customer_phone="0803 555 0104")
check("a time slot needs a time", s == 409, (s, j))

sql(f"INSERT INTO tasks (business_id, kind, title, due_date, due_time, end_time, created_by, created_at, updated_at) VALUES (4, 'appointment', 'Owner busy', '{day(3)}', '15:00:00', '16:00:00', 4, NOW(), NOW())")
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?date={day(3)}")
times = [t["t"] for t in j.get("times", [])]
check("the owner's own appointments are skipped", "14:00" in times and "14:30" not in times and "15:30" not in times and "16:00" in times, times)

s, j = book("chinedu-co", AC, date=day(2), time="09:00", customer_phone="0803 555 0201")
s2, j2 = book("chinedu-co", AC, date=day(2), time="09:00", customer_phone="0803 555 0202")
s3, j3 = book("chinedu-co", AC, date=day(2), time="09:30", customer_phone="0803 555 0203")
check("two at once when the shop says so, not three", s == 200 and s2 == 200 and s3 == 409, (s, s2, s3, j3))

# ── Stays ──────────────────────────────────────────────────────────────
s, j = book("haven-shortlets", ONEBED, checkin=day(5), checkout=day(7), bank_id=HAVEN_BANK)
check("a stay is booked by the night", s == 200 and j.get("total") == 170000 and j.get("deposit") == 85000 and "2 nights" in j.get("when", ""), j)
row = sql(f"SELECT starts_at, ends_at, units FROM bookings WHERE business_id = 10")[0]
check("from check-in time to check-out time", row == [f"{day(5)} 14:00:00", f"{day(7)} 12:00:00", "2"], row)
s, j = book("haven-shortlets", ONEBED, checkin=day(6), checkout=day(8), customer_phone="0803 555 0301")
check("overlapping nights are refused", s == 409 and "nights were just booked" in j.get("error", ""), (s, j))
s, j = book("haven-shortlets", ONEBED, checkin=day(7), checkout=day(9), customer_phone="0803 555 0302")
check("arriving the day the last guest leaves is fine", s == 200, (s, j))
s, j = book("haven-shortlets", STUDIO, checkin=day(5), checkout=day(7), customer_phone="0803 555 0303")
check("each apartment is booked on its own", s == 200, (s, j))
s, j = book("haven-shortlets", STUDIO, checkin=day(20), checkout=day(19), customer_phone="0803 555 0304")
check("leaving before arriving is refused", s == 409, (s, j))
s, j = book("haven-shortlets", STUDIO, checkin=day(20), checkout=day(60), customer_phone="0803 555 0305")
check("stays over 30 nights go to WhatsApp", s == 409 and "30 nights" in j.get("error", ""), (s, j))
s, j = shop("haven-shortlets").json("GET", f"/book/{ONEBED}/free?month={day(5)[:7]}")
check("taken nights show as full", j["days"].get(day(5)) == "full" and j["days"].get(day(6)) == "full", {k: v for k, v in j["days"].items() if k >= day(4)})

# ── Whole days ─────────────────────────────────────────────────────────
s, j = book("lola-events", INTIMATE, date=day(0), customer_phone="0803 555 0401")
check("whole days keep the shop's notice (a day)", s == 409, (s, j))
s, j = book("lola-events", INTIMATE, date=day(10), customer_phone="0803 555 0402")
check("a whole day is booked with its fixed deposit", s == 200 and j.get("deposit") == 100000 and j.get("total") == 450000, j)
s, j = book("lola-events", INTIMATE, date=day(10), customer_phone="0803 555 0403")
check("one booking a day per package", s == 409, (s, j))
s, j = book("lola-events", CLASSIC, date=day(10), customer_phone="0803 555 0404")
check("another package that day is fine", s == 200, (s, j))

# ── Rules ──────────────────────────────────────────────────────────────
for i in range(5):
    book("chinedu-co", AC, date=day(4), time=f"{9 + i:02d}:00", customer_phone="0803 555 0999")
s, j = book("chinedu-co", AC, date=day(4), time="15:00", customer_phone="0803 555 0999")
check("five bookings a day per phone", s == 429, (s, j))
s, j = book("glow-by-temi", GEL, date=day(5), time="10:00", customer_name="", customer_phone="")
check("name and phone are needed", s == 422, (s, j))
s, j = book("glow-by-temi", GEL, date=day(5), time="10:00", customer_phone="12", customer_name="A")
check("a phone number has to look like one", s == 422 and "phone number" in j.get("error", ""), (s, j))
sql("UPDATE subscriptions SET status = 'suspended' WHERE business_id = 11")   # a cancelled month stays open to its end
s, h, page = shop("chinedu-co").get(f"/book/{AC}-ac-servicing")
s2, j2 = book("chinedu-co", AC, date=day(6), time="09:00", customer_phone="0803 555 0601")
check("a closed shop takes no bookings", "closed for now" in page and s2 == 403, (s, s2))
sql("UPDATE subscriptions SET status = 'active' WHERE business_id = 11")

boundary = uuid.uuid4().hex
png = __import__("base64").b64decode("iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=")
parts = []
for k, v in {"customer_name": "Proof Person", "customer_phone": "0803 555 0701", "date": day(6), "time": "09:00", "bank_id": GLOW_BANK, "channel": "site"}.items():
    parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name="payment_proof"; filename="proof.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + png + b"\r\n")
parts.append(f"--{boundary}--\r\n".encode())
s, h, raw = shop("glow-by-temi").req("POST", f"/book/{BRAIDS}", headers={"Content-Type": f"multipart/form-data; boundary={boundary}"}, body=b"".join(parts))
proof = one("SELECT payment_proof_path FROM bookings WHERE customer_phone = '0803 555 0701'")
check("the deposit's proof is kept, privately", s == 200 and proof and proof.startswith("storage/private/booking_proofs/4/"), (s, raw[:200], proof))

# ── The shopper's booking page ─────────────────────────────────────────
path = urllib.parse.urlparse(braids_link).path
s, h, page = shop("glow-by-temi").get(path)
check("booking page draws cleanly and privately", s == 200 and not PHP_NOISE.search(page) and f"Booking {BKG1}</h1>" in page
      and "no-store" in h.get("cache-control", "") and "noindex" in h.get("x-robots-tag", "") and h.get("referrer-policy") == "same-origin", (s, h))
check("it says the deposit is being checked and what's left to pay", "is checking your deposit" in page and "₦13,500" in page and "₦31,500" in page, "")
check("it offers the phone's calendar", f'href="{path}/calendar.ics"' in page, "")
s, h, raw = shop("glow-by-temi").req("GET", path + "/calendar.ics")
ics = raw.decode()
start_utc = (datetime.datetime.fromisoformat(day(2) + "T10:00") - datetime.timedelta(hours=1)).strftime("%Y%m%dT%H%M%SZ")
check("the calendar file has the right time in UTC", h.get("content-type", "").startswith("text/calendar") and f"DTSTART:{start_utc}" in ics and "BEGIN:VEVENT" in ics and "\r\n" in ics, ics[:300])
s, h, _ = shop("haven-shortlets").get(path)
check("another shop can't open it", s == 404, s)

# ── The owner ──────────────────────────────────────────────────────────
temi = Client()
temi.login("owner4@nfelia.test")
s, h, page = temi.get("/bookings")
check("requests wait in Bookings", s == 200 and not PHP_NOISE.search(page) and BKG1 in page and "To confirm" in page, (s, PHP_NOISE.search(page)))
check("the menu has Bookings", 'href="' + "http://nfelia.test:8000" + '/bookings"' in page or "/bookings\"" in page, "")
braids_id = one(f"SELECT id FROM bookings WHERE business_id = 4 AND booking_number = '{BKG1}'")
s, h, page = temi.get(f"/bookings/view/{braids_id}")
check("one booking, with the shopper's note escaped", s == 200 and "&lt;b&gt;long&lt;/b&gt;" in page and "<b>long</b>" not in page and "Confirm and add to my calendar" in page, s)
temi.post(f"/bookings/confirm/{braids_id}", {"csrf_token": temi.token})
status, task = sql(f"SELECT status, task_id FROM bookings WHERE id = {braids_id}")[0]
check("confirming it puts it on the calendar", status == "confirmed" and task not in ("NULL", None), (status, task))
t = sql(f"SELECT kind, due_date, due_time, end_time, title FROM tasks WHERE id = {task}")[0]
check("as an appointment at its time", t[:4] == ["appointment", day(2), "10:00:00", "13:00:00"] and "Ada Okafor" in t[4], t)
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?date={day(2)}")
times = [x["t"] for x in j.get("times", [])]
check("a confirmed booking still holds its time (counted once)", "09:00" in times and "10:00" not in times and "13:00" not in times, times)
s, h, page = shop("glow-by-temi").get(path)
check("the shopper sees it confirmed", "Confirmed. Glow by Temi is expecting you." in page, "")
s, h, _ = temi.post(f"/bookings/confirm/{braids_id}", {"csrf_token": temi.token})
check("confirming twice does nothing more", one(f"SELECT COUNT(*) FROM tasks WHERE business_id = 4 AND kind = 'appointment'") == "2", "")

gel_id = one("SELECT id FROM bookings WHERE business_id = 4 AND product_name = 'Gel polish, hands'")
temi.post(f"/bookings/decline/{gel_id}", {"csrf_token": temi.token, "message": "Fully booked that day. <i>Sorry</i>"})
check("declining frees the time", one(f"SELECT status FROM bookings WHERE id = {gel_id}") == "declined"
      and "13:00" in [x["t"] for x in shop("glow-by-temi").json("GET", f"/book/{GEL}/free?date={day(2)}")[1].get("times", [])], "")
gel_token = one(f"SELECT public_token FROM bookings WHERE id = {gel_id}")
s, h, page = shop("glow-by-temi").get(f"/booking/{gel_token}")
check("the shopper sees why, escaped", "couldn&#039;t take this booking" in page and "Fully booked that day. &lt;i&gt;Sorry&lt;/i&gt;" in page, "")

temi.post(f"/bookings/cancel/{braids_id}", {"csrf_token": temi.token, "message": ""})
check("cancelling takes it off the calendar", one(f"SELECT status FROM bookings WHERE id = {braids_id}") == "cancelled"
      and one(f"SELECT deleted_at IS NOT NULL FROM tasks WHERE id = {task}") == "1", "")
temi.post(f"/bookings/complete/{braids_id}", {"csrf_token": temi.token})
check("a cancelled booking can't be marked done", one(f"SELECT status FROM bookings WHERE id = {braids_id}") == "cancelled", "")
proof_id = one("SELECT id FROM bookings WHERE customer_phone = '0803 555 0701'")
temi.post(f"/bookings/confirm/{proof_id}", {"csrf_token": temi.token})
temi.post(f"/bookings/complete/{proof_id}", {"csrf_token": temi.token})
check("done: the appointment is ticked", one(f"SELECT status FROM bookings WHERE id = {proof_id}") == "completed"
      and one(f"SELECT t.done_at IS NOT NULL FROM tasks t JOIN bookings b ON b.task_id = t.id WHERE b.id = {proof_id}") == "1", "")
s, h, _ = temi.post(f"/bookings/confirm/{gel_id}", {"csrf_token": "wrong"})
check("actions need the form token", one(f"SELECT status FROM bookings WHERE id = {gel_id}") == "declined", s)
s, h, page = temi.get(f"/bookings/view/{one('SELECT id FROM bookings WHERE business_id = 10 LIMIT 1')}")
check("owners only see their own bookings", "Booking BKG" not in page or s != 200, s)

# ── Booking hours ──────────────────────────────────────────────────────
s, h, page = temi.get("/bookings/settings")
check("booking hours open", s == 200 and not PHP_NOISE.search(page) and "Opening hours" in page, s)
form = {"csrf_token": temi.token, "notice": "0", "ahead": "60", "step": "60", "at_once": "1", "checkin": "14:00", "checkout": "12:00"}
for d in ["mon", "tue", "wed", "thu", "fri", "sat", "sun"]:
    form.update({"open_" + d: "1", "from_" + d: "10:00", "to_" + d: "16:00"})
closed_day = (TODAY + datetime.timedelta(days=8))
key = ["mon", "tue", "wed", "thu", "fri", "sat", "sun"][closed_day.weekday()]
del form["open_" + key]
temi.post("/bookings/settings", form)
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?date={day(9)}")
times = [x["t"] for x in j.get("times", [])]
check("new hours and start times apply at once", times[:2] == ["10:00", "11:00"] and times[-1] == "15:00", times)
s, j = shop("glow-by-temi").json("GET", f"/book/{LASH}/free?month={closed_day.isoformat()[:7]}")
check("a day the shop is shut is closed", j["days"].get(closed_day.isoformat()) == "closed", j["days"].get(closed_day.isoformat()))
form2 = dict(form, **{"from_" + key: "18:00", "to_" + key: "09:00", "open_" + key: "1"})
s, h, _ = temi.post("/bookings/settings", form2)
s, h, page = temi.get("/bookings/settings")
check("a closing time before the opening time is caught", "needs a closing time after its opening time" in page, "")

# ── Products and Studio ────────────────────────────────────────────────
cornrows = pid(4, "Cornrows with extensions")
s, h, page = temi.get(f"/products/edit/{cornrows}")
check("the product form has booking fields", 'name="booking_mode"' in page and 'name="deposit_type"' in page and not PHP_NOISE.search(page), "")
csrf = re.search(r'name="csrf_token" value="([0-9a-f]+)"', page).group(1)
temi.post(f"/products/edit/{cornrows}", {"csrf_token": csrf, "name": "Cornrows with extensions", "sell_price": "15000", "cost_price": "0", "unit": "",
                                         "is_service": "1", "booking_mode": "slot", "booking_minutes": "120", "deposit_type": "percent", "deposit_value": "150"})
row = sql(f"SELECT booking_mode, booking_minutes, deposit_type, deposit_value FROM products WHERE id = {cornrows}")[0]
check("saving a product saves how it's booked (a share is at most 100%)", row == ["slot", "120", "percent", "100.00"], row)
temi.post(f"/products/edit/{cornrows}", {"csrf_token": csrf, "name": "Cornrows with extensions", "sell_price": "15000", "cost_price": "0", "unit": "",
                                         "is_service": "1", "booking_mode": "none"})
check("and switching it off", sql(f"SELECT booking_mode, deposit_type FROM products WHERE id = {cornrows}")[0] == ["none", "none"], "")
s, h, prev = temi.get("/landing-page/preview")
check("Studio's preview keeps Book buttons inside the preview", f'href="#item-{BRAIDS}" data-book="{BRAIDS}"' in prev and 'href="/book/' not in prev, "")
s, h, studio = temi.get("/landing-page")
check("Studio offers the bookings section", '"booking":{"name":"Bookings"' in studio, "")

print()
print(f"{sum(results)} of {len(results)} passed")
sys.exit(0 if all(results) else 1)
