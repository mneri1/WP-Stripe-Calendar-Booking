# Release Notes

## Documentation Navigation
1. [README.md](README.md)
2. [HOW_TO_USE.md](HOW_TO_USE.md)
3. [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md)
4. [ADMIN_CHEAT_SHEET.md](ADMIN_CHEAT_SHEET.md)
5. [RELEASE_NOTES.md](RELEASE_NOTES.md)
6. [readme.txt](readme.txt)

## Table Of Contents
1. [1.8.27](#1827): refresh success checkmark feedback.
2. [1.8.26](#1826): spinner state on confirmed panel refresh button.
3. [1.8.25](#1825): refresh timestamp and right side slot refresh integration.
4. [1.8.24](#1824): auto hide confirmed panel and manual refresh button.
5. [1.8.23](#1823): docs sync and maintainer documentation rule.
6. [1.8.22](#1822): today dividers with auto collapse and 2 minute refresh.
7. [1.8.21](#1821): auto refresh relative timing and today sorting priority.
8. [1.8.20](#1820): brand colored today badge and starts in helper.
9. [1.8.19](#1819): timezone aware today badge.
10. [1.8.18](#1818): status badges, booked on line, copy details button.
11. [1.8.17](#1817): confirmed panel sorting and portal action links.
12. [1.8.16](#1816): confirmed bookings sidebar cards.
13. [1.7.4](#174): earliest badge and docs sync.
14. [1.7.3](#173): cards only days and admin columns.
15. [1.7.1](#171): daily counters and log retention.
16. [1.7.0](#170): full activity logging release.
17. [1.6.1](#161): settings preview polish.
18. [1.6.0](#160): preview and fade refinements.
19. [1.5.0](#150): skeleton load and editable modal copy.
20. [1.4.0](#140): frontend cards and time picker redesign.
21. [1.3.0](#130): six session workflow expansion.
22. [1.2.0](#120): entries page and iCal.
23. [1.1.0](#110): calendar and webhook baseline.
24. [1.0.0](#100): initial release.

## 1.8.27
1. Added refresh success state with tiny checkmark text `Refreshed ✓`.
2. Success feedback appears briefly after refresh then resets to `Refresh Now`.

## 1.8.26
1. Added loading spinner state to `Refresh Now` button in confirmed panel.
2. Spinner shows while refresh is running and clears when complete.

## 1.8.25
1. Added `Last refreshed` timestamp text in confirmed panel.
2. `Refresh Now` now refreshes confirmed panel state and right side available schedules together.

## 1.8.24
1. Added auto hide of entire confirmed bookings panel when there is no customer context and no confirmed bookings.
2. Added `Refresh Now` button in confirmed panel for instant manual refresh.
3. Kept live state refresh behavior for today badges and status updates.

## 1.8.23
1. Updated documentation with confirmed panel workflow details.
2. Added maintainer rule in `AGENTS.md` requiring docs update for every added feature.

## 1.8.22
1. Added `Today Upcoming` and `Today Completed` divider titles in confirmed bookings.
2. Added live section counts for both today groups.
3. Added auto collapse for empty today groups.
4. Added 2 minute auto refresh for today badge visibility and status text.

## 1.8.21
1. Added auto refresh for relative `Starts in X` text every minute.
2. Prioritized today cards and placed today completed below today upcoming.

## 1.8.20
1. Updated today badge to use plugin brand color.
2. Added today helper text with start countdown and started ago states.
3. Pinned today sessions first in confirmed booking sorting.

## 1.8.19
1. Added timezone aware `Today` badge on confirmed cards.

## 1.8.18
1. Added upcoming and completed visual states on confirmed cards.
2. Added `Booked On` line for each confirmed card.
3. Added `Copy Meeting Details` button with clipboard support.

## 1.8.17
1. Sorted confirmed cards by upcoming schedule order.
2. Added sessions used and sessions left summary cards.
3. Added `Open Client Portal` button in each confirmed card.

## 1.8.16
1. Added left column `Confirmed Bookings` panel on booking page.
2. Added confirmed booking cards with date time timezone amount and iCal link.
3. Added responsive two column layout on desktop and stacked mobile layout.

## 1.7.4
1. Added earliest time badge in each day card header.
2. Added day total amount in each day card header.
3. Updated docs to include admin desktop columns and day header behavior.

## 1.7.3
1. Switched to card only calendar day output.
2. Hidden days with no sessions.
3. Added admin desktop card columns setting with 2 or 4 options.

## 1.7.1
1. Added Total Logs Today counter.
2. Added Today Info counter.
3. Added Today Warning counter.
4. Added Today Error counter.
5. Added automatic log cleanup after 90 days.
6. Updated how to guide with beginner step by step flow.

## 1.7.0
1. Added full activity logging system.
2. Added Activity Logs admin page with filters and search.
3. Added logging for checkout flow, webhook flow, bookings, reminders, exports, and iCal downloads.
4. Added settings save toast with timestamp and timezone.
5. Added modal preview using next real upcoming slot.

## 1.6.1
1. Added settings modal preview card matching mobile style.
2. Added dismiss button on settings toast.

## 1.6.0
1. Added modal policy preview block in settings.
2. Added subtle frontend fade animation for card reload.

## 1.5.0
1. Added skeleton loading while slots load.
2. Added automatic first available month selection.
3. Added editable frontend modal copy fields.

## 1.4.0
1. Replaced frontend calendar grid with card list grouped by month.
2. Added scrollable time picker for slot creation.
3. Added responsive 4 column desktop card layout.

## 1.3.0
1. Added 6 session cap enforcement.
2. Added client portal shortcode.
3. Added reminder templates and dashboard cards.
4. Added timezone aware iCal output.

## 1.2.0
1. Added booking entries page.
2. Added iCal download support.
3. Added date filters and presets.

## 1.1.0
1. Added monthly admin calendar view.
2. Added webhook verification.
3. Added branded email notifications.

## 1.0.0
1. Initial release.
