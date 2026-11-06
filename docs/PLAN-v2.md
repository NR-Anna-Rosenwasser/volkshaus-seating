# Plan for v2: many events, many venues

Branch: `feature/make-app-generalizable`. Work milestone by milestone, commit after each, and stop at every ⛔ checkpoint.

## Where things stand

| Milestone | State |
|---|---|
| M9 Events as the foundation | ✅ Done: `944d74c`, `649cfc6` |
| M10 Import from Hi.Events, pull-only sync | **Next** |
| M11 Saalpläne in the database | Open |
| M12 Drag-and-drop builder | Open |
| M13 Email editor | Open |
| M14 Hardening and release | Open |

All 341 tests pass at `649cfc6`. The branch is not pushed yet.

**To pick up:** start M10 with its first step, capturing real Hi.Events responses. The local database only holds test events, so "Neues Event" is the way to make more.

## Goal
An admin imports any Hi.Events event, builds or reuses a Saalplan by drag and drop, edits the emails, and runs several events side by side.

## Decisions
- **Nothing is live yet.** No production data migration. «Hässig am Mittwoch» is set up fresh in the new version. The migration `scope_data_to_events` refuses a database that has orders.
- **Orders come in by pull only,** every 2 minutes. Webhooks are removed in M10.
- **We build our own builder.** Hi.Events shipped seating in October 2026 as a paid Enterprise add-on, usable only inside Hi.Events. Its `ee/` code is neither copied nor used as a reference.
- **First version of the builder: rows and blocks,** no tables.
- **Events are ordinary records, not Filament tenants.** The event stays in every URL (`/admin/events/{event}/...`), so two tabs can't act on the wrong one. The tenant slot stays free for a future Organizer model. Saalpläne are global, not below an event.
- **Organizer is not built yet.** M10 only stores the Hi.Events `organizer_id` on each event.

## Assumptions (say so if wrong)
- One Hi.Events instance and one API key for all events.
- Every admin can see every event.
- Recurring events (occurrences) stay out of scope.

## Rules for the code
- Services take an explicit `Event`. Seats are always reached through `$event->seats()`, and a test bans `Seat::` queries elsewhere (only `SeatImporter` is exempt).
- Admin pages scope to their event themselves. Filament no longer does it, so every new page needs an isolation test in `tests/Feature/MultiEventTest.php`.
- `seats.id` is numeric and `ORDER BY id FOR UPDATE` stays the lock order. `seats.code` is what the frontend and APIs use. Never parse a code: section, row, segment and number come from data attributes.
- `$seats->only()` on an Eloquent collection filters by primary key. Call `->toBase()` after `keyBy('code')`.
- Nested order URLs go through `OrderResource::viewUrl()`. Filament's guess only works on pages below that event.
- Commit only behind passing exit codes. Two test runs at once corrupt each other, because they share the `testing` database.

## M9: Events as the foundation ✅
- `events` table, seats and orders belong to an event, and event settings moved from `.env` to the event.
- `EventResource` with tabs: Übersicht, Saalplan, Bestellungen, Infodesk, Exporte, Einstellungen. `/admin` is an overview across events.
- Public plan at `/e/{slug}`, `/` lists upcoming events, `/s/{token}` unchanged.
- Commands run per event, and one failure doesn't stop the others. `--now` needs `--event`.
- Purge covers one event and leaves the queue alone. Queued mail for a purged event is dropped when it comes up. Failed jobs and guest sessions go after 7 days.

**Temporary until later milestones:**
- New events take a hand-typed Hi.Events id (M10), and always get the Theatersaal (M11).
- `events.layout` still has the old JSON shape, read by `$event->venueLayout()` (M11).
- Webhooks still work (M10).
- README and runbook describe the old setup (M14).

## M10: Import from Hi.Events, pull-only sync
- First capture real responses from the local Hi.Events for `GET /events`, `/events/{id}`, `/events/{id}/products` and `/events/{id}/questions` with a read-only key, and add them to `docs/hievents-api.md`. The source confirms these routes exist. Read-only access is still to be proven.
- `HieventsClient`: `events()`, `event()`, `products()`, `questions()`.
- «Event importieren» replaces the form at `/admin/events/neu`: pick from the list, get name, date and `organizer_id`, tick seated products and the wheelchair question by their real names. «Neu laden» refreshes them.
- Readiness panel on the event page: plan, products, window, capacity against sellable seats. Links only go out once it's complete.
- `seating:pull` every 2 minutes per running event, `withoutOverlapping`, reusing `OrderSync` and the walk in `Reconcile`.
- Delete `HieventsWebhookController`, `ProcessWebhookEvent`, `WebhookEvent`, the webhook route and throttle, the `webhook_events` table and the webhook setup in the README. Keep `/health`. "Letzter Webhook" leaves `SystemStatus`.
- Limits to design for:
  - Lists have no `question_answers`, so each new order needs one GET. A first import of 1,000 orders would break Hi.Events' default of 180 requests per minute, so cap detail GETs per run, carry the rest over, and honour 429.
  - Orders have no `updated_at` filter, so every run walks the full list. That's about 10 requests per 1,000 orders.
  - One bad order must not abort the run (today it does).
  - Local orders missing from the remote list: alert, never auto-cancel.
  - A changed wheelchair answer on a known order isn't in the list. Re-check at a slower rate or rely on the admin flag; decide after the capture.
- Alerts: only when pulling fails repeatedly or data is stale (e.g. 15 minutes), deduplicated. Normal changes are no longer "drift". `EventStatus` shows the last pull.
- Mail budget: replace the per-batch `delayFor` with a shared Redis next-slot, so several events can't add up to more than `MAIL_PER_SECOND`.

**Verify** against local Hi.Events: import an event, buy, cancel one attendee, cancel an order, reactivate, tick wheelchair; each appears within one pull. Stop Hi.Events and confirm the alert and recovery. Import 1,000 orders without a 429.

⛔ Checkpoint

## M11: Saalpläne in the database
- `seat_plans` table, reusable templates: `name`, `venue_name`, `layout` jsonb. `events.seat_plan_id` records the origin. `events.layout` stays a snapshot, so editing a template never changes a running event.
- `SeatPlanResource` at `/admin/saalplaene`.
- New layout shape: `elements` (stage, pillars, labels), `sections` with `rank`, `prefix`, `row_axis`, rows with an optional display label (1 or A), seats with `number`, `segment`, `x`, `y`, `flags`, plus optional block parameters (rows, seats, spacing, rotation, curve).
- `SeatPlanValidator` in PHP is the authority: no empty sections, unique prefixes, consecutive numbers within a segment, no hyphen in a prefix.
- `seating:import-plan` turns the Theatersaal JSON into a template. Its sections become free blocks: movable and seat-editable, but without the rows×seats resize.
- `SeatImporter::apply(Event, plan)`: snapshot and create seats. Re-applying shows a diff first, and is refused if it would remove or renumber an assigned or admin-blocked seat.
- `BestSeats`: `verticalSections()` becomes `row_axis`, row labels in `seatLabel` and exports, and the near-seat formula stops assuming numbers under 100. The ranking tuned to the Theatersaal stays and must pass the existing tests.
- `seating.seat_data_path` goes once events pick a plan.

**Verify:** the imported template matches the old JSON seat for seat (1,139 seats, 1,115 sellable), and `BestSeats` results are unchanged.

⛔ Checkpoint

## M12: Drag-and-drop builder
A Filament page below Saalpläne, built with Alpine and SVG, extending the pointer handling from `resources/js/admin-map.js` (`svgPoint`) and the zoom and pan from `seat-map.js`. Desktop only, with a clear notice on phones.

- **Palette:** drag Sitzblock, Bühne, Säule and Text onto the canvas. A dropped block asks for Reihen × Plätze with a live preview.
- **Direct manipulation:** drag to move, drag a handle to add rows or seats, rotate handle, snap to grid and alignment guides, mirror a block for left/right symmetry, duplicate.
- **Side panel:** name, prefix, spacing, curve, numbering direction, row labels 1 or A, row 1 side, priority for auto-assign (drag order of blocks).
- **Seat mode:** click or lasso, then Sichtbehinderung, Rollstuhl, gesperrt or remove. Removing makes a gap and a new `segment`, so adjacency stays correct.
- **Safety:** undo and redo, keyboard shortcuts, live seat count and errors, save conflict check, «Als Käufer ansehen» using the real `seat-map` component.
- **Tracing:** upload a PDF or image as a background with position, scale and opacity. PDFs render in the browser with pdf.js (new dependency). Never shown to buyers.
- Pure geometry in `resources/js/plan-geometry.js`, tested with Vitest (new dev dependency).

**Verify:** Playwright drives a full build, the plan is applied to an event, a buyer selects seats on it, and auto-assign fills it.

⛔ Checkpoint, with a hands-on test: can you draw a small hall without instructions?

## M13: Email editor
- A tab below the event, one section per mail: Link, Bestätigung (Änderung möglich), Bestätigung (Frist vorbei), Erinnerung, Automatisch zugeteilt, Letzte Info, plus the shared footers (Einlass, persönlicher Link).
- `mail_templates` table: `event_id`, `type`, du and ihr variants of subject, body and button.
- Blank line starts a paragraph. Plain text only, so no HTML injection.
- Placeholders offered as insert buttons: `{vorname}`, `{anzahl}`, `{frist}`, `{event}`, `{datum}`, `{tueroeffnung}`, `{kontakt}`, `{plan_link}`. A line with an empty placeholder is dropped.
- Stay fixed in code: seat list, button target, layout, the choice between the two Bestätigung templates, the contact fallback.
- Current wording becomes the defaults in code. A database row exists only once edited. «Zurücksetzen», live preview, test mail to yourself, «Von anderem Event kopieren».
- Validation: unknown placeholders, «ß» and empty variants are refused. «ihr»-words in a du text only warn.
- `SeatMail::copy()` reads the resolved templates. Fix text-part escaping (likely `&#039;` for apostrophes; confirm first). Edits reach mails still in the queue, and the editor says so.
- Not editable: admin alerts, `WheelchairNotice`, selection page copy.

**Verify:** preview and real mail in Mailpit for each type, du and ihr.

⛔ Checkpoint

## M14: Hardening and release
- Extend `MultiEventTest` to anything added since M9.
- Load test with two events, concurrency test with the same seat codes in both.
- Rewrite `docs/runbook.md`, README and `CLAUDE.md`; `.env.example` loses `HIEVENTS_WEBHOOK_SECRET`.
- Decide whether events can be deleted and how that fits the purge. There's no delete today.
- Production Docker stack as in M8: full-event run with two events, Redis restart, purge of one event leaving the other intact.
- Set up «Hässig am Mittwoch» for real from a test import.
- You create tag `v2.0.0`.

⛔ Checkpoint

## Later, not part of v2
- Organizer as the Filament tenant: `/admin/{organizer}/events/{event}/...`.
- Tables in the builder.
- Admin rights per event.
- Infodesk-only login (GitHub issue #1).

## Still open from v1
- Real order, refund and wheelchair flow on the server, Mailgun, HTTPS through CloudPanel.
- Tapping the Saalplan on a real phone, full 2FA setup.
- The live event needs the order-level wheelchair checkbox.
- The event title «Hässig am Mittwuch» may be a typo.
- Copy backups off the server.
- Volkshaus still has to answer about companion seats.
