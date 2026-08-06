<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Study_Service
{
    /**
     * @return array<string, mixed>|WP_Error
     */
    public function list_own(int $user_id, int $limit = 50, ?string $cursor = null)
    {
        $access = RSR_Capabilities::require_verified_doctor($user_id);
        if (is_wp_error($access)) {
            return $access;
        }

        global $wpdb;
        $limit = max(1, min(100, $limit));
        $before_id = $cursor ? absint(base64_decode($cursor, true) ?: 0) : PHP_INT_MAX;
        $table = RSR_DB::table('studies');
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE owner_user_id = %d AND status IN ('saved','archived') AND id < %d ORDER BY id DESC LIMIT %d",
                $user_id,
                $before_id,
                $limit + 1
            ),
            ARRAY_A
        );

        $has_more = count((array)$rows) > $limit;
        $rows = array_slice((array)$rows, 0, $limit);
        $items = [];
        foreach ($rows as $row) {
            $items[] = $this->dto($row, true);
        }
        $last = end($rows);

        return [
            'items' => $items,
            'next_cursor' => $has_more && is_array($last) ? base64_encode((string)$last['id']) : null,
            'privacy' => [
                'classification' => 'private_verified_doctor_only',
                'noindex' => true,
                'no_cache' => true,
                'pii_prohibited' => true,
                'excluded_from_search_ai_feed_trends' => true,
            ],
        ];
    }

    /**
     * @return array<string, mixed>|WP_Error
     */
    public function get_own(string $public_id, int $user_id)
    {
        $access = RSR_Capabilities::require_verified_doctor($user_id);
        if (is_wp_error($access)) {
            return $access;
        }
        $row = $this->find_owned($public_id, $user_id);
        if (!$row) {
            return new WP_Error('rsr_study_not_found', __('Study not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }
        return $this->dto($row, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    public function create(array $data, int $user_id)
    {
        $access = RSR_Capabilities::require_verified_doctor($user_id);
        if (is_wp_error($access)) {
            return $access;
        }
        if ((bool)get_option('rsr_safe_mode', false)) {
            return new WP_Error('rsr_safe_mode', __('Saving studies is temporarily disabled by safe mode.', RSR_TEXT_DOMAIN), ['status' => 503]);
        }

        global $wpdb;
        $count = (int)$wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . RSR_DB::table('studies') . " WHERE owner_user_id = %d AND status IN ('saved','archived')",
                $user_id
            )
        );
        if ($count >= RSR_Domain::MAX_STUDIES_PER_DOCTOR) {
            return new WP_Error('rsr_study_limit', __('The private study limit has been reached. Export or delete old studies first.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        $validated = $this->validate_payload($data);
        if (is_wp_error($validated)) {
            return $validated;
        }
        $encrypted_notes = $this->encrypt_notes($validated['notes']);
        if (is_wp_error($encrypted_notes)) {
            return $encrypted_notes;
        }

        $now = current_time('mysql', true);
        $public_id = wp_generate_uuid4();
        $inserted = $wpdb->insert(
            RSR_DB::table('studies'),
            [
                'public_id' => $public_id,
                'owner_user_id' => $user_id,
                'title' => $validated['title'],
                'tags_json' => wp_json_encode($validated['tags']),
                'query_json' => RSR_Domain::canonical_json($validated['query']),
                'remedy_refs_json' => wp_json_encode($validated['remedy_refs']),
                'notes_encrypted' => $encrypted_notes,
                'status' => 'saved',
                'version' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        if (!$inserted || !$wpdb->insert_id) {
            return new WP_Error('rsr_study_create_failed', __('The study could not be saved.', RSR_TEXT_DOMAIN), ['status' => 500]);
        }
        $row = $this->find_owned($public_id, $user_id);
        RSR_DB::audit('create_study', 'radar_study', $public_id, 'private_research', 'success');
        RSR_Observability::increment('study_created');
        return $this->dto((array)$row, true);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    public function update(string $public_id, array $data, int $user_id)
    {
        $access = RSR_Capabilities::require_verified_doctor($user_id);
        if (is_wp_error($access)) {
            return $access;
        }
        if ((bool)get_option('rsr_safe_mode', false)) {
            return new WP_Error('rsr_safe_mode', __('Updating studies is temporarily disabled by safe mode.', RSR_TEXT_DOMAIN), ['status' => 503]);
        }
        $current = $this->find_owned($public_id, $user_id);
        if (!$current) {
            return new WP_Error('rsr_study_not_found', __('Study not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }
        $expected = absint($data['version'] ?? 0);
        if ($expected !== (int)$current['version']) {
            return new WP_Error('rsr_version_conflict', __('The study changed in another session. Reload and try again.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        $validated = $this->validate_payload($data);
        if (is_wp_error($validated)) {
            return $validated;
        }
        $encrypted_notes = $this->encrypt_notes($validated['notes']);
        if (is_wp_error($encrypted_notes)) {
            return $encrypted_notes;
        }

        $status = in_array(($data['status'] ?? 'saved'), ['saved', 'archived'], true) ? $data['status'] : 'saved';
        global $wpdb;
        $affected = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . RSR_DB::table('studies') . ' SET title = %s, tags_json = %s, query_json = %s, remedy_refs_json = %s, notes_encrypted = %s, status = %s, version = version + 1, updated_at = %s WHERE public_id = %s AND owner_user_id = %d AND version = %d',
                $validated['title'],
                wp_json_encode($validated['tags']),
                RSR_Domain::canonical_json($validated['query']),
                wp_json_encode($validated['remedy_refs']),
                $encrypted_notes,
                $status,
                current_time('mysql', true),
                $public_id,
                $user_id,
                $expected
            )
        );
        if ($affected !== 1) {
            return new WP_Error('rsr_version_conflict', __('The study changed before the update completed.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        RSR_DB::audit('update_study', 'radar_study', $public_id, 'private_research', 'success');
        return $this->dto((array)$this->find_owned($public_id, $user_id), true);
    }

    /** @return true|WP_Error */
    public function delete(string $public_id, int $user_id, int $expected_version)
    {
        $access = RSR_Capabilities::require_verified_doctor($user_id);
        if (is_wp_error($access)) {
            return $access;
        }
        $current = $this->find_owned($public_id, $user_id);
        if (!$current) {
            return new WP_Error('rsr_study_not_found', __('Study not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
        }
        if ($expected_version !== (int)$current['version']) {
            return new WP_Error('rsr_version_conflict', __('The study changed; reload before deleting.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        global $wpdb;
        $affected = $wpdb->query(
            $wpdb->prepare(
                'UPDATE ' . RSR_DB::table('studies') . " SET status = 'deleted', notes_encrypted = NULL, query_json = '{}', remedy_refs_json = '[]', tags_json = '[]', title = %s, version = version + 1, deleted_at = %s, updated_at = %s WHERE public_id = %s AND owner_user_id = %d AND version = %d",
                __('Deleted study', RSR_TEXT_DOMAIN),
                current_time('mysql', true),
                current_time('mysql', true),
                $public_id,
                $user_id,
                $expected_version
            )
        );
        if ($affected !== 1) {
            return new WP_Error('rsr_version_conflict', __('The study could not be deleted because it changed.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        RSR_DB::audit('delete_study', 'radar_study', $public_id, 'data_rights', 'success');
        return true;
    }

    /** @return array<string, mixed>|WP_Error */
    public function export_own(int $user_id)
    {
        $access = RSR_Capabilities::require_verified_doctor($user_id);
        if (is_wp_error($access)) {
            return $access;
        }
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . RSR_DB::table('studies') . " WHERE owner_user_id = %d AND status IN ('saved','archived') ORDER BY id ASC LIMIT 1000",
                $user_id
            ),
            ARRAY_A
        );
        $items = [];
        foreach ((array)$rows as $row) {
            $items[] = $this->dto($row, true);
        }
        RSR_DB::audit('export_studies', 'radar_study_collection', self::pseudonymous_owner_id($user_id), 'data_portability', 'success');
        return [
            'format' => 'rsr-private-studies-v1',
            'exported_at' => gmdate('c'),
            'owner_scope' => 'current_authenticated_account',
            'items' => $items,
            'notice' => __('Private studies must not contain patient-identifying information and are not clinical records.', RSR_TEXT_DOMAIN),
        ];
    }

    public function purge_user(int $user_id): int
    {
        global $wpdb;
        $count = (int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . RSR_DB::table('studies') . ' WHERE owner_user_id = %d', $user_id));
        $wpdb->delete(RSR_DB::table('studies'), ['owner_user_id' => $user_id], ['%d']);
        RSR_DB::audit('purge_user_studies', 'radar_study_collection', self::pseudonymous_owner_id($user_id), 'user_deletion', 'success', null, ['count' => $count]);
        return $count;
    }

    public function retention_cleanup(int $days = 30): int
    {
        global $wpdb;
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, $days) * DAY_IN_SECONDS);
        $count = (int)$wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . RSR_DB::table('studies') . " WHERE status = 'deleted' AND deleted_at IS NOT NULL AND deleted_at < %s",
                $cutoff
            )
        );
        $wpdb->query(
            $wpdb->prepare(
                'DELETE FROM ' . RSR_DB::table('studies') . " WHERE status = 'deleted' AND deleted_at IS NOT NULL AND deleted_at < %s",
                $cutoff
            )
        );
        return $count;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    private function validate_payload(array $data)
    {
        $title = sanitize_text_field((string)($data['title'] ?? ''));
        if ($title === '' || (function_exists('mb_strlen') ? mb_strlen($title) : strlen($title)) > RSR_Domain::MAX_STUDY_TITLE_LENGTH) {
            return new WP_Error('rsr_invalid_study_title', __('A concise study title is required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }

        $query = (array)($data['query'] ?? []);
        $validated_query = RSR_Domain::validate_radar_query($query);
        if (!$validated_query['valid']) {
            return new WP_Error('rsr_invalid_study_query', __('The saved Radar query is invalid.', RSR_TEXT_DOMAIN), ['status' => 400, 'errors' => $validated_query['errors']]);
        }

        $remedies = array_values(array_unique(array_filter(array_map(
            static fn($value): string => sanitize_text_field((string)$value),
            (array)($data['remedy_refs'] ?? [])
        ))));
        if (count($remedies) > RSR_Domain::MAX_COMPARE_REMEDIES) {
            return new WP_Error('rsr_maximum_three_remedies', __('A study may compare no more than three remedies.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $remedy_validation = apply_filters('rsr_validate_study_remedy_refs', null, $remedies, 'v1');
        if (is_wp_error($remedy_validation)) {
            return $remedy_validation;
        }
        if ($remedies !== [] && $remedy_validation !== true) {
            return new WP_Error(
                'rsr_study_remedy_validation_unavailable',
                __('Saved remedy references cannot be validated against the current File 06 contract.', RSR_TEXT_DOMAIN),
                ['status' => 503]
            );
        }

        $notes = wp_kses_post((string)($data['notes'] ?? ''));
        if (strlen($notes) > RSR_Domain::MAX_STUDY_NOTES_BYTES) {
            return new WP_Error('rsr_study_notes_too_large', __('Study notes are too large.', RSR_TEXT_DOMAIN), ['status' => 413]);
        }

        $tags = array_slice(array_values(array_unique(array_filter(array_map(
            static fn($value): string => sanitize_text_field((string)$value),
            (array)($data['tags'] ?? [])
        )))), 0, 20);

        $scan = RSR_PII_Scanner::scan([
            'title' => $title,
            'query' => $validated_query['query'],
            'remedy_refs' => $remedies,
            'notes' => wp_strip_all_tags($notes),
            'tags' => $tags,
        ]);
        if (!$scan['safe']) {
            return new WP_Error(
                'rsr_patient_information_prohibited',
                __('Patient-identifying information is prohibited in Radar studies. Use the separate approved clinical record system when activated.', RSR_TEXT_DOMAIN),
                ['status' => 422, 'categories' => $scan['categories']]
            );
        }

        return [
            'title' => $title,
            'query' => $validated_query['query'],
            'remedy_refs' => $remedies,
            'notes' => $notes,
            'tags' => $tags,
        ];
    }

    /** @return string|WP_Error */
    private function encrypt_notes(string $notes)
    {
        try {
            return RSR_Crypto::encrypt($notes);
        } catch (Throwable $error) {
            RSR_Observability::log('error', 'study_encrypt_failed', [
                'error_class' => get_class($error),
            ]);
            return new WP_Error(
                'rsr_study_encryption_unavailable',
                __('The private study could not be encrypted. Nothing was saved.', RSR_TEXT_DOMAIN),
                ['status' => 503]
            );
        }
    }

    /** @return array<string, mixed>|null */
    private function find_owned(string $public_id, int $user_id): ?array
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                'SELECT * FROM ' . RSR_DB::table('studies') . ' WHERE public_id = %s AND owner_user_id = %d AND status <> %s LIMIT 1',
                sanitize_text_field($public_id),
                $user_id,
                'deleted'
            ),
            ARRAY_A
        );
        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
    private function dto(array $row, bool $include_notes): array
    {
        $notes = '';
        if ($include_notes && !empty($row['notes_encrypted'])) {
            try {
                $notes = RSR_Crypto::decrypt((string)$row['notes_encrypted']);
            } catch (Throwable $error) {
                RSR_Observability::log('error', 'study_decrypt_failed', [
                    'study_id' => $row['public_id'] ?? '',
                    'error_class' => get_class($error),
                ]);
                $notes = '';
            }
        }

        return [
            'public_id' => (string)$row['public_id'],
            'title' => (string)$row['title'],
            'tags' => json_decode((string)$row['tags_json'], true) ?: [],
            'query' => json_decode((string)$row['query_json'], true) ?: [],
            'remedy_refs' => json_decode((string)$row['remedy_refs_json'], true) ?: [],
            'notes' => $notes,
            'status' => (string)$row['status'],
            'version' => (int)$row['version'],
            'created_at' => mysql_to_rfc3339((string)$row['created_at']),
            'updated_at' => mysql_to_rfc3339((string)$row['updated_at']),
        ];
    }

    private static function pseudonymous_owner_id(int $user_id): string
    {
        return 'owner:' . substr(hash_hmac('sha256', (string)$user_id, wp_salt('auth')), 0, 24);
    }
}
