<?php

namespace App\Support;

/**
 * Hub-and-spoke SEO cluster (brief §10). Each post's body lives in
 * resources/views/public/blog/posts/{slug}.blade.php; sources are listed in
 * docs/zoom-api-notes.md §15.
 */
class Blog
{
    /** @return array<string, array{title: string, description: string, type: string, published_at: string, outline: array<int, string>}> */
    public static function posts(): array
    {
        return [
            'how-to-free-up-zoom-licenses' => [
                'title' => 'How to free up Zoom licenses (without cutting anyone off)',
                'description' => 'A practical guide for Zoom admins: find deactivated, pending and idle licensed users, downgrade them safely, and right-size your seat count at renewal.',
                'type' => 'hub',
                'published_at' => '2026-10-01',
                'outline' => [
                    'Where Zoom seats go to waste: deactivated users, pending invites, idle hosts, unassigned seats',
                    'Why downgrading does not lower the bill until renewal',
                    'The safe downgrade checklist: bundles, add-ons, upcoming meetings, admins',
                    'Reading the inactive hosts report (link to spoke)',
                    'Deactivated users that still hold a license (link to spoke)',
                    'Right-sizing at renewal: the one number that matters',
                ],
            ],
            'zoom-inactive-users-report-explained' => [
                'title' => 'Zoom inactive users report, explained',
                'description' => 'What Zoom\'s active/inactive hosts report actually measures, its one-month window and six-month limit, and how to turn it into a list of licenses to reclaim.',
                'type' => 'spoke',
                'published_at' => '2026-10-08',
                'outline' => [
                    'Hosting versus logging in: the metric that matters',
                    'Where to find the report in the Zoom admin portal',
                    'Limits: one month per report, six months of history',
                    'Why last login time misleads (3-day buffer, empty for deactivated users)',
                    'From report to action: thresholds, guardrails and exclusions',
                ],
            ],
            'zoom-deactivated-user-still-using-a-license' => [
                'title' => 'Zoom deactivated user still using a license? Here is why, and the fix',
                'description' => 'Deactivating a Zoom user removes their license, but the seat stays on your bill until renewal. Where the seat goes, how to check for leftovers, and what to do: reassign, reduce, or delete with data transfer.',
                'type' => 'spoke',
                'published_at' => '2026-10-15',
                'outline' => [
                    'Deactivating removes the license, not the seat',
                    'Check: are any deactivated users still Licensed?',
                    'Options: reassign, reduce the quantity, delete with data transfer, unlink',
                    'Automating the clean-up',
                ],
            ],
        ];
    }

    /** @return array{title: string, description: string, type: string, published_at: string, outline: array<int, string>}|null */
    public static function find(string $slug): ?array
    {
        return self::posts()[$slug] ?? null;
    }
}
