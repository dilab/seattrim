<p>Admins who want to know who isn't using their Zoom license usually start with the Inactive Hosts report. It's the right report, but what it measures and what it leaves out matter before you act on it. This explainer covers both, then shows how to turn the report into a list of seats you can safely reclaim.</p>

<h2>Hosting versus logging in: the metric that matters</h2>
<p>A Licensed seat mainly buys one thing: the ability to <em>host</em> meetings without the 40-minute limit that applies to Basic users, along with your plan's hosting features. Joining someone else's meeting works just as well with a Basic account.</p>
<p>That's why hosting, not logging in, is the right measure of whether someone needs a license. Someone might open Zoom every day to join calls, use chat, or join the all-hands, and still never start a meeting of their own. They're active users, but they're not using what the license pays for.</p>
<p>Zoom's reports use the same distinction. In Zoom's words, an active host is a user who "has hosted at least one meeting" in the period, and an inactive host is one who "has not hosted any meetings" in it.</p>

<h2>Where to find the report in the Zoom admin portal</h2>
<ol>
    <li>Sign in to the Zoom web portal as an owner or admin, or with a role that has access to usage reports.</li>
    <li>Go to <strong>Account Management → Reports</strong>.</li>
    <li>On the <strong>Usage Reports</strong> tab, open <strong>Inactive Hosts</strong>.</li>
    <li>Choose a date range and generate the report.</li>
</ol>
<p>Zoom describes the report as a list of users "who did not host a meeting or webinar during a specific period of time." Its counterpart, <strong>Active Hosts</strong>, lists who did host, with the number of meetings, participants and minutes. Reports only include meetings that ended at least 15 minutes ago, so a meeting running right now won't show up yet.</p>
<p>Usage reports need a Pro plan or higher. On a free account you won't see them.</p>

<h2>Limits: one month per report, six months of history</h2>
<p>Two limits shape how you use the report. They're stated plainly in Zoom's API documentation, and the portal works the same way:</p>
<ul>
    <li><strong>Each report covers at most about a month.</strong> You can't ask "who hasn't hosted in 90 days?" in one go.</li>
    <li><strong>History only goes back six months.</strong> Anything older is gone, so 180 days is the longest inactivity threshold you can check.</li>
</ul>
<p>To check a 90-day threshold, run three reports (this month, last month, the month before) and keep only the users who appear as inactive in <em>all three</em>. Anyone who hosted even once in any of them drops out. Export each to CSV and match on email address in a spreadsheet.</p>
<p>One more detail: the report lists users, not seats. It includes Basic users too, who don't cost anything. Before you act, filter the combined list down to users whose type is <strong>Licensed</strong>.</p>

<h2>Why last login time misleads</h2>
<p>User Management shows a last login time for each user, and it's tempting to sort by it. Don't rely on it for license decisions:</p>
<ul>
    <li><strong>It measures the wrong thing.</strong> A user who logs in every day to join meetings has a recent login and still doesn't need a license.</li>
    <li><strong>It's not exact.</strong> Zoom's API documentation says the value has a three-day buffer: logins within three days of the recorded one don't update it.</li>
    <li><strong>It's often empty for deactivated users</strong>, so it tells you nothing about them.</li>
</ul>
<p>Last login time is still useful as a second signal. A Licensed user who hasn't hosted in 90 days <em>and</em> hasn't logged in for 90 days is a very safe candidate. A user who hasn't hosted but logs in daily deserves a quick message before you change anything.</p>

<h2>From report to action: thresholds, guardrails and exclusions</h2>
<p>A list of inactive Licensed hosts is a list of candidates, not a list of people to downgrade. Three more steps turn it into something you can act on.</p>

<h3>1. Pick a threshold that fits how your company meets</h3>
<p>30 days catches the most seats and the most false alarms. 90 days is a sensible default for most companies. Use 180 days for teams whose meetings are seasonal, such as education, boards, or finance around quarter end.</p>

<h3>2. Apply the guardrails</h3>
<p>Remove anyone who shouldn't be downgraded regardless of activity: owners and admins; users on Zoom Workplace or Zoom United bundles; anyone with Webinar, Large Meeting, Zoom Phone or other add-ons; users with upcoming scheduled meetings; and accounts created in the last month. <a href="{{ route('blog.show', 'how-to-free-up-zoom-licenses') }}">The full checklist is in our guide to freeing up Zoom licenses</a>.</p>

<h3>3. Keep an exclusion list</h3>
<p>Some people should never be on the list: executives, their assistants, the person who runs the training program. Keep a short list of emails (or whole departments) and remove them every time. It's the fastest way to avoid a very awkward email.</p>

<p>Then warn the remaining users, give them a week to reply, and downgrade the rest to Basic in <strong>User Management → Users</strong>. Remember that downgrading frees seats but doesn't lower the bill until you reduce your license count, which Zoom applies at the end of your billing cycle.</p>

<h2>Running this every month</h2>
<p>By hand, this is three or six exports, a spreadsheet match, and a per-user check of bundles, add-ons and meetings. It's fine to do once, but tedious to repeat. <a href="{{ route('home') }}">SeatTrim</a> reads the same host reports through Zoom's API every night, combines the windows up to your threshold, applies the guardrails and your exclusions, and shows who is idle and why.</p>
