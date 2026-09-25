<?php

namespace App\Zoom;

use Carbon\CarbonImmutable;
use RuntimeException;

/**
 * Loads tests/Fixtures/zoom/*.json and resolves "@days_ago:N" / "@days_ahead:N"
 * placeholders to ISO-8601 timestamps relative to now(), so fixtures stay
 * Zoom-shaped and never go stale.
 */
class FixtureSet
{
    /** @var array<string, mixed> */
    private array $cache = [];

    public function __construct(private readonly string $directory) {}

    public static function default(): self
    {
        return new self(base_path('tests/Fixtures/zoom'));
    }

    /** @return array<int|string, mixed> */
    public function load(string $name): array
    {
        if (! array_key_exists($name, $this->cache)) {
            $path = rtrim($this->directory, '/')."/{$name}.json";

            if (! is_file($path)) {
                throw new RuntimeException("Zoom fixture {$path} is missing. Run: php tests/Fixtures/zoom/generate.php");
            }

            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            $this->cache[$name] = self::resolve($decoded);
        }

        return $this->cache[$name];
    }

    /**
     * Same as load() for object-shaped fixtures (string keys only).
     *
     * @return array<string, mixed>
     */
    public function loadAssoc(string $name): array
    {
        $assoc = [];
        foreach ($this->load($name) as $key => $value) {
            $assoc[(string) $key] = $value;
        }

        return $assoc;
    }

    /**
     * Same as load() for list-shaped fixtures whose rows are objects.
     *
     * @return array<int, array<string, mixed>>
     */
    public function loadList(string $name): array
    {
        $rows = [];
        foreach ($this->load($name) as $row) {
            if (is_array($row)) {
                $assoc = [];
                foreach ($row as $key => $value) {
                    $assoc[(string) $key] = $value;
                }
                $rows[] = $assoc;
            }
        }

        return $rows;
    }

    public static function resolve(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(self::resolve(...), $value);
        }

        if (is_string($value) && preg_match('/^@days_(ago|ahead):(\d+)(?:T(\d{2}:\d{2}))?$/', $value, $m) === 1) {
            $date = CarbonImmutable::now()->startOfDay();
            $date = $m[1] === 'ago' ? $date->subDays((int) $m[2]) : $date->addDays((int) $m[2]);

            if (isset($m[3])) {
                [$h, $i] = explode(':', $m[3]);
                $date = $date->setTime((int) $h, (int) $i);
            } else {
                $date = $date->setTime(10, 0);
            }

            return $date->toIso8601ZuluString();
        }

        return $value;
    }
}
