=== Stripe Calendar Booking Cards ===
Contributors: mikneri
Tags: booking, stripe, calendar, mentorship, appointments
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

6 Week Mentorship booking plugin with Stripe checkout, calendar slots, reminders, client portal, and iCal downloads.

== Description ==

Stripe Calendar Booking Cards is tailored for a 6 Week Mentorship flow.

Features:
- Monthly booking calendar shortcode
- Stripe checkout with webhook verification
- 6 session cap per client email
- Client portal shortcode with session usage and iCal links
- Booking entries admin page with filters and pagination
- CSV exports for summary and row level entries
- 24 hour reminder emails with editable templates
- Timezone aware iCal with VTIMEZONE blocks

Website:
https://vibeconnectionlounge.com

Support:
naturalmysticfrequencies@gmail.com

== Installation ==

1. Upload plugin folder to `/wp-content/plugins/`.
2. Activate plugin.
3. Open Settings then Stripe Booking.
4. Add Stripe keys and webhook secret.
5. Create booking slots under Booking Slots.
6. Add `[stripe_booking_calendar]` to booking page.
7. Optional add `[scbc_client_portal]` to a portal page.

== Frequently Asked Questions ==

= Is this plugin for a fixed program length? =
Yes. It enforces a 6 session cap per client email.

= Does it support iCal for Apple and Google calendars? =
Yes. It generates timezone aware iCal with VTIMEZONE blocks.

= Can reminders be customized? =
Yes. Subject and body are configurable in settings and support tokens.

== Changelog ==

= 1.3.0 =
- Added 6 session enforcement per email
- Added client portal shortcode
- Added reminder templates in settings
- Added settings dashboard metrics cards
- Added filtered entries export support
- Added timezone aware iCal DTSTART and DTEND with VTIMEZONE

= 1.2.0 =
- Added booking entries page
- Added iCal download
- Added date filters and presets

= 1.1.0 =
- Added monthly calendar view
- Added webhook verification
- Added branded email notifications

= 1.0.0 =
- Initial release
