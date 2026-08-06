<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Routes
{
    private RSR_Radar_Service $radar;
    private RSR_Study_Service $studies;
    private RSR_Trend_Service $trends;

    /** @var array<string, mixed> */
    private static array $view = [];
    private static bool $context_controls_rendered = false;

    public function __construct(RSR_Radar_Service $radar, RSR_Study_Service $studies, RSR_Trend_Service $trends)
    {
        $this->radar = $radar;
        $this->studies = $studies;
        $this->trends = $trends;
    }

    public function hooks(): void
    {
        add_action('init', [$this, 'rewrite_rules']);
        add_filter('query_vars', [$this, 'query_vars']);
        add_filter('template_include', [$this, 'template_include'], 99);
        add_action('wp_enqueue_scripts', [$this, 'assets']);
        add_action('template_redirect', [$this, 'privacy_headers'], 1);
        add_filter('wp_robots', [$this, 'robots']);
        add_filter('document_title_parts', [$this, 'title']);
        add_action('wp_head', [$this, 'canonical'], 2);
        add_filter('body_class', [$this, 'body_classes']);
    }

    public function rewrite_rules(): void
    {
        add_rewrite_rule('^radar/?$', 'index.php?rsr_route=radar', 'top');
        add_rewrite_rule('^radar/compare/?$', 'index.php?rsr_route=compare', 'top');
        add_rewrite_rule('^radar/studies/?$', 'index.php?rsr_route=studies', 'top');
        add_rewrite_rule('^trends/?$', 'index.php?rsr_route=trends', 'top');
        add_rewrite_rule('^trends/([A-Za-z0-9._:-]+)/?$', 'index.php?rsr_route=trend_report&rsr_report_id=$matches[1]', 'top');
        add_rewrite_rule('^radar/manage/?$', 'index.php?rsr_route=manage', 'top');
    }

    /** @param array<int, string> $vars @return array<int, string> */
    public function query_vars(array $vars): array
    {
        $vars[] = 'rsr_route';
        $vars[] = 'rsr_report_id';
        return $vars;
    }

    public function template_include(string $template): string
    {
        $route = $this->route();
        if ($route === '') {
            return $template;
        }

        $map = [
            'radar' => 'radar.php',
            'compare' => 'compare.php',
            'studies' => 'studies.php',
            'trends' => 'trends.php',
            'trend_report' => 'trend-report.php',
            'manage' => 'manage.php',
        ];
        if (!isset($map[$route])) {
            return $template;
        }

        $access = $this->authorize_route($route);
        if (is_wp_error($access)) {
            status_header((int)($access->get_error_data()['status'] ?? 403));
            nocache_headers();
            self::$view = ['route' => 'error', 'error' => $access];
            return RSR_DIR . 'templates/error.php';
        }

        self::$view = $this->build_view($route);
        if ($route === 'trend_report' && isset(self::$view['report']) && is_wp_error(self::$view['report'])) {
            $status = (int)(self::$view['report']->get_error_data()['status'] ?? 404);
            status_header($status > 0 ? $status : 404);
        }
        return RSR_DIR . 'templates/' . $map[$route];
    }

    public function assets(): void
    {
        $route = $this->route();
        if ($route === '') {
            return;
        }
        wp_enqueue_style('rsr-file-15', RSR_URL . 'assets/css/rsr.css', [], RSR_VERSION);
        wp_enqueue_script('rsr-file-15', RSR_URL . 'assets/js/rsr.js', [], RSR_VERSION, true);
        wp_localize_script('rsr-file-15', 'RSR_CONFIG', [
            'restRoot' => esc_url_raw(rest_url(RSR_API::NS . '/')),
            'nonce' => is_user_logged_in() ? wp_create_nonce('wp_rest') : '',
            'traceId' => RSR_Observability::trace_id(),
            'maxCompare' => RSR_Domain::MAX_COMPARE_REMEDIES,
            'strings' => [
                'loading' => __('Loading…', RSR_TEXT_DOMAIN),
                'error' => __('The request could not be completed.', RSR_TEXT_DOMAIN),
                'saved' => __('Saved.', RSR_TEXT_DOMAIN),
                'deleted' => __('Deleted.', RSR_TEXT_DOMAIN),
                'exportPrepared' => __('Export prepared.', RSR_TEXT_DOMAIN),
                'invalidStructuredQuery' => __('Structured query JSON is invalid.', RSR_TEXT_DOMAIN),
                'maximumThree' => __('Choose no more than three remedies.', RSR_TEXT_DOMAIN),
                'confirmDelete' => __('Delete this private study?', RSR_TEXT_DOMAIN),
            ],
        ]);
        if ($route === 'manage') {
            wp_enqueue_script('rsr-file-15-admin', RSR_URL . 'assets/js/rsr-admin.js', ['rsr-file-15'], RSR_VERSION, true);
        }
    }

    public function privacy_headers(): void
    {
        $route = $this->route();
        if (!in_array($route, ['studies', 'manage'], true)) {
            return;
        }
        nocache_headers();
        header('X-Robots-Tag: noindex, noarchive, nofollow', true);
        header('Referrer-Policy: no-referrer', true);
        header('X-Content-Type-Options: nosniff', true);
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()', true);
    }

    /** @param array<string, bool> $robots @return array<string, bool> */
    public function robots(array $robots): array
    {
        if (in_array($this->route(), ['studies', 'manage'], true)) {
            $robots['noindex'] = true;
            $robots['noarchive'] = true;
            $robots['nofollow'] = true;
        }
        return $robots;
    }

    /** @param array<string, string> $parts @return array<string, string> */
    public function title(array $parts): array
    {
        $titles = [
            'radar' => __('Radar Research', RSR_TEXT_DOMAIN),
            'compare' => __('Remedy Comparison', RSR_TEXT_DOMAIN),
            'studies' => __('Private Radar Studies', RSR_TEXT_DOMAIN),
            'trends' => __('Trend Intelligence', RSR_TEXT_DOMAIN),
            'trend_report' => __('Trend Report', RSR_TEXT_DOMAIN),
            'manage' => __('Radar Operations', RSR_TEXT_DOMAIN),
        ];
        $route = $this->route();
        if (isset($titles[$route])) {
            $parts['title'] = $titles[$route];
        }
        return $parts;
    }

    public function canonical(): void
    {
        $route = $this->route();
        if ($route === '' || in_array($route, ['studies', 'manage'], true)) {
            return;
        }
        $url = match ($route) {
            'radar' => home_url('/radar/'),
            'compare' => home_url('/radar/compare/'),
            'trends' => home_url('/trends/'),
            'trend_report' => home_url('/trends/' . rawurlencode((string)get_query_var('rsr_report_id')) . '/'),
            default => '',
        };
        if ($url !== '') {
            echo '<link rel="canonical" href="' . esc_url($url) . '">' . "\n";
        }
    }

    /** @param array<int, string> $classes @return array<int, string> */
    public function body_classes(array $classes): array
    {
        $route = $this->route();
        if ($route !== '') {
            $classes[] = 'rsr-file-15';
            $classes[] = 'rsr-route-' . sanitize_html_class($route);
        }
        return $classes;
    }

    /** @return array<string, mixed> */
    private function build_view(string $route): array
    {
        $base = [
            'route' => $route,
            'schema' => $this->radar->schema(),
            'safety' => RSR_Radar_Service::safety_notice(),
            'urls' => [
                'home' => home_url('/'),
                'radar' => home_url('/radar/'),
                'compare' => home_url('/radar/compare/'),
                'studies' => home_url('/radar/studies/'),
                'trends' => home_url('/trends/'),
                'manage' => home_url('/radar/manage/'),
                'login' => wp_login_url(home_url('/radar/studies/')),
            ],
            'can_save' => RSR_Capabilities::is_verified_doctor() || RSR_Capabilities::is_founder(),
            'can_manage_sources' => RSR_Capabilities::can(RSR_Capabilities::MANAGE_SOURCES),
            'can_run_ingestion' => RSR_Capabilities::can(RSR_Capabilities::RUN_INGESTION),
            'can_review_reports' => RSR_Capabilities::can(RSR_Capabilities::REVIEW_REPORTS),
            'can_publish_reports' => RSR_Capabilities::can(RSR_Capabilities::PUBLISH_REPORTS),
            'can_correct_reports' => RSR_Capabilities::can(RSR_Capabilities::CORRECT_REPORTS),
            'can_manage_reports' => $this->has_any_capability([RSR_Capabilities::REVIEW_REPORTS, RSR_Capabilities::PUBLISH_REPORTS, RSR_Capabilities::CORRECT_REPORTS]),
            'can_view_diagnostics' => RSR_Capabilities::can(RSR_Capabilities::VIEW_DIAGNOSTICS),
            'can_manage' => $this->has_any_capability([
                RSR_Capabilities::MANAGE_SOURCES,
                RSR_Capabilities::RUN_INGESTION,
                RSR_Capabilities::REVIEW_REPORTS,
                RSR_Capabilities::PUBLISH_REPORTS,
                RSR_Capabilities::CORRECT_REPORTS,
                RSR_Capabilities::VIEW_DIAGNOSTICS,
            ]),
        ];

        if ($route === 'radar') {
            $query = $this->request_query();
            $base['query'] = $query;
            $search = ($query['keyword'] !== '' || $query['filters'] !== [])
                ? $this->radar->search($query, 30, isset($_GET['cursor']) ? sanitize_text_field(wp_unslash((string)$_GET['cursor'])) : null)
                : null;
            $base['results'] = is_array($search)
                ? RSR_Four_Plan_Compliance::enhance_search_result($search, $query)
                : $search;
        } elseif ($route === 'compare') {
            $ids = isset($_GET['remedies']) ? array_map('sanitize_text_field', (array)wp_unslash($_GET['remedies'])) : [];
            $base['selected_ids'] = array_slice($ids, 0, RSR_Domain::MAX_COMPARE_REMEDIES);
            $base['comparison'] = $ids !== [] ? $this->radar->compare($ids) : null;
        } elseif ($route === 'studies') {
            $base['studies'] = is_user_logged_in() ? $this->studies->list_own(get_current_user_id(), 50, null) : null;
        } elseif ($route === 'trends') {
            $base['reports'] = $this->trends->public_reports([
                'window_type' => isset($_GET['window']) ? sanitize_key(wp_unslash((string)$_GET['window'])) : '',
                'geography' => isset($_GET['geography']) ? sanitize_key(wp_unslash((string)$_GET['geography'])) : '',
                'limit' => 50,
            ]);
        } elseif ($route === 'trend_report') {
            $base['report'] = $this->trends->get_report((string)get_query_var('rsr_report_id'), false);
        } elseif ($route === 'manage') {
            $base['diagnostics'] = $base['can_view_diagnostics'] ? RSR_Observability::diagnostics() : [
                'plugin_version' => RSR_VERSION,
                'schema_version' => (string)get_option('rsr_schema_version', '0'),
                'missing_tables' => [],
                'rows' => [],
            ];
            $base['sources'] = $base['can_manage_sources'] || $base['can_run_ingestion'] ? $this->trends->list_sources($base['can_manage_sources']) : [];
            $base['reports'] = $base['can_manage_reports'] ? $this->trends->manage_reports(['limit' => 100]) : [];
            $base['providers'] = $base['can_manage_sources'] ? RSR_Provider_Registry::describe() : [];
        }
        return $base;
    }

    /** @return true|WP_Error */
    private function authorize_route(string $route)
    {
        if ($route === 'studies') {
            if (!is_user_logged_in()) {
                return true;
            }
            return RSR_Capabilities::require_verified_doctor();
        }
        if ($route === 'manage' && !$this->has_any_capability([
            RSR_Capabilities::MANAGE_SOURCES,
            RSR_Capabilities::RUN_INGESTION,
            RSR_Capabilities::REVIEW_REPORTS,
            RSR_Capabilities::PUBLISH_REPORTS,
            RSR_Capabilities::CORRECT_REPORTS,
            RSR_Capabilities::VIEW_DIAGNOSTICS,
        ])) {
            return new WP_Error('rsr_forbidden', __('You cannot access Radar operations.', RSR_TEXT_DOMAIN), ['status' => is_user_logged_in() ? 403 : 401]);
        }
        return true;
    }

    /** @param array<int, string> $capabilities */
    private function has_any_capability(array $capabilities): bool
    {
        foreach ($capabilities as $capability) {
            if (RSR_Capabilities::can($capability)) {
                return true;
            }
        }
        return false;
    }

    /** @return array<string, mixed> */
    private function request_query(): array
    {
        $filters = [];
        foreach (array_keys(RSR_Domain::dimensions()) as $dimension) {
            $key = 'filter_' . $dimension;
            if (isset($_GET[$key])) {
                $values = array_map('sanitize_text_field', (array)wp_unslash($_GET[$key]));
                $values = array_values(array_filter($values, static fn(string $value): bool => $value !== ''));
                if ($values !== []) {
                    $filters[$dimension] = $values;
                }
            }
        }
        return [
            'keyword' => isset($_GET['keyword']) ? sanitize_text_field(wp_unslash((string)$_GET['keyword'])) : '',
            'mode' => isset($_GET['mode']) && strtoupper((string)$_GET['mode']) === 'OR' ? 'OR' : 'AND',
            'filters' => $filters,
        ];
    }

    public function route(): string
    {
        return sanitize_key((string)get_query_var('rsr_route'));
    }

    /** @return array<string, mixed> */
    public static function view(): array
    {
        return self::$view;
    }

    /** @param array<string, mixed> $args */
    public static function context_controls(array $args = []): void
    {
        if (self::$context_controls_rendered) {
            return;
        }
        self::$context_controls_rendered = true;

        $fallback = isset($args['fallback']) ? (string)$args['fallback'] : home_url('/radar/');
        $fallback = wp_validate_redirect($fallback, home_url('/radar/'));
        $args['fallback'] = $fallback;
        $args['home'] = home_url('/');
        $args['rtl'] = is_rtl();
        $args['module'] = 'file15';

        $owner_markup = apply_filters('sabri_file20_context_controls_markup_v1', '', $args);
        if (is_string($owner_markup) && trim($owner_markup) !== '') {
            echo wp_kses_post($owner_markup);
            return;
        }

        echo '<nav class="rsr-context-controls" aria-label="' . esc_attr__('Context navigation', RSR_TEXT_DOMAIN) . '" data-rsr-file20-fallback="1">';
        $back_icon = is_rtl() ? '→' : '←';
        echo '<button type="button" class="rsr-icon-button" data-rsr-back data-fallback="' . esc_url($fallback) . '"><span aria-hidden="true">' . esc_html($back_icon) . '</span><span>' . esc_html__('Back', RSR_TEXT_DOMAIN) . '</span></button>';
        echo '<a class="rsr-icon-button" href="' . esc_url(home_url('/')) . '"><span aria-hidden="true">⌂</span><span>' . esc_html__('Home', RSR_TEXT_DOMAIN) . '</span></a>';
        do_action('rsr_context_controls', $args);
        echo '</nav>';
    }
}
