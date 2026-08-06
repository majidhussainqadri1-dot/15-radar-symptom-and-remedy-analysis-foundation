<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

/**
 * Canonical trend-source, ingestion, scoring, report, and editorial workflow.
 *
 * The service accepts aggregated observations only. It never ingests private
 * Saved Studies, clinical records, messages, identity evidence, or payment data.
 */
final class RSR_Trend_Service
{
    /** @var array<int, string> */
    private const WINDOWS = ['daily', 'weekly', 'monthly', 'yearly'];

    /** @var array<int, string> */
    private const SOURCE_STATES = ['configured', 'healthy', 'degraded', 'quota_exhausted', 'disabled'];

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
        $geography = sanitize_key($geography) ?: 'global';
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

        // The canonical window identity, not a caller-chosen token, prevents
        // duplicate jobs for the same source/window/geography.
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

    /** @return array<int, array<string, mixed>> */
    public function public_reports(array $filters = []): array
    {
        global $wpdb;
        $table = RSR_DB::table('reports');
        $where = ["status IN ('published','corrected')"];
        $params = [];
        $window = sanitize_key((string)($filters['window_type'] ?? ''));
        if ($window !== '' && in_array($window, self::WINDOWS, true)) {
            $where[] = 'window_type = %s';
            $params[] = $window;
        }
        $geography = sanitize_key((string)($filters['geography'] ?? ''));
        if ($geography !== '') {
            $where[] = 'geography = %s';
            $params[] = $geography;
        }
        $limit = max(1, min(100, (int)($filters['limit'] ?? 20)));
        $offset = max(0, (int)($filters['offset'] ?? 0));
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY published_at DESC, id DESC LIMIT %d OFFSET %d';
        $params[] = $limit;
        $params[] = $offset;
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(fn(array $row): array => $this->report_dto($row, false), (array)$rows);
    }

    /** @return array<int, array<string, mixed>> */
    public function manage_reports(array $filters = []): array
    {
        if (!$this->can_manage_reports()) {
            return [];
        }
        global $wpdb;
        $table = RSR_DB::table('reports');
        $status = sanitize_key((string)($filters['status'] ?? ''));
        $where = '1=1';
        $params = [];
        if ($status !== '') {
            $where .= ' AND status = %s';
            $params[] = $status;
        }
        $limit = max(1, min(200, (int)($filters['limit'] ?? 50)));
        $params[] = $limit;
        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY updated_at DESC, id DESC LIMIT %d";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        return array_map(fn(array $row): array => $this->report_dto($row, true), (array)$rows);
    }

    /** @return array<string, mixed>|WP_Error */
    public function get_report(string $public_id, bool $management = false)
    {
        global $wpdb;
        $table = RSR_DB::table('reports');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", sanitize_text_field($public_id)), ARRAY_A);
        if (!is_array($row)) {
            return new WP_Error('rsr_report_not_found', __('Trend report not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }
        if (!$management && !in_array((string)$row['status'], ['published', 'corrected', 'retracted'], true)) {
            return new WP_Error('rsr_report_not_found', __('Trend report not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }
        if ($management && !$this->can_manage_reports()) {
            return new WP_Error('rsr_forbidden', __('You cannot review trend reports.', RSR_TEXT_DOMAIN), ['status' => 403]);
        }
        return $this->report_dto($row, $management);
    }

    /** @return array<string, mixed>|WP_Error */
    public function transition_report(string $public_id, string $to_state, int $expected_version, string $reason = '')
    {
        $to_state = sanitize_key($to_state);
        if (!in_array($to_state, ['draft', 'analyst_review', 'editorial_review', 'approved', 'published'], true)) {
            return new WP_Error('rsr_correction_command_required', __('Corrections and retractions require the dedicated correction command and a public notice.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $reason = sanitize_textarea_field($reason);
        if ($reason === '') {
            return new WP_Error('rsr_transition_reason_required', __('A documented transition reason is required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $capability = $to_state === 'published' ? RSR_Capabilities::PUBLISH_REPORTS : RSR_Capabilities::REVIEW_REPORTS;
        if (!RSR_Capabilities::can($capability)) {
            return new WP_Error('rsr_forbidden', __('You cannot perform this report transition.', RSR_TEXT_DOMAIN), ['status' => 403]);
        }

        global $wpdb;
        $table = RSR_DB::table('reports');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
        if (!is_array($row)) {
            return new WP_Error('rsr_report_not_found', __('Trend report not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }
        if ((int)$row['version'] !== $expected_version) {
            return new WP_Error('rsr_version_conflict', __('The report changed in another session.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        if (!RSR_Domain::can_transition_report((string)$row['status'], $to_state)) {
            return new WP_Error('rsr_invalid_report_transition', __('The report cannot move to that state.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        if (in_array($to_state, ['approved', 'published'], true)) {
            $gate = $this->report_release_gate($row, $to_state);
            if (is_wp_error($gate)) {
                return $gate;
            }
        }

        $actor = get_current_user_id();
        $now = current_time('mysql', true);
        $fields = [
            'status' => $to_state,
            'version' => (int)$row['version'] + 1,
            'updated_at' => $now,
        ];
        if ($to_state === 'draft') {
            $fields['reviewed_by'] = 0;
            $fields['approved_by'] = 0;
        } elseif (in_array($to_state, ['analyst_review', 'editorial_review'], true)) {
            $fields['reviewed_by'] = $actor;
            $fields['approved_by'] = 0;
        }
        if ($to_state === 'approved') {
            $fields['approved_by'] = $actor;
        }
        if ($to_state === 'published') {
            $fields['published_at'] = $now;
            $fields['approved_by'] = (int)$row['approved_by'] ?: $actor;
        }
        $updated = $wpdb->update($table, $fields, ['public_id' => $public_id, 'version' => $expected_version]);
        if ($updated !== 1) {
            return new WP_Error('rsr_version_conflict', __('The report changed before the transition completed.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        RSR_DB::audit('transition_report', 'trend_report', $public_id, 'editorial_governance', 'success', null, [
            'from' => $row['status'],
            'to' => $to_state,
            'reason' => $reason,
        ]);
        if ($to_state === 'published') {
            $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
            RSR_Events::publish('RadarTrendReportPublished.v1', 'trend_report', $public_id, $this->public_event_payload((array)$fresh));
        }
        return $this->get_report($public_id, true);
    }

    /** @return array<string, mixed>|WP_Error */
    public function correct_report(
        string $public_id,
        string $action,
        int $expected_version,
        string $reason,
        string $public_notice,
        ?array $replacement_results = null
    ) {
        if (!RSR_Capabilities::can(RSR_Capabilities::CORRECT_REPORTS)) {
            return new WP_Error('rsr_forbidden', __('You cannot correct or retract trend reports.', RSR_TEXT_DOMAIN), ['status' => 403]);
        }
        $action = sanitize_key($action);
        if (!in_array($action, ['correct', 'retract'], true)) {
            return new WP_Error('rsr_invalid_correction_action', __('Correction action must be correct or retract.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $reason = sanitize_textarea_field($reason);
        $public_notice = sanitize_textarea_field($public_notice);
        if ($reason === '' || $public_notice === '') {
            return new WP_Error('rsr_correction_reason_required', __('A reason and public notice are required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }

        global $wpdb;
        $reports = RSR_DB::table('reports');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$reports} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
        if (!is_array($row)) {
            return new WP_Error('rsr_report_not_found', __('Trend report not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }
        if ((int)$row['version'] !== $expected_version) {
            return new WP_Error('rsr_version_conflict', __('The report changed in another session.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        if (!in_array((string)$row['status'], ['published', 'corrected'], true)) {
            return new WP_Error('rsr_report_not_public', __('Only a published report may be corrected or retracted.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        $now = current_time('mysql', true);
        $new_status = $action === 'retract' ? 'retracted' : 'corrected';
        $fields = [
            'status' => $new_status,
            'version' => (int)$row['version'] + 1,
            'updated_at' => $now,
        ];
        if ($action === 'retract') {
            $fields['retracted_at'] = $now;
        } else {
            $fields['corrected_at'] = $now;
            if ($replacement_results !== null) {
                $fields['results_json'] = RSR_Domain::canonical_json($this->sanitize_report_results($replacement_results));
            }
        }
        $updated = $wpdb->update($reports, $fields, ['public_id' => $public_id, 'version' => $expected_version]);
        if ($updated !== 1) {
            return new WP_Error('rsr_version_conflict', __('The report changed before correction completed.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        $correction_id = wp_generate_uuid4();
        $wpdb->insert(
            RSR_DB::table('corrections'),
            [
                'public_id' => $correction_id,
                'report_id' => (int)$row['id'],
                'report_version' => (int)$row['version'] + 1,
                'action' => $action,
                'reason' => $reason,
                'public_notice' => $public_notice,
                'actor_id' => get_current_user_id(),
                'created_at' => $now,
            ]
        );
        RSR_DB::audit($action . '_report', 'trend_report', $public_id, 'public_correction', 'success', null, ['public_notice' => $public_notice]);

        $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$reports} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
        RSR_Events::publish('RadarTrendReportCorrected.v1', 'trend_report', $public_id, array_merge(
            $this->public_event_payload((array)$fresh),
            [
                'action' => $action,
                'correction_public_id' => $correction_id,
                'public_notice' => $public_notice,
            ]
        ));
        return $this->report_dto((array)$fresh, true);
    }

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

        foreach (array_slice($rows, 0, 10000) as $row) {
            if (!is_array($row)) {
                $rejected++;
                continue;
            }
            $topic_key = sanitize_title((string)($row['alias_key'] ?? $row['topic_key'] ?? $row['topic_label'] ?? ''));
            $topic_label = sanitize_text_field((string)($row['topic_label'] ?? ''));
            $source_reference = sanitize_text_field((string)($row['source_reference'] ?? ''));
            $privacy_scan = RSR_PII_Scanner::scan([
                'topic_label' => $topic_label,
                'source_reference' => $source_reference,
                'geography' => (string)($row['geography'] ?? $geography),
            ]);
            if (!$privacy_scan['safe']) {
                $rejected++;
                continue;
            }
            $row_geography = (string)apply_filters('rsr_normalize_geography', sanitize_key((string)($row['geography'] ?? $geography)) ?: $geography, $row, $source);
            $spam_probability = max(0.0, min(1.0, (float)($row['spam_probability'] ?? 0.0)));
            $bot_probability = max(0.0, min(1.0, (float)($row['bot_probability'] ?? 0.0)));
            if ($topic_key === '' || $topic_label === '' || $spam_probability >= 0.80 || $bot_probability >= 0.80) {
                $rejected++;
                continue;
            }
            $repost_factor = max(1.0, (float)($row['repost_factor'] ?? 1.0));
            $normalized_volume = max(0.0, (float)($row['volume'] ?? 0)) / $repost_factor;
            $normalized_baseline = max(0.0, (float)($row['baseline'] ?? 0)) / $repost_factor;
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
                "SELECT o.*, s.public_id AS source_public_id, s.name AS source_name, s.dataset, s.license_code, s.quality_score, s.stale_after_seconds, s.last_success_at
                 FROM {$observations} o
                 INNER JOIN {$sources_table} s ON s.id = o.source_id
                 WHERE o.window_type = %s AND o.geography = %s AND o.window_start_utc = %s AND o.window_end_utc = %s
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
                'license_code' => (string)$row['license_code'],
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
                // Public history is immutable. Late data produces a review-required successor only via change control.
                return $this->report_dto($existing, true);
            }
            $wpdb->update(
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
                ['id' => (int)$existing['id']]
            );
            $public_id = (string)$existing['public_id'];
        } else {
            $public_id = wp_generate_uuid4();
            $wpdb->insert(
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
        }
        $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$reports} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
        return $this->report_dto((array)$fresh, true);
    }

    /** @return array<string, mixed>|WP_Error */
    private function validate_source(array $input)
    {
        $provider_key = sanitize_key((string)($input['provider_key'] ?? 'manual'));
        if (!RSR_Provider_Registry::get($provider_key)) {
            return new WP_Error('rsr_provider_missing', __('The trend provider adapter is unavailable.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $name = sanitize_text_field((string)($input['name'] ?? ''));
        $dataset = sanitize_text_field((string)($input['dataset'] ?? ''));
        if ($name === '' || $dataset === '') {
            return new WP_Error('rsr_source_identity_required', __('Source name and dataset are required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $license = sanitize_key((string)($input['license_code'] ?? ''));
        if (!in_array($license, RSR_Domain::allowed_source_licenses(), true)) {
            return new WP_Error('rsr_license_not_approved', __('The source license is not on the approved allowlist.', RSR_TEXT_DOMAIN), ['status' => 422]);
        }
        $review_date = sanitize_text_field((string)($input['review_date'] ?? ''));
        if ($review_date === '') {
            return new WP_Error('rsr_source_review_date_required', __('The source review date is required.', RSR_TEXT_DOMAIN), ['status' => 422]);
        }
        $review = DateTimeImmutable::createFromFormat('!Y-m-d', $review_date);
        if (!$review || $review->format('Y-m-d') !== $review_date || $review > new DateTimeImmutable('today', new DateTimeZone('UTC'))) {
            return new WP_Error('rsr_invalid_source_review_date', __('A valid, non-future source review date is required.', RSR_TEXT_DOMAIN), ['status' => 422]);
        }
        $status = sanitize_key((string)($input['status'] ?? 'configured'));
        if (!in_array($status, self::SOURCE_STATES, true)) {
            $status = 'configured';
        }
        $credentials_ref = sanitize_text_field((string)($input['credentials_ref'] ?? ''));
        if ($credentials_ref !== '' && !preg_match('/^[A-Za-z0-9._:\/-]{3,191}$/', $credentials_ref)) {
            return new WP_Error('rsr_invalid_credentials_reference', __('Only a secret-manager reference may be stored.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $config = isset($input['config']) && is_array($input['config']) ? $input['config'] : [];
        $config_json = wp_json_encode($config);
        if (is_string($config_json) && preg_match('/"[^"]*(?:secret|token|password|api[_-]?key|authorization|cookie)[^"]*"\s*:/i', $config_json)) {
            return new WP_Error('rsr_raw_credentials_prohibited', __('Raw provider credentials are prohibited; store only a secret-manager reference.', RSR_TEXT_DOMAIN), ['status' => 422]);
        }
        $scan = RSR_PII_Scanner::scan($config);
        if (!$scan['safe']) {
            return new WP_Error('rsr_private_data_prohibited', __('Trend source configuration cannot contain patient-identifying information.', RSR_TEXT_DOMAIN), ['status' => 422]);
        }
        $cost_model = sanitize_key((string)($input['cost_model'] ?? 'free')) ?: 'free';
        if (!in_array($cost_model, ['free', 'fixed', 'per-request', 'per-record', 'internal'], true)) {
            return new WP_Error('rsr_invalid_cost_model', __('The source cost model is not supported.', RSR_TEXT_DOMAIN), ['status' => 422]);
        }
        return [
            'provider_key' => $provider_key,
            'name' => $name,
            'dataset' => $dataset,
            'edition' => sanitize_text_field((string)($input['edition'] ?? '')) ?: null,
            'license_code' => $license,
            'review_date' => $review_date,
            'territory' => sanitize_key((string)($input['territory'] ?? 'global')) ?: 'global',
            'restrictions' => sanitize_textarea_field((string)($input['restrictions'] ?? '')) ?: null,
            'quality_score' => max(0.0, min(1.0, (float)($input['quality_score'] ?? 0.5))),
            'credentials_ref' => $credentials_ref ?: null,
            'rate_limit_per_hour' => max(1, min(1000000, (int)($input['rate_limit_per_hour'] ?? 1000))),
            'cost_model' => $cost_model,
            'config' => RSR_Observability::redact($config),
            'status' => $status,
            'quota_remaining' => isset($input['quota_remaining']) && $input['quota_remaining'] !== '' ? max(0, (int)$input['quota_remaining']) : null,
            'stale_after_seconds' => max(HOUR_IN_SECONDS, min(YEAR_IN_SECONDS, (int)($input['stale_after_seconds'] ?? DAY_IN_SECONDS))),
        ];
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function source_dto(array $row, bool $include_config): array
    {
        $dto = [
            'public_id' => (string)($row['public_id'] ?? ''),
            'provider_key' => (string)($row['provider_key'] ?? ''),
            'name' => (string)($row['name'] ?? ''),
            'dataset' => (string)($row['dataset'] ?? ''),
            'edition' => $row['edition'] ?? null,
            'license_code' => (string)($row['license_code'] ?? ''),
            'review_date' => (string)($row['review_date'] ?? ''),
            'territory' => (string)($row['territory'] ?? 'global'),
            'restrictions' => $row['restrictions'] ?? null,
            'quality_score' => (float)($row['quality_score'] ?? 0.5),
            'status' => (string)($row['status'] ?? 'configured'),
            'quota_remaining' => isset($row['quota_remaining']) ? (int)$row['quota_remaining'] : null,
            'rate_limit_per_hour' => (int)($row['rate_limit_per_hour'] ?? 1000),
            'cost_model' => (string)($row['cost_model'] ?? 'free'),
            'stale_after_seconds' => (int)($row['stale_after_seconds'] ?? DAY_IN_SECONDS),
            'last_success_at' => !empty($row['last_success_at']) ? mysql_to_rfc3339((string)$row['last_success_at']) : null,
            'last_error_code' => $row['last_error_code'] ?? null,
            'version' => (int)($row['version'] ?? 1),
            'updated_at' => !empty($row['updated_at']) ? mysql_to_rfc3339((string)$row['updated_at']) : null,
        ];
        if ($include_config) {
            $dto['credentials_ref'] = $row['credentials_ref'] ?? null;
            $dto['config'] = json_decode((string)($row['config_json'] ?? '{}'), true) ?: [];
        }
        return $dto;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function job_dto(array $row, bool $include_result): array
    {
        $dto = [
            'public_id' => (string)($row['public_id'] ?? ''),
            'window_type' => (string)($row['window_type'] ?? ''),
            'display_timezone' => (string)($row['display_timezone'] ?? 'UTC'),
            'geography' => (string)($row['geography'] ?? 'global'),
            'status' => (string)($row['status'] ?? 'scheduled'),
            'attempts' => (int)($row['attempts'] ?? 0),
            'trace_id' => (string)($row['trace_id'] ?? ''),
            'error_code' => $row['error_code'] ?? null,
            'started_at' => !empty($row['started_at']) ? mysql_to_rfc3339((string)$row['started_at']) : null,
            'finished_at' => !empty($row['finished_at']) ? mysql_to_rfc3339((string)$row['finished_at']) : null,
        ];
        if ($include_result) {
            $dto['result'] = json_decode((string)($row['result_json'] ?? '{}'), true) ?: [];
        }
        return $dto;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function report_dto(array $row, bool $management): array
    {
        global $wpdb;
        $now = time();
        $stale_at = !empty($row['stale_at']) ? strtotime((string)$row['stale_at'] . ' UTC') : null;
        $public_retraction = !$management && (string)$row['status'] === 'retracted';
        $dto = [
            'public_id' => (string)$row['public_id'],
            'window_type' => (string)$row['window_type'],
            'display_timezone' => (string)$row['display_timezone'],
            'geography' => (string)$row['geography'],
            'window_start_utc' => mysql_to_rfc3339((string)$row['window_start_utc']),
            'window_end_utc' => mysql_to_rfc3339((string)$row['window_end_utc']),
            'method_version' => (string)$row['method_version'],
            'results' => $public_retraction ? ['topics' => [], 'limitations' => [__('This report was retracted and its former rankings are no longer distributed.', RSR_TEXT_DOMAIN)]] : (json_decode((string)$row['results_json'], true) ?: []),
            'sources' => $public_retraction ? [] : (json_decode((string)$row['sources_json'], true) ?: []),
            'confidence' => (float)$row['confidence'],
            'status' => (string)$row['status'],
            'stale' => $stale_at !== null && $stale_at < $now,
            'stale_at' => !empty($row['stale_at']) ? mysql_to_rfc3339((string)$row['stale_at']) : null,
            'published_at' => !empty($row['published_at']) ? mysql_to_rfc3339((string)$row['published_at']) : null,
            'corrected_at' => !empty($row['corrected_at']) ? mysql_to_rfc3339((string)$row['corrected_at']) : null,
            'version' => (int)$row['version'],
            'canonical_url' => home_url('/trends/' . rawurlencode((string)$row['public_id']) . '/'),
        ];
        $corrections = $wpdb->get_results(
            $wpdb->prepare('SELECT public_id, action, public_notice, created_at FROM ' . RSR_DB::table('corrections') . ' WHERE report_id = %d ORDER BY id ASC', (int)$row['id']),
            ARRAY_A
        );
        $dto['corrections'] = array_map(static fn(array $item): array => [
            'public_id' => (string)$item['public_id'],
            'action' => (string)$item['action'],
            'public_notice' => (string)$item['public_notice'],
            'created_at' => mysql_to_rfc3339((string)$item['created_at']),
        ], (array)$corrections);
        if ($management) {
            $dto['created_by'] = (int)$row['created_by'];
            $dto['reviewed_by'] = (int)$row['reviewed_by'];
            $dto['approved_by'] = (int)$row['approved_by'];
            $dto['updated_at'] = mysql_to_rfc3339((string)$row['updated_at']);
        }
        return $dto;
    }

    /** @return true|WP_Error */
    private function report_release_gate(array $row, string $target_state)
    {
        $results = json_decode((string)($row['results_json'] ?? ''), true);
        $sources = json_decode((string)($row['sources_json'] ?? ''), true);
        if (!is_array($results) || empty($results['topics']) || !is_array($sources) || $sources === []) {
            return new WP_Error('rsr_report_incomplete', __('The report has no qualified topics or governed sources.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        if ((string)($row['method_version'] ?? '') !== RSR_Domain::METHOD_VERSION) {
            return new WP_Error('rsr_report_method_stale', __('The report scoring method is not the current approved version.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        $stale_at = !empty($row['stale_at']) ? strtotime((string)$row['stale_at'] . ' UTC') : false;
        if ($stale_at === false || $stale_at <= time()) {
            return new WP_Error('rsr_report_sources_stale', __('The report source window is stale and must be refreshed before release.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        if ((float)($row['confidence'] ?? 0) <= 0.0) {
            return new WP_Error('rsr_report_confidence_missing', __('The report has no valid confidence evidence.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        foreach ($sources as $source) {
            if (!is_array($source)
                || empty($source['public_id'])
                || empty($source['dataset'])
                || !in_array((string)($source['license_code'] ?? ''), RSR_Domain::allowed_source_licenses(), true)
            ) {
                return new WP_Error('rsr_report_source_governance_failed', __('A report source is missing approved identity or license evidence.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        }
        if ($target_state === 'published' && (int)($row['approved_by'] ?? 0) <= 0) {
            return new WP_Error('rsr_report_approval_missing', __('The report must record editorial approval before publication.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        return true;
    }

    private function can_manage_reports(): bool
    {
        foreach ([RSR_Capabilities::REVIEW_REPORTS, RSR_Capabilities::PUBLISH_REPORTS, RSR_Capabilities::CORRECT_REPORTS] as $capability) {
            if (RSR_Capabilities::can($capability)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, mixed> */
    private function public_event_payload(array $row): array
    {
        $dto = $this->report_dto($row, false);
        return [
            'report_public_id' => $dto['public_id'],
            'window_type' => $dto['window_type'],
            'geography' => $dto['geography'],
            'window_start_utc' => $dto['window_start_utc'],
            'window_end_utc' => $dto['window_end_utc'],
            'method_version' => $dto['method_version'],
            'confidence' => $dto['confidence'],
            'status' => $dto['status'],
            'canonical_url' => $dto['canonical_url'],
            'stale_at' => $dto['stale_at'],
        ];
    }

    /** @return array<string, mixed> */
    private function sanitize_report_results(array $results): array
    {
        $clean = [];
        foreach (array_slice((array)($results['topics'] ?? []), 0, 100) as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $key = sanitize_title((string)($topic['topic_key'] ?? $topic['topic_label'] ?? ''));
            $label = sanitize_text_field((string)($topic['topic_label'] ?? ''));
            if ($key === '' || $label === '') {
                continue;
            }
            $clean[] = [
                'topic_key' => $key,
                'topic_label' => $label,
                'score' => max(0.0, min(100.0, (float)($topic['score'] ?? 0))),
                'confidence' => max(0.0, min(1.0, (float)($topic['confidence'] ?? 0))),
                'volume' => max(0.0, (float)($topic['volume'] ?? 0)),
                'baseline' => max(0.0, (float)($topic['baseline'] ?? 0)),
                'source_count' => max(0, (int)($topic['source_count'] ?? 0)),
                'source_refs' => array_values(array_unique(array_map('sanitize_text_field', (array)($topic['source_refs'] ?? [])))),
            ];
        }
        return [
            'method_version' => RSR_Domain::METHOD_VERSION,
            'corrected_at' => gmdate('c'),
            'topics' => $clean,
            'limitations' => array_map('sanitize_text_field', (array)($results['limitations'] ?? [])),
        ];
    }
}
