<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

trait RSR_Radar_Retrieval_Trait
{
    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    private function search_local_mappings(array $query, int $limit, string $cursor_key): array
    {
        global $wpdb;
        $mappings = RSR_DB::table('mappings');
        $sources = RSR_DB::table('sources');
        $candidate_limit = min(500, max(100, $limit * 10));
        $candidate_ids = (array)$wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT m.remedy_public_id FROM {$mappings} m INNER JOIN {$sources} s ON s.public_id=m.source_public_id WHERE m.status='active' AND s.status<>'disabled' AND m.license_code=s.license_code AND m.remedy_public_id>%s ORDER BY m.remedy_public_id ASC LIMIT %d",
            $cursor_key,
            $candidate_limit + 1
        ));
        $has_more = count($candidate_ids) > $candidate_limit;
        $candidate_ids = array_slice(array_values(array_map('strval', $candidate_ids)), 0, $candidate_limit);
        $rows = [];
        if ($candidate_ids !== []) {
            $placeholders = implode(',', array_fill(0, count($candidate_ids), '%s'));
            $sql = "SELECT m.* FROM {$mappings} m INNER JOIN {$sources} s ON s.public_id=m.source_public_id WHERE m.status='active' AND s.status<>'disabled' AND m.license_code=s.license_code AND m.remedy_public_id IN ({$placeholders}) ORDER BY m.remedy_public_id ASC,m.id ASC LIMIT 20000";
            $rows = (array)$wpdb->get_results($wpdb->prepare($sql, $candidate_ids), ARRAY_A); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }

        $by_remedy = [];
        foreach ($rows as $row) {
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
            if (count($by_remedy[$remedy_id]['mappings']) < 100) {
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
        }

        uasort($by_remedy, static function (array $a, array $b): int {
            $count = $b['match_count'] <=> $a['match_count'];
            return $count !== 0 ? $count : strcmp((string)$a['remedy_public_id'], (string)$b['remedy_public_id']);
        });
        $selected = array_slice($by_remedy, 0, $limit, true);
        $remedies = $this->file06_remedies(array_keys($selected));
        $results = [];
        foreach ($selected as $remedy_id => $result) {
            if (!isset($remedies[$remedy_id])) {
                continue;
            }
            $result['remedy'] = $remedies[$remedy_id];
            $results[] = $result;
        }
        $last_candidate = $candidate_ids !== [] ? end($candidate_ids) : null;

        return [
            'query' => $query,
            'explanation' => RSR_Domain::explain_query($query),
            'results' => $results,
            'next_cursor' => $has_more && is_string($last_candidate) ? base64_encode($last_candidate) : null,
            'source' => 'file15_local_mappings',
            'safety' => self::safety_notice(),
            'limitations' => __('Results are source-linked educational possibilities. Evidence counts are not probabilities, diagnoses, prescriptions, treatment rankings, potency choices, or dosage advice.', RSR_TEXT_DOMAIN),
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
        $raw = array_slice(array_values((array)($external['results'] ?? $external)), 0, $limit);
        $ids = [];
        foreach ($raw as $item) {
            if (is_array($item)) {
                $id = sanitize_text_field((string)($item['remedy_public_id'] ?? $item['remedy']['public_id'] ?? ''));
                if ($id !== '') {
                    $ids[] = $id;
                }
            }
        }
        $eligible = $this->file06_remedies($ids);
        $results = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $id = sanitize_text_field((string)($item['remedy_public_id'] ?? $item['remedy']['public_id'] ?? ''));
            if ($id === '' || !isset($eligible[$id])) {
                continue;
            }
            $mappings = [];
            foreach (array_slice((array)($item['mappings'] ?? []), 0, 50) as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }
                $license = sanitize_key((string)($mapping['license'] ?? $mapping['license_code'] ?? ''));
                $reference = sanitize_text_field((string)($mapping['reference'] ?? ''));
                $review_date = sanitize_text_field((string)($mapping['review_date'] ?? ''));
                if ($reference === '' || !in_array($license, RSR_Domain::allowed_source_licenses(), true) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $review_date)) {
                    continue;
                }
                $mappings[] = [
                    'mapping_id' => sanitize_text_field((string)($mapping['mapping_id'] ?? '')),
                    'explanation' => sanitize_text_field((string)($mapping['explanation'] ?? '')),
                    'source_id' => sanitize_text_field((string)($mapping['source_id'] ?? '')),
                    'reference' => $reference,
                    'license' => $license,
                    'review_date' => $review_date,
                    'version' => absint($mapping['version'] ?? 1),
                ];
            }
            $results[] = [
                'remedy_public_id' => $id,
                'remedy' => $eligible[$id],
                'match_count' => max(0, min(1000, (int)($item['match_count'] ?? count($mappings)))),
                'mappings' => $mappings,
            ];
        }
        return [
            'query' => $query,
            'explanation' => RSR_Domain::explain_query($query),
            'results' => $results,
            'next_cursor' => isset($external['next_cursor']) && is_string($external['next_cursor']) ? substr($external['next_cursor'], 0, 512) : null,
            'source' => sanitize_text_field((string)($external['source'] ?? 'file06_adapter')),
            'safety' => self::safety_notice(),
            'limitations' => __('Results are educational and source-linked; they do not authorize diagnosis or treatment.', RSR_TEXT_DOMAIN),
            'cache' => ['hit' => false],
            'generated_at' => gmdate('c'),
        ];
    }

    /** @param array<int,array<string,mixed>> $results @return array<int,array<string,mixed>> */
    private function revalidate_results(array $results): array
    {
        $ids = array_values(array_filter(array_map(static fn(array $item): string => (string)($item['remedy_public_id'] ?? $item['remedy']['public_id'] ?? ''), $results)));
        $eligible = $this->file06_remedies($ids);
        $out = [];
        foreach ($results as $item) {
            $id = (string)($item['remedy_public_id'] ?? $item['remedy']['public_id'] ?? '');
            if ($id === '' || !isset($eligible[$id])) {
                continue;
            }
            $item['remedy'] = $eligible[$id];
            $out[] = $item;
        }
        return $out;
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
