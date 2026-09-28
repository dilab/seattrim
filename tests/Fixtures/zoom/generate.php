<?php

/**
 * Generates the Zoom fixture set used by FakeZoomClient (tests + demo mode).
 *
 *   php tests/Fixtures/zoom/generate.php
 *
 * Deterministic: same output every run. Dates are "@days_ago:N" placeholders
 * resolved at load time (see App\Zoom\FixtureSet), so the population never ages.
 *
 * Population (69 users), designed to exercise every bucket and guardrail:
 *   - owner + 2 admins (protected: role)
 *   - 18 healthy licensed hosts (hosted within 30 days)
 *   - 4 licensed hosts whose last meeting was 30–60 days ago, 3 at 60–90, 3 at 90–180, 6 never
 *   - 1 deactivated still licensed (rare: Zoom normally removes licenses on deactivation), 6 deactivated basic
 *   - 4 pending licensed, 1 pending basic
 *   - 6 active basic, 1 "unassigned without meetings basic" (type 4)
 *   - 2 Zoom Rooms (licensed, idle; update fails with Zoom error 200)
 *   - bundles: 2 Workplace (zoom_one_type), 1 United (plan_united_type)
 *   - add-ons: 2 phone, 1 webinar, 1 large meeting (all idle, all protected)
 *   - 2 idle users with upcoming meetings (protected)
 *   - 2 new hires (< 30 days old, never hosted; protected)
 */
$users = [];
$hosting = [];
$features = [];
$upcoming = [];
$rooms = [];

$n = 0;
$make = function (string $slug, string $name, int $type, string $status, array $extra = []) use (&$users, &$n): string {
    $n++;
    $id = sprintf('u%02d_%s', $n, preg_replace('/[^a-z0-9]+/', '', $slug));
    [$first, $last] = array_pad(explode(' ', $name, 2), 2, '');
    $users[] = array_merge([
        'id' => $id,
        'first_name' => $first,
        'last_name' => $last,
        'display_name' => $name,
        'email' => "{$slug}@example.edu",
        'type' => $type,
        'status' => $status,
        'dept' => 'Operations',
        'group_ids' => [],
        'role_id' => '2',
        'user_created_at' => '@days_ago:400',
        'last_login_time' => '@days_ago:3',
        'last_client_version' => '6.4.0.51205(mac)',
        'timezone' => 'America/Chicago',
        'verified' => 1,
        'login_types' => [100],
        'zoom_one_type' => 0,
    ], $extra);

    return $id;
};

$host = function (string $id, array $daysAgo) use (&$hosting): void {
    $hosting[$id] = array_map(fn (int $d) => "@days_ago:{$d}", $daysAgo);
};

// Owner and admins. Owner hosts; one admin is idle (still protected by role).
$id = $make('dana.owner', 'Dana Owner', 2, 'active', ['role_id' => '0', 'role_name' => 'Owner', 'dept' => 'IT']);
$host($id, [2, 9, 16]);
$id = $make('sam.admin', 'Sam Admin', 2, 'active', ['role_id' => '1', 'role_name' => 'Admin', 'dept' => 'IT']);
$host($id, [4, 20]);
$id = $make('lee.admin', 'Lee Admin', 2, 'active', ['role_id' => '1', 'role_name' => 'Admin', 'dept' => 'IT']);
$host($id, [140]);

// 18 healthy licensed hosts.
$depts = ['Math', 'Science', 'English', 'Admin', 'Counseling', 'Athletics'];
for ($i = 1; $i <= 18; $i++) {
    $id = $make("teacher{$i}", "Teacher {$i}", 2, 'active', ['dept' => $depts[$i % 6], 'group_ids' => ['grp_teachers']]);
    $host($id, [($i % 25) + 1, ($i % 12) + 3, 28]);
}

// Idle by window.
for ($i = 1; $i <= 4; $i++) {
    $id = $make("idle30_{$i}", "Idle Thirty {$i}", 2, 'active', ['dept' => 'Science', 'last_login_time' => '@days_ago:35']);
    $host($id, [35 + $i, 50]);
}
for ($i = 1; $i <= 3; $i++) {
    $id = $make("idle60_{$i}", "Idle Sixty {$i}", 2, 'active', ['dept' => 'English', 'last_login_time' => '@days_ago:70']);
    $host($id, [61 + $i, 85]);
}
for ($i = 1; $i <= 3; $i++) {
    $id = $make("idle90_{$i}", "Idle Ninety {$i}", 2, 'active', ['dept' => 'Admin', 'last_login_time' => '@days_ago:120']);
    $host($id, [100 + ($i * 20)]);
}
for ($i = 1; $i <= 6; $i++) {
    $id = $make("never{$i}", "Never Hosted {$i}", 2, 'active', ['dept' => 'Athletics', 'last_login_time' => '@days_ago:200']);
}

// Deactivated. Zoom removes licenses on deactivation, so only one anomaly keeps Licensed.
for ($i = 1; $i <= 5; $i++) {
    $id = $make("gone{$i}", "Former Staff {$i}", $i === 1 ? 2 : 1, 'inactive', ['dept' => 'Admin', 'last_login_time' => '']);
}
for ($i = 1; $i <= 2; $i++) {
    $id = $make("gonebasic{$i}", "Former Basic {$i}", 1, 'inactive', ['last_login_time' => '']);
}

// Pending invites.
for ($i = 1; $i <= 4; $i++) {
    $id = $make("invite{$i}", "Pending Invite {$i}", 2, 'pending', ['user_created_at' => '@days_ago:'.(20 * $i), 'last_login_time' => '']);
}
$id = $make('invitebasic', 'Pending Basic', 1, 'pending', ['last_login_time' => '']);

// Active basic and one type-4.
for ($i = 1; $i <= 6; $i++) {
    $id = $make("basic{$i}", "Basic User {$i}", 1, 'active');
}
$id = $make('joinonly', 'Join Only', 4, 'active');

// Zoom Rooms: appear as licensed users, idle. Updates fail with error 200.
foreach (['room.library', 'room.boardroom'] as $i => $slug) {
    $id = $make($slug, ucfirst(explode('.', $slug)[1]).' Room', 2, 'active', ['dept' => 'Facilities', 'last_login_time' => '@days_ago:2']);
    $rooms[] = $id;
}

// Bundles (idle so the guardrail is what protects them).
$id = $make('bundle.workplace1', 'Wanda Workplace', 2, 'active', ['zoom_one_type' => 16, 'license_info_list' => [['license_type' => 'ZOOM_WORKPLACE_BUNDLE', 'license_option' => 16, 'subscription_id' => 'SUBREF-1']]]);
$id = $make('bundle.workplace2', 'Walter Workplace', 2, 'active', ['zoom_one_type' => 4]);
$id = $make('bundle.united', 'Uma United', 2, 'active', ['plan_united_type' => '16']);

// Add-ons (idle).
$id = $make('phone1', 'Pat Phone', 2, 'active', ['dept' => 'Front Desk']);
$features[$id] = ['zoom_phone' => true, 'webinar' => false, 'large_meeting' => false, 'meeting_capacity' => 300];
$id = $make('phone2', 'Priya Phone', 2, 'active', ['dept' => 'Front Desk']);
$features[$id] = ['zoom_phone' => true, 'webinar' => false, 'large_meeting' => false, 'meeting_capacity' => 300];
$id = $make('webinar1', 'Wes Webinar', 2, 'active', ['dept' => 'Communications']);
$features[$id] = ['zoom_phone' => false, 'webinar' => true, 'webinar_capacity' => 500, 'large_meeting' => false, 'meeting_capacity' => 300];
$id = $make('largemeeting1', 'Lara Large', 2, 'active', ['dept' => 'Communications']);
$features[$id] = ['zoom_phone' => false, 'webinar' => false, 'large_meeting' => true, 'large_meeting_capacity' => 500, 'meeting_capacity' => 500];

// Idle with upcoming meetings.
foreach (['upcoming1' => 'Ulla Upcoming', 'upcoming2' => 'Uri Upcoming'] as $slug => $name) {
    $id = $make($slug, $name, 2, 'active', ['dept' => 'Counseling']);
    $upcoming[$id] = [
        ['id' => 91000000000 + $n, 'topic' => 'Parent conference', 'start_time' => '@days_ahead:9T15:00', 'type' => 2, 'duration' => 60],
    ];
}

// New hires.
$id = $make('newhire1', 'Nadia Newhire', 2, 'active', ['user_created_at' => '@days_ago:6']);
$id = $make('newhire2', 'Noah Newhire', 2, 'active', ['user_created_at' => '@days_ago:21']);

if (count($users) !== 69) {
    fwrite(STDERR, 'Expected 69 users, got '.count($users).PHP_EOL);
    exit(1);
}

$licensedActive = count(array_filter($users, fn ($u) => $u['type'] === 2 && $u['status'] !== 'pending'));
$pendingLicensed = count(array_filter($users, fn ($u) => $u['type'] === 2 && $u['status'] === 'pending'));

// Plan usage: 9 seats bought and not assigned to anyone (5 never assigned, 4 released by leavers).
$planUsage = [
    'plan_base' => [
        'type' => 'yearly',
        'hosts' => $licensedActive + $pendingLicensed + 9,
        'usage' => $licensedActive + $pendingLicensed,
        'pending' => $pendingLicensed,
        'active_hosts' => 21,
    ],
    'plan_zoom_one' => [
        ['type' => 'zoom_one_business_plus_yearly', 'hosts' => 3, 'usage' => 3, 'pending' => 0],
    ],
    'plan_webinar' => [['type' => 'webinar500_yearly', 'hosts' => 1, 'usage' => 1, 'pending' => 0]],
    'plan_large_meeting' => [['type' => 'large500_yearly', 'hosts' => 1, 'usage' => 1, 'pending' => 0]],
    'plan_zoom_rooms' => ['type' => 'zoom_rooms_yearly', 'hosts' => 2, 'usage' => 2],
    'plan_recording' => ['type' => 'cmr_yearly', 'free_storage' => '5 GB', 'free_storage_usage' => '1.2 GB', 'plan_storage' => '0', 'plan_storage_usage' => '0', 'plan_storage_exceed' => '0'],
];

$dir = __DIR__;
$write = function (string $name, mixed $data) use ($dir): void {
    file_put_contents("{$dir}/{$name}.json", json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL);
};

$write('users', $users);
$write('hosting', $hosting);
$write('features', $features);
$write('upcoming_meetings', $upcoming);
$write('rooms', $rooms);
$write('plan_usage', $planUsage);

echo 'Wrote '.count($users).' users, '.count($hosting).' hosting records, '.count($features).' feature records, '.count($upcoming).' upcoming-meeting records, '.count($rooms).' rooms.'.PHP_EOL;
