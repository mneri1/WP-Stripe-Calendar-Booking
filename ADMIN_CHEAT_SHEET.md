# Admin Cheat Sheet

## Documentation Navigation
1. [README.md](README.md)
2. [HOW_TO_USE.md](HOW_TO_USE.md)
3. [DEPLOY_CHECKLIST.md](DEPLOY_CHECKLIST.md)
4. [ADMIN_CHEAT_SHEET.md](ADMIN_CHEAT_SHEET.md)
5. [RELEASE_NOTES.md](RELEASE_NOTES.md)
6. [readme.txt](readme.txt)

## Table Of Contents
1. [Quick Menu Paths](#quick-menu-paths): fastest admin navigation.
2. [Daily Quick Actions](#daily-quick-actions): daily checks.
3. [When Adding New Slots](#when-adding-new-slots): slot creation routine.
4. [If A Client Says Booking Failed](#if-a-client-says-booking-failed): first response steps.
5. [If Payment Was Made But Not Reflected](#if-payment-was-made-but-not-reflected): payment mismatch flow.
6. [Weekly Maintenance](#weekly-maintenance): recurring upkeep.

## Quick Menu Paths
1. `Settings > Stripe Booking`
2. `Booking Slots > Calendar View`
3. `Booking Slots > Booking Entries`
4. `Booking Slots > Export Bookings`
5. `Booking Slots > Activity Logs`
6. `Booking Slots > Add New`

## Daily Quick Actions
1. Open Activity Logs.
2. Check today counters:
Total Logs Today  
Today Info  
Today Warning  
Today Error
3. Open Booking Entries and check newest paid booking rows.
4. Confirm next upcoming slots are available and not overbooked.

## When Adding New Slots
1. Go to `Booking Slots > Add New`.
2. Fill title.
3. Choose date.
4. Choose time in scroll list.
5. Set price.
6. Set timezone.
7. Set capacity.
8. Set duration.
9. Publish.

## If A Client Says Booking Failed
1. Open Activity Logs and search by email.
2. Check event level and message.
3. Confirm Stripe keys in Settings.
4. Confirm slot still has spots left.
5. Confirm client email has not reached 6 sessions.

## If Payment Was Made But Not Reflected
1. Open Activity Logs and filter `warning` and `error`.
2. Search for `reconcile` and `checkout_return` events.
3. Confirm booking row in Booking Entries.
4. Check `Reconciled Last 24h` card in Settings.
5. If missing, run one test payment again in Stripe test mode and recheck logs.

## Weekly Maintenance
1. Export entries CSV.
2. Review reminders and mail delivery.
3. Confirm logs are rotating and old data is purged.
4. Check docs and changelog for updates before next release.
