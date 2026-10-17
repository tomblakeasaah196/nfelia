# NFELIA — Multi-Country Setup Guide
**For Business Owners | Non-Technical | Version 1.0 | 2026-06-03**

---

## Overview

Nfelia is built to operate across multiple countries, each with its own currency and payment method. This guide explains how to add a new country, configure it for payment, and activate it for users — entirely through the Admin Panel. No coding required.

---

## Step 1 — Log In as the Nfelia Admin

Go to `https://nfelia.com` and log in with the **Nfelia Admin** credentials (the founder account, Business ID 1).

Once logged in, you will see three new items in the left sidebar under **"Founder Panel"**:
- **Admin Panel** — general platform management
- **Countries** — manage all markets *(this is what you use for this guide)*
- **MoMo Review** — approve/reject Mobile Money payment receipts

---

## Step 2 — Open the Countries Panel

Click **Countries** in the sidebar.

You will see a table showing all configured countries with:
- Their flag, name, and currency
- Payment method (Paystack, Mobile Money, etc.)
- Monthly subscription price in local currency
- Seat price
- Number of businesses using that country
- Active/Inactive status toggle
- Edit button

**Currently configured:** Nigeria (NGN, Paystack) and Cameroon (XAF, Mobile Money).

---

## Step 3 — Add a New Country

Click the **"+ Add Country"** button at the top right.

A form will appear. Fill in:

| Field | What to enter | Example |
|---|---|---|
| Country Name | The country's full name | Ghana |
| ISO Code | 2-letter country code (look it up at iso.org) | GH |
| Flag Emoji | Paste the flag emoji | 🇬🇭 |
| Currency Name | Full name of the currency | Ghanaian Cedi |
| Currency Code | 3-letter ISO code | GHS |
| Currency Symbol | Symbol displayed on invoices | ¢ or GH₵ |
| Payment Method | How users in this country will pay | Paystack |
| Monthly Price | Subscription price in LOCAL currency | 150 |
| Extra Seat Price | Price per additional staff member | 75 |
| Trial Days | How many free trial days new users get | 14 |
| Mobile Money Number | Only needed for Mobile Money countries | (leave blank for Paystack) |
| Payment Instructions | Instructions shown at checkout | (leave blank for Paystack) |
| Active | Check this to make it live immediately | ✓ |

Click **"Add Country"** to save.

---

## Step 4 — Configure Mobile Money Countries

If the new country uses **Mobile Money** (like Cameroon), fill in two extra fields:

**Mobile Money Number:**
Enter the full phone number users send money to, including country code.
Example: `+237 6XX XXX XXX`

**Payment Instructions:**
Write clear, step-by-step instructions for users. This text appears on the payment screen.
Example:
> Send the exact amount to the MTN/Orange Money number shown above. Take a screenshot of the successful transaction (must show: your name, amount, and date). Upload it below as proof.

---

## Step 5 — Edit an Existing Country

To update pricing, payment instructions, or MoMo number for an existing country:

1. Find the country in the table
2. Click **"Edit"** on the right
3. Update the fields you want to change
4. Click **"Save Changes"**

**What you can edit:**
- Currency symbol (if it changed)
- Flag emoji
- Payment method
- Monthly price and extra seat price
- Trial days
- Mobile Money number
- Payment instructions

**What you cannot edit after creation:** Country name, ISO code, currency code. (These are identity fields — changing them would break existing subscriptions.)

---

## Step 6 — Activate or Deactivate a Country

Each country (except Nigeria, which cannot be deactivated) has a **toggle switch** in the Status column.

- **Green / on** = Active. Users from this country can register and pay.
- **Grey / off** = Inactive. The country is hidden from the currency selector. Existing users are unaffected.

Click the toggle to switch. The change takes effect immediately — no page refresh needed.

---

## Step 7 — Reviewing Mobile Money Payments

When a user in a Mobile Money country pays their subscription, they upload a receipt. You must manually verify and approve it.

**To review payments:**
1. Click **"MoMo Review"** in the sidebar (a red badge shows how many are waiting)
2. You will see a list of pending payments, oldest first
3. For each one, you can see: the business name, the amount, the submission time, and a thumbnail of their receipt
4. Click **"View Full"** to open the receipt in a new tab
5. If the payment is correct: click **✓ Approve**
6. If there is a problem: click **✕ Reject**, type a clear reason, then click **Confirm Rejection**

The user is automatically notified by email and in-app for both outcomes.

---

## Step 8 — Setting Up a New Market: Checklist

Before activating a new country for real users, confirm:

- [ ] Country is added in the Countries panel with correct currency
- [ ] Monthly price is set correctly in local currency
- [ ] Payment method is configured
- [ ] If Mobile Money: MoMo number and payment instructions are filled in
- [ ] If Paystack: confirm Paystack supports the currency (NGN only for now; additional currencies need Paystack setup)
- [ ] Country is toggled **Active**
- [ ] Test: Register a dummy account and confirm the currency selector shows the new country
- [ ] Test: Confirm pricing on the billing page reflects the correct amount

---

## Current Countries

| Country | Currency | Payment Method | Monthly Price |
|---|---|---|---|
| Nigeria | NGN (₦) | Paystack (Card/Bank) | ₦10,000 |
| Cameroon | XAF (FCFA) | Mobile Money | FCFA 6,500 |

---

## Support

For technical issues: support@nfelia.com  
WhatsApp: +234 816 978 9106
