<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

/**
 * Safe built-in provider for reviewed manual/imported aggregates.
 *
 * It does not perform network requests. Administrators may seed normalized rows
 * through the governed admin/REST command; external adapters are plugins.
 */
final class RSR_Manual_Provider implements RSR_Trend_Provider
{
    public function key(): string
    {
        return 'manual';
    }

    public function capabilities(): array
    {
        return [
            'network' => false,
            'windows' => ['daily', 'weekly', 'monthly', 'yearly'],
            'geography' => true,
            'requires_credentials' => false,
            'supports_replay' => true,
            'privacy' => 'aggregated_only',
        ];
    }

    public function collect(array $source, array $window, array $context = []): array
    {
        $rows = [];
        $config = isset($source['config_json'])
            ? json_decode((string)$source['config_json'], true)
            : [];
        if (is_array($config) && isset($config['rows']) && is_array($config['rows'])) {
            $rows = $config['rows'];
        }

        $option_rows = get_option('rsr_manual_trend_rows', []);
        if (is_array($option_rows) && isset($option_rows[$source['public_id']])) {
            $rows = (array)$option_rows[$source['public_id']];
        }

        if (isset($context['manual_rows']) && is_array($context['manual_rows'])) {
            $rows = $context['manual_rows'];
        }

        $normalized = [];
        if (count($rows) > RSR_Hardening::MAX_MANUAL_ROWS || RSR_Hardening::encoded_size($rows) > RSR_Hardening::MAX_MANUAL_BODY_BYTES) {
            throw new InvalidArgumentException('Manual trend rows exceed the governed input limit.');
        }
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $topic = sanitize_text_field((string)($row['topic'] ?? $row['topic_label'] ?? ''));
            if ($topic === '') {
                continue;
            }
            $normalized[] = [
                'topic_key' => self::topic_key($topic),
                'topic_label' => $topic,
                'geography' => RSR_Hardening::normalize_geography((string)($row['geography'] ?? $context['geography'] ?? 'global')),
                'volume' => RSR_Hardening::finite_float($row['volume'] ?? 0, 0.0, RSR_Hardening::MAX_ABSOLUTE_VOLUME, 0.0),
                'baseline' => RSR_Hardening::finite_float($row['baseline'] ?? 0, 0.0, RSR_Hardening::MAX_ABSOLUTE_VOLUME, 0.0),
                'coverage' => RSR_Hardening::finite_float($row['coverage'] ?? 1.0, 0.0, 1.0, 1.0),
                'freshness' => RSR_Hardening::finite_float($row['freshness'] ?? 1.0, 0.0, 1.0, 1.0),
                'source_reference' => substr(sanitize_text_field((string)($row['source_reference'] ?? $source['dataset'] ?? 'manual')), 0, RSR_Hardening::MAX_SOURCE_REFERENCE_LENGTH),
                'alias_key' => sanitize_title((string)($row['alias_key'] ?? '')),
                'spam_probability' => RSR_Hardening::finite_float($row['spam_probability'] ?? 0.0, 0.0, 1.0, 0.0),
                'bot_probability' => RSR_Hardening::finite_float($row['bot_probability'] ?? 0.0, 0.0, 1.0, 0.0),
                'repost_factor' => RSR_Hardening::finite_float($row['repost_factor'] ?? 1.0, 1.0, 1000000.0, 1.0),
            ];
        }

        return $normalized;
    }

    private static function topic_key(string $topic): string
    {
        $topic = strtolower(remove_accents($topic));
        $topic = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}]+/u', '-', $topic) ?? '';
        return trim($topic, '-');
    }
}
