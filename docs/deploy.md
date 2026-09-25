# Deploying SeatTrim (Laravel Forge, single server, Cloudflare)

## Server

- Forge "App" server, PHP 8.3, MySQL 8, Redis, Nginx. 2 GB RAM is enough for the first few hundred organizations.
- Site: `app.seattrim.com` (marketing pages are served by the same app at `seattrim.com`; point both at the site or redirect the apex to `app.`).
- Cloudflare in front: proxied DNS, SSL mode Full (strict), Forge-issued Let's Encrypt certificate on the origin.
  Add a Cloudflare rule to bypass cache for `/livewire/*`, `/zoom/*`, `/stripe/*`, `/keep/*`.
- Nginx: keep the default Forge template. Increase `client_max_body_size` is not needed (no uploads).

## Environment

Copy `.env.example` and set:

| Key | Value |
| --- | --- |
| `APP_ENV`, `APP_DEBUG` | `production`, `false` |
| `APP_URL` | `https://app.seattrim.com` |
| `DB_CONNECTION` … | MySQL credentials from Forge |
| `QUEUE_CONNECTION` | `redis` |
| `CACHE_STORE`, `SESSION_DRIVER` | `redis` (locks for token refresh use the cache store) |
| `ZOOM_DRIVER` | `http` |
| `ZOOM_CLIENT_ID`, `ZOOM_CLIENT_SECRET` | **Production** credentials from the Marketplace app |
| `ZOOM_WEBHOOK_SECRET_TOKEN` | Features → Access → Secret Token |
| `ZOOM_REDIRECT_URI` | `https://app.seattrim.com/zoom/callback` |
| `STRIPE_KEY`, `STRIPE_SECRET`, `STRIPE_WEBHOOK_SECRET` | from Stripe; webhook endpoint `https://app.seattrim.com/stripe/webhook` |
| `STRIPE_PRICE_STARTER/GROWTH/SCALE` | yearly price ids |
| `MAIL_*` | transactional provider (Postmark/SES). `MAIL_FROM_ADDRESS=hello@seattrim.com` |
| `HORIZON_ADMIN_EMAILS` | who may open `/horizon` |
| `DEMO_ENABLED` | `true` (public demo) |

## Processes

- **Horizon** (Forge → Daemons): `php artisan horizon` in the site root, user `forge`. Restart on deploy with `php artisan horizon:terminate` in the deploy script.
- **Scheduler** (Forge → Scheduler): `php artisan schedule:run` every minute. It runs the hourly scan dispatcher, digests, renewal reminders and demo pruning.
- Deploy script (after `composer install --no-dev`): `php artisan migrate --force`, `php artisan config:cache`, `php artisan route:cache`, `php artisan view:cache`, `npm ci && npm run build`, `php artisan horizon:terminate`.

Queue names: everything runs on `default`. Scans are serialised per organization by `WithoutOverlapping`; a large account takes a few minutes, so set Horizon's `timeout` ≥ 1800 (config/horizon.php) and `maxProcesses` 3–5.

## Stripe

- Create three yearly prices (Starter 290, Growth 790, Scale 1990 USD) and put the ids in `.env`.
- Run `php artisan cashier:webhook --url=https://app.seattrim.com/stripe/webhook` once, copy the signing secret to `STRIPE_WEBHOOK_SECRET`.
- Enable the Customer Portal in Stripe (cancel, update card, invoices).

## Zoom

Follow `docs/zoom-app-setup.md`. Production redirect URL and allow list must contain `https://app.seattrim.com/zoom/callback`; webhook and deauthorization URL `https://app.seattrim.com/zoom/webhook`. Validate the webhook from the Marketplace console after deploying (the CRC handler needs the secret token in `.env`).

## Backups

- Forge → Backups: MySQL nightly to S3 (or Forge's backup provider), 30-day retention. The database is the only state; `storage/` holds nothing that cannot be regenerated.
- Encrypted Zoom tokens are protected by `APP_KEY`: back up `.env` (or the key) separately and never rotate `APP_KEY` without re-encrypting, or every organization must reconnect.
- Restore drill: restore the dump to a staging server, set `ZOOM_DRIVER=fake`, and check the dashboard renders.

## Logging and monitoring

- `LOG_CHANNEL=stack` with `daily` files (14 days) plus an error channel to your alerting provider (Slack/Bugsnag via a stack channel) — configure in `config/logging.php`.
- Watch for `zoom.token.revoked`, `zoom.rate_limited`, `scan.crashed`, `license.downgrade.failed` messages. Tokens are never logged; emails appear only in audit rows and notifications.
- Horizon at `/horizon` shows failed jobs; failed scans and actions are also visible in the app (scan warnings, audit log).
- Uptime check on `/up`.

## Local development

```
composer setup          # installs, .env, key, migrate, npm build
composer dev            # server + queue worker + logs + vite
```

`.env` defaults to `ZOOM_DRIVER=fake`, so the whole app runs on the 69-user fixture account without Zoom credentials. Visit `/demo` for a ready-made organization, or register and connect (the fake OAuth completes instantly). For a real Zoom account, set `ZOOM_DRIVER=http`, expose Herd with Expose/ngrok and use the tunnel URL as `APP_URL` and in the Marketplace app.
