<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

trait RSR_Trend_Reports_Trait
{
    /** @return array<int, array<string, mixed>> */
    public function public_reports_page(array $filters = []): array
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
        $cursor = isset($filters['cursor']) ? base64_decode((string)$filters['cursor'], true) : false;
        if (is_string($cursor) && ctype_digit($cursor) && (int)$cursor > 0) {
            $where[] = 'id < %d';
            $params[] = (int)$cursor;
        }
        $sql = "SELECT * FROM {$table} WHERE " . implode(' AND ', $where) . ' ORDER BY published_at DESC, id DESC LIMIT %d';
        $params[] = $limit + 1;
        $rows = (array)$wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
        $has_more = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $items = array_map(fn(array $row): array => $this->report_dto($row, false), $rows);
        $last = end($rows);
        return [
            'items' => $items,
            'next_cursor' => $has_more && is_array($last) ? base64_encode((string)$last['id']) : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function public_reports(array $filters = []): array
    {
        $page = $this->public_reports_page($filters);
        return (array)($page['items'] ?? []);
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
        $capability = match ($to_state) {
            'approved' => RSR_Capabilities::APPROVE_REPORTS,
            'published' => RSR_Capabilities::PUBLISH_REPORTS,
            default => RSR_Capabilities::REVIEW_REPORTS,
        };
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
        $allow_same_actor = (bool)apply_filters('rsr_allow_same_actor_report_release', false, $row, $to_state, $actor);
        if ($to_state === 'approved' && !$allow_same_actor && (int)$row['reviewed_by'] > 0 && (int)$row['reviewed_by'] === $actor) {
            return new WP_Error('rsr_independent_approval_required', __('A different authorized person must approve this report.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        if ($to_state === 'published' && !$allow_same_actor && (int)$row['approved_by'] > 0 && (int)$row['approved_by'] === $actor) {
            return new WP_Error('rsr_independent_publisher_required', __('A different authorized person must publish this approved report.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
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
        try {
            RSR_Hardening::transaction(function () use ($wpdb, $table, $fields, $public_id, $expected_version, $to_state): bool {
                $updated = $wpdb->update($table, $fields, ['public_id' => $public_id, 'version' => $expected_version]);
                if ($updated !== 1) {
                    throw new RuntimeException('version_conflict');
                }
                if ($to_state === 'published') {
                    $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE public_id = %s LIMIT 1", $public_id), ARRAY_A);
                    RSR_Events::publish('RadarTrendReportPublished.v1', 'trend_report', $public_id, $this->public_event_payload((array)$fresh));
                }
                return true;
            });
        } catch (Throwable $error) {
            return new WP_Error('rsr_report_transition_failed', __('The report transition could not be committed safely.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        RSR_DB::audit('transition_report', 'trend_report', $public_id, 'editorial_governance', 'success', null, [
            'from' => $row['status'],
            'to' => $to_state,
            'reason' => $reason,
        ]);
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
        $reason = sanitize_textarea_field($reason);
        $public_notice = sanitize_textarea_field($public_notice);
        if (!in_array($action, ['correct', 'retract'], true) || $reason === '' || $public_notice === '') {
            return new WP_Error('rsr_correction_invalid', __('A valid action, reason and public notice are required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        global $wpdb;
        $reports = RSR_DB::table('reports');
        $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$reports} WHERE public_id=%s LIMIT 1", $public_id), ARRAY_A);
        if (!is_array($row)) {
            return new WP_Error('rsr_report_not_found', __('Trend report not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }
        if ((int)$row['version'] !== $expected_version || !in_array((string)$row['status'], ['published', 'corrected'], true)) {
            return new WP_Error('rsr_version_conflict', __('The public report changed or is no longer correctable.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        $sources = json_decode((string)$row['sources_json'], true) ?: [];
        $allowed_refs = array_values(array_filter(array_map(static fn(array $source): string => (string)($source['public_id'] ?? ''), (array)$sources)));
        $clean_results = null;
        if ($action === 'correct' && $replacement_results !== null) {
            $clean_results = $this->sanitize_report_results($replacement_results, $allowed_refs);
            if ($clean_results['topics'] === []) {
                return new WP_Error('rsr_correction_results_invalid', __('Corrected results contain no governed topics.', RSR_TEXT_DOMAIN), ['status' => 422]);
            }
        }
        $now = current_time('mysql', true);
        $correction_id = wp_generate_uuid4();
        $before_hash = hash('sha256', (string)$row['results_json']);
        $after_json = $clean_results !== null ? RSR_Domain::canonical_json($clean_results) : (string)$row['results_json'];
        $after_hash = hash('sha256', $after_json);
        $fields = [
            'status' => $action === 'retract' ? 'retracted' : 'corrected',
            'version' => (int)$row['version'] + 1,
            'updated_at' => $now,
        ];
        if ($action === 'retract') {
            $fields['retracted_at'] = $now;
        } else {
            $fields['corrected_at'] = $now;
            if ($clean_results !== null) {
                $fields['results_json'] = $after_json;
            }
        }
        try {
            $fresh = RSR_Hardening::transaction(function () use ($wpdb, $reports, $fields, $public_id, $expected_version, $row, $correction_id, $action, $reason, $public_notice, $now, $before_hash, $after_hash): array {
                if ($wpdb->update($reports, $fields, ['public_id' => $public_id, 'version' => $expected_version]) !== 1) {
                    throw new RuntimeException('version_conflict');
                }
                if (!$wpdb->insert(RSR_DB::table('corrections'), [
                    'public_id' => $correction_id,
                    'report_id' => (int)$row['id'],
                    'report_version' => (int)$row['version'] + 1,
                    'action' => $action,
                    'reason' => $reason,
                    'public_notice' => $public_notice,
                    'before_hash' => $before_hash,
                    'after_hash' => $after_hash,
                    'actor_id' => get_current_user_id(),
                    'created_at' => $now,
                ])) {
                    throw new RuntimeException('correction_insert_failed');
                }
                $fresh = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$reports} WHERE public_id=%s LIMIT 1", $public_id), ARRAY_A);
                if (!is_array($fresh)) {
                    throw new RuntimeException('report_reload_failed');
                }
                $event = $action === 'retract' ? 'RadarTrendReportRetracted.v1' : 'RadarTrendReportCorrected.v1';
                RSR_Events::publish($event, 'trend_report', $public_id, array_merge($this->public_event_payload($fresh), [
                    'action' => $action,
                    'correction_public_id' => $correction_id,
                    'public_notice' => $public_notice,
                ]));
                return $fresh;
            });
        } catch (Throwable $error) {
            return new WP_Error('rsr_correction_commit_failed', __('The correction could not be committed safely.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        RSR_DB::audit($action . '_report', 'trend_report', $public_id, 'public_correction', 'success', null, ['public_notice' => $public_notice]);
        return $this->report_dto($fresh, true);
    }
}
