# Review rubric — SeatTrim additions

Created by `/seo-agent:init` on 2026-11-26. `review` applies the default rubric (keyword/intent match, voice, never-write list, word range, product claims verified, images from the frame kit, links resolve, nothing lifted from a competitor) and then everything here. `feedback` proposes new lines when three rejections share a theme.

## Product-specific checks
- Every statement about how Zoom behaves (what deactivation does, report windows, when a quantity reduction applies, what a Basic user can do) has a source row in docs/zoom-api-notes.md §15, added in the same PR if new; quoted Zoom wording is verbatim
- Any article that mentions downgrading says, in its own words, that this frees a seat and the bill only changes when the license quantity is reduced, at the end of the billing cycle
- Zoom portal paths and control names are bold and joined with →, spelled as the existing posts spell them (**User Management → Users**, **Account Management → Reports**, **Change Licenses**); license types are capitalised "Licensed" and "Basic"
- The manual, in-portal way is complete before SeatTrim is mentioned; SeatTrim appears once, in the last section, linked with `route('home')`
- A spoke links to its cluster hub in the body with `route('blog.show', '<slug>')`; internal links never use hard-coded URLs
- Prices, seat limits and plan features match config/plans.php; guardrails, buckets and thresholds (30, 60, 90, 180 days) match resources/views/public/docs/using-seattrim.blade.php
- The post is registered in `App\Support\Blog::posts()` with title ≤ 70 characters, description ≤ 165, `type`, `published_at` and an `outline` matching its h2s, and is added to the dataset in tests/Feature/Public/PublicSiteTest.php
- The partial has no `<h1>`, no layout and no "Part of the guide" / "Read next" footer of its own; show.blade.php adds those

## Automatic fails
- A sentence stating or implying that downgrading or deactivating a user reduces the Zoom invoice immediately
- A Zoom price, limit or behaviour with no entry in docs/zoom-api-notes.md
- A dollar saving presented as real that does not come from the reader's own seat price
- Wording that implies affiliation with or endorsement by Zoom
- `php artisan test tests/Feature/Public/PublicSiteTest.php` fails
