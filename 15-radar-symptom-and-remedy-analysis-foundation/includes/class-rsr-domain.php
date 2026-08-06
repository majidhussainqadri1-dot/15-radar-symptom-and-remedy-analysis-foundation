<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

/**
 * Pure domain rules for Radar queries, report workflows, windows, and scoring.
 *
 * This class deliberately avoids WordPress APIs so core safety rules can be
 * tested in isolation and reused by REST, admin, cron, and CLI entry points.
 */
final class RSR_Domain
{
    public const MAX_COMPARE_REMEDIES = 3;
    public const MAX_FILTERS_PER_DIMENSION = 12;
    public const MAX_TOTAL_FILTER_VALUES = 60;
    public const MAX_QUERY_TEXT_LENGTH = 240;
    public const MAX_STUDY_TITLE_LENGTH = 160;
    public const MAX_STUDY_NOTES_BYTES = 32768;
    public const MAX_STUDIES_PER_DOCTOR = 500;
    public const METHOD_VERSION = 'rsr-trend-score-v1';

    /** @return array<int, string> */
    public static function allowed_source_licenses(): array
    {
        return [
            'public-domain',
            'cc0-1.0',
            'cc-by-4.0',
            'cc-by-sa-4.0',
            'odc-by-1.0',
            'proprietary-authorized',
            'internal-reviewed-aggregate',
            // Legacy values remain readable for controlled migration only.
            'permission',
            'cc-by',
            'cc-by-sa',
            'proprietary-licensed',
        ];
    }

    /** @return array<string, array<string, mixed>> */
    public static function dimensions(): array
    {
        return [
            'location' => [
                'label' => 'Location',
                'description' => 'Anatomical or functional location of the symptom.',
                'aliases' => ['site', 'part', 'organ'],
            ],
            'body_region' => [
                'label' => 'Body region',
                'description' => 'Broad body region used for structured narrowing.',
                'aliases' => ['region', 'system'],
            ],
            'sensation' => [
                'label' => 'Sensation',
                'description' => 'How the symptom is felt or described.',
                'aliases' => ['feeling', 'character'],
            ],
            'aggravation' => [
                'label' => 'Aggravation',
                'description' => 'Conditions that make the symptom worse.',
                'aliases' => ['worse', 'modalities_worse'],
            ],
            'amelioration' => [
                'label' => 'Amelioration',
                'description' => 'Conditions that make the symptom better.',
                'aliases' => ['better', 'modalities_better'],
            ],
            'concomitants' => [
                'label' => 'Concomitants',
                'description' => 'Symptoms occurring together with the main complaint.',
                'aliases' => ['accompanying', 'associated'],
            ],
            'causation' => [
                'label' => 'Causation',
                'description' => 'Known or suspected exciting and maintaining causes.',
                'aliases' => ['cause', 'ailments_from'],
            ],
            'time' => [
                'label' => 'Time',
                'description' => 'Time, periodicity, chronology, or recurrence pattern.',
                'aliases' => ['timing', 'periodicity'],
            ],
            'temperature' => [
                'label' => 'Temperature',
                'description' => 'Thermal state and response to heat or cold.',
                'aliases' => ['thermal', 'heat_cold'],
            ],
            'thirst' => [
                'label' => 'Thirst',
                'description' => 'Thirst intensity, frequency, and preferences.',
                'aliases' => ['drinking'],
            ],
            'appetite' => [
                'label' => 'Appetite and food',
                'description' => 'Appetite, cravings, aversions, and food modalities.',
                'aliases' => ['food', 'desires', 'aversions'],
            ],
            'sleep' => [
                'label' => 'Sleep',
                'description' => 'Sleep timing, quality, position, and associated symptoms.',
                'aliases' => ['dreams', 'sleep_position'],
            ],
            'mind' => [
                'label' => 'Mind and emotions',
                'description' => 'Mental, emotional, behavioral, and cognitive features.',
                'aliases' => ['mental', 'emotional'],
            ],
            'constitution' => [
                'label' => 'Constitution and temperament',
                'description' => 'Constitutional build, reactivity, and temperament.',
                'aliases' => ['temperament', 'constitution_type'],
            ],
            'miasm' => [
                'label' => 'Miasmatic assessment',
                'description' => 'Source-linked educational miasmatic classification.',
                'aliases' => ['miasmatic'],
            ],
            'discharges' => [
                'label' => 'Discharges and secretions',
                'description' => 'Character, color, odor, consistency, and effects.',
                'aliases' => ['secretions', 'excretions'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{valid: bool, query?: array<string, mixed>, errors: array<int, string>}
     */
    public static function validate_radar_query(array $input): array
    {
        $errors = [];
        $mode = strtoupper((string)($input['mode'] ?? 'AND'));
        if (!in_array($mode, ['AND', 'OR'], true)) {
            $errors[] = 'invalid_mode';
            $mode = 'AND';
        }

        $keyword = trim((string)($input['keyword'] ?? ''));
        if (self::length($keyword) > self::MAX_QUERY_TEXT_LENGTH) {
            $errors[] = 'keyword_too_long';
        }

        $known = self::dimensions();
        $filters = [];
        $total = 0;
        $raw_filters = isset($input['filters']) && is_array($input['filters']) ? $input['filters'] : [];

        foreach ($raw_filters as $dimension => $values) {
            $dimension = self::normalize_key((string)$dimension);
            if (!isset($known[$dimension])) {
                $errors[] = 'unknown_dimension:' . $dimension;
                continue;
            }

            $values = is_array($values) ? $values : [$values];
            $normalized = [];
            foreach ($values as $value) {
                $value = trim((string)$value);
                if ($value === '') {
                    continue;
                }
                if (self::length($value) > self::MAX_QUERY_TEXT_LENGTH) {
                    $errors[] = 'filter_value_too_long:' . $dimension;
                    continue;
                }
                $normalized[] = $value;
            }
            $normalized = array_values(array_unique($normalized));

            if (count($normalized) > self::MAX_FILTERS_PER_DIMENSION) {
                $errors[] = 'too_many_values:' . $dimension;
                $normalized = array_slice($normalized, 0, self::MAX_FILTERS_PER_DIMENSION);
            }

            if ($normalized !== []) {
                $filters[$dimension] = $normalized;
                $total += count($normalized);
            }
        }

        if ($total > self::MAX_TOTAL_FILTER_VALUES) {
            $errors[] = 'too_many_total_filters';
        }
        if ($keyword === '' && $filters === []) {
            $errors[] = 'empty_query';
        }

        $query = [
            'version' => '1',
            'mode' => $mode,
            'keyword' => $keyword,
            'filters' => $filters,
        ];
        $query['hash'] = self::canonical_hash($query);

        return [
            'valid' => $errors === [],
            'query' => $query,
            'errors' => array_values(array_unique($errors)),
        ];
    }

    /**
     * @param array<int, mixed> $ids
     * @return array{valid: bool, ids: array<int, string>, errors: array<int, string>}
     */
    public static function validate_comparison_ids(array $ids): array
    {
        $normalized = [];
        foreach ($ids as $id) {
            $id = trim((string)$id);
            if ($id === '') {
                continue;
            }
            if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/', $id)) {
                continue;
            }
            $normalized[] = $id;
        }
        $normalized = array_values(array_unique($normalized));
        $errors = [];

        if ($normalized === []) {
            $errors[] = 'no_remedies_selected';
        }
        if (count($normalized) > self::MAX_COMPARE_REMEDIES) {
            $errors[] = 'maximum_three_remedies';
        }

        return [
            'valid' => $errors === [],
            'ids' => array_slice($normalized, 0, self::MAX_COMPARE_REMEDIES),
            'errors' => $errors,
        ];
    }

    /** @param array<string, mixed> $query */
    public static function explain_query(array $query): string
    {
        $parts = [];
        $keyword = trim((string)($query['keyword'] ?? ''));
        if ($keyword !== '') {
            $parts[] = 'Keyword: ' . $keyword;
        }
        $filters = isset($query['filters']) && is_array($query['filters']) ? $query['filters'] : [];
        foreach ($filters as $dimension => $values) {
            $label = self::dimensions()[$dimension]['label'] ?? $dimension;
            $parts[] = $label . ': ' . implode(', ', array_map('strval', (array)$values));
        }
        $separator = strtoupper((string)($query['mode'] ?? 'AND')) === 'OR' ? ' OR ' : ' AND ';
        return implode($separator, $parts);
    }

    /**
     * @param array<string, mixed> $metrics
     * @return array{score: float, confidence: float, qualified: bool, method: string, components: array<string, float>}
     */
    public static function score_trend(array $metrics): array
    {
        $volume = max(0.0, (float)($metrics['volume'] ?? 0.0));
        $baseline = max(0.0, (float)($metrics['baseline'] ?? 0.0));
        $source_quality = self::clamp((float)($metrics['source_quality'] ?? 0.5), 0.0, 1.0);
        $coverage = self::clamp((float)($metrics['coverage'] ?? 0.5), 0.0, 1.0);
        $freshness = self::clamp((float)($metrics['freshness'] ?? 1.0), 0.0, 1.0);
        $minimum_volume = max(1.0, (float)($metrics['minimum_volume'] ?? 5.0));

        $change_ratio = $baseline > 0.0
            ? ($volume - $baseline) / max($baseline, 1.0)
            : ($volume > 0.0 ? 1.0 : 0.0);
        $change_component = self::clamp(($change_ratio + 1.0) / 3.0, 0.0, 1.0);
        $volume_component = self::clamp(log(1.0 + $volume) / log(1.0 + max(100.0, $minimum_volume * 20.0)), 0.0, 1.0);
        $confidence = self::clamp(
            (0.45 * $source_quality) + (0.30 * $coverage) + (0.25 * $freshness),
            0.0,
            1.0
        );
        $score = 100.0 * (
            (0.40 * $volume_component) +
            (0.30 * $change_component) +
            (0.20 * $source_quality) +
            (0.10 * $confidence)
        );

        $qualified = $volume >= $minimum_volume && $confidence >= 0.45;

        return [
            'score' => round(self::clamp($score, 0.0, 100.0), 2),
            'confidence' => round($confidence, 4),
            'qualified' => $qualified,
            'method' => self::METHOD_VERSION,
            'components' => [
                'volume' => round($volume_component, 4),
                'change' => round($change_component, 4),
                'source_quality' => round($source_quality, 4),
                'coverage' => round($coverage, 4),
                'freshness' => round($freshness, 4),
            ],
        ];
    }

    /** @return array<string, array<int, string>> */
    public static function report_transitions(): array
    {
        return [
            'draft' => ['analyst_review'],
            'analyst_review' => ['draft', 'editorial_review'],
            'editorial_review' => ['analyst_review', 'approved'],
            'approved' => ['published'],
            'published' => ['corrected', 'retracted'],
            'corrected' => ['corrected', 'retracted'],
            'retracted' => [],
        ];
    }

    public static function can_transition_report(string $from, string $to): bool
    {
        $transitions = self::report_transitions();
        return isset($transitions[$from]) && in_array($to, $transitions[$from], true);
    }

    /**
     * @return array{start_utc: string, end_utc: string, label: string, timezone: string}
     */
    public static function trend_window(
        string $window,
        string $timezone,
        ?DateTimeImmutable $reference = null
    ): array {
        try {
            $tz = new DateTimeZone($timezone);
        } catch (Throwable $error) {
            $tz = new DateTimeZone('UTC');
            $timezone = 'UTC';
        }

        $reference = ($reference ?? new DateTimeImmutable('now', $tz))->setTimezone($tz);
        $today = $reference->setTime(0, 0, 0);
        $window = strtolower($window);

        switch ($window) {
            case 'daily':
                $end = $today;
                $start = $end->modify('-1 day');
                $label = $start->format('Y-m-d');
                break;
            case 'weekly':
                $end = $today;
                $start = $end->modify('-7 days');
                $label = $start->format('Y-m-d') . '/' . $end->format('Y-m-d');
                break;
            case 'monthly':
                $end = $today->modify('first day of this month');
                $start = $end->modify('-1 month');
                $label = $start->format('Y-m');
                break;
            case 'yearly':
                $end = $today->setDate((int)$today->format('Y'), 1, 1);
                $start = $end->modify('-1 year');
                $label = $start->format('Y');
                break;
            default:
                throw new InvalidArgumentException('Unsupported trend window.');
        }

        return [
            'start_utc' => $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'end_utc' => $end->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'label' => $label,
            'timezone' => $timezone,
        ];
    }

    /** @param mixed $value */
    public static function canonical_json($value): string
    {
        $normalized = self::sort_recursive($value);
        $encoded = json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION
        );
        if ($encoded === false) {
            throw new InvalidArgumentException('Value cannot be serialized.');
        }
        return $encoded;
    }

    /** @param mixed $value */
    public static function canonical_hash($value): string
    {
        return hash('sha256', self::canonical_json($value));
    }

    public static function normalize_key(string $key): string
    {
        $key = strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9_]+/', '_', $key) ?? '';
        return trim($key, '_');
    }

    private static function length(string $value): int
    {
        return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
    }

    private static function clamp(float $value, float $min, float $max): float
    {
        return max($min, min($max, $value));
    }

    /** @param mixed $value @return mixed */
    private static function sort_recursive($value)
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map([self::class, 'sort_recursive'], $value);
        }

        ksort($value, SORT_STRING);
        foreach ($value as $key => $item) {
            $value[$key] = self::sort_recursive($item);
        }
        return $value;
    }
}
