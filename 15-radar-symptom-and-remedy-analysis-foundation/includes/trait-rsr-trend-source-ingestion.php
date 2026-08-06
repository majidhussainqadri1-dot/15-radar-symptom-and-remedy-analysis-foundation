<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

trait RSR_Trend_Source_Ingestion_Trait
{
    /** @return array<int, array<string, mixed>> */
    public function list_sources(bool $include_config = false): array
    {
        $include_config = $include_config && RSR_Capabilities::can(RSR_Capabilities::MANAGE_SOURCES);
        global $wpdb;
        $rows = $wpdb->get_results(
            'SELECT * FROM ' . RSR_DB::table('sources') . ' ORDER BY name ASC, id ASC',
            ARRAY_A
        );
        return array_map(fn(array $row): array => $this->source_dto($row, $include_config), (array)$rows);
    }

    /** @return array<string, mixed>|WP_Error */
    public function save_source(array $input, ?string $public_id = null, ?int $expected_version = null)
    {
        if (!RSR_Capabilities::can(RSR_Capabilities::MANAGE_SOURCES)) {
            return new WP_Error('rsr_forbidden', __('You cannot manage Radar trend sources.', RSR_TEXT_DOMAIN), ['status' => 403]);
        }

        $validated = $this->validate_source($input);
        if (is_wp_error($validated)) {
            return $validated;
        }

        global $wpdb;
        $table = RSR_DB::table('sources');
        $now = current_time('mysql', true);
        $trace = RSR_Observability::trace_id();

        if ($public_id === null || $public_id === '') {
            $public_id = wp_generate_uuid4();
            $inserted = $wpdb->insert(
                $table,
                [
                    'public_id' => $public_id,
                    'provider_key' => $validated['provider_key'],
                    'name' => $validated['name'],
                    'dataset' => $validated['dataset'],
                    'edition' => $validated['edition'],
                    'license_code' => $validated['license_code'],
                    'review_date' => $validated['review_date'],
                    'territory' => $validated['territory'],
                    'restrictions' => $validated['restrictions'],
                    'quality_score' => $validated['quality_score'],
                    'credentials_ref' => $validated['credentials_ref'],
                    'rate_limit_per_hour' => $validated['rate_limit_per_hour'],
                    'cost_model' => $validated['cost_model'],
                    'config_json' => wp_json_encode($validated['config']),
                    'status' => $validated['status'],
                    'quota_remaining' => $validated['quota_remaining'],
                    'stale_after_seconds' => $validated['stale_after_seconds'],
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            if (!$inserted) {
                return new WP_Error('rsr_source_create_failed', __('The source could not be created.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
            RSR_DB::audit('create_source', 'trend_source', $public_id, 'trend_governance', 'success', null, ['trace_id' => $trace]);
        } else {
            $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
            if (!is_array($current)) {
                return new WP_Error('rsr_source_not_found', __('Trend source not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
            }
            $expected_version = $expected_version ?? (int)$current['version'];
            if ($expected_version !== (int)$current['version']) {
                return new WP_Error('rsr_version_conflict', __('The trend source changed in another session.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
            $affected = $wpdb->update(
                $table,
                [
                    'provider_key' => $validated['provider_key'],
                    'name' => $validated['name'],
                    'dataset' => $validated['dataset'],
                    'edition' => $validated['edition'],
                    'license_code' => $validated['license_code'],
                    'review_date' => $validated['review_date'],
                    'territory' => $validated['territory'],
                    'restrictions' => $validated['restrictions'],
                    'quality_score' => $validated['quality_score'],
                    'credentials_ref' => $validated['credentials_ref'],
                    'rate_limit_per_hour' => $validated['rate_limit_per_hour'],
                    'cost_model' => $validated['cost_model'],
                    'config_json' => wp_json_encode($validated['config']),
                    'status' => $validated['status'],
                    'quota_remaining' => $validated['quota_remaining'],
                    'stale_after_seconds' => $validated['stale_after_seconds'],
                    'version' => $expected_version + 1,
                    'updated_at' => $now,
                ],
                [
                    'public_id' => $public_id,
                    'version' => $expected_version,
                ]
            );
            if ($affected !== 1) {
                return new WP_Error('rsr_version_conflict', __('The trend source changed before the update completed.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
            RSR_DB::audit('update_source', 'trend_source', $public_id, 'trend_governance', 'success', null, ['trace_id' => $trace]);
        }

        update_option('rsr_comparison_cache_generation', (int)get_option('rsr_comparison_cache_generation', 1) + 1, false);
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
        return $this->source_dto((array)$row, true);
    }

    /** @return array<string, mixed>|WP_Error */
    public function run_ingestion(
        string $source_public_id,
        string $window_type,
        string $display_timezone = 'UTC',
        string $geography = 'global',
        string $idempotency_token = '',
        array $context = []
    ) {
        $is_cli = defined('WP_CLI') && WP_CLI;
        if (!RSR_Capabilities::can(RSR_Capabilities::RUN_INGESTION) && !wp_doing_cron() && !$is_cli) {
            return new WP_Error('rsr_forbidden', __('You cannot run Radar trend ingestion.', RSR_TEXT_DOMAIN), ['status' => 403]);
        }

        $window_type = sanitize_key($window_type);
        if (!in_array($window_type, self::WINDOWS, true)) {
            return new WP_Error('rsr_invalid_window', __('Unsupported trend window.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        try {
            $timezone_object = new DateTimeZone($display_timezone);
            $display_timezone = $timezone_object->getName();
            $anchor = null;
            if (!empty($context['anchor'])) {
                $anchor = $context['anchor'] instanceof DateTimeImmutable
                    ? $context['anchor']
                    : new DateTimeImmutable((string)$context['anchor'], $timezone_object);
            }
            $window = RSR_Domain::trend_window($window_type, $display_timezone, $anchor);
        } catch (Throwable $error) {
            return new WP_Error('rsr_invalid_timezone', __('The trend display time zone is invalid.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }

        $source_public_id = sanitize_text_field($source_public_id);
        $geography = RSR_Hardening::normalize_geography($geography);
        if (isset($context['manual_rows'])) {
            if (!is_array($context['manual_rows'])
                || count($context['manual_rows']) > RSR_Hardening::MAX_MANUAL_ROWS
                || RSR_Hardening::encoded_size($context['manual_rows']) > RSR_Hardening::MAX_MANUAL_BODY_BYTES
            ) {
                return new WP_Error('rsr_manual_rows_too_large', __('Manual trend input exceeds the governed size limit.', RSR_TEXT_DOMAIN), ['status' => 413]);
            }
        }
        global $wpdb;
        $source_table = RSR_DB::table('sources');
        $jobs = RSR_DB::table('jobs');
        $source = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$source_table} WHERE public_id = %s LIMIT 1", $source_public_id),
            ARRAY_A
        );
        if (!is_array($source)) {
            return new WP_Error('rsr_source_not_found', __('Trend source not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }

        $canonical_command = [
            'source_public_id' => $source_public_id,
            'source_version' => (int)$source['version'],
            'method_version' => RSR_Domain::METHOD_VERSION,
            'window_type' => $window_type,
            'display_timezone' => $display_timezone,
            'geography' => $geography,
            'window_start_utc' => $window['start_utc'],
            'window_end_utc' => $window['end_utc'],
        ];
        $idempotency_key = RSR_Domain::canonical_hash($canonical_command);
        $request_token_hash = $idempotency_token !== '' ? hash('sha256', trim($idempotency_token)) : null;
        $existing = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$jobs} WHERE idempotency_key = %s LIMIT 1", $idempotency_key),
            ARRAY_A
        );

        $retry_requested = !empty($context['retry']) || wp_doing_cron() || $is_cli;
        $max_attempts = max(1, min(8, (int)apply_filters('rsr_ingestion_max_attempts', 3, $source, $canonical_command)));
        $is_retry = false;
        if (is_array($existing)) {
            $retryable = (string)$existing['status'] === 'failed'
                && (int)$existing['attempts'] < $max_attempts
                && $retry_requested;
            if (!$retryable) {
                return $this->job_dto($existing, true);
            }
            $is_retry = true;
        }

        if (in_array((string)$source['status'], ['disabled', 'quota_exhausted'], true)) {
            return new WP_Error('rsr_source_unavailable', __('The trend source is unavailable.', RSR_TEXT_DOMAIN), ['status' => 409, 'source_status' => $source['status']]);
        }
        $provider = RSR_Provider_Registry::get((string)$source['provider_key']);
        if (!$provider) {
            return new WP_Error('rsr_provider_missing', __('The configured trend provider adapter is unavailable.', RSR_TEXT_DOMAIN), ['status' => 503]);
        }
        $provider_capabilities = $provider->capabilities();
        if ((string)($provider_capabilities['privacy'] ?? '') !== 'aggregated_only') {
            return new WP_Error('rsr_provider_privacy_contract_failed', __('The provider does not declare the required aggregate-only privacy contract.', RSR_TEXT_DOMAIN), ['status' => 503]);
        }
        if (!in_array($window_type, (array)($provider_capabilities['windows'] ?? []), true)) {
            return new WP_Error('rsr_provider_window_unsupported', __('The provider does not support this trend window.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        if ($geography !== 'global' && empty($provider_capabilities['geography'])) {
            return new WP_Error('rsr_provider_geography_unsupported', __('The provider does not support geographic trend segmentation.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        if (!empty($provider_capabilities['requires_credentials']) && empty($source['credentials_ref'])) {
            return new WP_Error('rsr_provider_credentials_reference_missing', __('The provider requires a configured secret-manager reference.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        if (!$is_retry) {
            $recent_jobs = (int)$wpdb->get_var(
                $wpdb->prepare(
                    'SELECT COUNT(*) FROM ' . $jobs . ' WHERE source_id = %d AND created_at >= %s',
                    (int)$source['id'],
                    gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS)
                )
            );
            if ($recent_jobs >= max(1, (int)($source['rate_limit_per_hour'] ?? 1000))) {
                return new WP_Error('rsr_source_rate_limited', __('The source ingestion rate limit has been reached.', RSR_TEXT_DOMAIN), ['status' => 429]);
            }
        }

        $trace = RSR_Observability::trace_id();
        $now = current_time('mysql', true);
        if ($is_retry) {
            $job_public_id = (string)$existing['public_id'];
            $attempts = (int)$existing['attempts'] + 1;
        } else {
            $job_public_id = wp_generate_uuid4();
            $attempts = 1;
            $created = $wpdb->insert(
                $jobs,
                [
                    'public_id' => $job_public_id,
                    'idempotency_key' => $idempotency_key,
                    'source_id' => (int)$source['id'],
                    'window_type' => $window_type,
                    'display_timezone' => $display_timezone,
                    'geography' => $geography,
                    'status' => 'scheduled',
                    'attempts' => 0,
                    'trace_id' => $trace,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            if (!$created) {
                $existing = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$jobs} WHERE idempotency_key = %s LIMIT 1", $idempotency_key), ARRAY_A);
                if (is_array($existing)) {
                    return $this->job_dto($existing, true);
                }
                return new WP_Error('rsr_job_create_failed', __('The ingestion job could not be created.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        }

        $lock_key = 'rsr_ingest_' . substr($idempotency_key, 0, 40);
        if (get_transient($lock_key)) {
            $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$jobs} WHERE public_id = %s LIMIT 1", $job_public_id), ARRAY_A);
            return is_array($current)
                ? $this->job_dto($current, true)
                : new WP_Error('rsr_ingestion_locked', __('This trend window is already being processed.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        set_transient($lock_key, 1, 15 * MINUTE_IN_SECONDS);

        try {
            $wpdb->update(
                $jobs,
                [
                    'status' => 'running',
                    'attempts' => $attempts,
                    'trace_id' => $trace,
                    'started_at' => $now,
                    'finished_at' => null,
                    'error_code' => null,
                    'result_json' => null,
                    'updated_at' => $now,
                ],
                ['public_id' => $job_public_id]
            );
            $rows = $provider->collect($source, $window, array_merge($context, ['geography' => $geography]));
            $result = $this->persist_observations($source, $window_type, $window, $geography, $rows, $trace);
            if ($result['accepted'] === 0 && $result['rejected'] > 0) {
                throw new RuntimeException('all_rows_rejected');
            }

            $report = null;
            if ($result['accepted'] > 0) {
                $report = $this->build_or_refresh_report($window_type, $display_timezone, $geography, $window, get_current_user_id());
                if (is_wp_error($report)) {
                    throw new RuntimeException($report->get_error_code());
                }
            }

            $status = $result['rejected'] > 0 ? 'partial' : 'succeeded';
            $finished = current_time('mysql', true);
            $job_result = [
                'accepted' => $result['accepted'],
                'rejected' => $result['rejected'],
                'deduplicated' => $result['deduplicated'],
                'no_data' => $result['accepted'] === 0,
                'report_public_id' => is_array($report) ? ($report['public_id'] ?? null) : null,
                'window' => $window,
                'attempt' => $attempts,
                'request_token_hash' => $request_token_hash,
            ];
            $wpdb->update(
                $jobs,
                [
                    'status' => $status,
                    'finished_at' => $finished,
                    'result_json' => wp_json_encode($job_result),
                    'updated_at' => $finished,
                ],
                ['public_id' => $job_public_id]
            );
            $source_update = [
                'status' => 'healthy',
                'last_success_at' => $finished,
                'last_error_code' => null,
                'updated_at' => $finished,
            ];
            if ($source['quota_remaining'] !== null) {
                $remaining = max(0, (int)$source['quota_remaining'] - 1);
                $source_update['quota_remaining'] = $remaining;
                if ($remaining === 0) {
                    $source_update['status'] = 'quota_exhausted';
                }
            }
            $wpdb->update($source_table, $source_update, ['id' => (int)$source['id']]);
            RSR_DB::audit('run_ingestion', 'trend_job', $job_public_id, 'trend_collection', 'success', null, [
                'trace_id' => $trace,
                'source_public_id' => $source_public_id,
                'window_type' => $window_type,
                'accepted' => $result['accepted'],
                'rejected' => $result['rejected'],
                'attempt' => $attempts,
                'request_token_hash' => $request_token_hash,
            ]);
        } catch (Throwable $error) {
            $finished = current_time('mysql', true);
            $code = sanitize_key($error->getMessage() !== '' ? $error->getMessage() : get_class($error));
            $wpdb->update(
                $jobs,
                [
                    'status' => 'failed',
                    'finished_at' => $finished,
                    'error_code' => substr($code, 0, 100),
                    'updated_at' => $finished,
                ],
                ['public_id' => $job_public_id]
            );
            $wpdb->update(
                $source_table,
                [
                    'status' => 'degraded',
                    'last_error_code' => substr($code, 0, 100),
                    'updated_at' => $finished,
                ],
                ['id' => (int)$source['id']]
            );
            RSR_Events::publish('RadarSourceDegraded.v1', 'trend_source', $source_public_id, [
                'source_public_id' => $source_public_id,
                'provider_key' => (string)$source['provider_key'],
                'error_code' => substr($code, 0, 100),
                'attempt' => $attempts,
                'max_attempts' => $max_attempts,
                'retryable' => $attempts < $max_attempts,
                'degraded_at' => gmdate('c'),
            ]);
            RSR_DB::audit('run_ingestion', 'trend_job', $job_public_id, 'trend_collection', 'failed', $code, [
                'trace_id' => $trace,
                'attempt' => $attempts,
                'max_attempts' => $max_attempts,
                'request_token_hash' => $request_token_hash,
            ]);
            RSR_Observability::log('error', 'trend_ingestion_failed', [
                'source_public_id' => $source_public_id,
                'job_public_id' => $job_public_id,
                'attempt' => $attempts,
                'error_class' => get_class($error),
                'error_code' => $code,
            ]);
        } finally {
            delete_transient($lock_key);
        }

        $job = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$jobs} WHERE public_id = %s LIMIT 1", $job_public_id), ARRAY_A);
        return $this->job_dto((array)$job, true);
    }
}
