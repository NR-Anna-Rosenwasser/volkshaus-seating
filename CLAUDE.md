# Volkshaus Sitzplatzwahl

Seat selection for a Hi.Events-ticketed event at Volkshaus Zürich, Theatersaal.
Laravel + Filament + PostgreSQL, Blade/Alpine/inline SVG, Pest.

- Code, comments and commit messages in English. UI in de-CH: no «ß», use «ss».
- Development runs in DDEV. Run PHP, Composer and npm through `ddev`.
- Tests: `ddev composer test`. They need Postgres, never switch them to SQLite.
- Do not guess Hi.Events field names or statuses: use `docs/hievents-api.md`.
- Keep the seat-locking order from the save flow (`ORDER BY id FOR UPDATE`) everywhere seats are assigned.
