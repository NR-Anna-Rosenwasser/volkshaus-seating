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
ddev composer test
```

Set `ADMIN_EMAIL` and `ADMIN_PASSWORD` in `.env` before seeding. The admin panel is at `/admin`.

Seeding needs the seat data at `database/data/volkshaus-theatersaal-seats.json`.

## Production

Docker Compose runs `app`, `queue`, `scheduler` and `postgres`. The app listens on
`127.0.0.1:${APP_PORT}` for CloudPanel, which terminates TLS and proxies to it.

```sh
cp .env.example .env          # fill in the values, then generate APP_KEY:
docker compose run --rm --no-deps -e RUN_MIGRATIONS=false app php artisan key:generate --show
docker compose up -d --build
docker compose exec app php artisan db:seed --force
```

Migrations run when the `app` container starts.
