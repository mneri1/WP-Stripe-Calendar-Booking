# Stripe Calendar Booking Cards

This plugin helps you sell a 6 week program in a simple way.

## Who Made It
- Mik Neri
- https://mikneri.dev

## Website
- https://vibeconnectionlounge.com

## Support
- naturalmysticfrequencies@gmail.com

## What It Does
1. Admin makes booking slots.
2. Clients see booking cards on the page.
3. Client picks a slot and pays with Stripe.
4. Plugin saves booking and sends emails.
5. Plugin logs actions so admin can track everything.
6. Plugin runs reconciliation every 15 minutes to catch paid sessions that did not return to site.

## Main Features
1. 6 session limit per client email.
2. Mobile first booking cards.
3. Month filter and pagination.
4. Booking details modal.
5. Stripe checkout return verification.
6. Secure success URL params with `customer_ref` and `sched`.
7. Client portal shortcode.
8. Timezone aware iCal with VTIMEZONE.
9. Reminder emails.
10. Activity logs with 90 day purge.
11. Reconciliations page with manual run and CSV export.

## Quick Setup
1. Upload plugin folder to `wp-content/plugins`.
2. Activate plugin.
3. Go to `Settings > Stripe Booking`.
4. Add Stripe Publishable Key and Secret Key.
5. Create at least one slot.
6. Add shortcode `[stripe_booking_calendar]` to booking page.
7. Test one booking.

## Shortcodes
1. `[stripe_booking_calendar]` for booking page.
2. `[scbc_client_portal]` for client portal page.

## Admin Menu
1. `Booking Slots > Calendar View`
2. `Booking Slots > Booking Entries`
3. `Booking Slots > Reconciliations`
4. `Booking Slots > Export Bookings`
5. `Booking Slots > Activity Logs`
6. `Settings > Stripe Booking`

## Documentation
1. [HOW_TO_USE.md](HOW_TO_USE.md)
2. [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md)
3. [ADMIN_CHEAT_SHEET.md](ADMIN_CHEAT_SHEET.md)
4. [RELEASE_NOTES.md](RELEASE_NOTES.md)
5. [readme.txt](readme.txt)
