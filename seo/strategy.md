# Strategy — SeatTrim

DRAFT by `/seo-agent:init` on 2026-11-26 from the existing pages. Edit freely; `analysis` and `keywords` only read the sections marked ★.

## ICP
Zoom account owners and admins at organizations with roughly 50–2,000 Licensed seats (the home page addresses "Zoom admins with 50–2,000 seats"; paid tiers stop at 100, 500 and 2,000 seats) on Pro, Business, Education or Enterprise plans, usually billed annually. They do the work in the Zoom web portal themselves: offboarding leavers, chasing pending invites, answering to finance before renewal. The posts speak to that person as "you" and assume they can open User Management, Reports and Billing but have not read Zoom's API documentation.

## Positioning (one paragraph)
SeatTrim is an admin-managed Zoom Marketplace app that finds Licensed seats nobody uses (idle hosts, pending invites, unassigned seats, the rare deactivated user still licensed), downgrades them to Basic behind guardrails with one-click restore, and gives a seat count to reduce to at renewal. The content earns rank by being accurate about how Zoom actually behaves: every claim about Zoom is checked against Zoom's help center or API documentation (docs/zoom-api-notes.md §15), every procedure can be done by hand in the Zoom portal, and the posts say plainly what the product itself says, that a downgrade frees a seat and only a quantity reduction at renewal changes the bill.

## Clusters ★
<!-- one line per cluster: name (priority 1–3) — hub: /path or none — what the cluster covers -->
- reclaim-licenses (priority 1) — hub: /blog/how-to-free-up-zoom-licenses — finding unused Licensed seats and freeing them: where waste hides, downgrading Licensed to Basic one at a time or in bulk, the safe downgrade checklist, pending invites, unassigned seats
- inactive-users (priority 1) — hub: /blog/zoom-inactive-users-report-explained — measuring who uses a license: the Active/Inactive Hosts reports, hosting versus logging in, last login time, thresholds, exclusion lists
- offboarding (priority 2) — hub: /blog/zoom-deactivated-user-still-using-a-license — what happens to a leaver's license and data: deactivate, delete with data transfer, unlink, checking for leftovers
- renewal (priority 2) — hub: none — the bill itself: reducing license quantity, billing-cycle timing, contracts and resellers, working out the seat count to renew at

## Competitors
(none yet — `/seo-agent:keywords` proposes domains here as `- domain (proposed <date>, n shared keywords)`; delete the ones you don't want compared against)

## Never write about ★
- Anything implying a downgrade lowers the Zoom bill by itself, or a dollar saving "today"; the site's own line is "It never claims you saved money today"
- Zoom list prices, per-seat prices or discounts, unless quoted from a Zoom page linked in the article and dated; never an estimate
- Ways around Zoom licensing: sharing one Licensed account, beating the 40-minute limit, anything against Zoom's terms
- Competitor roundups, "best Zoom license management tools", "X alternative" pages
- Legal or compliance advice: recording consent, employee monitoring law, data-retention obligations
- Zoom behaviour that is not in Zoom's help center or API documentation; open items in docs/MARKETPLACE_SUBMISSION.md (plan usage on non-master accounts, PATCH on deactivated users, Zoom Rooms in the user list) stay out until verified on a real account
- Meeting content, recordings, chat or participant analytics; SeatTrim "never reads meeting content, recordings, chat or participant lists"
- Any wording that suggests SeatTrim is made, endorsed or certified by Zoom
- Other vendors' seats (Microsoft Teams, Slack, Google Workspace); the product only covers Zoom

## Caps ★
new_per_week: 1
refresh_per_week: 2
open_prs_max: 3
analysis_max_keywords: 5
analysis_budget_usd: 0.50
discovery_budget_usd: 1.00
discovery_per_cluster: 5
proposed_auto_accept_days: 7
word_range: 1200–2200
subject_words: zoom, license, licenses
location_code: 2840
language_code: en

## Auto-merge
[]

## Tracked keywords ★
<!-- - <keyword> (cluster: <name>) ; `keywords` appends proposals tagged `proposed <date>` -->
- how to free up zoom licenses (cluster: reclaim-licenses)
- zoom inactive users report (cluster: inactive-users)
- zoom deactivated user still using a license (cluster: offboarding)
- reduce zoom license quantity (cluster: renewal)
