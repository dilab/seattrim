<?php

namespace App\Zoom\Data;

/**
 * GET /accounts/me/plans/usage (docs/zoom-api-notes.md §6). `purchased`/`used`
 * are the base meeting plan; `bundlePlans` lists Workplace / United plans that
 * also carry licensed hosts. Everything is nullable because the endpoint may be
 * unavailable (fallback to UserSummary).
 */
final readonly class PlanUsage
{
    /**
     * @param  array<int, array{name: string, type: ?string, hosts: int, usage: int, pending: int}>  $bundlePlans
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public ?int $purchased,
        public ?int $used,
        public ?int $pending,
        public ?int $activeHosts,
        public ?string $planType,
        public array $bundlePlans,
        public array $raw,
        public bool $available = true,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $base = (array) ($data['plan_base'] ?? []);

        $bundles = [];
        foreach (['plan_zoom_one', 'plan_zoom_one_premier', 'plan_zoom_one_edu_school_campus_plus', 'plan_zoom_one_edu_premier'] as $key) {
            foreach ((array) ($data[$key] ?? []) as $plan) {
                if (is_array($plan)) {
                    $bundles[] = self::bundle($key, $plan);
                }
            }
        }
        foreach (['plan_united', 'plan_zoom_one_edu_student'] as $key) {
            if (isset($data[$key]) && is_array($data[$key]) && $data[$key] !== []) {
                $bundles[] = self::bundle($key, $data[$key]);
            }
        }

        return new self(
            purchased: isset($base['hosts']) ? (int) $base['hosts'] : null,
            used: isset($base['usage']) ? (int) $base['usage'] : null,
            pending: isset($base['pending']) ? (int) $base['pending'] : null,
            activeHosts: isset($base['active_hosts']) ? (int) $base['active_hosts'] : null,
            planType: isset($base['type']) ? (string) $base['type'] : null,
            bundlePlans: $bundles,
            raw: $data,
        );
    }

    public static function unavailable(string $reason): self
    {
        return new self(null, null, null, null, null, [], ['unavailable_reason' => $reason], available: false);
    }

    /**
     * @param  array<string, mixed>  $plan
     * @return array{name: string, type: ?string, hosts: int, usage: int, pending: int}
     */
    private static function bundle(string $name, array $plan): array
    {
        return [
            'name' => $name,
            'type' => isset($plan['type']) ? (string) $plan['type'] : null,
            'hosts' => (int) ($plan['hosts'] ?? 0),
            'usage' => (int) ($plan['usage'] ?? 0),
            'pending' => (int) ($plan['pending'] ?? 0),
        ];
    }

    /** Purchased licensed seats across base + bundle plans. */
    public function totalPurchased(): ?int
    {
        if ($this->purchased === null && $this->bundlePlans === []) {
            return null;
        }

        return ($this->purchased ?? 0) + array_sum(array_column($this->bundlePlans, 'hosts'));
    }

    public function totalUsed(): ?int
    {
        if ($this->used === null && $this->bundlePlans === []) {
            return null;
        }

        return ($this->used ?? 0) + array_sum(array_column($this->bundlePlans, 'usage'));
    }

    public function unassigned(): ?int
    {
        $purchased = $this->totalPurchased();
        $used = $this->totalUsed();

        if ($purchased === null || $used === null) {
            return null;
        }

        return max(0, $purchased - $used);
    }

    public function hasBundlePlans(): bool
    {
        return array_sum(array_column($this->bundlePlans, 'hosts')) > 0;
    }
}
