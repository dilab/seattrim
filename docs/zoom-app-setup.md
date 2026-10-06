# Zoom Marketplace app setup (draft, M0)

Step-by-step for creating the SeatTrim app in the Zoom App Marketplace developer console. Written against
the current build flow on 2026-09-25 (sources: https://developers.zoom.us/docs/integrations/create/,
https://developers.zoom.us/docs/build-flow/before-you-build/, https://developers.zoom.us/docs/api/webhooks/,
https://developers.zoom.us/docs/integrations/oauth/). Zoom moves these screens around; if a label below
does not match, the concept still applies.

## 0. Prerequisites

- A Zoom account where you are **owner or admin**, or have the *Zoom for developers* role
  (User Management → Roles → Role Settings → Advanced features → "Zoom for developers": View + Edit).
- The customer-side requirement for SeatTrim is a **Pro or higher** plan: the host report and plan usage
  endpoints are "Pro or higher" / "paid account only". Free (Basic) accounts can install the app, but the
  scan cannot get hosting data. The app shows a friendly "your account plan doesn't expose reports" screen
  and still lists users and license types from `GET /users`.
- Local dev: Herd serves `https://seattrim.test`; expose it with Expose or ngrok so Zoom can reach the
  OAuth callback and webhook. Put the tunnel URL in `APP_URL` and restart the queue worker after changing it.

## 1. Create the app

1. Sign in at https://marketplace.zoom.us → **Develop → Build App** (or the *Developer* link bottom-left).
2. Choose **General app** → Create. (There is one OAuth app type now; the old "OAuth app" / "Server-to-Server"
   split is gone. Server-to-Server is a separate type for your own account only and is *not* what we want.)
3. **Basic Info**
   - Name: `SeatTrim` (do not include the word "Zoom" in the name; "for Zoom" is allowed in descriptions).
   - **Select how the app is managed → Admin-managed.** Doc text: "Account admins add and manage the app.
     Depending on the scope, the app can access and manage the user data of users on their account."
     This is what lets one install act on the whole account and is required for every `:admin` scope we use.
   - App Credentials: you get **two** pairs, *Development* and *Production*. Put the development pair in
     `.env` (`ZOOM_CLIENT_ID`, `ZOOM_CLIENT_SECRET`) now; production goes into Forge env later.
   - OAuth Information:
     - **OAuth Redirect URL:** `{APP_URL}/zoom/callback`, e.g. `https://abc123.sharedwithexpose.com/zoom/callback`
       for dev and `https://seattrim.com/zoom/callback` for production.
     - **OAuth allow list:** add both the full callback URLs *and* the bare origins
       (`https://abc123.sharedwithexpose.com`, `https://seattrim.com`).
     - Leave *Strict Mode URL* and *Subdomain check* on if the UI offers them; our redirect is exact.

## 2. Features → Access

1. **Secret Token:** copy it into `ZOOM_WEBHOOK_SECRET_TOKEN`. It is used for both the CRC challenge and
   the `x-zm-signature` HMAC (see `docs/zoom-api-notes.md` §11).
2. **Event Subscriptions → Add New Event Subscription**
   - Name: `SeatTrim user events`
   - Event notification endpoint URL: `{APP_URL}/zoom/webhook`
   - Event notification receiver: **All users in your account**.
   - Event types (under *User*): `user.activated`, `user.deactivated`, `user.updated`, `user.deleted`,
     `user.created`. (The build flow auto-adds the read scopes these events need; confirm nothing beyond
     the list in §3 was added.)
   - Click **Validate** next to the URL. Zoom POSTs `endpoint.url_validation`; the app must answer within
     3 seconds with the `plainToken`/`encryptedToken` JSON. The app implements this in M2 — before that,
     validation will fail, which is expected.
   - Save. Zoom re-validates every 72 h; six consecutive failures disable the subscription.
3. Do **not** enable Surface / Embed / Connect. SeatTrim has no in-client UI.

## 3. Scopes (least privilege)

Add exactly these granular scopes. Each row's justification text is reused verbatim in the Marketplace
submission (M10). Verified against each endpoint's `x-granular-scopes` on 2026-09-25.

| Granular scope | Endpoint | Justification (for reviewers) |
| --- | --- | --- |
| `user:read:list_users:admin` | `GET /users` | SeatTrim lists every user on the account (active, deactivated, pending) to see which ones hold a Licensed seat. This is the core inventory the product is built on. |
| `user:read:user:admin` | `GET /users/{userId}` | Before any change, SeatTrim re-reads the single user to confirm current type, status, role, Workplace/United bundle fields and creation date, so that decisions are made on fresh data. |
| `user:read:settings:admin` | `GET /users/{userId}/settings` | Read-only check of the user's `feature` flags (Zoom Phone, Webinar, Large Meeting, Events, …). Users with add-ons are excluded from downgrade because removing their Licensed type would break paid features. Only called for downgrade candidates. |
| `user:update:user:admin` | `PATCH /users/{userId}` | The only write. Changes `type` between 2 (Licensed) and 1 (Basic) when an admin clicks Downgrade or Restore, or when an admin-configured rule fires after a warning email. No other profile field is ever sent. |
| `report:read:list_users:admin` | `GET /report/users` | The active/inactive hosts report is how SeatTrim decides whether a licensed user has hosted a meeting in the last 30–180 days. Meeting content is never accessed. |
| `billing:read:plan_usage:admin` | `GET /accounts/me/plans/usage` | Shows how many Licensed seats the account has purchased versus assigned, so the admin can see unassigned seats and right-size at renewal. Read-only; SeatTrim never changes plans. |
| `meeting:read:list_meetings:admin` | `GET /users/{userId}/meetings?type=upcoming` | Before downgrading, SeatTrim checks whether the user has upcoming scheduled meetings (topic/time only) and protects them, because a Basic host is limited to 40 minutes. Only called for downgrade candidates. |

Scopes we deliberately do **not** request: any `phone:*` (Phone assignment is visible via
`feature.zoom_phone` in user settings), `zoom_rooms:*`, `user:write:*` classic scope (superset of update),
`report:read:admin` classic scope, anything on meetings content, recordings, chat or participants.

⚠ Open question: the plan-usage operation lists both a `:master` and an `:admin` granular scope and
contradicts itself about whether non-master accounts may call it. If `billing:read:plan_usage:admin` is not
offered in the scope picker for an admin-managed General app, screenshot the picker and stop; we will fall
back to `user:read:summary:admin` (`GET /users/summary`) and hide the purchased-seat figures.

## 4. Deauthorization

- On the **App Listing** (Production) page there is a **Deauthorization Notification Endpoint URL** field.
  Set it to the same `{APP_URL}/zoom/webhook`. The handler recognises `event: app_deauthorized`.
- Zoom only sends this for **published** apps ("Private apps or apps in development do not trigger
  deauthorization notifications"). During development, use the app's own **Disconnect** button, which runs
  the same revoke-and-delete flow.
- On receipt SeatTrim: verifies the signature, marks the connection `revoked`, calls
  `POST https://zoom.us/oauth/revoke` (best effort), queues deletion of all Zoom-derived data for the org,
  and emails the org owners. Zoom's requirement is "apps must delete all associated user data"; no deadline
  is stated in current docs (see `docs/zoom-api-notes.md` §11).

## 5. Local test

1. Development tab → **Local Test → Add App Now → Allow**. This runs the OAuth flow against the
   development credentials and lands on `/zoom/callback`.
2. Or start from inside SeatTrim: Onboarding → Connect Zoom, which sends you to
   `https://zoom.us/oauth/authorize?response_type=code&client_id=…&redirect_uri=…&state=…`.
3. Authorize with an account **owner/admin**; account-level scopes are refused for users whose Zoom role
   lacks the matching permission (Usage Reports, User management, Billing).
4. To let colleagues on the same Zoom account test, use *Generate authorization URL* on the Local Test page.
   Beta access outside your own Zoom account requires a request to Zoom.

## 6. Going to production (checklist for M10)

- Switch the build flow to **Production**; fill Basic Info again with the production redirect URL and allow list.
- App Listing: short description (≤150 chars), long description, 2–3 gallery images at 1200×780, category,
  **Privacy Policy URL, Terms of Use URL, Support URL, Documentation URL** — all on `seattrim.com`.
  Documentation must cover adding, using and removing the app and what happens to data on removal.
- Support page must state hours, first-response SLA, and contact channels.
- Technical Design section: stack, hosting (Forge, single server, Cloudflare), data stored (user metadata
  only, tokens encrypted at rest), retention, deletion on deauthorization.
- EU and Discoverability tab (DSA): business name, address, phone, email; as a paid app we are a "trader"
  and must add bank name, last four digits, and an ID/registration document. Company name must match the
  privacy policy exactly: **StaticMaker Pte Ltd**.
- Domain verification for `seattrim.com` (TXT record is easiest).
- Release notes for reviewers: link to the test plan (`docs/MARKETPLACE_SUBMISSION.md` will hold it) and a
  reviewer test account on SeatTrim.
- Use the **production** client ID for the first submission; **development** client ID for later updates.
- Choose *Activate my app immediately after it is approved*.
