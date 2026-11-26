# Voice — SeatTrim

DRAFT by `/seo-agent:init` on 2026-11-26, derived from 3 existing articles. Every rule below cites the page it came from. Edit freely; `write`, `refresh` and `review` read all of it.

Paths are short for `resources/views/public/blog/posts/<name>.blade.php`: **hub** = how-to-free-up-zoom-licenses, **report** = zoom-inactive-users-report-explained, **deactivated** = zoom-deactivated-user-still-using-a-license.

## Tone
- Address the admin as "you", and blame nobody for the waste. "Nobody is being careless. People leave, projects end, invitations go unanswered" (hub)
- Say the uncomfortable fact outright and early, in bold if it is the point of the piece. "changing a user from Licensed to Basic doesn't reduce what you pay" (hub)
- Quote Zoom for what Zoom does, in Zoom's words, and name the source in the sentence. "Zoom's help center is clear about what deactivation does: "Deactivating a user will remove all licenses associated with a user."" (deactivated)
- The reader can do everything without the product, and the article says so. "Everything here can be done in the Zoom web portal. We point out where a tool saves time, but you don't need one." (hub)
- Give a number and a default, then say when to deviate. "30 days catches the most seats and the most false alarms. 90 days is a sensible default for most companies." (report)
- Admit where reality differs from the documentation. "not every account looks the way the documentation describes" (deactivated)
- Short declarative sentences, contractions throughout, and a dry aside at most once per article. "It's the fastest way to avoid a very awkward email." (report)
- Direct instructions, including negative ones. "Don't rely on it for license decisions" (report); "Leave them alone." (hub)

## Structure
- Opening: one paragraph that names the admin's situation, then one that says what the article covers. No question, no definition, no statistic. "Someone leaves, you deactivate their Zoom account, and a month later the invoice hasn't changed." (deactivated); "This explainer covers both, then shows how to turn the report into a list of seats you can safely reclaim." (report)
- Headings: sentence case, statements or noun phrases, often with a colon carrying the takeaway: "Deactivating removes the license, not the seat" (deactivated), "Limits: one month per report, six months of history" (report), "Right-sizing at renewal: the one number that matters" (hub). One question heading exists, phrased as a check: "Check: are any deactivated users still Licensed?" (deactivated). h3s are numbered options or steps: "1. Reassign the seat" (deactivated).
- Order: what is actually happening → how to see it in the Zoom portal → options or steps → doing it routinely. No FAQ section and no "Conclusion" heading in any of the three.
- The partial starts at the first `<p>`; the h1, the Guide/Explainer pill, the date, "Part of the guide" / "Read next" and the "Run a free Zoom license audit" link come from resources/views/public/blog/show.blade.php.
- Cross-links sit inside the sentence that needs them. The hub links each spoke where its topic comes up; each spoke links back to the hub once: "The full checklist is in our guide to freeing up Zoom licenses." (report)
- SeatTrim is named once, in the last section, after the manual method is complete, as the first word of a sentence linked to the home page, describing what it does and what stays with the admin: "SeatTrim runs these checks every night: it groups unused seats into the categories above, applies the guardrails, and shows your renewal target. You still decide what gets downgraded, and every change can be restored." (hub)
- Last-section headings name the routine, not the product: "Doing this every month instead of once a year" (hub), "Running this every month" (report), "Automating the clean-up" (deactivated). The article ends on the SeatTrim paragraph; no sign-off line.

## Formatting
- Paragraphs of 2–5 short sentences; nothing longer in the three posts.
- One table at most, for a taxonomy the rest of the article refers back to: Group / What it looks like / How to spot it (hub).
- `<ol>` for portal procedures and ordered plans; `<ul>` with a bold lead-in ending in a full stop for checklists: "<strong>Owners and admins.</strong> Leave them alone." (hub)
- Bold for Zoom UI paths and controls, joined with →: "<strong>User Management → Users</strong>", "<strong>Change Licenses</strong>" (hub); and for the one sentence the reader must not miss. `<em>` for a single stressed word: "<em>host</em>", "<em>all three</em>" (report).
- "Licensed" and "Basic" are capitalised as license types; "seat" is what is paid for, "license" is what is assigned (deactivated: "Deactivating removes the license, not the seat").
- A formula gets its own bold paragraph: "Seats to keep = Licensed users who host + the hires you expect before the next renewal + a small buffer." (hub)
- No images, code blocks, callout boxes or external links in the three posts; Zoom is quoted but not linked (sources are kept in docs/zoom-api-notes.md §15). Internal links use `route('blog.show', '<slug>')` and `route('home')`.
- Spelling as written: "clean-up", "right-sizing", "add-ons", "sign in".

## Good paragraphs
1. hub — states the counter-intuitive fact, bolds it, and finishes with the mechanism in two plain sentences:
   > This is the most important point in this guide, and it surprises many admins: **changing a user from Licensed to Basic doesn't reduce what you pay**. It frees a seat you already paid for. Your bill only changes when you lower the license quantity on your plan.
2. deactivated — opens on the reader's exact situation, then corrects the assumption without talking down:
   > Someone leaves, you deactivate their Zoom account, and a month later the invoice hasn't changed. It's a common frustration for Zoom admins. It feels like the deactivated user is still holding the license. Usually they aren't. The seat is still on your plan, and that's a different problem with a different fix.
3. report — a procedure the reader can follow from the paragraph alone, with the reason for each step:
   > To check a 90-day threshold, run three reports (this month, last month, the month before) and keep only the users who appear as inactive in *all three*. Anyone who hosted even once in any of them drops out. Export each to CSV and match on email address in a spreadsheet.

## Bad paragraphs
Rewrites of the three paragraphs above, written for this file to show what to avoid. None of these appear on the site.

1. Fault: promises a saving the product itself says does not happen, with an unsourced number.
   > Downgrading unused users from Licensed to Basic is the fastest way to cut your Zoom spend. Most companies save up to 30% instantly, and the savings show up on your very next invoice.
2. Fault: rhetorical opener, hype, and no information; the reader's situation is never named.
   > Ever wondered why your Zoom bill never seems to go down? In today's fast-paced world of hybrid work, license management can be a real headache. The good news is that there's a seamless way to unlock hidden savings.
3. Fault: vague where the original is exact (no window count, no matching rule) and pushes the product before the manual method.
   > Checking for inactivity over longer periods can be tricky, so it's important to note that you may need to look at more than one report. Rather than wrestling with spreadsheets, let SeatTrim's robust automation streamline the whole process for you.

## Banned words and patterns
<!-- Words never seen in the existing articles that AI drafts tend to add, plus anything the owner bans. Start from this list and remove any the articles actually use. -->
- delve, leverage, seamless(ly), robust, streamline, unlock, elevate, empower, game-changer, cutting-edge
- "in today's fast-paced world", "whether you're a … or a …", "it's important to note", "at the end of the day"
- rhetorical openers ("Ever wondered…?"), triple adjectives, exclamation marks
- em-dash chains; more than one parenthetical per paragraph
- Nothing was removed from the default list: none of these words, no exclamation mark and no em dash appears in the three posts.
- "save", "savings", "cut costs" with SeatTrim or a downgrade as the subject; the posts say "frees a seat" (hub) and the home page says "It never claims you saved money today" (resources/views/public/landing.blade.php)
- "simply", "just" before an instruction that takes several portal steps
- "Zoom-certified", "official", "partner" for SeatTrim; the footer says "SeatTrim is not affiliated with Zoom Video Communications, Inc." (resources/views/layouts/public.blade.php)

## Product claims
<!-- Where a verifiable fact about the product lives in the repo (feature lists, config, docs, pricing). `write` may only state product facts found there; `review` checks them. -->
- config/plans.php — tiers, yearly prices (Free, Starter $290, Growth $790, Scale $1,990), seat limits (100 / 500 / 2,000), paid features, 14-day grace period
- resources/views/public/pricing.blade.php — plan feature wording and the pricing FAQ (annual only, what happens past the tier)
- resources/views/public/docs/using-seattrim.blade.php — buckets, guardrails, bundle handling, downgrade and restore flow, bulk cap, automation, exclusions, renewal reminders (60, 30, 7 days)
- resources/views/public/docs/index.blade.php — the seven Zoom scopes and what each is used for; what SeatTrim never reads
- resources/views/public/docs/faq.blade.php and remove-the-app.blade.php — stored data, hosting, deletion on disconnect
- resources/views/public/landing.blade.php and audit.blade.php — nightly scan, thresholds (30, 60, 90, 180 days), free audit requirements (Pro, Business, Education or Enterprise; owner or admin role)
- docs/zoom-api-notes.md — every verified Zoom behaviour; §15 lists the sources behind the blog posts. A new Zoom claim needs a new row there first
- docs/MARKETPLACE_SUBMISSION.md — open items not yet verified on a real Zoom account; do not state these as fact
