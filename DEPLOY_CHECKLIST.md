# Deploy Checklist

Use this checklist every time before pushing live.

## Pre Deploy
1. Confirm plugin version in `wp-stripe-calendar-booking.php`.
2. Confirm Stripe keys are set in live settings.
3. Confirm webhook URL exists in Stripe and points to:
`/wp-json/scbc/v1/stripe-webhook`
4. Confirm webhook secret in plugin settings matches Stripe.
5. Confirm at least one future slot exists.

## Functional Smoke Test
1. Open booking page with `[stripe_booking_calendar]`.
2. Confirm cards render and month filter works.
3. Confirm pagination works.
4. Click a card and confirm modal opens.
5. Confirm modal policy text is correct.
6. Continue to Stripe checkout and complete one test payment.
7. Confirm success notice appears.
8. Confirm iCal download works.

## Admin Verification
1. Check `Booking Slots > Booking Entries` for new row.
2. Check `Booking Slots > Activity Logs` for event records.
3. Confirm daily counters update:
Total Logs Today  
Today Info  
Today Warning  
Today Error
4. Confirm reminder cron is active and no fatal errors in logs.

## Email Verification
1. Confirm admin notification email received.
2. Confirm customer confirmation email received.
3. Confirm reminder template text still correct.

## Final Release Steps
1. Rebuild plugin zip.
2. Commit changes to git.
3. Push to GitHub `main`.
4. Upload zip to WordPress site.
5. Run one final live booking check.

