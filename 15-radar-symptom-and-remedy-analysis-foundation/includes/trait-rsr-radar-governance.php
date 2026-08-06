<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

trait RSR_Radar_Governance_Trait
{
    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    public function upsert_schema_value(array $data)
    {
        if (!RSR_Capabilities::can(RSR_Capabilities::MANAGE_SCHEMA)) {
            return new WP_Error('rsr_forbidden', __('You cannot manage the Radar schema.', RSR_TEXT_DOMAIN), ['status' => 403]);
        }
        global $wpdb;
        $dimension = RSR_Domain::normalize_key((string)($data['dimension_key'] ?? ''));
        $value_key = RSR_Domain::normalize_key((string)($data['value_key'] ?? ''));
        $label = sanitize_text_field((string)($data['label'] ?? ''));
        if (!isset(RSR_Domain::dimensions()[$dimension]) || $value_key === '' || $label === '') {
            return new WP_Error('rsr_invalid_schema_value', __('Dimension, value key, and label are required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $aliases = array_slice(array_values(array_unique(array_filter(array_map('sanitize_text_field', (array)($data['aliases'] ?? []))))), 0, 50);
        $source_public_id = sanitize_text_field((string)($data['source_public_id'] ?? ''));
        if ($source_public_id !== '') {
            $source_ok = (int)$wpdb->get_var($wpdb->prepare(
                'SELECT COUNT(*) FROM ' . RSR_DB::table('sources') . " WHERE public_id=%s AND status <> 'disabled'",
                $source_public_id
            ));
            if ($source_ok !== 1) {
                return new WP_Error('rsr_schema_source_invalid', __('The governed schema source is unavailable.', RSR_TEXT_DOMAIN), ['status' => 422]);
            }
        }
        $public_id = sanitize_text_field((string)($data['public_id'] ?? ''));
        $now = current_time('mysql', true);

        if ($public_id !== '') {
            $current = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . RSR_DB::table('schema') . ' WHERE public_id = %s', $public_id), ARRAY_A);
            if (!$current) {
                return new WP_Error('rsr_schema_value_not_found', __('Schema value not found.', RSR_TEXT_DOMAIN), ['status' => 404]);
            }
            $expected = absint($data['version'] ?? 0);
            if ($expected !== (int)$current['version']) {
                return new WP_Error('rsr_version_conflict', __('The schema value changed; reload and try again.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
            $affected = $wpdb->update(
                RSR_DB::table('schema'),
                [
                    'dimension_key' => $dimension,
                    'value_key' => $value_key,
                    'label' => $label,
                    'aliases_json' => wp_json_encode($aliases),
                    'definition' => sanitize_textarea_field((string)($data['definition'] ?? '')),
                    'source_public_id' => $source_public_id ?: null,
                    'status' => in_array(($data['status'] ?? 'active'), ['active', 'deprecated'], true) ? $data['status'] : 'active',
                    'version' => $expected + 1,
                    'updated_at' => $now,
                ],
                ['public_id' => $public_id, 'version' => $expected]
            );
            if ($affected !== 1) {
                return new WP_Error('rsr_version_conflict', __('The schema value changed before the update completed.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        } else {
            $public_id = wp_generate_uuid4();
            $inserted = $wpdb->insert(
                RSR_DB::table('schema'),
                [
                    'public_id' => $public_id,
                    'dimension_key' => $dimension,
                    'value_key' => $value_key,
                    'label' => $label,
                    'aliases_json' => wp_json_encode($aliases),
                    'definition' => sanitize_textarea_field((string)($data['definition'] ?? '')),
                    'source_public_id' => $source_public_id ?: null,
                    'status' => 'active',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
            if (!$inserted) {
                return new WP_Error('rsr_schema_value_conflict', __('The schema value could not be created; its key may already exist.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        }

        RSR_DB::audit('upsert_schema_value', 'radar_schema', $public_id, 'schema_governance', 'success');
        return ['public_id' => $public_id];
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>|WP_Error
     */
    public function upsert_mapping(array $data)
    {
        if (!RSR_Capabilities::can(RSR_Capabilities::MANAGE_SCHEMA)) {
            return new WP_Error('rsr_forbidden', __('You cannot manage Radar mappings.', RSR_TEXT_DOMAIN), ['status' => 403]);
        }

        $validated = RSR_Domain::validate_radar_query((array)($data['query'] ?? []));
        $remedy_id = sanitize_text_field((string)($data['remedy_public_id'] ?? ''));
        $source_id = sanitize_text_field((string)($data['source_public_id'] ?? ''));
        $reference = sanitize_textarea_field((string)($data['reference'] ?? ''));
        if (strlen($reference) > 4000) {
            return new WP_Error('rsr_mapping_reference_too_large', __('The mapping reference is too large.', RSR_TEXT_DOMAIN), ['status' => 413]);
        }
        $license = sanitize_key((string)($data['license_code'] ?? ''));
        $review_date = sanitize_text_field((string)($data['review_date'] ?? ''));
        $allowed_licenses = (array)apply_filters('rsr_writable_source_licenses', RSR_Domain::writable_source_licenses());

        if (!$validated['valid']
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/', $remedy_id) !== 1
            || preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,190}$/', $source_id) !== 1
            || $reference === ''
            || !in_array($license, $allowed_licenses, true)
        ) {
            return new WP_Error('rsr_invalid_mapping', __('The mapping is incomplete or its source license is not approved.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $review = DateTimeImmutable::createFromFormat('!Y-m-d', $review_date);
        if (!$review || $review->format('Y-m-d') !== $review_date || $review > new DateTimeImmutable('today')) {
            return new WP_Error('rsr_invalid_review_date', __('A valid non-future review date is required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        if (count($this->file06_remedies([$remedy_id])) !== 1) {
            return new WP_Error('rsr_ineligible_remedy', __('The File 06 remedy is not published and eligible.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }

        global $wpdb;
        $source = $wpdb->get_row($wpdb->prepare(
            'SELECT public_id,license_code,status,review_date FROM ' . RSR_DB::table('sources') . ' WHERE public_id=%s LIMIT 1',
            $source_id
        ), ARRAY_A);
        if (!is_array($source) || (string)$source['status'] === 'disabled' || (string)$source['license_code'] !== $license) {
            return new WP_Error('rsr_mapping_source_invalid', __('The mapping source is unavailable or its licence does not match.', RSR_TEXT_DOMAIN), ['status' => 422]);
        }

        $public_id = sanitize_text_field((string)($data['public_id'] ?? '')) ?: wp_generate_uuid4();
        $now = current_time('mysql', true);
        $existing = $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . RSR_DB::table('mappings') . ' WHERE public_id = %s', $public_id), ARRAY_A);
        $row = [
            'rubric_hash' => $validated['query']['hash'],
            'query_json' => RSR_Domain::canonical_json($validated['query']),
            'remedy_public_id' => $remedy_id,
            'source_public_id' => $source_id,
            'reference_text' => $reference,
            'license_code' => $license,
            'review_date' => $review_date,
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'review_required', 'retracted'], true) ? $data['status'] : 'active',
            'updated_at' => $now,
        ];
        if ($existing) {
            $expected = absint($data['version'] ?? 0);
            if ($expected !== (int)$existing['version']) {
                return new WP_Error('rsr_version_conflict', __('The mapping changed; reload and try again.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
            $row['version'] = $expected + 1;
            $affected = $wpdb->update(RSR_DB::table('mappings'), $row, ['public_id' => $public_id, 'version' => $expected]);
            if ($affected !== 1) {
                return new WP_Error('rsr_version_conflict', __('The mapping changed before the update completed.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        } else {
            $row['public_id'] = $public_id;
            $row['version'] = 1;
            $row['created_at'] = $now;
            $inserted = $wpdb->insert(RSR_DB::table('mappings'), $row);
            if (!$inserted) {
                return new WP_Error('rsr_mapping_conflict', __('The mapping could not be created; an equivalent governed mapping may already exist.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        }

        update_option('rsr_comparison_cache_generation', (int)get_option('rsr_comparison_cache_generation', 1) + 1, false);
        RSR_DB::audit('upsert_mapping', 'radar_mapping', $public_id, 'source_linked_mapping', 'success');
        return ['public_id' => $public_id];
    }

}
