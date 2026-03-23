# Vibe Connection Lounge 6 Week Mentorship Plugin Guide

## Plugin Name
Stripe Calendar Booking Cards

## Maintainer
Mik Neri

## Website
https://vibeconnectionlounge.com

## Support
naturalmysticfrequencies@gmail.com

## Purpose
This plugin is built for the 6 Week Mentorship workflow.
Each client email can book up to 6 paid sessions total.

## What It Does
1. Admin creates session slots with date, price, timezone, capacity, and duration.
2. Frontend shows a monthly calendar for available slots.
3. Client enters email, clicks Book 6 Week Session, pays with Stripe.
4. Booking is saved, confirmations are sent, and iCal download is available.
5. Admin can review row level entries, filter, export, and monitor progress.
6. Automated 24 hour reminder emails are sent.

## Shortcodes
- `[stripe_booking_calendar]` booking calendar and checkout flow
- `[scbc_client_portal]` client portal by email with session count and iCal links

## Initial Setup
1. Upload and activate plugin.
2. Open Settings then Stripe Booking.
3. Add Stripe publishable key, secret key, and webhook secret.
4. Set currency and admin notification email.
5. Set Default Event Duration Minutes.
6. Configure Reminder Email Subject and Reminder Email Body if needed.
7. Create slots in Booking Slots.
8. Add shortcode to pages.

## Stripe Webhook
Use this endpoint in Stripe:
`/wp-json/scbc/v1/stripe-webhook`

You can copy the full webhook URL from plugin settings page.

## Reminder Template Tokens
Available tokens:
- `{session_title}`
- `{schedule}`
- `{timezone}`
- `{gmt_offset}`
- `{ics_url}`
- `{site_name}`

## Admin Tools
1. Booking Calendar page
2. Booking Entries page with search, date filters, and presets
3. Export Bookings page
4. Export Entries CSV button from entries page
5. Settings dashboard cards:
- Active Clients
- Completed Clients
- Total Sessions Booked
- Total Remaining Sessions

## Client Portal Flow
1. Client opens portal page with `[scbc_client_portal]`.
2. Client enters booking email.
3. Portal shows used sessions and remaining sessions out of 6.
4. Portal lists iCal links for booked sessions.

## Notes
- Program cap is fixed to 6 sessions by design.
- iCal uses timezone aware DTSTART and DTEND with VTIMEZONE.
- Success notice includes timezone plus GMT offset explanation.

## Troubleshooting
1. If checkout fails, verify Stripe keys and webhook secret.
2. If reminders do not send, check WP Cron and mail delivery.
3. If client cannot book, check whether email already reached 6 sessions.

## Release Notes Summary
- 6 week program enforcement
- client portal
- reminder automation
- timezone aware iCal
- entries filtering and export
