# Stripe Calendar Booking Cards

## Simple Guide
This guide is written for non tech users.

## What You Need
1. WordPress admin access.
2. Stripe account keys.
3. One booking page.

## Setup Steps
### Step 1 Activate Plugin
First click: `Plugins` in the left menu.
1. Go to Plugins.
2. Upload plugin zip.
3. Click Activate.

### Step 2 Open Settings
First click: `Settings` in the left menu.
1. Go to `Settings > Stripe Booking`.
2. Follow the 3 step cards at top.

### Step 3 Add Stripe Keys
First click: inside `Stripe Publishable Key` box.
1. Paste Publishable Key.
2. Paste Secret Key.
3. Click Save.

### Step 4 Make Slots
First click: `Booking Slots` in the left menu.
1. Go to `Booking Slots > Add New`.
2. Add title.
3. Pick date.
4. Pick time.
5. Add price.
6. Pick timezone.
7. Add capacity.
8. Add duration.
9. Click Publish.

### Step 5 Add Booking Page
First click: `Pages` in the left menu.
1. Open your booking page.
2. Add shortcode block.
3. Paste `[stripe_booking_calendar]`.
4. Save page.

### Step 6 Add Client Portal Page
First click: `Pages > Add New`.
1. Create another page.
2. Add shortcode block.
3. Paste `[scbc_client_portal]`.
4. Save page.

## First Test
First click: open your booking page in browser.
1. Open booking page.
2. Click a slot.
3. Type test email inside modal.
4. Click BOOK NOW.
5. Finish Stripe test payment.
6. Return to your site.
7. Confirm success notice.
8. Confirm iCal download works.

## Confirmed Bookings Panel
First click: open your booking page after a successful paid booking.
1. Look at the left side panel named `Confirmed Bookings`.
2. Confirm your booked card appears with status badge.
3. Confirm `Today` cards are pinned first.
4. Confirm section titles show:
5. `Today Upcoming (count)`
6. `Today Completed (count)`
7. Confirm sections auto collapse when count is zero.
8. Confirm `Starts in X` text updates every 2 minutes.
9. Confirm each card has:
10. `Booked On` line
11. `Copy Meeting Details` button
12. `Open Client Portal` button
13. `Download iCal` link

## Where To Check In Admin
First click: `Booking Slots` in the left menu.
1. `Booking Slots > Booking Entries` shows paid bookings.
2. `Booking Slots > Reconciliations` shows auto fixed paid bookings.
3. `Booking Slots > Activity Logs` shows system actions.
4. `Settings > Stripe Booking` shows next reconciliation run time.

## Important Rules
1. One email can book up to 6 sessions.
2. Reconciliation runs every 15 minutes.
3. Old logs auto delete after 90 days.

## If Something Looks Wrong
First click: `Settings > Stripe Booking`.
1. Check keys in settings.
2. Check Booking Entries.
3. Check Reconciliations page.
4. Check Activity Logs page.
5. Run `Run Reconciliation Now` button.

## Success URL
After payment success, return URL includes:
1. `customer_ref` token
2. `sched` date

## Support
- Mik Neri
- https://mikneri.dev
- naturalmysticfrequencies@gmail.com
