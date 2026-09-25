# Zoom API notes (verified)

Everything SeatTrim relies on from the Zoom platform, with where it was verified. Rule from the build brief:
**no Zoom field, path, scope or limit goes into code without a row here.** If a row says *unverified*, the
code must treat it as unknown (e.g. "license bundle unknown" → protected).

Verification date: **2026-09-25**. Method: Zoom's reference pages are client-rendered, but every product
page embeds its OpenAPI document and exposes it for download. Those documents were downloaded and read
directly, which is more reliable than scraping the rendered page:

| Product | Reference page | OpenAPI download |
| --- | --- | --- |
| Users | https://developers.zoom.us/docs/api/users/ | https://developers.zoom.us/api-hub/users/methods/endpoints.json |
| Meetings (includes `/report/*`) | https://developers.zoom.us/docs/api/meetings/ | https://developers.zoom.us/api-hub/meetings/methods/endpoints.json |
| Billing (master-account section) | https://developers.zoom.us/docs/api/billing/ma/ | https://developers.zoom.us/api-hub/billing/ma/master.json |
| Phone | https://developers.zoom.us/docs/api/phone/ | https://developers.zoom.us/api-hub/phone/methods/endpoints.json |
| Rooms | https://developers.zoom.us/docs/api/rooms/ | https://developers.zoom.us/api-hub/rooms/methods/endpoints.json |
| Marketplace webhooks | https://developers.zoom.us/docs/api/marketplace/events/ | – |

Guide pages read in full: OAuth (https://developers.zoom.us/docs/integrations/oauth/), Webhooks
(https://developers.zoom.us/docs/api/webhooks/), Rate limits (https://developers.zoom.us/docs/api/rate-limits/),
Pagination (https://developers.zoom.us/docs/api/pagination/), Errors (https://developers.zoom.us/docs/api/errors/),
Create an OAuth app (https://developers.zoom.us/docs/integrations/create/), Before you start
(https://developers.zoom.us/docs/build-flow/before-you-build/), End user authorization
(https://developers.zoom.us/docs/integrations/end-user-auth/), App review process
(https://developers.zoom.us/docs/distribute/app-review-process/), App review guidelines
(https://developers.zoom.us/docs/distribute/app-review-guidelines/), Common rejection reasons
(https://developers.zoom.us/docs/distribute/app-submission/common-rejection-issues/), Submit apps for review
(https://developers.zoom.us/docs/distribute/app-submission/submit-apps-review/), App credentials
(https://developers.zoom.us/docs/build-flow/basic-info/app-credentials/), EU requirements
(https://developers.zoom.us/docs/distribute/eu-requirements/).

Re-verify: before M2 (OAuth + client), before M3 (report windows), before M5 (update-user error codes), and
before submission (M10). Zoom's changelog: https://developers.zoom.us/changelog/.

---

## 1. Capability → endpoint → granular scope

| Capability (brief §3) | Endpoint | Granular scope | Rate-limit label | Verified |
| --- | --- | --- | --- | --- |
| List users, all statuses | `GET /users?status=active\|inactive\|pending` | `user:read:list_users:admin` | MEDIUM | ✅ Users spec |
| Read one user | `GET /users/{userId}` | `user:read:user:admin` | LIGHT | ✅ Users spec |
| Read a user's add-on flags (phone, webinar, large meeting, …) | `GET /users/{userId}/settings` → `feature` object | `user:read:settings:admin` | MEDIUM | ✅ Users spec |
| Update user type (Licensed ↔ Basic) | `PATCH /users/{userId}` body `{"type": 1\|2}` | `user:update:user:admin` | LIGHT | ✅ Users spec |
| Active/inactive host report | `GET /report/users?type=active\|inactive&from&to` | `report:read:list_users:admin` | HEAVY | ✅ Meetings spec |
| Plan usage (purchased vs used seats) | `GET /accounts/me/plans/usage` | `billing:read:plan_usage:admin` | HEAVY | ✅ Billing spec, ⚠ see §6 |
| Fallback seat counts | `GET /users/summary` | `user:read:summary:admin` | MEDIUM | ✅ Users spec |
| Upcoming meetings for a user | `GET /users/{userId}/meetings?type=upcoming` | `meeting:read:list_meetings:admin` | MEDIUM | ✅ Meetings spec |
| Zoom Phone assignment | **No phone scope needed.** `feature.zoom_phone` on `GET /users/{userId}/settings` | (covered by `user:read:settings:admin`) | – | ✅ Users spec |
| Zoom Rooms list (only if we need it, see §8) | `GET /rooms` | `zoom_rooms:read:list_rooms:admin` | MEDIUM | ✅ Rooms spec, **not requested in v1** |

Granular scope names come from the `x-granular-scopes` extension in each OpenAPI operation. The legacy
"classic" scopes (`user:read:admin`, `report:read:admin`, `account:read:admin`, …) are still listed by Zoom
but we only request granular ones. The scopes list lives in `config/zoom.php`.

The phone API (`GET /phone/users/{userId}`, scope `phone:read:user:admin`) exists but is unnecessary
because the user-settings `feature.zoom_phone` boolean already tells us whether the user has Zoom Phone.
`PATCH /users/{userId}` also accepts `feature.zoom_phone=false`, which *removes* the phone license and
unassigns numbers, so we never send `feature` in an update body.

## 2. OAuth (General app, admin-managed)

Source: https://developers.zoom.us/docs/integrations/oauth/ and https://developers.zoom.us/docs/integrations/create/

| Item | Value |
| --- | --- |
| Authorize URL | `https://zoom.us/oauth/authorize?response_type=code&client_id=…&redirect_uri=…&state=…` |
| Token URL | `POST https://zoom.us/oauth/token` with `Authorization: Basic base64(client_id:client_secret)` and form/query params `grant_type=authorization_code&code=…&redirect_uri=…` |
| Refresh | same URL, `grant_type=refresh_token&refresh_token=…`. Response contains a **new** `refresh_token`; "always use the latest refresh token for the next refresh request". |
| Access token lifetime | 1 hour (`expires_in: 3600`) |
| Refresh token lifetime | 90 days |
| Revoke | `POST https://zoom.us/oauth/revoke?token=<access_token>` with the same Basic header → `{"status":"success"}` |
| Token response fields | `access_token`, `token_type`, `refresh_token`, `expires_in`, `scope` (space-separated), `api_url` |
| Re-consent | "If you've updated scopes or webhook settings, Zoom will prompt the user to authorize again" |
| Who can authorize | Account-level (`:admin`) scopes must be authorized by a user whose Zoom role has the matching permissions (e.g. usage reports for `report:*`). Doc: end-user-auth page. |
| Admin-managed meaning | "Account admins add and manage the app. Depending on the scope, the app can access and manage the user data of users on their account." |
| Dev vs prod credentials | Two client ID/secret pairs. Use **production** credentials for the first review submission, **development** credentials for update submissions. |

Not stated in docs but standard: `state` is echoed back on the redirect. We generate it per install and
verify it in the callback.

`invalid_grant` on refresh is not enumerated in Zoom's OAuth doc; it is the standard RFC 6749 error and is
what Zoom returns when a refresh token was revoked or rotated away (developer-forum reports). Treat any
`4xx` from the token endpoint during refresh as "connection needs re-authorization", mark `revoked`, notify
owners.

## 3. Users

`GET /users` (Users spec):

- Query: `status` (`active` default, `inactive` = deactivated, `pending`), `page_size` (spec says
  `maximum: 2000`, default 30; the Pagination guide says "light data retrieval: up to 300 items per page".
  **We send 300**, the lower of the two, until a live test proves 2000 works), `next_page_token` (15-minute
  expiry), `role_id`, `include_fields`, `license`. `page_number` is deprecated.
- Response: `next_page_token`, `page_count`, `page_size`, `total_records`, `users[]`.
- Per-user fields we store: `id` (**not returned for `pending` users** — key pending members by email),
  `email`, `first_name`, `last_name`, `display_name`, `type`, `status`, `dept`, `group_ids[]`,
  `role_id` (`"0"` = Owner in the examples; the Roles API gives names), `user_created_at`
  (`created_at` is deprecated: "date and time when this user's latest login type was created"),
  `last_login_time`, `last_client_version`, `plan_united_type`, `license_info_list[]`, `login_types[]`.
- `type` enum: `1` Basic, `2` Licensed, `4` "Unassigned without Meetings Basic", `99` None (struck through,
  SSO-create only). We treat only `2` as "holds a seat"; `4` and `99` are stored raw and bucketed `healthy`.
- `last_login_time` has a documented **three-day buffer**: "if user first logged in on 2020-01-01 and then
  logged out and logged in on 2020-01-02, this value will still reflect 2020-01-01… if the user logs in on
  2020-01-04, the value will reflect [that]". Use only as a secondary signal. (Empty for deactivated users:
  observed in the field, not stated in the spec.)
- `status` enum: `active`, `inactive` ("A deactivated user"), `pending`.

`GET /users/{userId}` adds `role_name`, `account_id`, `zoom_one_type` (Zoom Workplace bundle option),
`plan_united_type`, `license_info_list[]` (`license_type` = `MEETING` | `ZOOM_WORKPLACE_BUNDLE`,
`license_option`, `subscription_id`), `pmi`, `use_pmi`, `job_title`, `location`, `phone_numbers[]`.
Note: "Users who have not activated their account will have a `pending` status. These users' `created_at`
timestamp will also display the time at which the API call was made, not the account's creation date."

`GET /users/summary` → `licensed_users_count`, `basic_users_count`, `on_prem_users_count`,
`room_users_count`, `pending_users_count`, `join_only_users_count`, `total_users_count`. Cheap cross-check
for pagination completeness (compare `licensed_users_count` with what we paged).

## 4. Bundles and add-ons (guardrails)

Bundle detection **from the user object alone** (no extra scope):

- `zoom_one_type` (on `GET /users/{userId}`): non-zero means a Zoom Workplace / Zoom One bundle. Values are
  bit flags (16 = Business Plus US/CA Unlimited, 4 = Enterprise, …, education and government variants).
- `plan_united_type` (on list and single): present only if the user is on a Zoom United plan (bundle with
  Phone). Any value ⇒ bundled.
- `license_info_list[]` with `license_type = "ZOOM_WORKPLACE_BUNDLE"` ⇒ bundled. Only returned "for accounts
  with multiple subscription plans enabled that contain duplicate plans".
- `PATCH /users/{userId}` doc: "If a Zoom Workplace series plan is configured as the account's base plan,
  you can directly assign the corresponding Zoom Workplace license by setting type = 2" and error 300
  "You can't change the type or plan_united_type or zoom_one_type at the same time" / 2039 "You can't turn
  off your Zoom One license now because you don't have enough basic licenses."

So the rule in `Classifier`: `zoom_one_type > 0` or `plan_united_type` set or a `ZOOM_WORKPLACE_BUNDLE`
entry ⇒ **protected: "Workplace/United bundle"**. If the single-user fetch fails or omits all three fields
on an account whose plan usage shows `plan_zoom_one`/`plan_united` seats ⇒ **protected: "license bundle
unknown"** (brief §6.4).

Add-ons come from `GET /users/{userId}/settings` → `feature` object (scope `user:read:settings:admin`):
`zoom_phone` (bool), `webinar` (bool) + `webinar_capacity`, `large_meeting` (bool) + `large_meeting_capacity`,
`meeting_capacity`, `zoom_events`, `zoom_events_unlimited`, `zoom_whiteboard`, `zoom_whiteboard_plus`,
`zoom_revenue_accelerator`, `zoom_scheduler`, `zoom_clips_plus`, `zoom_translated_captions`,
`zoom_customer_managed_key`, `zoom_workforce_management`, `zoom_quality_management`, `concurrent_meeting`
(`Basic|Plus|None`), plus a `license_info_list[]` for these add-on license types. Any true add-on ⇒
protected in v1. Only fetched for downgrade candidates (MEDIUM label, one call per candidate).

## 5. Host activity report

`GET /report/users` (Meetings spec, tag Reports):

- "Retrieve a host report for a specified period of time **within the last six months**. The report time
  range is **limited to a month**." `from`/`to` are `yyyy-mm-dd`, both required.
- `type` = `active` | `inactive`. "An active host is defined as any user who has hosted at least one
  meeting during the month specified… An inactive host is any user who has not hosted any meetings during
  the specified period."
- `page_size` max **300**, `next_page_token`, optional `group_id`.
- Response: `from`, `to`, `total_records`, `total_meetings`, `total_meeting_minutes`, `total_participants`,
  `users[]` with `id`, `email`, `user_name`, `dept`, `type`, `meetings`, `meeting_minutes`, `participants`,
  `custom_attributes[]`.
- Prerequisite: **Pro or higher plan.** Error `200 No permission` on 400.
- Rate-limit label **HEAVY** (Pro: 10/s, Business+: 40/s, shared daily cap 30k/60k).

Scan plan (brief §6.3): windows of ≤30 days ending now, `[0,30) [30,60) [60,90)` and up to `[150,180)`
when the org threshold is 180. "One month" is read conservatively as 30 days; a calendar-month window can
be 31 days and Zoom does not say which it means. Six-month lookback means anything older than ~180 days is
unavailable — the 180-day threshold is the maximum we can offer.

Hosting = `meetings ≥ 1` in the *active* report for that window. The *inactive* report is used only as a
cross-check (an id in neither report for a window is a data-quality warning).

## 6. Plan usage (seats purchased vs used)

`GET /accounts/{accountId}/plans/usage` (Billing spec, master-account section):

- "This API supports standard and master accounts and subaccounts. To get a **standard account's** plan
  usage, use the `account:read:admin` scope. Enter `me` as the value of the `accountId` path parameter."
- Granular scopes listed: `billing:read:plan_usage:master`, **`billing:read:plan_usage:admin`**. We request
  the `:admin` one and call `/accounts/me/plans/usage`.
- ⚠ The same operation also says "Prerequisite: Master account on a paid Pro, Business or Enterprise plan"
  and its 400 is `200 Only available for paid account`. The two statements conflict for ordinary (non-master)
  accounts. Developer-forum threads (e.g. https://devforum.zoom.us/t/scope-for-get-plan-usage/38604) report
  the call working on standard accounts with the account-read scope. **Must be confirmed on a real Pro
  account in M2.** If the scope is not selectable or the call 400s, fall back to `GET /users/summary`
  (licensed/basic/pending counts) and mark `purchased` unknown → the `unassigned` bucket is hidden and the
  dashboard says so.
- Rate-limit label HEAVY. One call per scan.
- Response fields we use: `plan_base.hosts` (purchased), `plan_base.usage` (assigned), `plan_base.pending`,
  `plan_base.active_hosts`, `plan_base.type`; arrays `plan_zoom_one[]`, `plan_zoom_one_premier[]`,
  `plan_zoom_one_edu_*[]`, `plan_united` (object) with the same `hosts/usage/pending` shape;
  `plan_webinar[]`, `plan_large_meeting[]`, `plan_zoom_rooms`, `plan_zoom_events[]`. Everything else is
  stored raw in the snapshot JSON.
- `unassigned = hosts − usage` per plan object; the dashboard sums base + Workplace/United bundles.

## 7. Update user type (downgrade/restore)

`PATCH /users/{userId}` body `{"type": 1}` (Basic) or `{"type": 2}` (Licensed). Success is **204** with an
empty body, so we must re-fetch to confirm (brief §7).

Documented error codes to map explicitly (all HTTP 400 unless noted):

| Code | Meaning (doc text, abbreviated) | SeatTrim handling |
| --- | --- | --- |
| 200 | "A Zoom Room user cannot be changed to a free user type" / other admin-only fields | Skip, reason from message; flag `is_room` |
| 300 | "You cannot change the user type to 'Basic' because this user has an upcoming Zoom Events scheduled." / "You can't change the type or plan_united_type or zoom_one_type at the same time." | Skip with message |
| 1001 (404) | User does not exist | Mark member removed, skip |
| 1108 | "Permission requirements to change the user type of this user were not met." | Fail with message (installer's role lacks permission) |
| 1109 | "Host is not a paid user." | Skip (already Basic) |
| 1120 | Invitation expired, userId invalid | Mark removed, skip |
| 2033 / 3412 | Account reached maximum Basic users | Fail with message, surface prominently |
| 2034 / 2038 / 3412 | Reached maximum paying users / subscription has no licenses | Restore fails "no free seat" (brief §7) |
| 2039 | "You can't turn off your Zoom One/United license now because you don't have enough basic licenses." | Fail with message |
| 4700 | Invalid access token / missing scope | Refresh or mark connection `error` |
| 429 | Rate limit | Retry per §9 |

**Unverified:** whether `PATCH /users/{userId}` with `type` succeeds for a user whose `status` is
`inactive` (deactivated). Zoom's spec neither allows nor forbids it. The web portal lets an admin change the
type of a deactivated user, which suggests the API does too, but this is not documented. **Test on a real
account in M5** before the `deactivated_licensed` bucket offers one-click downgrade. Until then the bucket
shows the manual alternative (reactivate → downgrade → deactivate; deletion is v2 and documented only).

**Unverified:** the 2025 developer-forum report of update-user failing on Business Plus. Searches on
2026-09-25 did not surface the thread. The error table above is exhaustive for the spec; anything
unlisted is stored verbatim in `license_actions.fail_reason`.

## 8. Zoom Rooms

- `GET /users/summary.room_users_count` confirms rooms are counted as users on the account.
- `GET /rooms` returns `id`, `room_id`, `name`, `location_id`, `status`, `tag_ids[]` — **no user id or
  email**, so it cannot be joined to `/users` rows. Scope `zoom_rooms:read:list_rooms:admin`, prerequisite
  "Pro or higher plan with Zoom Room license".
- `PATCH /users/{userId}` error 200 "A Zoom Room user cannot be changed to a free user type" is the only
  documented signal that a `/users` row is a room.
- **Unverified:** how (or whether) a Zoom Room appears in `GET /users` and `GET /report/users`. Plan: never
  auto-downgrade a member that is absent from the users list; treat error 200 "Zoom Room user" as
  `is_room=true` and protect it going forward; revisit after a live scan on an account with rooms. The rooms
  scope is **not** requested in v1.

## 9. Rate limits and retries

Source: https://developers.zoom.us/docs/api/rate-limits/ (table copied verbatim).

| Label | Free | Pro | Business+ |
| --- | --- | --- | --- |
| Light | 4/s, 6000/day | 30/s | 80/s |
| Medium | 2/s, 2000/day | 20/s | 60/s |
| Heavy | 1/s, 1000/day | 10/s* | 40/s* |
| Resource-intensive | 10/min, 30,000/day | 10/min* | 20/min* |

\* Pro: 30,000 requests/day combined for heavy + resource-intensive. Business+: 60,000/day.

- Limits are **per account, shared by all apps installed on the account** — SeatTrim competes with the
  customer's other integrations, so be frugal.
- 429 messages: "You have reached the maximum per-second rate limit for this API. Try again later." and
  "You have reached the maximum daily rate limit for this API. Refer to the response header for details on
  when you can make another request." The doc says "refer to the response header" without naming it; we
  honour `Retry-After` if present, else exponential backoff (1s, 2s, 4s… capped at 60s, max 5 tries).
- Guide's own advice: wait before retrying, backoff, cache, prefer webhooks to polling.
- 5xx: "Implement retry logic with exponential backoff and jitter."
- Per-scan budget for a 2,000-seat org on Pro: users list ≈ 7 medium calls ×3 statuses, report ≈ 6 windows
  × 2 types × 7 pages = 84 heavy calls, plan usage 1 heavy, settings+meetings only for candidates. Well
  inside limits; heavy calls are serialised per connection anyway.

Tracking id: Zoom's webhook example headers show `x-zm-trackingid`; the API reference does not document a
response header for REST calls. `ZoomClient` stores `x-zm-trackingid` when present and leaves the column
null otherwise (**unverified for REST responses**).

## 10. Pagination

Source: https://developers.zoom.us/docs/api/pagination/

- Loop on `next_page_token` until it is an empty string; keep every other query parameter identical between
  pages. Token expires after 15 minutes.
- `page_number` is deprecated on `/users`.
- Completeness check (brief §5): after listing, compare licensed count with `GET /users/summary
  .licensed_users_count` and with `plan_base.usage`; a mismatch is a scan warning, not a failure.

## 11. Webhooks

Source: https://developers.zoom.us/docs/api/webhooks/

- Endpoint requirements: public HTTPS (TLS 1.2+, CA cert), FQDN, accepts POST JSON, responds 200/204
  **within 3 seconds** — so the controller only verifies and queues.
- **CRC validation**: body `{"event":"endpoint.url_validation","payload":{"plainToken":"…"},"event_ts":…}`.
  Respond 200 with `{"plainToken": <same>, "encryptedToken": hex(HMAC-SHA256(secret_token, plainToken))}`.
  Zoom re-validates every 72 hours and disables the subscription after 6 consecutive failures.
- **Signature**: `message = "v0:" + x-zm-request-timestamp + ":" + rawBody`;
  `signature = "v0=" + hex(HMAC-SHA256(secret_token, message))`; compare with `x-zm-signature` using a
  constant-time compare. Use the raw request body, not a re-encoded one.
- Retries: for 5xx (and some transport errors) Zoom retries at +5 min, +20 min, +60 min. No retry on 4xx.
  Our handler must be idempotent.
- Deauthorization: event `app_deauthorized`, payload `account_id`, `user_id`, `client_id`,
  `deauthorization_time`, `signature`. Sent to the **Deauthorization Notification Endpoint URL** set on the
  app listing page. "After receiving a deauthorization webhook event, apps must delete all associated user
  data." Only production (published) apps receive it: "Private apps or apps in development do not trigger
  deauthorization notifications." Verify it like any other webhook (secret token; the old verification token
  was deprecated Oct 2023).
- **Data-deletion deadline: unverified.** Current docs say "must delete all associated user data" with no
  number of days. The old Data Compliance API (which carried a 10-day expectation) is being deprecated
  per Zoom's announcements page. SeatTrim deletes synchronously-queued on receipt (minutes, not days), so
  any future stated deadline is met.
- We also subscribe to `user.deactivated`, `user.activated`, `user.updated`, `user.deleted`, `user.created`
  (Users webhook events) to keep members fresh between scans. Payloads not yet verified; do that in M2 when
  wiring the handler.

## 12. Errors

Source: https://developers.zoom.us/docs/api/errors/. Body shape `{"code": 300, "message": "…",
"errors": [{"field": "…", "message": "…"}]}`. 4700 = invalid access token / missing scopes. Standard 400,
401, 403, 404, 409, 429, 5xx.

## 13. Marketplace review facts we design around

- Documentation URL must cover **adding, using, removing** the app, including "how user data is handled
  after removal" (common-rejection page). → M9 docs pages.
- All metadata URLs (privacy, terms, support, docs) must be on a domain we own; no Google Drive.
- Each scope needs a written justification and test steps; redundant scopes are rejected.
- Support page must list hours, first-response SLA, and contact channels.
- Test plan + test credentials go in the Release Notes / Test account fields.
- First submission uses **production** client ID; updates use **development** client ID.
- Domain verification (TXT / HTML file / meta tag) for every domain in the listing URLs.
- EU DSA: business name, address, phone, email displayed on the listing; traders also supply bank name,
  last four digits of account and an ID document (not displayed). Company name must match the privacy
  policy exactly (StaticMaker Pte Ltd).
- Review SLA: first response within 72 hours, typically 36.


---

## 14. Implementation decisions that follow from the above

- **Pending invites in accounts with bundle plans** are classified `pending_licensed` but *not* eligible for
  downgrade, with reason "license bundle unknown": list rows for pending users carry no `zoom_one_type`
  and no id to fetch. Accounts without any bundle plan get eligible pending invites. Revisit once a real
  bundle account has been observed (M10 checklist).
- **Zoom Rooms** are not detectable up front; they become protected after the first `PATCH` answers error
  200 "A Zoom Room user cannot be changed to a free user type". The fixture account contains two rooms to
  exercise this path.
- **Windows** are 30 days (≤31 per request). Threshold 30/60/90 uses three windows; 180 uses six.
- **Unknown hosting** (report failed or scope missing) never marks anyone idle.
- **Demo organizations** (`settings.demo = true`) are served by `FakeZoomClient` even with `ZOOM_DRIVER=http`,
  through `DelegatingZoomApi`; their tokens are fake and never refreshed.
