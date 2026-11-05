# Volkshaus Sitzplatzwahl

Seat selection after purchase for a Hi.Events event at Volkshaus Zürich, Theatersaal.
Hi.Events handles payment, tickets and check-in. This app only decides who sits where.

## Development

Requires [DDEV](https://ddev.com) and Docker.

```sh
ddev start
cp .env.example .env          # first time only; then run `ddev restart` so DDEV fills in the DB values
ddev composer install
ddev php artisan key:generate
ddev npm install && ddev npm run build
ddev php artisan migrate --seed
ddev composer test            # includes one whole event at full size, in tests/Load
```

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` before seeding. The admin panel is at `/admin`.

Seeding needs the seat data at `database/data/volkshaus-theatersaal-seats.json`.

Assets are not committed. After changing CSS or JS, run `ddev npm run build`. Horizon keeps code in
memory, so after changing jobs or mails run `ddev exec php artisan horizon:terminate`.

## Production

Docker Compose runs `app`, `horizon`, `scheduler`, `redis` and `postgres`. The app listens on
`127.0.0.1:${APP_PORT}` for CloudPanel, which terminates TLS and proxies to it.

Images are public on GHCR, at `ghcr.io/nr-anna-rosenwasser/volkshaus-seating`. The server only needs
`docker-compose.yml` and `.env`, not the source code.

### First deploy

```sh
cp .env.example .env          # fill in the values, including APP_VERSION and REDIS_PASSWORD
docker run --rm ghcr.io/nr-anna-rosenwasser/volkshaus-seating:$APP_VERSION php artisan key:generate --show
docker compose pull
docker compose up -d
docker compose exec app php artisan db:seed --force
```

Migrations run when the `app` container starts. Containers read `.env` when they start, so after
changing it run `docker compose up -d`.

Back up `APP_KEY` somewhere safe. Personal links are derived from it and two-factor secrets are
encrypted with it, so changing it breaks every link that has already gone out.

### Releases

Create a release with a tag like `v1.0.0`. [GitHub Actions](.github/workflows/release.yml) runs the
tests and, only if they pass, publishes the image as `1.0.0` and `latest`. Then, on the server:

```sh
# set APP_VERSION=1.0.0 in .env
docker compose pull
docker compose up -d
```

To go back, set the old version and run the same commands. Migrations are not undone by this, so take
a backup before a release that changes the database, and restore it if you have to go back past it.

To build the image yourself instead, run `docker compose build`.

### Connecting Hi.Events

1. In the event, under Webhooks, add `https://<your domain>/webhooks/hievents`.
2. Select all order and attendee events. These eight are the ones that exist:
   `order.created`, `order.updated`, `order.marked_as_paid`, `order.refunded`, `order.cancelled`,
   `attendee.created`, `attendee.updated`, `attendee.cancelled`.
3. Put the signing secret it shows you in `HIEVENTS_WEBHOOK_SECRET`.
4. Create a read-only API key for `HIEVENTS_API_KEY`, and set `HIEVENTS_BASE_URL` and `HIEVENTS_EVENT_ID`.
5. Set `SEATED_PRODUCT_IDS` to every product that needs a seat, and `WHEELCHAIR_QUESTION_IDS` to the
   id of the order-level wheelchair question.

Run `docker compose exec app php artisan seating:reconcile` to check the connection. Field names and
behaviour of the Hi.Events API are in [docs/hievents-api.md](docs/hievents-api.md).

### What runs by itself

| When | Command | What it does |
|---|---|---|
| Every minute | `seating:heartbeat` | Proves to `/health` that scheduler and queue are alive |
| Every minute | `seating:send-links` | Sends links to new orders while selection is open |
| Every 15 minutes | `seating:check-auto-assign` | Alerts if auto-assign hasn't run 3 hours after the deadline |
| Every 30 minutes | `seating:reconcile` | Fixes anything webhooks missed |
| Hourly | `seating:send-reminders`, `seating:send-final` | Reminders and the final mail, on their days |
| 03:00 | `seating:backup` | Nightly dump |
| 04:00 | `seating:purge` | Anonymizes personal data once it is due |

**Auto-assign doesn't run by itself.** Start it after the deadline, see the [runbook](docs/runbook.md).

### Monitoring

Point Uptime Kuma at `GET /health`. It answers `200` when the database, queue and scheduler are alive
and `503` otherwise, and shows only ok or failed. The queue check allows 10 minutes, so that a large
mail batch doesn't raise a false alarm.

System alerts go to `ADMIN_EMAILS`.

Horizon shows queued, finished and failed jobs at `/horizon`, linked as «Warteschlange» in the admin.
It takes the admin login. Finished jobs stay visible for an hour and failed ones for a week.

### Backups

`seating:backup` runs in the `scheduler` container and writes to the `backups` Docker volume. Dumps
are kept for `BACKUP_KEEP_DAYS` days, and a failed dump alerts `ADMIN_EMAILS`. The last backup is
shown on the admin dashboard.

The volume is on the same disk as the database, so copy dumps off the server:

```sh
docker compose exec scheduler ls /backups
docker compose cp scheduler:/backups/<file>.dump .
```

To restore, stop the writers, restore, and start them again:

```sh
docker compose stop app queue
docker compose exec scheduler sh -c 'PGPASSWORD="$DB_PASSWORD" pg_restore --clean --if-exists --no-owner \
  --host "$DB_HOST" --username "$DB_USERNAME" --dbname "$DB_DATABASE" /backups/<file>.dump'
docker compose start app queue
```

Afterwards run `seating:reconcile`, so anything sold since the dump comes back. Queued mails live in
Redis, not in the dump, so a restore neither brings them back nor removes them.

### Personal data

`seating:purge` runs by itself `PURGE_AFTER_DAYS` days after `EVENT_DATE`. It removes names, addresses,
Hi.Events ids, wheelchair requests, webhook payloads, queued jobs, everything Horizon stored and guest
sessions, and keeps the counts. **This can't be undone**, so `ADMIN_EMAILS` gets
a warning 7 days before. A wrong `EVENT_DATE` is the one thing to watch for.

- From that date on, webhooks are ignored and reconcile stops, so nothing can copy names back.
- `seating:purge --dry-run` shows the date and what would be removed.
- Dumps from before the purge still hold personal data until they expire `BACKUP_KEEP_DAYS` days later.
  That's why the selection page promises deletion within 45 days: 30 + 14, and these two have to stay below it.
- If Redis is unreachable, nothing is removed, `ADMIN_EMAILS` is told, and the next night tries again.
- Hi.Events keeps its own data. Delete it there separately.

The audit log and `mail_log` hold no names, only ids that lead nowhere after the purge.

## Local Hi.Events

For end-to-end tests, a local Hi.Events v2.0.0 runs next to this project in `../hievents-local`, on the
same Docker network as DDEV. See the README there. It sends webhooks to
`http://ddev-volkshaus-seating-web/webhooks/hievents`, and its mail lands in Mailpit.
