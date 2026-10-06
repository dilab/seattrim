# Zoom scope descriptions (Marketplace submission)

Paste each block into the Marketplace "Scope description" field. The field asks how the data is used, whether it
is stored, and if so whether it is encrypted or plain text.

Only the OAuth tokens are encrypted by the application (`app/Models/ZoomConnection.php`, Laravel `encrypted` casts,
AES-256). Zoom user, report and plan data is stored as plain text in MySQL. If the server's disk or database is
confirmed to be encrypted at rest, "plain text in MySQL" can be changed to "MySQL on an encrypted volume".

## 1. `user:read:list_users:admin`

Used to list every user on the account (active, deactivated, pending) to find which users hold a Licensed seat. This is SeatTrim's core license inventory. Stored: user ID, email, name, department, group IDs, role, license type, status, created date and last login, in plain text in our MySQL database, access-restricted per organization. All of it is deleted immediately when the app is uninstalled or disconnected.

## 2. `user:read:user:admin`

Used to re-read a single user right before a downgrade or restore, to confirm their current license type, status, role and bundle on fresh data. The result updates the same stored user record (plain text in MySQL, access-restricted per organization). Nothing extra is stored, and the record is deleted on uninstall or disconnect.

## 3. `user:read:settings:admin`

Read-only. Used to check a downgrade candidate's feature flags (Zoom Phone, Webinar, Large Meeting) so users with paid add-ons are never downgraded. Only the yes/no add-on flags are stored, in plain text in MySQL with access restricted per organization. They are deleted on uninstall or disconnect.

## 4. `user:update:user:admin`

SeatTrim's only write. Changes a user's license type between Licensed and Basic, only when an admin clicks Downgrade/Restore or a rule the admin configured fires. No other field is ever sent. We store an audit record of each change (user, old type, new type, who made it, time) in plain text in MySQL. It is deleted on uninstall or disconnect.

## 5. `report:read:list_users:admin`

Used to read the active/inactive hosts report, which tells SeatTrim whether a licensed user has hosted meetings in the last 30–180 days. Only the number of meetings hosted per period is stored, in plain text in MySQL. Meeting content is never read. Deleted on uninstall or disconnect.

## 6. `billing:read:plan_usage:admin`

Read-only. Used to show purchased versus assigned Licensed seats, so the admin can see unassigned seats and right-size at renewal. Only the seat totals are stored, in plain text in MySQL. SeatTrim never changes plans or billing. Deleted on uninstall or disconnect.

## 7. `meeting:read:list_meetings:admin`

Used only for downgrade candidates, to check whether they have upcoming scheduled meetings. Those users are protected from downgrade, because a Basic host is limited to 40 minutes. Only the count of upcoming meetings is stored, in plain text in MySQL. Meeting topics, content and participants are not stored. Deleted on uninstall or disconnect.

## Authentication (if asked)

OAuth access and refresh tokens are stored AES-256 encrypted in the database (Laravel encrypted casts), are never logged, and are revoked and deleted on uninstall.
