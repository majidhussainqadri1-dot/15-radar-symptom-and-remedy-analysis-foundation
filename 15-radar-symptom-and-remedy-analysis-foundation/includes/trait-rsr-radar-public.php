<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

trait RSR_Radar_Public_Trait
{
    /** @return array<string, mixed> */
    public function schema(): array
    {
        global $wpdb;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT v.public_id, v.dimension_key, v.value_key, v.label, v.aliases_json, v.definition, v.source_public_id, v.version, v.updated_at FROM ' . RSR_DB::table('schema') . ' v LEFT JOIN ' . RSR_DB::table('sources') . ' s ON s.public_id=v.source_public_id WHERE v.status=%s AND (v.source_public_id IS NULL OR s.status<>%s) ORDER BY v.dimension_key,v.label LIMIT 5000',
                'active',
                'disabled'
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
        $decoded_cursor = $cursor ? base64_decode($cursor, true) : false;
        $cursor_key = is_string($decoded_cursor) && preg_match('/^[A-Za-z0-9._:-]{1,191}$/', $decoded_cursor) ? $decoded_cursor : '';
        $cache_generation = (int)get_option('rsr_comparison_cache_generation', 1);
        $cache_key = 'search:' . $cache_generation . ':' . $query['hash'] . ':' . $limit . ':' . hash('sha256', $cursor_key);
        $cached = wp_cache_get($cache_key, 'rsr');
        if (is_array($cached)) {
            $cached['results'] = $this->revalidate_results((array)($cached['results'] ?? []));
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

        $result = $this->search_local_mappings($query, $limit, $cursor_key);
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
        $sql = 'SELECT m.public_id, m.rubric_hash, m.query_json, m.remedy_public_id, m.source_public_id, m.reference_text, m.license_code, m.review_date, m.version, m.updated_at FROM '
            . RSR_DB::table('mappings') . ' m INNER JOIN ' . RSR_DB::table('sources') . ' s ON s.public_id=m.source_public_id'
            . " WHERE m.status = 'active' AND s.status <> 'disabled' AND m.license_code=s.license_code AND m.remedy_public_id IN ({$placeholders}) ORDER BY m.review_date DESC, m.id DESC LIMIT 1500";
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

}
