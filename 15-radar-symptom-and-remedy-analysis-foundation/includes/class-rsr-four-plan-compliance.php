<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

/**
 * Cross-plan hardening for File 15.
 *
 * This class contains the controls added after the four-plan audit against the
 * three platform constitutions and the dedicated File 15 plan. It does not
 * create a second data owner; it decorates public contracts, enforces privacy
 * at transport boundaries, and validates cross-file references.
 */
final class RSR_Four_Plan_Compliance
{
    private RSR_Radar_Service $radar;

    public function __construct(RSR_Radar_Service $radar)
    {
        $this->radar = $radar;
    }

    public function hooks(): void
    {
        add_filter('rest_pre_dispatch', [$this, 'guard_rest_request'], 5, 3);
        add_filter('rest_post_dispatch', [$this, 'harden_rest_response'], 99, 3);
        add_filter('sabri_file15_public_search_v1', [$this, 'enhance_public_search_contract'], 99, 2);
        add_filter('rsr_validate_study_remedy_refs', [$this, 'validate_study_remedy_refs'], 10, 2);
        add_action('template_redirect', [$this, 'protect_query_routes'], 0);
        add_filter('wp_robots', [$this, 'protect_query_robots'], 99);
    }

    /**
     * Enforce mutation throttles, public-report throttles, and robust If-Match parsing.
     *
     * @param mixed $result
     * @return mixed
     */
    public function guard_rest_request($result, WP_REST_Server $server, WP_REST_Request $request)
    {
        if ($result !== null || strpos($request->get_route(), '/rsr/v1/') !== 0) {
            return $result;
        }

        $route = $request->get_route();
        $method = strtoupper($request->get_method());

        if ($method === 'DELETE' && preg_match('#^/rsr/v1/studies/[^/]+$#', $route)) {
            $version = self::parse_entity_version($request->get_param('version'));
            if ($version <= 0) {
                $version = self::parse_entity_version($request->get_header('If-Match'));
            }
            if ($version > 0) {
                $request->set_param('version', $version);
            }
        }

        if ($method === 'GET' && preg_match('#^/rsr/v1/trends(?:/|$)#', $route)) {
            $limited = $this->rate_limit('public_trends', 180, MINUTE_IN_SECONDS);
            return is_wp_error($limited) ? $limited : $result;
        }

        if (preg_match('#^/rsr/v1/studies(?:/|$)#', $route) && $method !== 'GET') {
            $limited = $this->rate_limit('study_mutation', 30, MINUTE_IN_SECONDS);
            return is_wp_error($limited) ? $limited : $result;
        }

        if (preg_match('#^/rsr/v1/manage(?:/|$)#', $route) && $method !== 'GET') {
            $limited = $this->rate_limit('management_mutation', 60, MINUTE_IN_SECONDS);
            return is_wp_error($limited) ? $limited : $result;
        }

        return $result;
    }

    /**
     * Prevent health-research queries and private operations from being cached,
     * leaked through referrers, or distributed as public shared-cache objects.
     *
     * @param mixed $response
     * @return mixed
     */
    public function harden_rest_response($response, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (strpos($request->get_route(), '/rsr/v1/') !== 0 || !($response instanceof WP_REST_Response)) {
            return $response;
        }

        $route = $request->get_route();
        $method = strtoupper($request->get_method());
        $sensitive = $method !== 'GET'
            || strpos($route, '/rsr/v1/studies') === 0
            || strpos($route, '/rsr/v1/manage') === 0
            || strpos($route, '/rsr/v1/radar/search') === 0
            || strpos($route, '/rsr/v1/radar/compare') === 0;

        $response->header('X-Content-Type-Options', 'nosniff');
        $response->header('Referrer-Policy', $sensitive ? 'no-referrer' : 'same-origin');
        $response->header('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        $response->header('Vary', 'Accept, Accept-Language, Cookie');

        if ($sensitive) {
            $response->header('Cache-Control', 'private, no-store, max-age=0, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('X-Robots-Tag', 'noindex, noarchive, nofollow');
        }

        if ($route === '/rsr/v1/radar/search') {
            $payload = $response->get_data();
            if (is_array($payload) && isset($payload['data']) && is_array($payload['data'])) {
                $query = isset($payload['data']['query']) && is_array($payload['data']['query'])
                    ? $payload['data']['query']
                    : [];
                $payload['data'] = self::enhance_search_result($payload['data'], $query);
                $response->set_data($payload);
            }
        }

        return $response;
    }

    /**
     * @param mixed $current
     * @param array<string, mixed> $query
     * @return mixed
     */
    public function enhance_public_search_contract($current, array $query)
    {
        $effective_query = isset($query['radar_query']) && is_array($query['radar_query'])
            ? $query['radar_query']
            : $query;
        return is_array($current) ? self::enhance_search_result($current, $effective_query) : $current;
    }

    /**
     * @param mixed $current
     * @param array<int, string> $remedy_refs
     * @return true|WP_Error|mixed
     */
    public function validate_study_remedy_refs($current, array $remedy_refs)
    {
        if ($current !== null) {
            return $current;
        }
        if ($remedy_refs === []) {
            return true;
        }

        $comparison = $this->radar->compare($remedy_refs);
        if (is_wp_error($comparison)) {
            return new WP_Error(
                'rsr_study_remedy_reference_ineligible',
                __('Every saved remedy reference must resolve to a currently published and eligible File 06 remedy.', RSR_TEXT_DOMAIN),
                ['status' => 409, 'cause' => $comparison->get_error_code()]
            );
        }

        $resolved = array_map(
            static fn(array $remedy): string => (string)($remedy['public_id'] ?? ''),
            (array)($comparison['remedies'] ?? [])
        );
        $resolved = array_values(array_filter($resolved));
        $expected = array_values(array_unique(array_map('strval', $remedy_refs)));
        sort($resolved);
        sort($expected);

        if ($resolved !== $expected) {
            return new WP_Error(
                'rsr_study_remedy_reference_ineligible',
                __('One or more saved remedy references are unavailable or no longer eligible.', RSR_TEXT_DOMAIN),
                ['status' => 409]
            );
        }
        return true;
    }

    public function protect_query_routes(): void
    {
        $route = sanitize_key((string)get_query_var('rsr_route'));
        $has_research_query = ($route === 'radar' && (isset($_GET['keyword']) || self::has_filter_query()))
            || ($route === 'compare' && isset($_GET['remedies']));

        if (!$has_research_query) {
            return;
        }

        nocache_headers();
        header('Cache-Control: private, no-store, max-age=0, must-revalidate', true);
        header('Pragma: no-cache', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Robots-Tag: noindex, noarchive, nofollow', true);
        header('X-Content-Type-Options: nosniff', true);
    }

    /** @param array<string, bool> $robots @return array<string, bool> */
    public function protect_query_robots(array $robots): array
    {
        $route = sanitize_key((string)get_query_var('rsr_route'));
        $has_research_query = ($route === 'radar' && (isset($_GET['keyword']) || self::has_filter_query()))
            || ($route === 'compare' && isset($_GET['remedies']));
        if ($has_research_query) {
            $robots['noindex'] = true;
            $robots['noarchive'] = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }

    /**
     * Converts opaque count-based ranking into an explainable evidence view.
     *
     * @param array<string, mixed> $result
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public static function enhance_search_result(array $result, array $query = []): array
    {
        if ($query === [] && isset($result['query']) && is_array($result['query'])) {
            $query = $result['query'];
        }

        $items = [];
        foreach ((array)($result['results'] ?? []) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $mappings = array_slice((array)($item['mappings'] ?? []), 0, 8);
            $explanations = [];
            $sources = [];
            $latest_review = null;
            foreach ($mappings as $mapping) {
                if (!is_array($mapping)) {
                    continue;
                }
                $explanation = sanitize_text_field((string)($mapping['explanation'] ?? ''));
                if ($explanation !== '') {
                    $explanations[] = $explanation;
                }
                $source = sanitize_text_field((string)($mapping['source_id'] ?? ''));
                if ($source !== '') {
                    $sources[] = $source;
                }
                $review_date = sanitize_text_field((string)($mapping['review_date'] ?? ''));
                if ($review_date !== '' && ($latest_review === null || strcmp($review_date, $latest_review) > 0)) {
                    $latest_review = $review_date;
                }
            }

            $item['evidence_match_count'] = max(0, (int)($item['match_count'] ?? count($mappings)));
            $item['why_this_result'] = [
                'matched_query' => RSR_Domain::explain_query($query),
                'matched_rubrics' => array_values(array_unique($explanations)),
                'source_ids' => array_values(array_unique($sources)),
                'latest_review_date' => $latest_review,
                'meaning' => __('Ordered by matching governed evidence, not by likelihood, popularity, cure probability, prescription priority, potency, or dosage.', RSR_TEXT_DOMAIN),
            ];
            unset($item['educational_match_score']);
            $items[] = $item;
        }
        $result['results'] = $items;
        $result['ranking'] = [
            'method' => 'explainable_evidence_match_v1',
            'clinical_rank' => false,
            'paid_or_donor_bias' => false,
            'notice' => __('Result order reflects matching reviewed mappings only and has no prescribing authority.', RSR_TEXT_DOMAIN),
        ];
        $result['privacy'] = [
            'query_not_public_profile_data' => true,
            'private_studies_excluded' => true,
            'shared_cache_allowed' => false,
        ];

        if ($items === []) {
            $result['zero_result_recovery'] = self::zero_result_recovery($query);
        }
        $escalation = self::safety_escalation($query);
        if ($escalation !== null) {
            $result['safety_escalation'] = $escalation;
        }
        return $result;
    }

    /** @param array<string, mixed> $query @return array<string, mixed> */
    public static function zero_result_recovery(array $query): array
    {
        $filters = (array)($query['filters'] ?? []);
        $suggestions = [
            __('Check spelling and approved Urdu, Arabic, English, or Latin aliases.', RSR_TEXT_DOMAIN),
            __('Remove one narrow filter and run the research query again.', RSR_TEXT_DOMAIN),
            __('Review the Encyclopedia or submit a governed source-mapping gap instead of accepting a fabricated result.', RSR_TEXT_DOMAIN),
        ];
        if (strtoupper((string)($query['mode'] ?? 'AND')) === 'AND' && count($filters) > 1) {
            array_unshift($suggestions, __('Try the OR match rule to inspect broader educational possibilities.', RSR_TEXT_DOMAIN));
        }

        $alternate = $query;
        if (count($filters) > 1) {
            $alternate['mode'] = 'OR';
        }
        unset($alternate['hash']);

        return [
            'fabricated_results' => false,
            'suggestions' => $suggestions,
            'alternate_query' => $alternate,
            'gap_submission_url' => esc_url_raw((string)apply_filters('rsr_zero_result_help_url', '', $query)),
        ];
    }

    /** @param array<string, mixed> $query @return array<string, mixed>|null */
    public static function safety_escalation(array $query): ?array
    {
        $text = strtolower(wp_strip_all_tags((string)($query['keyword'] ?? '')));
        foreach ((array)($query['filters'] ?? []) as $values) {
            $text .= ' ' . strtolower(implode(' ', array_map('strval', (array)$values)));
        }

        $signals = [
            'chest pain', 'difficulty breathing', 'shortness of breath', 'unconscious',
            'severe bleeding', 'stroke', 'suicide', 'poisoning', 'anaphylaxis',
            'سینے میں شدید درد', 'سانس لینے میں دشواری', 'بے ہوش', 'شدید خون',
            'خودکشی', 'فالج', 'زہر', 'اختناق', 'ألم شديد في الصدر', 'صعوبة التنفس',
        ];
        foreach ($signals as $signal) {
            if ($signal !== '' && strpos($text, $signal) !== false) {
                return [
                    'triggered' => true,
                    'message' => __('This query may describe an urgent red flag. Do not use Radar for emergency decisions; seek appropriate local emergency medical care immediately.', RSR_TEXT_DOMAIN),
                    'radar_replacement' => false,
                ];
            }
        }
        return null;
    }

    /** @param mixed $value */
    public static function parse_entity_version($value): int
    {
        if (is_int($value) || is_float($value)) {
            return max(0, (int)$value);
        }
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }
        if (preg_match('/(?:W\/)?["\']?(?:v)?(\d+)["\']?/', $value, $matches)) {
            return max(0, (int)$matches[1]);
        }
        return 0;
    }

    /** @return true|WP_Error */
    private function rate_limit(string $bucket, int $maximum, int $period)
    {
        $subject = get_current_user_id() > 0
            ? 'u:' . get_current_user_id()
            : 'i:' . (RSR_Observability::request_ip_hash() ?: 'unknown');
        $key = 'rsr_cp_' . substr(hash('sha256', $bucket . '|' . $subject), 0, 38);
        $count = (int)get_transient($key);
        if ($count >= $maximum) {
            RSR_DB::audit('rate_limit', 'api_bucket', $bucket, 'abuse_prevention', 'denied', 'too_many_requests');
            return new WP_Error(
                'rsr_rate_limited',
                __('Too many requests. Try again shortly.', RSR_TEXT_DOMAIN),
                ['status' => 429, 'retry_after' => $period]
            );
        }
        set_transient($key, $count + 1, $period);
        return true;
    }

    private static function has_filter_query(): bool
    {
        foreach (array_keys($_GET) as $key) {
            if (strpos((string)$key, 'filter_') === 0) {
                return true;
            }
        }
        return false;
    }
}
