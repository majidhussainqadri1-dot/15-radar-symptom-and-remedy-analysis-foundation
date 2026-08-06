<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

trait RSR_Trend_Serialization_Trait
{
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
        if (!in_array($license, RSR_Domain::writable_source_licenses(), true)) {
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
        if ($credentials_ref !== '' && !preg_match('/^(?:vault|secret|kms|wp-secret):[A-Za-z0-9._\/-]{3,180}$/', $credentials_ref)) {
            return new WP_Error('rsr_invalid_credentials_reference', __('Only a secret-manager reference may be stored.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $config = isset($input['config']) && is_array($input['config']) ? $input['config'] : [];
        if (RSR_Hardening::encoded_size($config) > RSR_Hardening::MAX_SOURCE_CONFIG_BYTES) {
            return new WP_Error('rsr_source_config_too_large', __('Trend source configuration is too large.', RSR_TEXT_DOMAIN), ['status' => 413]);
        }
        if (RSR_Hardening::contains_secret_material($config)) {
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
            'quality_score' => RSR_Hardening::finite_float($input['quality_score'] ?? 0.5, 0.0, 1.0, 0.5) ?? 0.5,
            'credentials_ref' => $credentials_ref ?: null,
            'rate_limit_per_hour' => max(1, min(1000000, (int)($input['rate_limit_per_hour'] ?? 1000))),
            'cost_model' => $cost_model,
            'config' => $config,
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
        $minimum_confidence = (float)apply_filters('rsr_report_minimum_confidence', 0.45, $row, $target_state);
        if ((float)($row['confidence'] ?? 0) < $minimum_confidence) {
            return new WP_Error('rsr_report_confidence_missing', __('The report has no valid confidence evidence.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        $source_ids = [];
        foreach ($sources as $source) {
            $review = is_array($source) ? DateTimeImmutable::createFromFormat('!Y-m-d', (string)($source['review_date'] ?? '')) : false;
            if (!is_array($source)
                || empty($source['public_id'])
                || empty($source['dataset'])
                || !$review
                || $review > new DateTimeImmutable('today', new DateTimeZone('UTC'))
                || !in_array((string)($source['license_code'] ?? ''), RSR_Domain::writable_source_licenses(), true)
            ) {
                return new WP_Error('rsr_report_source_governance_failed', __('A report source is missing approved identity, review date, or licence evidence.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
            $source_ids[] = (string)$source['public_id'];
        }
        foreach ((array)$results['topics'] as $topic) {
            $refs = is_array($topic) ? array_values(array_unique(array_map('strval', (array)($topic['source_refs'] ?? [])))) : [];
            if ($refs === [] || array_diff($refs, $source_ids) !== [] || (int)($topic['source_count'] ?? 0) !== count($refs)) {
                return new WP_Error('rsr_report_topic_provenance_failed', __('A report topic has inconsistent source provenance.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        }
        if ($target_state === 'published' && (int)($row['approved_by'] ?? 0) <= 0) {
            return new WP_Error('rsr_report_approval_missing', __('The report must record editorial approval before publication.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        return true;
    }

    private function can_manage_reports(): bool
    {
        foreach ([RSR_Capabilities::REVIEW_REPORTS, RSR_Capabilities::APPROVE_REPORTS, RSR_Capabilities::PUBLISH_REPORTS, RSR_Capabilities::CORRECT_REPORTS] as $capability) {
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

    /** @param array<int,string> $allowed_source_refs @return array<string, mixed> */
    private function sanitize_report_results(array $results, array $allowed_source_refs = []): array
    {
        $clean = [];
        foreach (array_slice((array)($results['topics'] ?? []), 0, 100) as $topic) {
            if (!is_array($topic)) {
                continue;
            }
            $key = sanitize_title((string)($topic['topic_key'] ?? $topic['topic_label'] ?? ''));
            $label = sanitize_text_field((string)($topic['topic_label'] ?? ''));
            $score = RSR_Hardening::finite_float($topic['score'] ?? 0, 0.0, 100.0, null);
            $confidence = RSR_Hardening::finite_float($topic['confidence'] ?? 0, 0.0, 1.0, null);
            $volume = RSR_Hardening::finite_float($topic['volume'] ?? 0, 0.0, RSR_Hardening::MAX_ABSOLUTE_VOLUME, null);
            $baseline = RSR_Hardening::finite_float($topic['baseline'] ?? 0, 0.0, RSR_Hardening::MAX_ABSOLUTE_VOLUME, null);
            $refs = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array)($topic['source_refs'] ?? [])))));
            if ($allowed_source_refs !== []) {
                $refs = array_values(array_intersect($refs, $allowed_source_refs));
            }
            if ($key === '' || $label === '' || $score === null || $confidence === null || $volume === null || $baseline === null || $refs === []) {
                continue;
            }
            $clean[] = [
                'topic_key' => $key,
                'topic_label' => $label,
                'score' => $score,
                'confidence' => $confidence,
                'volume' => $volume,
                'baseline' => $baseline,
                'source_count' => count($refs),
                'source_refs' => $refs,
            ];
        }
        return [
            'method_version' => RSR_Domain::METHOD_VERSION,
            'corrected_at' => gmdate('c'),
            'topics' => $clean,
            'limitations' => array_slice(array_values(array_filter(array_map('sanitize_text_field', (array)($results['limitations'] ?? [])))), 0, 20),
        ];
    }
}
