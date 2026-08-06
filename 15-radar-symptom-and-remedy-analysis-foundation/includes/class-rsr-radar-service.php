<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Radar_Service
{
    /** @return array<string, mixed> */
    public function schema(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT public_id, dimension_key, value_key, label, aliases_json, definition, source_public_id, version, updated_at FROM ' . RSR_DB::table('schema') . ' WHERE status = %s ORDER BY dimension_key, label LIMIT 5000',
                'active'
            ),
            ARRAY_A
        );

        $values = [];
        foreach ((array)$rows as $row) {
            $dimension = (string)$row['dimension_key'];
            if (!isset($values[$dimension])) {
                $values[$dimension] = [];
            }
            if ($row['value_key'] !== '__dimension__') {
                $values[$dimension][] = [
                    'id' => $row['public_id'],
                    'key' => $row['value_key'],
                    'label' => $row['label'],
                    'aliases' => json_decode((string)$row['aliases_json'], true) ?: [],
                    'definition' => $row['definition'],
                    'source_id' => $row['source_public_id'],
                    'version' => (int)$row['version'],
                    'updated_at' => mysql_to_rfc3339((string)$row['updated_at']),
                ];
            }
        }

        return [
            'schema_version' => RSR_SCHEMA_VERSION,
            'dimensions' => RSR_Domain::dimensions(),
            'values' => $values,
            'limits' => [
                'max_compare_remedies' => RSR_Domain::MAX_COMPARE_REMEDIES,
                'max_values_per_dimension' => RSR_Domain::MAX_FILTERS_PER_DIMENSION,
                'max_total_values' => RSR_Domain::MAX_TOTAL_FILTER_VALUES,
            ],
            'safety' => self::safety_notice(),
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>|WP_Error
     */
    public function search(array $input, int $limit = 30, ?string $cursor = null)
    {
        $validated = RSR_Domain::validate_radar_query($input);
        if (!$validated['valid']) {
            return new WP_Error(
                'rsr_invalid_radar_query',
                __('The Radar query is invalid.', RSR_TEXT_DOMAIN),
                ['status' => 400, 'errors' => $validated['errors']]
            );
        }
        $query = $validated['query'];
        $limit = max(1, min(100, $limit));
        $cursor_id = $cursor ? absint(base64_decode($cursor, true) ?: 0) : 0;
        $cache_generation = (int)get_option('rsr_comparison_cache_generation', 1);
        $cache_key = 'search:' . $cache_generation . ':' . $query['hash'] . ':' . $limit . ':' . $cursor_id;
        $cached = wp_cache_get($cache_key, 'rsr');
        if (is_array($cached)) {
            $cached['cache'] = ['hit' => true, 'generation' => $cache_generation];
            return $cached;
        }

        /**
         * File 06/File 26 may supply a fully indexed versioned search result.
         * Null means no adapter answered; an empty array is an authoritative
         * empty result.
         */
        $external = apply_filters('rsr_file06_search', null, $query, $limit, $cursor, 'v1');
        if (is_array($external)) {
            $result = $this->normalize_external_search($external, $query, $limit);
            wp_cache_set($cache_key, $result, 'rsr', 300);
            return $result;
        }

        $result = $this->search_local_mappings($query, $limit, $cursor_id);
        wp_cache_set($cache_key, $result, 'rsr', 300);
        return $result;
    }

    /**
     * @param array<int, mixed> $ids
     * @return array<string, mixed>|WP_Error
     */
    public function compare(array $ids)
    {
        $validated = RSR_Domain::validate_comparison_ids($ids);
        if (!$validated['valid']) {
            return new WP_Error(
                'rsr_invalid_comparison',
                __('Choose between one and three eligible remedies.', RSR_TEXT_DOMAIN),
                ['status' => 400, 'errors' => $validated['errors']]
            );
        }

        $remedies = $this->file06_remedies($validated['ids']);
        if (count($remedies) !== count($validated['ids'])) {
            return new WP_Error(
                'rsr_ineligible_remedy',
                __('One or more remedies are unavailable, unpublished, or ineligible.', RSR_TEXT_DOMAIN),
                ['status' => 409]
            );
        }

        global $wpdb;
        $placeholders = implode(',', array_fill(0, count($validated['ids']), '%s'));
        $sql = 'SELECT public_id, rubric_hash, query_json, remedy_public_id, source_public_id, reference_text, license_code, review_date, version, updated_at FROM '
            . RSR_DB::table('mappings')
            . " WHERE status = 'active' AND remedy_public_id IN ({$placeholders}) ORDER BY review_date DESC, id DESC LIMIT 1500";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $validated['ids']), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

        $rubrics = [];
        foreach ((array)$rows as $row) {
            $query = json_decode((string)$row['query_json'], true);
            if (!is_array($query)) {
                continue;
            }
            $rubric_key = (string)$row['rubric_hash'];
            if (!isset($rubrics[$rubric_key])) {
                $rubrics[$rubric_key] = [
                    'query' => $query,
                    'explanation' => RSR_Domain::explain_query($query),
                    'remedies' => [],
                ];
            }
            $rubrics[$rubric_key]['remedies'][(string)$row['remedy_public_id']] = [
                'mapping_id' => $row['public_id'],
                'source_id' => $row['source_public_id'],
                'reference' => $row['reference_text'],
                'license' => $row['license_code'],
                'review_date' => $row['review_date'],
                'version' => (int)$row['version'],
                'updated_at' => mysql_to_rfc3339((string)$row['updated_at']),
            ];
        }

        $common = [];
        $different = [];
        foreach ($rubrics as $rubric) {
            if (count($rubric['remedies']) === count($validated['ids'])) {
                $common[] = $rubric;
            } else {
                $different[] = $rubric;
            }
        }

        return [
            'remedies' => array_values($remedies),
            'common_rubrics' => array_values($common),
            'differing_rubrics' => array_values($different),
            'safety' => self::safety_notice(),
            'interpretation' => __('A comparison shows source-linked similarities and differences. It is not a prescription, potency recommendation, dosage recommendation, diagnosis, or rank of treatment.', RSR_TEXT_DOMAIN),
            'generated_at' => gmdate('c'),
        ];
    }

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
        $aliases = array_values(array_unique(array_filter(array_map('sanitize_text_field', (array)($data['aliases'] ?? [])))));
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
                    'source_public_id' => sanitize_text_field((string)($data['source_public_id'] ?? '')) ?: null,
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
                    'source_public_id' => sanitize_text_field((string)($data['source_public_id'] ?? '')) ?: null,
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
        $license = sanitize_key((string)($data['license_code'] ?? ''));
        $review_date = sanitize_text_field((string)($data['review_date'] ?? ''));
        $allowed_licenses = (array)apply_filters('rsr_allowed_source_licenses', RSR_Domain::allowed_source_licenses());

        if (!$validated['valid'] || $remedy_id === '' || $source_id === '' || $reference === '' || !in_array($license, $allowed_licenses, true)) {
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

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function search_local_mappings(array $query, int $limit, int $cursor_id): array
    {
        global $wpdb;
        $table = RSR_DB::table('mappings');
        $candidate_limit = min(1000, max(200, $limit * 20));
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = 'active' AND id > %d ORDER BY id ASC LIMIT %d",
                $cursor_id,
                $candidate_limit
            ),
            ARRAY_A
        );

        $by_remedy = [];
        $last_id = $cursor_id;
        foreach ((array)$rows as $row) {
            $last_id = max($last_id, (int)$row['id']);
            $mapping_query = json_decode((string)$row['query_json'], true);
            if (!is_array($mapping_query) || !$this->query_matches($query, $mapping_query)) {
                continue;
            }
            $remedy_id = (string)$row['remedy_public_id'];
            if (!isset($by_remedy[$remedy_id])) {
                $by_remedy[$remedy_id] = [
                    'remedy_public_id' => $remedy_id,
                    'match_count' => 0,
                    'mappings' => [],
                ];
            }
            $by_remedy[$remedy_id]['match_count']++;
            $by_remedy[$remedy_id]['mappings'][] = [
                'mapping_id' => $row['public_id'],
                'query' => $mapping_query,
                'explanation' => RSR_Domain::explain_query($mapping_query),
                'source_id' => $row['source_public_id'],
                'reference' => $row['reference_text'],
                'license' => $row['license_code'],
                'review_date' => $row['review_date'],
                'version' => (int)$row['version'],
            ];
        }

        uasort($by_remedy, static fn(array $a, array $b): int => $b['match_count'] <=> $a['match_count']);
        $selected = array_slice($by_remedy, 0, $limit, true);
        $remedies = $this->file06_remedies(array_keys($selected));
        $results = [];
        foreach ($selected as $remedy_id => $result) {
            if (!isset($remedies[$remedy_id])) {
                continue;
            }
            $result['remedy'] = $remedies[$remedy_id];
            $result['educational_match_score'] = min(100, $result['match_count'] * 10);
            $results[] = $result;
        }

        return [
            'query' => $query,
            'explanation' => RSR_Domain::explain_query($query),
            'results' => $results,
            'next_cursor' => count((array)$rows) === $candidate_limit ? base64_encode((string)$last_id) : null,
            'source' => 'file15_local_mappings',
            'safety' => self::safety_notice(),
            'limitations' => __('Results are source-linked educational possibilities. Match scores are not probabilities, diagnoses, prescriptions, treatment rankings, potency choices, or dosage advice.', RSR_TEXT_DOMAIN),
            'cache' => ['hit' => false],
            'generated_at' => gmdate('c'),
        ];
    }

    /** @param array<string, mixed> $requested @param array<string, mixed> $mapping */
    private function query_matches(array $requested, array $mapping): bool
    {
        $requested_terms = $this->flatten_query_terms($requested);
        $mapping_terms = $this->flatten_query_terms($mapping);
        if ($requested_terms === []) {
            return false;
        }
        $matches = array_intersect($requested_terms, $mapping_terms);
        if (($requested['mode'] ?? 'AND') === 'OR') {
            return $matches !== [];
        }
        return count($matches) === count($requested_terms);
    }

    /** @param array<string, mixed> $query @return array<int, string> */
    private function flatten_query_terms(array $query): array
    {
        $terms = [];
        $keyword = strtolower(trim((string)($query['keyword'] ?? '')));
        if ($keyword !== '') {
            $terms[] = 'keyword:' . $keyword;
        }
        foreach ((array)($query['filters'] ?? []) as $dimension => $values) {
            foreach ((array)$values as $value) {
                $terms[] = sanitize_key((string)$dimension) . ':' . strtolower(trim((string)$value));
            }
        }
        return array_values(array_unique($terms));
    }

    /** @param array<string, mixed> $external @param array<string, mixed> $query @return array<string, mixed> */
    private function normalize_external_search(array $external, array $query, int $limit): array
    {
        $results = array_slice(array_values((array)($external['results'] ?? $external)), 0, $limit);
        return [
            'query' => $query,
            'explanation' => RSR_Domain::explain_query($query),
            'results' => $results,
            'next_cursor' => $external['next_cursor'] ?? null,
            'source' => sanitize_text_field((string)($external['source'] ?? 'file06_adapter')),
            'safety' => self::safety_notice(),
            'limitations' => __('Results are educational and source-linked; they do not authorize diagnosis or treatment.', RSR_TEXT_DOMAIN),
            'cache' => ['hit' => false],
            'generated_at' => gmdate('c'),
        ];
    }

    /**
     * @param array<int, string> $ids
     * @return array<string, array<string, mixed>> keyed by public ID
     */
    private function file06_remedies(array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('strval', $ids))));
        if ($ids === []) {
            return [];
        }

        $external = apply_filters('rsr_file06_get_remedies', [], $ids, 'v1');
        $out = [];
        if (is_array($external)) {
            foreach ($external as $key => $remedy) {
                if (!is_array($remedy)) {
                    continue;
                }
                $id = (string)($remedy['public_id'] ?? $key);
                if (!in_array($id, $ids, true) || empty($remedy['published']) || empty($remedy['eligible'])) {
                    continue;
                }
                $out[$id] = [
                    'public_id' => $id,
                    'title' => sanitize_text_field((string)($remedy['title'] ?? '')),
                    'url' => esc_url_raw((string)($remedy['url'] ?? '')),
                    'version' => sanitize_text_field((string)($remedy['version'] ?? '1')),
                    'review_date' => sanitize_text_field((string)($remedy['review_date'] ?? '')),
                    'summary' => wp_kses_post((string)($remedy['summary'] ?? '')),
                    'source' => 'file06_contract',
                ];
            }
        }

        // Compatibility adapter for the existing File 06 class, not a canonical dependency.
        if (count($out) < count($ids) && class_exists('HE_Content')) {
            foreach ($ids as $id) {
                if (isset($out[$id]) || !ctype_digit($id)) {
                    continue;
                }
                $post_id = (int)$id;
                if (get_post_status($post_id) !== 'publish' || get_post_type($post_id) !== HE_Content::TYPE) {
                    continue;
                }
                if (method_exists('HE_Content', 'type') && HE_Content::type($post_id, 'slug') !== 'remedy') {
                    continue;
                }
                $out[$id] = [
                    'public_id' => $id,
                    'title' => get_the_title($post_id),
                    'url' => get_permalink($post_id),
                    'version' => (string)get_post_meta($post_id, '_he_version', true) ?: 'legacy',
                    'review_date' => (string)get_post_modified_time('Y-m-d', true, $post_id),
                    'summary' => wp_kses_post(get_the_excerpt($post_id)),
                    'source' => 'file06_compatibility_bridge',
                ];
            }
        }

        return $out;
    }

    public static function safety_notice(): array
    {
        return [
            'type' => 'educational_research_only',
            'message' => __('Radar is an educational research tool. It does not diagnose disease, prescribe a remedy, choose potency or dosage, replace a qualified clinician, or provide emergency care.', RSR_TEXT_DOMAIN),
            'red_flags' => __('For severe, rapidly worsening, life-threatening, or emergency symptoms, seek appropriate local emergency medical care immediately.', RSR_TEXT_DOMAIN),
        ];
    }
}
