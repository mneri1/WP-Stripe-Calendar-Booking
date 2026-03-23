# Stripe Calendar Booking Cards

## Documentation Navigation
1. [README.md](README.md)
2. [HOW_TO_USE.md](HOW_TO_USE.md)
3. [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md)
4. [ADMIN_CHEAT_SHEET.md](ADMIN_CHEAT_SHEET.md)
5. [RELEASE_NOTES.md](RELEASE_NOTES.md)
6. [readme.txt](readme.txt)

## Table Of Contents
1. [Made For](#made-for): project client and site.
2. [Maintainer](#maintainer): plugin owner details.
3. [Support](#support): help contact.
4. [What This Plugin Does](#what-this-plugin-does): plain language overview.
5. [Quick Start Checklist](#quick-start-checklist): fast launch steps.
6. [New Additions Included](#new-additions-included): latest improvements.
7. [Shortcodes](#shortcodes): embed codes.
8. [Step By Step Setup Guide](#step-by-step-setup-guide): full setup flow.
9. [First Test Booking](#first-test-booking): first end to end test.
10. [Screenshot Guide](#screenshot-guide): capture plan for handoff.
11. [How Client Booking Works](#how-client-booking-works): user journey.
12. [Admin Pages You Can Use](#admin-pages-you-can-use): admin tools map.
13. [Activity Logs Explained Simply](#activity-logs-explained-simply): logging basics.
14. [Important Program Rule](#important-program-rule): session cap rule.
15. [Troubleshooting](#troubleshooting): quick fixes.

## Made For
Vibe Connection Lounge  
https://vibeconnectionlounge.com

## Maintainer
Mik Neri  
https://mikneri.dev

## Support
naturalmysticfrequencies@gmail.com

## What This Plugin Does
This plugin helps you sell and manage a 6 session mentorship program.

1. You create booking slots in WordPress admin.
2. Clients see nice booking cards on your page.
3. Client clicks a card, sees details in a popup, then pays with Stripe.
4. Booking is saved, emails are sent, and calendar download is available.
5. Admin can track everything with entries, exports, and activity logs.

## Quick Start Checklist
Use this super fast checklist first.

1. Install and activate plugin.
2. Open Settings then Stripe Booking.
3. Add Stripe Publishable Key, Secret Key, and Webhook Secret.
4. Create at least one booking slot.
5. Add `[stripe_booking_calendar]` to your booking page.
6. Test one booking from frontend.
7. Check Booking Entries and Activity Logs in admin.

## New Additions Included
1. Frontend card layout grouped by month.
2. Mobile first design with tablet and desktop responsiveness.
3. Month filter dropdown on frontend.
4. Numbered pagination with previous and next controls.
5. Load more schedules button.
6. Booking detail popup before payment.
7. Editable popup policy text in settings.
8. Popup policy preview in settings with real upcoming slot data.
9. Settings save toast with time and timezone plus dismiss button.
10. Activity Logs admin page.
11. Daily log summary cards:
Total logs today  
Info today  
Warning today  
Error today
12. Automatic log cleanup after 90 days.
13. Admin desktop card columns setting with 2 or 4 options.
14. Calendar day headers show day total and earliest time badge.
15. Timezone aware iCal with VTIMEZONE blocks.
16. Client portal shortcode.
17. Reminder emails.

## Shortcodes
1. `[stripe_booking_calendar]`  
Shows booking cards and Stripe checkout flow.

2. `[scbc_client_portal]`  
Lets client enter email and see used sessions and remaining sessions.

## Step By Step Setup Guide
Think of this like building with blocks.

### Step 1 Install The Plugin
1. Go to WordPress admin.
2. Go to Plugins.
3. Upload the plugin zip.
4. Click Activate.

### Step 2 Open Settings
1. Go to Settings.
2. Click Stripe Booking.

### Step 3 Add Stripe Keys
1. Paste Stripe Publishable Key.
2. Paste Stripe Secret Key.
3. Paste Stripe Webhook Secret.
4. Set currency like `usd`.
5. Set admin notification email.

### Step 4 Set Program Details
1. Set Default Event Duration Minutes.
2. Set Admin Desktop Card Columns to 2 or 4.
3. Optional set pricing tier thresholds.
4. Optional set brand name and brand color for emails.

### Step 5 Set Reminder Message
1. Edit reminder subject.
2. Edit reminder body.
3. You can use tokens:
`{session_title}`  
`{schedule}`  
`{timezone}`  
`{gmt_offset}`  
`{ics_url}`  
`{site_name}`

### Step 6 Set Popup Policy Text
1. In settings find Frontend Modal Copy.
2. Fill Session Expectations Copy.
3. Fill Cancellation Policy Copy.
4. Look at Modal Policy Preview below settings form.

### Step 7 Add Stripe Webhook
1. In Stripe create a webhook endpoint.
2. Copy webhook URL from plugin settings.
3. Paste in Stripe endpoint URL.
4. Listen for checkout session completed events.
5. Save webhook.
6. Copy webhook signing secret from Stripe.
7. Paste it into plugin settings Webhook Secret.

### Step 8 Create Booking Slots
1. Go to Booking Slots.
2. Click Add New.
3. Add title.
4. Pick Start Date.
5. Pick Start Time from scroll list.
6. Set Price.
7. Set Timezone.
8. Set Capacity.
9. Set Duration.
10. Publish.

### Step 9 Put Shortcode On Page
1. Open your booking page editor.
2. Add shortcode block.
3. Paste `[stripe_booking_calendar]`.
4. Update page.

For client portal page:
1. Open another page editor.
2. Add shortcode block.
3. Paste `[scbc_client_portal]`.
4. Update page.

## First Test Booking
Do this once before going live.

1. Open your booking page.
2. Type a test email you control.
3. Click one booking card.
4. Check that popup shows:
Title  
Date and time  
Price  
Policy text
5. Click Continue to Payment.
6. Complete payment in Stripe test mode.
7. Return to your site and confirm success message appears.
8. Click Download iCal and confirm file downloads.
9. Go to `WP Admin > Booking Slots > Booking Entries` and confirm new row exists.
10. Go to `WP Admin > Booking Slots > Activity Logs` and confirm events were recorded.
11. Confirm customer email and admin email both received notifications.

## Screenshot Guide
This section tells you exactly what to screenshot and where to click.

1. Plugin active screen  
Path: `WP Admin > Plugins > Installed Plugins`  
Capture: Plugin row showing Stripe Calendar Booking Cards is Active.

2. Stripe settings screen  
Path: `WP Admin > Settings > Stripe Booking`  
Capture: Stripe keys fields and webhook URL.

3. Frontend modal copy settings  
Path: `WP Admin > Settings > Stripe Booking`  
Capture: Frontend Modal Copy section fields.

4. Modal policy preview card  
Path: `WP Admin > Settings > Stripe Booking`  
Capture: Mobile style preview card with sample real upcoming slot.

5. Booking slot creation form  
Path: `WP Admin > Booking Slots > Add New`  
Capture: Date, time scroll list, price, timezone, capacity, duration.

6. Frontend booking cards  
Path: `Booking page with [stripe_booking_calendar]`  
Capture: Month filter, card grid, pagination, and load more.

7. Booking details modal  
Path: `Frontend booking page`  
Capture: Modal with session details and policy text.

8. Stripe checkout handoff  
Path: `Frontend booking page after clicking Continue to Payment`  
Capture: Stripe checkout page loaded.

9. Booking success with iCal  
Path: `Frontend return URL after paid booking`  
Capture: Success message with timezone and iCal link.

10. Booking entries admin  
Path: `WP Admin > Booking Slots > Booking Entries`  
Capture: entries table with filters.

11. Activity logs admin  
Path: `WP Admin > Booking Slots > Activity Logs`  
Capture: Today counters and logs table.

12. Admin calendar card headers  
Path: `WP Admin > Booking Slots > Calendar View`  
Capture: Day total amount and Earliest badge in each day card header.

13. Export page  
Path: `WP Admin > Booking Slots > Export Bookings`  
Capture: Export buttons.

## How Client Booking Works
1. Client opens booking page.
2. Client enters email.
3. Client can filter by month.
4. Client clicks a booking card button.
5. Popup shows booking details and policy.
6. Client clicks Continue to Payment.
7. Stripe checkout opens.
8. After payment success client sees confirmation and iCal download link.

## Admin Pages You Can Use
1. Booking Calendar  
View slot density and monthly booking status.

2. Booking Entries  
See paid bookings with filters and pagination.

3. Export Bookings  
Download booking csv files.

4. Activity Logs  
See system events and actions.  
Filter by level and search text.  
Clear logs when needed.

## Activity Logs Explained Simply
Think of logs as a notebook.

1. Every important action writes a note in the notebook.
2. Notes include time, type, message, and context.
3. Old notes older than 90 days are cleaned automatically.

Logged examples:
1. Slot saved.
2. Settings saved.
3. Checkout request started.
4. Stripe response success or fail.
5. Webhook received and processed.
6. Booking finalized.
7. Emails sent or failed.
8. Reminder sent.
9. Export downloaded.
10. iCal downloaded.

## Important Program Rule
Each client email can book up to 6 sessions total.

## Troubleshooting
1. Checkout not starting  
Check Stripe keys and webhook secret.

2. Booking not marked paid  
Check webhook in Stripe and webhook logs.

3. Client cannot book  
Check if email already used 6 sessions.

4. Reminder not sending  
Check WP cron and email delivery service.

5. Missing logs  
Open Activity Logs page and clear filters.
