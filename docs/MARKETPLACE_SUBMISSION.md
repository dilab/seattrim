# Zoom App Marketplace submission kit

Everything the reviewer form asks for, in the order the build flow asks for it. Facts about the
review process were taken from https://developers.zoom.us/docs/distribute/app-review-process/,
https://developers.zoom.us/docs/distribute/app-submission/submit-apps-review/ and
https://developers.zoom.us/docs/distribute/app-submission/common-rejection-issues/ on 2026-09-25.

## Listing

**App name:** SeatTrim
**Short description (≤150 chars):** Find Zoom licensed seats nobody uses, downgrade them safely, and right-size your seat count before renewal.
**Category:** Administration / IT
**Long description:**

SeatTrim is for Zoom admins at organizations with 50 to 2,000 seats who pay for Licensed users nobody
uses. Once an admin adds SeatTrim, it scans the account every night and groups Licensed users into
buckets: pending invites that reserve a seat, any deactivated user still holding a seat, users who have not
hosted a meeting within your threshold (30–180 days), and seats you pay for that nobody holds, including those released by leavers. It shows the
annual cost at your seat price and lets you downgrade users to Basic with one click and restore them just
as easily.

Every change is guarded: account owners and admins, Zoom Rooms, users on Workplace/United bundles, users
with Zoom Phone, Webinar or Large Meeting add-ons, users with upcoming scheduled meetings, recently created
accounts and anyone on your exclusion list are never touched. SeatTrim re-checks each user in Zoom
immediately before acting and writes an audit entry with Zoom's tracking id.

Paid plans add bulk actions, automation with a plain warning email and a "Keep my license" button, a weekly
digest, renewal reminders and CSV export. SeatTrim does not change your Zoom subscription: it frees seats
and tells you the number to reduce to in Zoom Billing.

Main features:
- Nightly scan of users, host reports and plan usage
- Waste in dollars per bucket, purchased vs assigned seats
- One-click downgrade and restore with guardrails and a full audit log
- Exclusions by email, domain or group
- Optional automation with warning emails, dry run first
- Renewal right-sizing target and reminders

**Links (all on seattrim.com):** Privacy `https://seattrim.com/privacy`, Terms `https://seattrim.com/terms`,
Support `https://seattrim.com/support`, Documentation `https://seattrim.com/docs`.
**Adding the app:** "From your site" → landing page `https://seattrim.com/zoom-license-audit`
(lets logged-in users authorize, unauthenticated users sign up, and visitors learn what it does).
**Gallery:** three 1200×780 screenshots: dashboard, members table with drawer, automation settings. (To capture from `/demo`.)

## Scopes and justifications

| Scope | Justification (paste as Scope Description) | How the reviewer tests it |
| --- | --- | --- |
| `user:read:list_users:admin` | Lists all users (active, deactivated, pending) to determine which hold a Licensed seat. Core inventory. | Run a scan; members page lists every user of the test account. |
| `user:read:user:admin` | Re-reads a single user immediately before a downgrade/restore to confirm type, status, role and bundle on fresh data. | Downgrade a user; audit log shows the re-check. |
| `user:read:settings:admin` | Reads the `feature` flags (Zoom Phone, Webinar, Large Meeting) so users with add-ons are protected from downgrade. Read-only, only for candidates. | A user with Zoom Phone shows "has Zoom Phone" in the Protected bucket. |
| `user:update:user:admin` | The only write: changes a user's `type` between Licensed (2) and Basic (1) when an admin clicks Downgrade/Restore or a rule the admin configured fires. No other field is sent. | Downgrade then restore a test user; verify in the Zoom portal. |
| `report:read:list_users:admin` | Active/inactive hosts report per 30-day window decides who is idle. Meeting content is never read. | Members drawer shows meetings hosted per window. |
| `billing:read:plan_usage:admin` | Purchased vs assigned seats for the "unassigned seats" figure and the renewal target. Read-only. | Dashboard seat card. |
| `meeting:read:list_meetings:admin` | Counts upcoming scheduled meetings (topic/time only) of downgrade candidates so hosts with meetings are protected (Basic = 40-minute limit). | A user with an upcoming meeting shows "has upcoming scheduled meetings". |

Event subscriptions: `user.created`, `user.updated`, `user.activated`, `user.deactivated`, `user.deleted`
(keeps the inventory fresh between scans). Deauthorization endpoint: `/zoom/webhook`.

## Technical design (paste)

- Stack: Laravel 13 (PHP 8.3), Livewire, MySQL, Redis; hosted on a Laravel Forge server behind Cloudflare; TLS 1.2+.
- OAuth: admin-managed General app, authorization-code flow with per-attempt `state`; tokens stored AES-256 encrypted at rest (Laravel encrypted casts); refresh under a distributed lock; tokens never logged.
- Webhooks: `x-zm-signature` HMAC verified with the secret token on every request including CRC; 5-minute timestamp window; processed asynchronously.
- Data stored: user id, email, name, department, group ids, role, license type, status, creation date, last login, hosted-meeting counts per window, add-on/bundle flags, count of upcoming meetings, plan usage totals. No meeting content, recordings, chat or participants.
- Deletion: on `app_deauthorized` or in-app disconnect, tokens are revoked and all Zoom-derived data is deleted immediately; owners are emailed. Only an anonymised count of audit actions and billing records remain.
- Access control: per-organization tenancy with a global scope and policies; only owners/admins can change licenses; viewers are read-only.
- Rate limits: per-endpoint retry with `Retry-After`, exponential backoff, one scan per organization at a time; heavy report calls are paginated at 300.
- Security testing: OWASP Top 10 review, dependency audit (`composer audit`, `npm audit`), CSRF on all forms, signed expiring URLs for the keep-my-license link.

## Test plan for reviewers

Test account (SeatTrim): `reviewer@seattrim.com` / password provided in the submission form (owner of organization "Zoom Review").
Zoom test account: the reviewer's own Pro+ account, or ask us for access to our test account (details in the form).

1. Sign in to SeatTrim → Connection → **Connect Zoom** → authorize with the Zoom admin account → allow all listed scopes.
2. Onboarding asks for seat price and renewal date (optional) → **Save and run first scan**. Wait for the dashboard.
3. Dashboard: headline waste, seat card (`billing:read:plan_usage:admin`), bucket cards.
4. Members: filter bucket = Idle licensed; open a user; the drawer shows meetings per window (`report:read:list_users:admin`), last login, add-ons (`user:read:settings:admin`), upcoming meetings (`meeting:read:list_meetings:admin`).
5. Click **Downgrade to Basic** → confirm. Audit log shows re-check + `user:update:user:admin` result with tracking id. Verify in Zoom User Management that the user is Basic.
6. Click **Restore Licensed** → user is Licensed again.
7. Automation: enable, keep dry run, save; run `Scan now`; the "Warnings sent" table lists scheduled notices (dry run makes no change).
8. Removal: Connection → **Disconnect and delete data** → type DELETE. Members page is empty; owner receives an email. Alternatively remove the app from Zoom Marketplace → Added Apps; the same happens via the deauthorization webhook.

Demo without a Zoom account: `https://seattrim.com/demo` (fixture data, throw-away organization).

## Reviewer checklist (from Zoom's current requirements)

- [ ] Production client id used for this first submission (development id for later updates)
- [ ] Domain verification (TXT) for seattrim.com
- [ ] Privacy, Terms, Support, Documentation URLs on seattrim.com; company name "StaticMaker Pte Ltd" identical everywhere
- [ ] Documentation covers adding, using, removing, data after removal, troubleshooting, support hours + first-response SLA
- [ ] Every scope has a description and a test step; no unused scopes (checked: `zoom_rooms:*`, `phone:*`, classic `user:write:admin` are not requested)
- [ ] Event subscription validated (CRC) and deauthorization endpoint set
- [ ] Technical Design section completed
- [ ] EU/DSA section: business details; as a paid app also bank name, last four digits and an identity/registration document
- [ ] Gallery images 1200×780, short description ≤150 chars
- [ ] Test plan link and test credentials in Release Notes / Test account fields
- [ ] Activation: "Activate my app immediately after it is approved"

## Privacy and data-handling answers

- Collects personal data? Yes: Zoom user profile metadata listed above; no sensitive data; no users under 16 targeted.
- Shares data with third parties? Only processors: hosting provider, Cloudflare, Stripe (billing), email delivery. Never sold or used for advertising.
- Retention: while the organization is connected; deleted immediately on removal; account data deleted within 30 days of account deletion.
- Data subject requests: privacy@seattrim.com; admins can delete any user's data by disconnecting.
- Encryption: TLS in transit; tokens encrypted at rest; database on an encrypted volume; backups encrypted.

## Known unverified items to confirm on the reviewer's account

1. `billing:read:plan_usage:admin` on a standard (non-master) account. If unavailable, SeatTrim falls back to `GET /users/summary` and hides purchased seats (still functional).
2. `PATCH /users/{userId}` for deactivated users. If Zoom refuses, the audit row records the error and the UI suggests reactivate → downgrade → deactivate.
3. Whether Zoom Rooms appear in `GET /users`; if they do, SeatTrim learns from Zoom error 200 and protects them afterwards.
4. Whether a deactivated user ever comes back from `GET /users?status=inactive` as Licensed (`type = 2`). Zoom documents that deactivation removes licenses; if it never happens, the "Deactivated, still licensed" bucket stays hidden and can be removed.
