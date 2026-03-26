# AGENTS.md

## Mission
This file is the full handoff for the WordPress plugin `wp-stripe-calendar-booking`.

Primary objective was to build a production ready Stripe booking plugin for Vibe Connection Lounge with a simple admin flow and a simple client flow.

## Maintainer Rule
1. Every newly added feature must be documented in project docs before finishing a change.
2. Minimum required doc updates per feature:
   1. `HOW_TO_USE.md` for user facing behavior.
   2. `RELEASE_NOTES.md` for version change log.

Site context used:
1. `https://vibeconnectionlounge.com/`
2. Booking page used for frontend tuning: `https://vibeconnectionlounge.com/book-your-1-on-1-mentorship-session/`

Author metadata requested:
1. Author: Mik Neri
2. Author URI: `https://mikneri.dev`

## Current Plugin Identity
1. Plugin name: Stripe Calendar Booking Cards
2. Current version: `1.8.12`
3. Main file: `wp-stripe-calendar-booking.php`
4. Frontend assets:
   1. `assets/js/scbc.js`
   2. `assets/css/scbc.css`
5. Includes:
   1. `includes/trait-scbc-customer-ref.php`
   2. `includes/trait-scbc-reconciliation.php`

## Core Product Direction Finalized
1. Only the 6 week program is supported.
2. Frontend no longer renders empty month grids.
3. Frontend uses grouped monthly card lists for days with slots.
4. Booking flow is modal first.
5. Stripe webhook dependency was removed.
6. Success handling uses secure return parameters and local verification paths.

## What Was Built

### Admin side
1. Custom post type for booking slots.
2. Slot editor with:
   1. Date picker
   2. Scrollable time picker
   3. Price
   4. Timezone
   5. Capacity
   6. Duration
3. Admin calendar view with card style day blocks and only days that have sessions.
4. Admin density and column controls with 2 or 4 desktop columns.
5. Booking entries page with search, date filtering, presets, and CSV export.
6. Reconciliations page with summary cards, filter tools, export, and manual run.
7. Activity logs page with retention and counters.
8. Settings link and documentation link in plugin list row.
9. Settings submenu under booking slot menu.
10. Dashboard onboarding widget and onboarding cards.
11. Extensive help tips and click to expand examples, written for non technical users.

### Client side
1. `[stripe_booking_calendar]` shortcode redesigned to card list format grouped by month.
2. Slots shown only when available and only days that contain sessions.
3. Modal first booking details flow.
4. Email field moved into the booking modal.
5. Continue to Payment and Retry Payment support with inline error messages.
6. Pagination plus Load More controls.
7. Skeleton loading, fade transitions, sticky month filter.
8. Mobile first responsive layout then tablet then desktop behavior.
9. Client portal shortcode for session tracking.
10. iCal download support with timezone aware DTSTART and DTEND plus VTIMEZONE blocks.

### Stripe and payments
1. AJAX checkout session creation flow.
2. Stripe mode system added:
   1. LIVE mode
   2. TEST mode
3. Separate key pairs for each mode:
   1. Live publishable and secret
   2. Test publishable and secret
4. Settings page behavior:
   1. Hide live key rows when TEST is selected
   2. Hide test key rows when LIVE is selected
   3. Add inline LIVE key or TEST key badges on matching rows
5. Stripe connectivity test action in settings.
6. Checkout troubleshooting copy in modal.
7. Retry click logging for failed Stripe starts.

### Reconciliation and reliability
1. Webhook flow removed intentionally.
2. Return URL success path implemented.
3. Secure customer reference token flow added.
4. Reconciliation cron added for Stripe verification.
5. Reconciliation schedule moved to every 15 minutes.
6. Reconciliation status surfaced in settings and dashboard metric.

### Logging and retention
1. Broad event logging added for admin visibility.
2. Log severity and daily counters added.
3. Log retention purge after 90 days enabled.
4. Logs include booking and retry and reconciliation related actions.

## UX decisions captured from client feedback
1. Removed old monthly table style calendar rendering for frontend.
2. Kept card dimensions more even when content varies.
3. Moved email input into modal to reduce page clutter.
4. Improved CTA size and spacing for mobile touch targets.
5. Added child friendly helper text in settings.

## Documentation produced in repo
1. `HOW_TO_USE.md`
2. `README.md`
3. `readme.txt`
4. `RELEASE_NOTES.md`
5. `DEPLOY_CHECKLIST.md`
6. `ADMIN_CHEAT_SHEET.md`

Docs include:
1. Step by step setup
2. Troubleshooting for Stripe redirect issues
3. Testing checklists
4. Release notes trail

## Key Recent Git History
1. `f5976ea` Move client email input into booking modal
2. `578d77e` Add LIVE or TEST badges beside Stripe key fields
3. `c165481` Hide Stripe key fields by selected mode
4. `ed0f3fd` Add Stripe live or test mode switch with separate key pairs
5. `4bb2355` Remove webhook flow and redirect success with customer params
6. `e64471b` Add secure customer ref tokens and Stripe reconciliation cron
7. `4ff8334` Run reconciliation every 15 minutes and add reconciliations admin page
8. `161c5e5` Add full activity logging admin logs page and real slot preview
9. `3a14925` Add modal first booking flow with month filter and paginated slots
10. `4fdb590` Frontend shortcode card list by month and scrollable time picker

## Known behavior and important notes
1. Stripe checkout can fail with generic Stripe page error if keys do not match selected mode or if Stripe side session validation fails.
2. Webhook secret is not required in current architecture because webhook flow was removed.
3. Reconciliation is the source of truth for delayed state checks after redirect.
4. Hidden key rows still save previously stored values in options, they are only hidden in UI based on mode.

## Suggested next upgrades
1. Add optional no slots now helper with notify me flow.
2. Add reschedule request in client portal.
3. Add conflict warning engine in slot editor.
4. Add duplicate slot action for admin speed.
5. Add reminder preset templates with click apply.
6. Add conversion funnel dashboard, visits to checkout start to paid.

## Fast verification checklist
1. Verify settings mode toggle hides the opposite key rows.
2. Verify Stripe connection test passes in selected mode.
3. Verify frontend cards load and modal opens.
4. Verify modal email required validation works.
5. Verify Continue to Payment creates Stripe session.
6. Verify return flow records booking entry.
7. Verify reconciliations run and are logged.
8. Verify activity logs purge entries older than 90 days.

## Handoff prompt for next agent
Continue from plugin version `1.8.12` in `wp-stripe-calendar-booking`. Keep the 6 week only flow, modal first client booking UI, stripe live or test mode behavior, 15 minute reconciliation, and 90 day log retention. Do not reintroduce webhook dependency. Prioritize non technical UX clarity and preserve current admin onboarding and help tips.
