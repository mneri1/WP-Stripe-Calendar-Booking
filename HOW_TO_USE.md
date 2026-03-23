# Stripe Calendar Booking Cards

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
13. Timezone aware iCal with VTIMEZONE blocks.
14. Client portal shortcode.
15. Reminder emails.

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
2. Optional set pricing tier thresholds.
3. Optional set brand name and brand color for emails.

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

