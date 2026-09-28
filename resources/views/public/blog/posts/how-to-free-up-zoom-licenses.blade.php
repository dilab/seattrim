<p>Most Zoom accounts pay for more Licensed seats than anyone uses. Nobody is being careless. People leave, projects end, invitations go unanswered, and the seat count set at the last renewal quietly becomes the seat count for the next one.</p>
<p>This guide shows where the waste hides, how to reclaim seats without cutting off someone who needs them, and the one number to take into your renewal. Everything here can be done in the Zoom web portal. We point out where a tool saves time, but you don't need one.</p>

<h2>Where Zoom seats go to waste</h2>
<p>Unused seats almost always fall into one of four groups:</p>
<table>
    <thead>
        <tr><th>Group</th><th>What it looks like</th><th>How to spot it</th></tr>
    </thead>
    <tbody>
        <tr><td>Unassigned seats</td><td>You bought more seats than are assigned to anyone.</td><td>Compare purchased and assigned licenses on the billing page.</td></tr>
        <tr><td>Pending invites</td><td>A user was invited as Licensed and never accepted.</td><td>Filter User Management → Users by the Pending status.</td></tr>
        <tr><td>Idle hosts</td><td>Active, Licensed users who haven't hosted a meeting in months.</td><td>The Inactive Hosts usage report.</td></tr>
        <tr><td>Deactivated users</td><td>Former staff who were deactivated. Zoom removes their license, and the seat joins the unassigned pool.</td><td>Deactivated filter in User Management → Users.</td></tr>
    </tbody>
</table>
<p><strong>Unassigned seats</strong> are the easiest to miss because they don't belong to anyone. The license count on your plan is what you pay for. The number of Licensed users is what you use. The gap between them is pure cost.</p>
<p><strong>Pending invites</strong> build up when someone is invited with a Licensed seat and the email is ignored, lost in a spam filter, or sent to the wrong address. Zoom counts pending users separately in its plan usage figures, so a pile of old invitations can make your account look fuller than it is.</p>
<p><strong>Idle hosts</strong> are usually the biggest group. A Licensed seat mainly buys the ability to <em>host</em> meetings without the 40-minute limit and with the plan's features. Someone who only joins other people's meetings doesn't need one: anyone can join a meeting with a Basic account. <a href="{{ route('blog.show', 'zoom-inactive-users-report-explained') }}">The inactive users report</a> is how you find them.</p>
<p><strong>Deactivated users</strong> are where a lot of confusion starts. Zoom's documentation says deactivating a user removes their licenses. So the seat isn't lost, but it doesn't disappear from your bill either. It just moves into the unassigned pool. We explain this in <a href="{{ route('blog.show', 'zoom-deactivated-user-still-using-a-license') }}">why a deactivated user can still cost you a license</a>.</p>

<h2>Why downgrading doesn't lower the bill until renewal</h2>
<p>This is the most important point in this guide, and it surprises many admins: <strong>changing a user from Licensed to Basic doesn't reduce what you pay</strong>. It frees a seat you already paid for. Your bill only changes when you lower the license quantity on your plan.</p>
<p>Zoom's help center is explicit about timing: a reduction in license quantity "will take effect at the end of your current billing cycle, not immediately". You also have to unassign the licenses from users before you can reduce the count. If your account has custom pricing or a signed contract, you usually can't reduce the count in the portal and have to go through Zoom or your reseller.</p>
<p>So reclaiming seats is a two-step job:</p>
<ol>
    <li><strong>During the year:</strong> free seats that aren't being used, so new hires get a seat you already own and you don't buy more.</li>
    <li><strong>Before renewal:</strong> lower the license quantity to what you actually need, so the next term costs less.</li>
</ol>
<p>Step 1 on its own still saves money if your company is growing, because you stop buying seats. Step 2 is where the bill goes down.</p>

<h2>The safe downgrade checklist</h2>
<p>Downgrading the wrong person is worse than wasting a seat. A Basic user can host meetings of up to 40 minutes only, and loses any features that came with the license. Before you change anyone from Licensed to Basic, check these:</p>
<ul>
    <li><strong>Owners and admins.</strong> Leave them alone. They run the account, and a mistake there is the hardest one to undo.</li>
    <li><strong>Bundles.</strong> On Zoom Workplace or Zoom United plans the meeting license is tied to other products, such as Zoom Phone. Removing it can take more with it, and Zoom may refuse the change.</li>
    <li><strong>Add-ons.</strong> Webinar, Large Meeting, Zoom Phone and similar licenses are assigned per user. Someone who never hosts meetings may still run your webinars.</li>
    <li><strong>Upcoming meetings.</strong> A user with scheduled meetings may be about to host a long call. Zoom also blocks the change to Basic for users with upcoming Zoom Events sessions.</li>
    <li><strong>New starters.</strong> Someone created last week hasn't had time to host anything. Give new accounts at least a month.</li>
    <li><strong>Seasonal hosts.</strong> Board meetings, quarterly reviews, training cohorts and teaching terms don't happen every month. Check with the team before you trust a 30-day window.</li>
</ul>
<p>Before you downgrade anyone, tell them. A short email with a date and a way to say "I still need this" avoids almost all complaints. Anyone who needs their seat back can have it restored in a minute, as long as you still have an unassigned license.</p>

<h3>How to downgrade in the Zoom portal</h3>
<p>For one user: go to <strong>User Management → Users</strong>, click <strong>Edit</strong> on the user, set the Zoom Workplace license to <strong>Unassigned</strong>, tick <strong>Zoom Meetings Basic</strong> so they can still host short meetings, and save.</p>
<p>For many users: select them in the same list and use <strong>Change Licenses</strong>. For large batches, use <strong>Import → Update Users</strong> with a CSV file. In the Licenses column, set <em>Unassigned with Zoom Meetings Basic</em>, and leave other columns blank so they don't change.</p>

<h2>Reading the inactive hosts report</h2>
<p>Zoom's <strong>Inactive Hosts</strong> report, under <strong>Account Management → Reports → Usage Reports</strong>, lists users who didn't host a meeting or webinar in a period you choose. It's the right starting point because it measures hosting, the one thing a license is for. It has limits, though: each report covers at most about a month, and history goes back only six months. <a href="{{ route('blog.show', 'zoom-inactive-users-report-explained') }}">Our guide to the report</a> covers how to combine several months and what to do with the list.</p>

<h2>Deactivated users and the seats they leave behind</h2>
<p>When someone leaves, most admins deactivate them instead of deleting them, so their recordings and meetings are kept. According to Zoom, deactivation removes their licenses, but that doesn't make the bill smaller. The seat sits unassigned until you give it to someone else or reduce your quantity. If you have deactivated users who still show as Licensed, clean them up. <a href="{{ route('blog.show', 'zoom-deactivated-user-still-using-a-license') }}">This explainer</a> covers what to check and your options: downgrade, reassign, or delete with a data transfer.</p>

<h2>Right-sizing at renewal: the one number that matters</h2>
<p>Before your renewal date, work out one number:</p>
<p><strong>Seats to keep = Licensed users who host + the hires you expect before the next renewal + a small buffer.</strong></p>
<p>Everything above that number is a seat you'll pay for all next term without using. To make the number reliable:</p>
<ol>
    <li>Clear pending invites that are more than a few weeks old.</li>
    <li>Downgrade idle hosts who passed the checklist, after warning them.</li>
    <li>Check that deactivated users hold no licenses.</li>
    <li>Count Licensed users again and add your hiring plan.</li>
    <li>Reduce the license quantity in billing <em>before</em> the renewal date, or tell your Zoom account manager the number if you're on a contract.</li>
</ol>
<p>Start at least 30 days before renewal. Warnings, replies and contract changes all take time, and a reduction you make the day after renewal waits a whole term.</p>

<h2>Doing this every month instead of once a year</h2>
<p>All of the above works by hand. Where it gets hard is keeping up with it: the reports cover a month at a time, bundle and add-on checks are per user, and the list changes every week. <a href="{{ route('home') }}">SeatTrim</a> runs these checks every night: it groups unused seats into the categories above, applies the guardrails, and shows your renewal target. You still decide what gets downgraded, and every change can be restored.</p>
