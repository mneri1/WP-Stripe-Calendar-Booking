=== Stripe Calendar Booking Cards ===
Contributors: mikneri
Tags: booking, stripe, calendar, mentorship, appointments
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.8.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

6 Week Mentorship booking plugin with Stripe checkout, card based slot display, reminders, client portal, iCal downloads, and activity logs.

== Description ==

Stripe Calendar Booking Cards is tailored for a fixed 6 session mentorship flow.

Main features:
- Frontend booking cards grouped by month
- Month filter, numbered pagination, and load more controls
- Booking detail modal before Stripe checkout
- Stripe checkout return verification with reconciliation cron fallback
- Success redirect with secure customer ref token and schedule date
- 6 session cap per client email
- Client portal shortcode with session usage and iCal links
- Booking entries admin page with filters and pagination
- CSV exports for summary and row level entries
- 24 hour reminder emails with editable templates
- Timezone aware iCal with VTIMEZONE blocks
- Activity logs page with severity filters and search
- Daily counters for total info warning and error logs
- Automatic log retention purge after 90 days

Website:
https://vibeconnectionlounge.com

Support:
naturalmysticfrequencies@gmail.com

Documentation files in plugin package:
- README.md
- HOW_TO_USE.md
- DEPLOY_CHECKLIST.md
- ADMIN_CHEAT_SHEET.md
- RELEASE_NOTES.md

== Installation ==

1. Upload plugin folder to `/wp-content/plugins/`.
2. Activate plugin.
3. Open Settings then Stripe Booking.
4. Add Stripe publishable key and secret key.
5. Create booking slots under Booking Slots.
6. Add `[stripe_booking_calendar]` to booking page.
7. Optional add `[scbc_client_portal]` to a portal page.

== Frequently Asked Questions ==

= Is this plugin for a fixed program length? =
Yes. It enforces a 6 session cap per client email.

= Does it support iCal for Apple and Google calendars? =
Yes. It generates timezone aware iCal with VTIMEZONE blocks.

= Can reminder and policy text be customized? =
Yes. Reminder templates and frontend modal policy text are editable in settings.

= Does it include logs for troubleshooting? =
Yes. It has an Activity Logs page with filters, search, and daily counters.

== Changelog ==

= 1.8.0 =
- Removed webhook dependency from booking confirmation flow
- Added hourly reconciliation cron for missed return finalization
- Added secure customer ref token in success redirect params
- Added settings card for reconciled bookings in last 24 hours
- Split token and reconciliation logic into separate include traits

= 1.8.5 =
- Simplified help tips and examples for non technical users
- Added onboarding links and dashboard quick start widget
- Added expandable info tips in slot editor and modal policy

= 1.7.4 =
- Added earliest time badge in day card header
- Added day total amount display in day card header
- Added docs updates for admin desktop columns and day header behavior

= 1.7.3 =
- Switched calendar render path to card only day output
- Hid dates with no sessions in calendar card view
- Added setting to choose admin desktop card columns 2 or 4

= 1.7.1 =
- Added Total Logs Today counter in Activity Logs page
- Added Today Info counter in Activity Logs page
- Added Today Warning counter in Activity Logs page
- Added Today Error counter in Activity Logs page
- Added 90 day automatic log retention purge
- Updated documentation with full beginner step by step guide

= 1.7.0 =
- Added full activity logging system and admin logs page
- Added logging for checkout webhook booking reminders exports and iCal
- Added log filtering search pagination and clear logs action
- Added settings toast with save time and timezone and dismiss button
- Added settings modal policy preview using next real upcoming slot

= 1.6.1 =
- Added mobile style modal preview card in settings
- Added dismiss button to settings save toast

= 1.6.0 =
- Added settings modal policy preview section
- Added subtle fade animation for card renders

= 1.5.0 =
- Added skeleton loading for frontend slot loading
- Added automatic first available month selection
- Added editable modal policy text in settings

= 1.4.0 =
- Replaced frontend month grid with month grouped card list
- Added scrollable time picker in slot creation
- Added responsive card layout with 4 columns on desktop

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
