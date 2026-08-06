<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

trait RSR_Trend_Processing_Trait
{
    /**
     * Runs idempotent due-window reconciliation for every active source.
     * External adapters may use their own queues; this command remains the
     * canonical scheduler and records every attempt.
     */
    public function reconcile_due_windows(): void
    {
        $sources = $this->list_sources(false);
        if ($sources === []) {
            return;
        }
        $site_timezone = wp_timezone_string() ?: 'UTC';
        foreach ($sources as $source) {
            if (in_array((string)$source['status'], ['disabled', 'quota_exhausted'], true)) {
                continue;
            }
            foreach (self::WINDOWS as $window_type) {
                $window = RSR_Domain::trend_window($window_type, $site_timezone);
                $token = implode('|', ['cron', $source['public_id'], $window_type, $site_timezone, 'global', $window['start_utc'], $window['end_utc']]);
                $this->run_ingestion((string)$source['public_id'], $window_type, $site_timezone, 'global', $token, ['cron' => true]);
            }
        }
    }

    /** @return array<string, int> */
    public function retention_cleanup(): array
    {
        global $wpdb;
        $settings = (array)get_option('rsr_settings', []);
        $observation_days = max(90, (int)($settings['observation_retention_days'] ?? 1095));
        $job_days = max(30, (int)($settings['job_retention_days'] ?? 180));
        $audit_days = max(180, (int)($settings['audit_retention_days'] ?? 2555));
        $outbox_days = max(30, (int)($settings['outbox_retention_days'] ?? 180));

        $cutoffs = [
            'observations' => gmdate('Y-m-d H:i:s', time() - $observation_days * DAY_IN_SECONDS),
            'jobs' => gmdate('Y-m-d H:i:s', time() - $job_days * DAY_IN_SECONDS),
            'audit' => gmdate('Y-m-d H:i:s', time() - $audit_days * DAY_IN_SECONDS),
            'outbox' => gmdate('Y-m-d H:i:s', time() - $outbox_days * DAY_IN_SECONDS),
        ];
        $counts = [];
        $counts['observations'] = (int)$wpdb->query($wpdb->prepare('DELETE FROM ' . RSR_DB::table('observations') . ' WHERE created_at < %s', $cutoffs['observations']));
        $counts['jobs'] = (int)$wpdb->query($wpdb->prepare("DELETE FROM " . RSR_DB::table('jobs') . " WHERE status IN ('succeeded','partial','failed','reconciled') AND created_at < %s", $cutoffs['jobs']));
        $counts['audit'] = (int)$wpdb->query($wpdb->prepare('DELETE FROM ' . RSR_DB::table('audit') . ' WHERE created_at < %s', $cutoffs['audit']));
        $counts['outbox'] = (int)$wpdb->query($wpdb->prepare("DELETE FROM " . RSR_DB::table('outbox') . " WHERE status IN ('delivered','dead') AND created_at < %s", $cutoffs['outbox']));
        $counts['inbox'] = (int)$wpdb->query($wpdb->prepare("DELETE FROM " . RSR_DB::table('inbox') . " WHERE status IN ('delivered','failed') AND created_at < %s", $cutoffs['outbox']));
        $counts['rate_limits'] = (int)$wpdb->query($wpdb->prepare('DELETE FROM ' . RSR_DB::table('rate_limits') . ' WHERE window_expires_at < %s', gmdate('Y-m-d H:i:s', time() - DAY_IN_SECONDS)));
        return $counts;
    }

    /** @param array<string, mixed> $source @param array<int, array<string, mixed>> $rows @return array{accepted:int,rejected:int,deduplicated:int} */
    private function persist_observations(array $source, string $window_type, array $window, string $geography, array $rows, string $trace): array
    {
        global $wpdb;
        $table = RSR_DB::table('observations');
        $accepted = 0;
        $rejected = 0;
        $deduplicated = 0;
        $seen = [];
        $now = current_time('mysql', true);

        foreach (array_slice($rows, 0, RSR_Hardening::MAX_MANUAL_ROWS) as $row) {
            if (!is_array($row)) {
                $rejected++;
                continue;
            }
            $topic_key = sanitize_title((string)($row['alias_key'] ?? $row['topic_key'] ?? $row['topic_label'] ?? ''));
            $topic_label = sanitize_text_field((string)($row['topic_label'] ?? ''));
            $source_reference = substr(sanitize_text_field((string)($row['source_reference'] ?? '')), 0, RSR_Hardening::MAX_SOURCE_REFERENCE_LENGTH);
            $privacy_scan = RSR_PII_Scanner::scan([
                'topic_label' => $topic_label,
                'source_reference' => $source_reference,
                'geography' => (string)($row['geography'] ?? $geography),
            ]);
            if (!$privacy_scan['safe']) {
                $rejected++;
                continue;
            }
            $row_geography = RSR_Hardening::normalize_geography((string)apply_filters('rsr_normalize_geography', RSR_Hardening::normalize_geography((string)($row['geography'] ?? $geography)), $row, $source));
            $spam_probability = RSR_Hardening::finite_float($row['spam_probability'] ?? 0.0, 0.0, 1.0, 0.0) ?? 0.0;
            $bot_probability = RSR_Hardening::finite_float($row['bot_probability'] ?? 0.0, 0.0, 1.0, 0.0) ?? 0.0;
            if ($topic_key === '' || $topic_label === '' || $source_reference === '' || $spam_probability >= 0.80 || $bot_probability >= 0.80) {
                $rejected++;
                continue;
            }
            $repost_factor = RSR_Hardening::finite_float($row['repost_factor'] ?? 1.0, 1.0, 1000000.0, 1.0) ?? 1.0;
            $volume = RSR_Hardening::finite_float($row['volume'] ?? 0, 0.0, RSR_Hardening::MAX_ABSOLUTE_VOLUME, null);
            $baseline = RSR_Hardening::finite_float($row['baseline'] ?? 0, 0.0, RSR_Hardening::MAX_ABSOLUTE_VOLUME, null);
            if ($volume === null || $baseline === null) {
                $rejected++;
                continue;
            }
            $normalized_volume = $volume / $repost_factor;
            $normalized_baseline = $baseline / $repost_factor;
            $dedupe = $topic_key . '|' . $row_geography;
            if (isset($seen[$dedupe])) {
                $deduplicated++;
                continue;
            }
            $seen[$dedupe] = true;

            $score = RSR_Domain::score_trend([
                'volume' => $normalized_volume,
                'baseline' => $normalized_baseline,
                'source_quality' => (float)$source['quality_score'],
                'coverage' => (float)($row['coverage'] ?? 1.0),
                'freshness' => (float)($row['freshness'] ?? 1.0),
                'minimum_volume' => (float)apply_filters('rsr_trend_minimum_volume', 5.0, $source, $window_type),
            ]);
            $normalized = [
                'score' => $score['score'],
                'qualified' => $score['qualified'],
                'method' => $score['method'],
                'components' => $score['components'],
                'source_reference' => $source_reference,
                'spam_probability' => $spam_probability,
                'bot_probability' => $bot_probability,
                'repost_factor' => $repost_factor,
                'normalization_version' => 'rsr-normalization-v1',
            ];

            $existing_id = (int)$wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE source_id = %d AND topic_key = %s AND geography = %s AND window_type = %s AND window_start_utc = %s AND window_end_utc = %s LIMIT 1",
                    (int)$source['id'],
                    $topic_key,
                    $row_geography,
                    $window_type,
                    $window['start_utc'],
                    $window['end_utc']
                )
            );
            $data = [
                'source_id' => (int)$source['id'],
                'topic_key' => $topic_key,
                'topic_label' => $topic_label,
                'geography' => $row_geography,
                'window_type' => $window_type,
                'window_start_utc' => $window['start_utc'],
                'window_end_utc' => $window['end_utc'],
                'volume' => $normalized_volume,
                'baseline' => $normalized_baseline,
                'source_quality' => (float)$source['quality_score'],
                'confidence' => $score['confidence'],
                'normalized_json' => wp_json_encode($normalized),
                'trace_id' => $trace,
                'observed_at' => $now,
                'created_at' => $now,
            ];
            if ($existing_id > 0) {
                unset($data['created_at']);
                $written = $wpdb->update($table, $data, ['id' => $existing_id]);
                if ($written === false) {
                    $rejected++;
                    continue;
                }
                $deduplicated++;
            } else {
                $data['public_id'] = wp_generate_uuid4();
                $written = $wpdb->insert($table, $data);
                if (!$written) {
                    $race_id = (int)$wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT id FROM {$table} WHERE source_id = %d AND topic_key = %s AND geography = %s AND window_type = %s AND window_start_utc = %s AND window_end_utc = %s LIMIT 1",
                            (int)$source['id'],
                            $topic_key,
                            $row_geography,
                            $window_type,
                            $window['start_utc'],
                            $window['end_utc']
                        )
                    );
                    if ($race_id > 0) {
                        $deduplicated++;
                        continue;
                    }
                    $rejected++;
                    continue;
                }
            }
            $accepted++;
        }
        return compact('accepted', 'rejected', 'deduplicated');
    }

    /** @return array<string, mixed>|WP_Error */
    private function build_or_refresh_report(string $window_type, string $timezone, string $geography, array $window, int $actor)
    {
        global $wpdb;
        $observations = RSR_DB::table('observations');
        $sources_table = RSR_DB::table('sources');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT o.*, s.public_id AS source_public_id, s.name AS source_name, s.dataset, s.edition, s.license_code, s.review_date, s.territory, s.restrictions, s.quality_score, s.stale_after_seconds, s.last_success_at
                 FROM {$observations} o
                 INNER JOIN {$sources_table} s ON s.id = o.source_id
                 WHERE s.status <> 'disabled' AND o.window_type = %s AND o.geography = %s AND o.window_start_utc = %s AND o.window_end_utc = %s
                 ORDER BY o.topic_key ASC, o.confidence DESC",
                $window_type,
                $geography,
                $window['start_utc'],
                $window['end_utc']
            ),
            ARRAY_A
        );
        if ($rows === []) {
            return new WP_Error('rsr_no_observations', __('No normalized observations are available for this trend window.', RSR_TEXT_DOMAIN));
        }

        $topics = [];
        $sources = [];
        $confidence_values = [];
        $stale_at_epoch = null;
        foreach ((array)$rows as $row) {
            $normalized = json_decode((string)$row['normalized_json'], true) ?: [];
            if (empty($normalized['qualified'])) {
                continue;
            }
            $topic_key = (string)$row['topic_key'];
            if (!isset($topics[$topic_key])) {
                $topics[$topic_key] = [
                    'topic_key' => $topic_key,
                    'topic_label' => (string)$row['topic_label'],
                    'score' => 0.0,
                    'confidence' => 0.0,
                    'volume' => 0.0,
                    'baseline' => 0.0,
                    'source_count' => 0,
                    'source_refs' => [],
                ];
            }
            $topics[$topic_key]['score'] += (float)($normalized['score'] ?? 0.0) * (float)$row['confidence'];
            $topics[$topic_key]['confidence'] += (float)$row['confidence'];
            $topics[$topic_key]['volume'] += (float)$row['volume'];
            $topics[$topic_key]['baseline'] += (float)$row['baseline'];
            $topics[$topic_key]['source_count']++;
            $topics[$topic_key]['source_refs'][] = (string)$row['source_public_id'];
            $confidence_values[] = (float)$row['confidence'];

            $sources[(string)$row['source_public_id']] = [
                'public_id' => (string)$row['source_public_id'],
                'name' => (string)$row['source_name'],
                'dataset' => (string)$row['dataset'],
                'edition' => $row['edition'] ?? null,
                'license_code' => (string)$row['license_code'],
                'review_date' => (string)$row['review_date'],
                'territory' => (string)$row['territory'],
                'restrictions' => $row['restrictions'] ?? null,
                'quality_score' => (float)$row['quality_score'],
                'last_success_at' => $row['last_success_at'] ? mysql_to_rfc3339((string)$row['last_success_at']) : null,
            ];
            $candidate = strtotime((string)$row['observed_at'] . ' UTC') + (int)$row['stale_after_seconds'];
            $stale_at_epoch = $stale_at_epoch === null ? $candidate : min($stale_at_epoch, $candidate);
        }

        if ($topics === []) {
            return [
                'public_id' => null,
                'status' => 'no_qualified_topics',
                'window_type' => $window_type,
                'geography' => $geography,
            ];
        }

        foreach ($topics as &$topic) {
            $weight = max(0.00001, (float)$topic['confidence']);
            $topic['score'] = round((float)$topic['score'] / $weight, 6);
            $topic['confidence'] = round(min(1.0, $weight / max(1, (int)$topic['source_count'])), 6);
            $topic['change_ratio'] = $topic['baseline'] > 0
                ? round(($topic['volume'] - $topic['baseline']) / max(1.0, $topic['baseline']), 6)
                : ($topic['volume'] > 0 ? 1.0 : 0.0);
            $topic['source_refs'] = array_values(array_unique($topic['source_refs']));
        }
        unset($topic);
        usort($topics, static function (array $a, array $b): int {
            $score = (float)$b['score'] <=> (float)$a['score'];
            if ($score !== 0) {
                return $score;
            }
            $volume = (float)$b['volume'] <=> (float)$a['volume'];
            return $volume !== 0 ? $volume : strcmp((string)$a['topic_label'], (string)$b['topic_label']);
        });
        $topics = array_slice(array_values($topics), 0, 100);
        $confidence = $confidence_values === [] ? 0.0 : array_sum($confidence_values) / count($confidence_values);

        $result_payload = [
            'method_version' => RSR_Domain::METHOD_VERSION,
            'window_type' => $window_type,
            'window' => $window,
            'geography' => $geography,
            'generated_at' => gmdate('c'),
            'topics' => $topics,
            'limitations' => [
                __('Trend scores describe source activity, not disease prevalence.', RSR_TEXT_DOMAIN),
                __('Trend reports are informational and are not diagnosis, prescription, or emergency guidance.', RSR_TEXT_DOMAIN),
                __('Coverage and provider availability may differ by geography and time window.', RSR_TEXT_DOMAIN),
            ],
        ];
        $sources_payload = array_values($sources);
        $reports = RSR_DB::table('reports');
        $existing = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$reports} WHERE window_type = %s AND geography = %s AND display_timezone = %s AND window_start_utc = %s AND window_end_utc = %s LIMIT 1",
                $window_type,
                $geography,
                $timezone,
                $window['start_utc'],
                $window['end_utc']
            ),
            ARRAY_A
        );
        $now = current_time('mysql', true);
        $stale_at = gmdate('Y-m-d H:i:s', (int)$stale_at_epoch);
        if (is_array($existing)) {
            if (in_array((string)$existing['status'], ['published', 'corrected', 'retracted'], true)) {
                return $this->report_dto($existing, true);
            }
            $written = $wpdb->update(
                $reports,
                [
                    'method_version' => RSR_Domain::METHOD_VERSION,
                    'results_json' => RSR_Domain::canonical_json($result_payload),
                    'sources_json' => RSR_Domain::canonical_json($sources_payload),
                    'confidence' => round($confidence, 5),
                    'stale_at' => $stale_at,
                    'status' => 'draft',
                    'version' => (int)$existing['version'] + 1,
                    'updated_at' => $now,
                ],
                ['id' => (int)$existing['id'], 'version' => (int)$existing['version']]
            );
            if ($written !== 1) {
                return new WP_Error('rsr_report_refresh_conflict', __('The trend report changed before refresh completed.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
            $public_id = (string)$existing['public_id'];
        } else {
            $public_id = wp_generate_uuid4();
            $inserted = $wpdb->insert(
                $reports,
                [
                    'public_id' => $public_id,
                    'window_type' => $window_type,
                    'display_timezone' => $timezone,
                    'geography' => $geography,
                    'window_start_utc' => $window['start_utc'],
                    'window_end_utc' => $window['end_utc'],
                    'method_version' => RSR_Domain::METHOD_VERSION,
                    'results_json' => RSR_Domain::canonical_json($result_payload),
                    'sources_json' => RSR_Domain::canonical_json($sources_payload),
                    'confidence' => round($confidence, 5),
                    'status' => 'draft',
                    'version' => 1,
                    'stale_at' => $stale_at,
                    'created_by' => $actor,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            if (!$inserted) {
                return new WP_Error('rsr_report_create_failed', __('The trend report could not be created.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        }
        $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$reports} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
        return $this->report_dto((array)$fresh, true);
    }
}
