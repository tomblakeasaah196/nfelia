# Phase 5 — Storefront Cart, Checkout & Orders

**Status:** Approved, not yet built. Implementation to be done in a new chat (Opus).
**Author of this spec:** Sonnet 4.6, from a planning conversation with Tom — 2026-06-10.
**Scope:** Turns the public landing page (`/biz/{slug}`) from a "request a quote" brochure into a real storefront with multi-image products, predefined variants (color/size etc.), a cart, checkout (proof-of-payment or WhatsApp), and an Orders panel in the business dashboard that can convert approved orders into Invoices/Receipts/Deliveries.

No gateway integration (Paystack/MoMo auto-charge) in this phase. Two checkout paths only: **Pay now (upload proof of payment)** or **Order via WhatsApp**.

---

## 1. Decisions Log (context for whoever builds this)

- **Variants**: Build predefined dropdown variants (e.g. Color: Black/White/Red, Size: S/M/L) **now**, not deferred to v2. Per-product, business-defined, via Studio/product form. No per-variant price or stock deltas in v1 — just labels recorded on the order.
- **Free-text note**: Every cart line ALSO has an optional free-text note field, regardless of whether variants exist (covers cases variants don't, e.g. "extra spicy", "engrave 'Happy Birthday'").
- **Stock**: Not enforced. Cart never blocks on stock level. Some businesses (e.g. fashion) display items they don't have yet and produce on order — this is normal and fine.
- **Delivery/pickup**: One-liner business-level setting (e.g. "Delivery at customer's expense — pickup available at 123 Main St"), shown on landing page + included in WhatsApp/order summary. Customer also picks Delivery vs Pickup at checkout (with optional address field if Delivery).
- **Payment**: No Paystack, no auto MoMo charge. At checkout, "Pay now" shows the business's existing bank/MoMo account details (reuse the multi-account selector built for subscriptions), customer uploads proof + optional note → order status `pending_verification`.
- **WhatsApp order**: Alternative to "Pay now". Still creates an `orders` row (channel = `whatsapp`, payment_method = `none`, status `pending`) so it's tracked even though payment is arranged off-platform.
- **WhatsApp buttons — two levels** (confirmed solid):
  1. Per-product "Ask about this" button — prefilled with that product's name/price/variant.
  2. Cart-level "Order via WhatsApp" — prefilled with full cart summary + total + order reference number.
- **Order approval flow**:
  - POP order approved → create **Invoice** (marked paid) + **Receipt** automatically + match/create **Customer**. If fulfillment = Delivery, also create a **Delivery** record. Order status → `fulfilled` (or `processing` until delivery confirms).
  - WhatsApp order approved/acknowledged → create **Invoice** (unpaid) + **Customer**. Business records the Receipt later via the existing receipt flow once payment lands. Order status → `processing`.
  - Order keeps `invoice_id` / `receipt_id` / `delivery_id` links for one-click "View Invoice/Receipt/Delivery" from the order detail.
- **RFQ rework**: Reposition "Request a Quote" as the **B2B / bulk / custom request** form (separate from Cart checkout, which is "buy now"). v1 scope: a fixed set of **optional fields the business can toggle on/off from the Studio** (quantity range, budget, delivery deadline, reference image upload, additional requirements textarea). A full drag-and-drop form builder is a documented **future phase**, not v1 — flag this if priorities change.

---

## 2. Database Changes — new migration `audit/migrations/005_storefront_cart_orders.sql`

```sql
-- =============================================================
-- Migration 005 — Storefront Cart, Checkout & Orders
-- =============================================================

-- 1. Multi-image products
CREATE TABLE IF NOT EXISTS product_images (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    business_id INT NOT NULL,
    image_path  VARCHAR(255) NOT NULL,
    sort_order  INT DEFAULT 0,
    is_cover    TINYINT(1) DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Additional product images. products.image_path remains the legacy cover fallback.';

-- Backfill: copy existing products.image_path into product_images as the cover image
INSERT INTO product_images (product_id, business_id, image_path, sort_order, is_cover)
SELECT id, business_id, image_path, 0, 1
FROM products
WHERE image_path IS NOT NULL AND image_path != '';

-- 2. Predefined variant groups (e.g. "Color", "Size") and options (e.g. "Black", "M")
CREATE TABLE IF NOT EXISTS product_variant_groups (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    product_id  INT NOT NULL,
    business_id INT NOT NULL,
    name        VARCHAR(50) NOT NULL,   -- e.g. "Color", "Size"
    sort_order  INT DEFAULT 0,
    INDEX idx_product (product_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Per-product variant groups, e.g. Color, Size.';

CREATE TABLE IF NOT EXISTS product_variant_options (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    variant_group_id  INT NOT NULL,
    label             VARCHAR(50) NOT NULL,  -- e.g. "Black", "Medium"
    sort_order        INT DEFAULT 0,
    INDEX idx_group (variant_group_id),
    FOREIGN KEY (variant_group_id) REFERENCES product_variant_groups(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Selectable options within a variant group. No price/stock delta in v1.';

-- 3. Orders & order items
CREATE TABLE IF NOT EXISTS orders (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    business_id         INT NOT NULL,
    customer_id         INT NULL,
    order_number        VARCHAR(20) NOT NULL,  -- e.g. ORD-0001 (per-business sequence)
    channel             ENUM('cart_pop','whatsapp') NOT NULL DEFAULT 'cart_pop',
    fulfillment_type    ENUM('delivery','pickup') NOT NULL DEFAULT 'pickup',
    customer_name       VARCHAR(100) NOT NULL,
    customer_phone      VARCHAR(30) NOT NULL,
    customer_email      VARCHAR(100) NULL,
    delivery_address    TEXT NULL,
    customer_note       TEXT NULL,            -- order-level note (separate from per-item notes)
    payment_method      ENUM('bank_transfer','mobile_money','none') NOT NULL DEFAULT 'none',
    payment_proof_path  VARCHAR(255) NULL,
    payment_note        TEXT NULL,
    currency_code       VARCHAR(3) NOT NULL DEFAULT 'XAF',
    subtotal            DECIMAL(12,2) NOT NULL DEFAULT 0,
    total               DECIMAL(12,2) NOT NULL DEFAULT 0,
    status              ENUM('pending','pending_verification','approved','rejected','processing','fulfilled','cancelled')
                          NOT NULL DEFAULT 'pending',
    invoice_id          INT NULL,
    receipt_id          INT NULL,
    delivery_id         INT NULL,
    reviewed_by         INT NULL,
    reviewed_at         DATETIME NULL,
    created_at          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_business (business_id),
    INDEX idx_status (business_id, status),
    UNIQUE KEY uniq_order_number (business_id, order_number)
) ENGINE=InnoDB COMMENT='Customer orders placed via the public landing page (cart or WhatsApp).';

CREATE TABLE IF NOT EXISTS order_items (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    order_id          INT NOT NULL,
    product_id        INT NULL,
    product_name      VARCHAR(150) NOT NULL,  -- snapshot at time of order
    unit_price        DECIMAL(12,2) NOT NULL,
    quantity          INT NOT NULL DEFAULT 1,
    variant_selection VARCHAR(255) NULL,      -- e.g. "Color: Black, Size: L"
    item_note         VARCHAR(255) NULL,      -- free-text per-line note
    line_total        DECIMAL(12,2) NOT NULL,
    INDEX idx_order (order_id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB COMMENT='Line items for an order, with snapshot price/name and chosen variant.';

-- 4. Business-level storefront settings
ALTER TABLE businesses
  ADD COLUMN IF NOT EXISTS delivery_info VARCHAR(255) DEFAULT NULL
    COMMENT 'One-liner shown at checkout, e.g. "Delivery at customer expense — pickup at 123 Main St"',
  ADD COLUMN IF NOT EXISTS rfq_fields JSON DEFAULT NULL
    COMMENT 'Which optional RFQ fields are enabled, e.g. {"quantity_range":true,"budget":true,"deadline":false,"reference_image":true}',
  ADD COLUMN IF NOT EXISTS order_number_seq INT NOT NULL DEFAULT 0
    COMMENT 'Per-business counter used to generate order_number (ORD-0001, ORD-0002, ...)';
```

**Note for implementer**: confirm `businesses` doesn't already have a column named `rfq_fields` / `delivery_info` / `order_number_seq` before running (unlikely, but the `ADD COLUMN IF NOT EXISTS` syntax used elsewhere in this project's migrations handles it).

---

## 3. Backend Changes

### 3.1 New: `app/controllers/OrderController.php`
Authenticated, business-scoped (mirrors `MoMoController` admin review pattern).

| Method | Route | Purpose |
|---|---|---|
| `index()` | `GET /orders` | List all orders for the business, filterable by status |
| `view($id)` | `GET /orders/view/{id}` | Order detail: items, customer, payment proof, fulfillment info |
| `approve($id)` | `POST /orders/approve` | Approve order → run conversion logic (see §5) |
| `reject($id)` | `POST /orders/reject` | Reject (e.g. bad/missing proof) → status `rejected`, notify customer to retry via WhatsApp |
| `update_status($id)` | `POST /orders/status` | Manual status changes (e.g. `processing` → `fulfilled`) |

### 3.2 Modified: `app/controllers/PublicController.php`
New public (no-auth) endpoints, alongside existing `quote_request`:

| Method | Route | Purpose |
|---|---|---|
| `cart_checkout()` | `POST /public/cart-checkout` | Submit cart as an order. Handles both `payment_method=bank_transfer/mobile_money` (with file upload for proof) and `channel=whatsapp` (no proof). Creates `orders` + `order_items`, generates `order_number`, fires notification to business owner. Returns order reference + (for WhatsApp channel) the data needed to build the `wa.me` link. |
| `order_confirmation($token)` | `GET /order/{token}` | Optional: simple "Thanks, your order #ORD-0001 was received" page. Could reuse the `/doc/{token}` pattern with a new token column on `orders`, or skip if cart-side confirmation modal is enough. |

Also: the existing `quote_request()` becomes the handler for the reworked **RFQ** form — extend it to accept the new optional fields (quantity range, budget, deadline, reference image, additional requirements) based on what the business has enabled in `rfq_fields`.

### 3.3 Modified: `app/controllers/LandingPageController.php`
- `save_settings()`: persist `delivery_info` (text input) and `rfq_fields` (checkboxes → JSON).
- `settings()`: pass current `delivery_info` and `rfq_fields` to the view.

### 3.4 Modified: `app/controllers/ProductController.php`
- Multi-image: handle multiple file uploads on create/edit, write to `product_images`, support delete/reorder/set-cover. Keep writing the cover image to `products.image_path` too (so any code still reading that column keeps working).
- Variants: CRUD for `product_variant_groups` + `product_variant_options` — likely an inline repeatable form section ("+ Add option group" → name + comma-separated options, or one row per option).

### 3.5 Routing (`index.php`)
Add routes for:
- `POST /public/cart-checkout` (public, no auth)
- `GET /orders`, `GET /orders/view/{id}`, `POST /orders/approve`, `POST /orders/reject`, `POST /orders/status` (auth required)
- Optional `GET /order/{token}` confirmation page

---

## 4. Frontend / View Changes

### 4.1 `views/public/business.php` — the big one
- **Product card click** → opens a quick-view modal (Alpine.js, consistent with existing category-filter pattern):
  - Image gallery/carousel (from `product_images`)
  - Full description
  - Variant dropdowns (one `<select>` per `product_variant_groups` row, populated from `product_variant_options`)
  - Free-text "Notes" field (e.g. "Black, size L" or "no nuts")
  - Quantity stepper
  - "Add to Cart" button
  - "Ask about this on WhatsApp" button (per-product prefill — see §6)
- **Cart**: client-side, `localStorage`, keyed per business slug. Cart icon + item count in header. Cart drawer: items (name, variant/note, qty, line total), qty +/-, remove, subtotal.
- **Checkout modal** (from cart drawer):
  - Customer name, phone, email (optional)
  - Fulfillment: Delivery vs Pickup (radio). If Delivery → address textarea. Show the business's `delivery_info` one-liner here as helper text.
  - Order-level note (optional)
  - Payment choice:
    - **Pay now** → shows business's bank/MoMo account details (reuse multi-account selector UI from MoMo checkout), file upload for proof + optional note → submits to `/public/cart-checkout` with `payment_method`.
    - **Order via WhatsApp** → submits to `/public/cart-checkout` with `channel=whatsapp`, then immediately opens `wa.me` link prefilled with the full cart summary (see §6).
- **Delivery info**: shown in checkout modal and possibly as a small badge near the catalogue section.
- **RFQ section rework**: re-labeled as "Bulk / Custom Order Request" (B2B framing). Renders only the fields enabled in `rfq_fields` (plus the existing name/phone/message fields, which stay mandatory).
- **WhatsApp floating button**: keep as general "Chat with us" fallback, OR replace with the cart-level "Order via WhatsApp" when the cart has items — needs a small UX decision during build (recommend: floating button stays generic/general-contact; cart-level WhatsApp button lives in the cart drawer, separate and clearly an alternative to "Pay now").

### 4.2 Product form view (in `views/products/` — confirm exact filename, e.g. `form.php`)
- Multi-image uploader with thumbnails, drag-to-reorder, "set as cover", delete.
- Variant builder: repeatable "Option group" rows (name + comma-separated values, e.g. `Color: Black, White, Red`), parsed into `product_variant_groups` + `product_variant_options` on save.

### 4.3 `views/landing/settings.php`
- New field: "Delivery & Pickup Info" (text input, e.g. *"Delivery at customer's expense — pickup at 123 Main St"*).
- New section: "Bulk Order Request Form" — checkboxes to toggle each optional RFQ field (Quantity range / Budget / Deadline / Reference image / Additional requirements).

### 4.4 `views/orders/index.php` (new)
List: order #, customer name/phone, item count, total, channel (cart/WhatsApp), payment status, date, status badge. Filter by status. Click → detail.

### 4.5 `views/orders/view.php` (new)
- Order header (customer info, fulfillment type + address, order-level note)
- Line items table (product, variant/note, qty, unit price, line total)
- Payment section: method, proof image (if uploaded) with zoom, payment note
- Action buttons: Approve / Reject (with reason), and post-approval links to generated Invoice/Receipt/Delivery

### 4.6 `views/layouts/app.php`
- New sidebar nav item "Orders" — placed near Quotations/Invoices, or grouped under a new "Storefront" section alongside "Landing Page". Badge with count of `pending` + `pending_verification` orders (same pattern as existing notification badges).

---

## 5. Order Approval Logic (detail)

**On `OrderController::approve($id)`:**

1. Load order + items.
2. Match or create `customers` record (by phone, same pattern as `quote_request()`).
3. Create an `invoices` row:
   - One `invoice_items` row per `order_items` row (carry over product_id, name, qty, unit_price, line_total, variant/note as a description suffix).
   - `customer_id` = matched/created customer.
4. **If POP order** (`payment_method != 'none'` and proof was approved):
   - Create a `receipts` row against the new invoice for the full `total`, marking the invoice as paid.
   - Order status → `fulfilled` if pickup, or `processing` if delivery (awaiting dispatch).
5. **If WhatsApp order** (`channel = 'whatsapp'`):
   - No receipt created yet. Invoice stays unpaid.
   - Order status → `processing`.
6. **If fulfillment = delivery** (either case): create a `deliveries` row linked to the invoice/customer, status `pending`, so the existing delivery tracking/signoff flow takes over.
7. Store `invoice_id` / `receipt_id` / `delivery_id` back on the `orders` row.
8. Notification to business owner already exists (created at order submission) — optionally fire a second notification confirming conversion, or just rely on the order detail page links.

**On `reject($id)`**: status → `rejected`, store rejection reason in `payment_note` (appended) or a new `rejection_reason` column if cleaner. No invoice/receipt created. Business is expected to follow up with the customer via WhatsApp/phone (number is on the order).

---

## 6. WhatsApp Message Templates

**Per-product "Ask about this" button** (in product modal):
```
Hi {business_name}! I'm interested in {product_name} ({variant_selection if any}) — {price}. Is it available?
```
Links to `https://wa.me/{business_whatsapp}?text={urlencoded message}`.

**Cart-level "Order via WhatsApp" button** (in cart drawer, after order is recorded via `/public/cart-checkout`):
```
Hi {business_name}! I'd like to place order #{order_number}:

1x {product_name} ({variant_selection / note}) — {line_total}
2x {product_name_2} ({variant_selection / note}) — {line_total_2}

Total: {total}
Fulfillment: {Delivery to {address} / Pickup}
{order-level note, if any}
```
This mirrors the existing floating WhatsApp button's number source (`business['phone']`, digits-only via `preg_replace('/[^0-9]/', '', ...)`).

---

## 7. RFQ Optional Fields (v1 toggle list)

Stored in `businesses.rfq_fields` JSON. Each key is a boolean toggle in Landing Page settings:

- `quantity_range` — free-text "Approx. quantity needed"
- `budget` — free-text "Estimated budget"
- `deadline` — date picker "Needed by"
- `reference_image` — file upload "Attach a reference image"
- `additional_requirements` — textarea (likely always-on, but listed for completeness)

Existing mandatory fields (name, phone, message) stay as-is.

---

## 8. Suggested Build Sequence

1. Migration `005_storefront_cart_orders.sql` (run + verify backfill of `product_images`).
2. `ProductController` + product form: multi-image + variant groups/options (admin-side first, so test data exists).
3. `views/public/business.php`: product modal with gallery + variants + notes + qty + Add to Cart + per-product WhatsApp button.
4. Cart (localStorage) + cart drawer UI.
5. `PublicController::cart_checkout()` + checkout modal (Pay now / WhatsApp paths) + `orders`/`order_items` writes.
6. `OrderController` + `views/orders/*` + nav item.
7. Approval conversion logic (§5) — Invoice/Receipt/Delivery generation.
8. Landing settings: `delivery_info` + `rfq_fields` toggles.
9. RFQ form rework using `rfq_fields`.
10. End-to-end test: place a POP order → approve → verify Invoice+Receipt+Delivery created and linked; place a WhatsApp order → approve → verify Invoice (unpaid) created, then manually add a Receipt via existing flow.

---

## 9. Open Items for the Build Session

- Confirm exact filename/path of the product create/edit form view.
- Decide final placement of "Orders" in the sidebar nav (own item vs. grouped under a new "Storefront" section).
- Decide whether floating WhatsApp button stays generic or is replaced/supplemented by cart-aware version when cart has items.
- Confirm `businesses.phone` is the right source for the WhatsApp number (vs. a dedicated `whatsapp_number` field) — currently the floating button strips non-digits from `phone`.
