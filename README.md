# Stripe Calendar Booking Cards

WordPress plugin for the Vibe Connection Lounge 6 session mentorship booking flow with Stripe checkout, reminders, iCal, and full activity logs.

## Documentation Navigation
- [README.md](README.md)
- [HOW_TO_USE.md](HOW_TO_USE.md)
- [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md)
- [ADMIN_CHEAT_SHEET.md](ADMIN_CHEAT_SHEET.md)
- [RELEASE_NOTES.md](RELEASE_NOTES.md)
- [readme.txt](readme.txt)

## Maintainer
- Mik Neri
- https://mikneri.dev

## Website
- https://vibeconnectionlounge.com

## Support
- naturalmysticfrequencies@gmail.com

## Core Features
- 6 session cap per client email
- Frontend month grouped booking cards
- Month filter plus numbered pagination plus load more
- Booking details modal before payment
- Stripe Checkout with webhook verification
- Booking entries table with filters and CSV export
- Client portal shortcode `[scbc_client_portal]`
- Timezone aware iCal with VTIMEZONE blocks
- Reminder email automation
- Activity Logs admin page with:
  - Total Logs Today
  - Today Info
  - Today Warning
  - Today Error
  - Search, level filter, pagination
  - Clear logs action
  - 90 day retention purge

## Quick Start
1. Upload `wp-stripe-calendar-booking` to `wp-content/plugins`.
2. Activate plugin.
3. Go to `Settings > Stripe Booking`.
4. Add Stripe Publishable Key, Secret Key, and Webhook Secret.
5. Create booking slots in `Booking Slots`.
6. Add `[stripe_booking_calendar]` to booking page.
7. Run one test booking in Stripe test mode.

## Shortcodes
- `[stripe_booking_calendar]` frontend booking flow
- `[scbc_client_portal]` client session tracker

## Admin Menu Paths
- `Booking Slots > Calendar View`
- `Booking Slots > Booking Entries`
- `Booking Slots > Export Bookings`
- `Booking Slots > Activity Logs`
- `Settings > Stripe Booking`

## Documentation
- Full step by step guide: [HOW_TO_USE.md](HOW_TO_USE.md)
- WordPress.org style readme: [readme.txt](readme.txt)
- Deploy checklist: [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md)
- Admin quick actions: [ADMIN_CHEAT_SHEET.md](ADMIN_CHEAT_SHEET.md)
- Version history summary: [RELEASE_NOTES.md](RELEASE_NOTES.md)
