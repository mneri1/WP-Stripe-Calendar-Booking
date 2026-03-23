# Stripe Calendar Booking Cards

WordPress plugin for the Vibe Connection Lounge 6 Week Mentorship program with Stripe checkout and webhook verification.

## Features

- Admin creates booking slots from Dashboard menu Booking Slots.
- Admin can review schedules in month navigation calendar view inside Booking Slots menu.
- Frontend shows schedules in a month calendar with previous and next controls via shortcode `[stripe_booking_calendar]`.
- Plugin is tailored for the 6 Week Mentorship program only.
- Each slot supports explicit timezone so displayed schedule stays locked to event timezone.
- Each slot supports capacity with automatic spots left tracking.
- Client clicks Book and Pay and is redirected to Stripe Checkout.
- On successful payment the slot is marked booked and both admin and customer get branded emails.
- Stripe webhook endpoint verifies signatures for stronger payment confirmation.
- Admin can export booked schedules to CSV from Booking Slots then Export Bookings.
- Admin can view Booking Entries page which lists every paid Stripe session row.
- Booking Entries page has direct CSV export for paid session rows.
- Booking Entries supports search by customer email or Stripe session id with pagination.
- Booking Entries supports date range filtering with From and To fields.
- Booking Entries has quick presets for Today, This Week, and This Month.
- Booking Entries has search plus pagination.
- Customers can download iCal file after successful booking.
- Customer email is required before checkout and each email is capped at 6 paid sessions.
- Optional client portal shortcode `[scbc_client_portal]` shows used and remaining sessions plus iCal links.
- Automated 24 hour reminder emails are sent for upcoming sessions.
- Reminder subject and body templates are editable in plugin settings with token placeholders.
- Settings dashboard shows active clients, completed clients, total sessions booked, and remaining sessions.
- iCal event end time uses per slot duration and falls back to default duration setting.
- iCal and success notice include timezone with GMT offset for clarity.
- iCal uses timezone aware DTSTART and DTEND with VTIMEZONE blocks for local calendar clients.

## Setup

1. Upload folder `wp-stripe-calendar-booking` to `wp-content/plugins`.
2. Activate plugin in WordPress.
3. Open Settings > Stripe Booking and set publishable key and secret key.
4. In Stripe set webhook endpoint to the URL shown in plugin settings and paste webhook secret.
5. Optional set brand name and brand color for email templates.
6. Set Default Event Duration Minutes in settings.
7. Add booking slots in Booking Slots with date time and price.
8. Set timezone, capacity, and duration minutes for each slot.
9. Place shortcode `[stripe_booking_calendar]` on your booking page.
10. After payment success customer can click Download iCal from the success notice.
11. Optional add `[scbc_client_portal]` to a portal page for client session tracking.

## Support

- Site: `https://vibeconnectionlounge.com`
- Maintainer: `Mik Neri`
- Support Email: `naturalmysticfrequencies@gmail.com`

## Notes

- Currency defaults to `usd`.
- Return path after payment goes back to the same booking page.
- This version uses Stripe Checkout API directly through WordPress HTTP functions.
