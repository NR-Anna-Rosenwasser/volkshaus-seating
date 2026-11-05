# Runbook

For whoever runs the event. Everything here happens in the admin at `/admin`, unless a command is shown.
Commands run on the server, in the project folder.

## Before selection opens

- [ ] `.env` has the real `EVENT_DATE`, `DOORS_AT`, `SELECTION_OPENS_AT` and `SELECTION_DEADLINE`.
- [ ] `EVENT_DATE` is right. It also decides when personal data is deleted.
- [ ] `CONTACT_EMAIL`, `ADMIN_EMAILS` and `WHEELCHAIR_NOTIFY_EMAILS` are real addresses.
- [ ] Hi.Events has an order-level wheelchair checkbox, and its id is in `WHEELCHAIR_QUESTION_IDS`.
- [ ] The capacity in Hi.Events equals «Verkaufbar» on the Übersicht.
- [ ] A real test order worked: webhook received, link mail arrived, seats saved, confirmation arrived.
- [ ] A test mail looks right in Gmail and Outlook.
- [ ] A refund and a cancellation behave as expected in the real Hi.Events.
- [ ] `/health` shows `ok`, and Uptime Kuma watches it.
- [ ] The first nightly backup is on the Übersicht, and a dump has been copied off the server.
- [ ] `APP_KEY` is saved somewhere safe.
- [ ] Two-factor login is set up under the profile.

Links go out by themselves at `SELECTION_OPENS_AT`, at `MAIL_PER_SECOND` per second.

## While selection is open

**Blocking seats:** Saalplan, tap seats or use «Bereich wählen», pick a reason, «Sperren».
After each change, set the capacity in Hi.Events to the number shown. This app can't stop Hi.Events
from selling, so the Übersicht turns red if tickets exceed seats.

**A buyer lost their link:** Bestellungen, open the order, «Neuer Link». The link is copied, and the
old one stops working, including the ones in their emails.

**A buyer can't find seats:** Bestellungen, open the order, «Plätze im Saalplan zuweisen».

**Wheelchair orders:** the addresses in `WHEELCHAIR_NOTIFY_EMAILS` are told. These orders get no link,
no reminder and no auto-assignment. Assign them by hand. The map opens at the wheelchair seats. No
email goes out automatically, so use «Bestätigung senden» when you want it to. All 8 seats stay
blocked until someone sits there. If Volkshaus allows companion seats, block those too.

## After the deadline

Auto-assign doesn't start by itself. `ADMIN_EMAILS` gets an alert if it hasn't run after 3 hours.

1. Übersicht, «Auto-Zuteilung». The preview changes nothing.
2. Check how many orders are split or got no seat.
3. «Ausführen». Everyone gets their seats by email.
4. Orders without a seat also trigger an alert. Filter Bestellungen by «Fehlende Plätze» and assign by
   hand. Free up seats on the Saalplan if needed.

Late orders are seated as they arrive, and they get an email too. Only an order that doesn't fit
raises an alert.

## Two days before

The final mail goes out by itself, `FINAL_MAIL_OFFSET_DAYS` days before the event.

Check first:

- [ ] Übersicht: «Ohne Platz» shows only wheelchair tickets that are still open, ideally none.
- [ ] Every wheelchair order has its seats.

## On the day

Print these from Exporte a few hours before doors, and again shortly before if sales are still open:

- **Platzliste** for the ushers
- **Namensliste** for the info desk
- **Sperrliste** for whoever handles reserved seats

Also save the CSV versions on a laptop or phone that doesn't depend on the server.

**The info desk** uses Infodesk, on a phone or laptop. Search by name, a guest's name, or order number.
Red text means a ticket has no seat, the order is cancelled, or it is a wheelchair order.

**Someone has no seat:** find a free seat on the Saalplan, then open the order and assign it.

**Entry is unaffected by this app.** Tickets are checked by Hi.Events. A person whose seat can't be
found still gets in.

## If something breaks

| What you see | What to do |
|---|---|
| The app is down on the night | Use the printed lists. Nothing about entry depends on this app. |
| `/health` is `failed` | Look at which check failed, then `docker compose ps` and `docker compose up -d`. |
| `queue: failed` | Mails aren't going out. `docker compose restart queue`. Nothing is lost, they send once it's back. |
| `scheduler: failed` | No links, reminders or backups. `docker compose restart scheduler`. |
| «E-Mails schlagen fehl» alert | Check the mail provider. To see the failed jobs: `docker compose exec app php artisan queue:failed`. To resend them: `docker compose exec app php artisan queue:retry all`. |
| «Abgleich fehlgeschlagen» alert | Hi.Events or the API key has a problem. Webhooks still work. Run «Abgleich» again later. |
| Sales aren't showing up | Check «Letzter Webhook» on the Übersicht, then run «Abgleich mit Hi.Events». |
| Red warning on the Übersicht | More tickets than seats. Lower the capacity in Hi.Events, then free blocked seats if you can. |
| Data is lost or wrong | Restore a backup, see the README. |

## After the event

`seating:purge` anonymizes everything by itself 30 days after `EVENT_DATE`. You get a warning a week
before, and a message when it's done. Delete the buyers' data in Hi.Events separately.

To check what would happen:

```sh
docker compose exec scheduler php artisan seating:purge --dry-run
```
