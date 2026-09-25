# SeatTrim

Reclaim unused Zoom licenses. Connects to a Zoom account (admin-managed Marketplace app), scans it nightly,
shows deactivated / pending / idle / unassigned licensed seats with their annual cost, lets admins downgrade
users to Basic with guardrails and one-click restore, automates it with warning emails, and reminds them to
right-size at renewal.

- `docs/stack.md` – versions and environment matrix
- `docs/zoom-api-notes.md` – every Zoom endpoint, field, scope and limit we rely on, with doc links
- `docs/zoom-app-setup.md` – creating the Marketplace app
- `docs/deploy.md` – Forge deployment, backups, monitoring
- `docs/MARKETPLACE_SUBMISSION.md` – listing text, scope justifications, test plan, checklist

## Run it locally

```
composer setup     # composer install, .env, key, migrations, npm install + build
composer dev       # server, queue worker, logs, vite
```

`.env` defaults to `ZOOM_DRIVER=fake`: the app runs on a 69-user fixture Zoom account
(`tests/Fixtures/zoom`, regenerate with `php tests/Fixtures/zoom/generate.php`). Open `/demo` for an
instant demo organization, or register, create an organization and click *Connect Zoom* (the fake OAuth
completes immediately).

For a real Zoom account set `ZOOM_DRIVER=http`, fill `ZOOM_*` from the Marketplace app and expose the site
with Expose/ngrok for the OAuth callback and webhook.

## Quality gate

```
composer test      # Pint (check), PHPStan level 7, Pest
```

Tests never call Zoom: `phpunit.xml` forces the fake driver.
