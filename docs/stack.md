# Stack

Versions below are what `composer show` / `package.json` report after scaffolding on 2026-09-25.
Update this file whenever a major dependency changes.

## Runtime

| Component | Version | Notes |
| --- | --- | --- |
| PHP | 8.3.32 | Herd-managed locally. `composer.json` requires `^8.3`. |
| Laravel framework | 13.33.0 | `laravel/framework ^13.17` |
| Livewire | 4.4.6 | `livewire/livewire ^4.1` |
| Flux UI (free) | 2.20.0 | `livewire/flux ^2.13.1`. **Flux Pro is not installed.** |
| Laravel Fortify | 1.40.0 | Auth backend shipped by the starter kit (login, register, 2FA, passkeys). |
| livewire/blaze | ^1.0 | Blade compile-time optimiser shipped by the kit. |
| laravel/chisel | ^0.1.0 | Shipped by the kit. |
| Pest | 4.7.8 | With `pest-plugin-laravel` and `pest-plugin-drift`. |
| Pint | 1.32.1 | Preset `laravel` (`pint.json`). |
| Larastan / PHPStan | ^3.9 | Level 7 (`phpstan.neon`). Memory limit raised to 1G in the composer script. |
| Node | 22.14.0 | Vite 8 + `vite-plus`, Tailwind CSS 4 via `@tailwindcss/vite`. |
| Laravel Cashier (Stripe) | 16.8.0 | Organization is the Billable customer model; own migrations. |
| Laravel Horizon | ^5.50 | Production queue dashboard at `/horizon`, gated by `HORIZON_ADMIN_EMAILS`. |

Scaffolded with `laravel new seattrim --livewire --pest --database=sqlite --npm --no-boost --git`
(Laravel Installer 5.31.0, official Livewire starter kit). Cashier added in M8, Horizon in M10.

## Databases and queues

| Environment | Database | Queue | Cache/session |
| --- | --- | --- | --- |
| Local (Herd) | SQLite `database/database.sqlite` (switch `DB_CONNECTION=mysql` if you prefer) | `database` | `database` |
| Tests | SQLite `:memory:` (`phpunit.xml`) | `sync` | `array` |
| Production (Forge) | MySQL 8 | Redis + Horizon | Redis |

Everything is driven by `.env`, nothing is hard-coded to a driver.

## Zoom driver

`ZOOM_DRIVER=http|fake` (`config/zoom.php`). `phpunit.xml` forces `fake`, so the test suite can never reach
`api.zoom.us`. `.env` for local dev defaults to `fake` so the UI runs on fixtures until you have Marketplace
credentials.

## Flux: free vs Pro

Checked against the component stubs installed in `vendor/livewire/flux/stubs/resources/views/flux/` (2.20.0).
The public component index at https://fluxui.dev/components does not label tiers, so the installed stubs are
the source of truth.

**Free (installed):** accent, aside, avatar, badge, brand, breadcrumbs, button, callout, card, checkbox,
container, description, dropdown, error, field, fieldset, flag, footer, header, heading, icon, input, label,
legend, link, main, menu, modal, navbar, navlist, navmenu, otp, pagination, profile, progress, radio, select,
separator, sidebar, skeleton, spacer, subheading, switch, **table**, text, textarea, toast, toggle, tooltip.

**Pro only (not installed):** accordion, autocomplete, calendar, chart, command, context, composer,
date picker, editor, file upload, kanban, popover, tabs, time picker, timeline, slider, color picker,
carousel, pillbox, phone.

Consequence for the spec: the members table, badges, modals and dropdowns in §10 are all free. Things that
would want Pro: **tabs** (settings pages), **date picker** (renewal date, exclusion expiry), **chart**
(waste trend). Plain `<input type="date">` and a Tailwind tab strip are the fallback. See open questions in
the M0 summary.

## Scripts

| Command | What it does |
| --- | --- |
| `composer test` | `config:clear`, `pint --test`, `phpstan analyse --memory-limit=1G`, `php artisan test`. This is the CI gate (`.github/workflows/ci.yml`). |
| `composer lint` | Pint, fixing. |
| `composer dev` | `php artisan dev` (server, queue, logs, vite). |
| `npm run build` / `npm run dev` | Vite. |
