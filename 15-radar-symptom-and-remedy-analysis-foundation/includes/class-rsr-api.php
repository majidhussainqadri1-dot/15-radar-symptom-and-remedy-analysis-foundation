<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_API
{
    public const NS = 'rsr/v1';

    private RSR_Radar_Service $radar;
    private RSR_Study_Service $studies;
    private RSR_Trend_Service $trends;

    public function __construct(RSR_Radar_Service $radar, RSR_Study_Service $studies, RSR_Trend_Service $trends)
    {
        $this->radar = $radar;
        $this->studies = $studies;
        $this->trends = $trends;
    }

    public function hooks(): void
    {
        add_action('rest_api_init', [$this, 'register_routes']);
        add_filter('rest_post_dispatch', [$this, 'post_dispatch'], 20, 3);
    }

    public function register_routes(): void
    {
        register_rest_route(self::NS, '/schema', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn(WP_REST_Request $request) => $this->respond($this->radar->schema()),
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/radar/search', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'radar_search'],
            'permission_callback' => '__return_true',
            'args' => [
                'keyword' => ['type' => 'string', 'required' => false],
                'mode' => ['type' => 'string', 'enum' => ['AND', 'OR'], 'required' => false],
                'filters' => ['type' => 'object', 'required' => false],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100, 'required' => false],
            ],
        ]);

        register_rest_route(self::NS, '/radar/compare', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'radar_compare'],
            'permission_callback' => '__return_true',
            'args' => [
                'remedy_public_ids' => ['type' => 'array', 'required' => true, 'minItems' => 1, 'maxItems' => 3],
            ],
        ]);

        register_rest_route(self::NS, '/studies', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'study_list'],
                'permission_callback' => [$this, 'verified_doctor_permission'],
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'study_create'],
                'permission_callback' => [$this, 'verified_doctor_permission'],
            ],
        ]);

        register_rest_route(self::NS, '/studies/export', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'study_export'],
            'permission_callback' => [$this, 'verified_doctor_permission'],
        ]);

        register_rest_route(self::NS, '/studies/(?P<id>[A-Za-z0-9._:-]+)', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'study_get'],
                'permission_callback' => [$this, 'verified_doctor_permission'],
            ],
            [
                'methods' => WP_REST_Server::EDITABLE,
                'callback' => [$this, 'study_update'],
                'permission_callback' => [$this, 'verified_doctor_permission'],
            ],
            [
                'methods' => WP_REST_Server::DELETABLE,
                'callback' => [$this, 'study_delete'],
                'permission_callback' => [$this, 'verified_doctor_permission'],
            ],
        ]);

        register_rest_route(self::NS, '/trends', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'trend_list'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/trends/(?P<id>[A-Za-z0-9._:-]+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'trend_get'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/manage/sources', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => fn(WP_REST_Request $request) => $this->respond($this->trends->list_sources(true)),
                'permission_callback' => fn() => $this->capability_permission(RSR_Capabilities::MANAGE_SOURCES),
            ],
            [
                'methods' => WP_REST_Server::CREATABLE,
                'callback' => [$this, 'source_create'],
                'permission_callback' => fn() => $this->capability_permission(RSR_Capabilities::MANAGE_SOURCES),
            ],
        ]);

        register_rest_route(self::NS, '/manage/sources/(?P<id>[A-Za-z0-9._:-]+)', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'source_update'],
            'permission_callback' => fn() => $this->capability_permission(RSR_Capabilities::MANAGE_SOURCES),
        ]);

        register_rest_route(self::NS, '/manage/ingestion', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'run_ingestion'],
            'permission_callback' => fn() => $this->capability_permission(RSR_Capabilities::RUN_INGESTION),
        ]);

        register_rest_route(self::NS, '/manage/reports', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn(WP_REST_Request $request) => $this->respond($this->trends->manage_reports($request->get_params())),
            'permission_callback' => fn() => $this->any_capability_permission([RSR_Capabilities::REVIEW_REPORTS, RSR_Capabilities::PUBLISH_REPORTS, RSR_Capabilities::CORRECT_REPORTS]),
        ]);

        register_rest_route(self::NS, '/manage/reports/(?P<id>[A-Za-z0-9._:-]+)/transition', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'report_transition'],
            'permission_callback' => fn() => $this->any_capability_permission([RSR_Capabilities::REVIEW_REPORTS, RSR_Capabilities::PUBLISH_REPORTS]),
        ]);

        register_rest_route(self::NS, '/manage/reports/(?P<id>[A-Za-z0-9._:-]+)/correction', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'report_correction'],
            'permission_callback' => fn() => $this->capability_permission(RSR_Capabilities::CORRECT_REPORTS),
        ]);

        register_rest_route(self::NS, '/manage/schema', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'schema_upsert'],
            'permission_callback' => fn() => $this->capability_permission(RSR_Capabilities::MANAGE_SCHEMA),
        ]);

        register_rest_route(self::NS, '/manage/mappings', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'mapping_upsert'],
            'permission_callback' => fn() => $this->capability_permission(RSR_Capabilities::MANAGE_SCHEMA),
        ]);

        register_rest_route(self::NS, '/manage/diagnostics', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => fn(WP_REST_Request $request) => $this->respond(RSR_Observability::diagnostics()),
            'permission_callback' => fn() => $this->capability_permission(RSR_Capabilities::VIEW_DIAGNOSTICS),
        ]);
    }

    /** @return WP_REST_Response|WP_Error */
    public function radar_search(WP_REST_Request $request)
    {
        $limit = $this->rate_limit('public_search', 120, MINUTE_IN_SECONDS);
        if (is_wp_error($limit)) {
            return $limit;
        }
        
        $body = $request->get_json_params() ?: $request->get_params();
        return $this->respond($this->radar->search($body, (int)($body['limit'] ?? 30), isset($body['cursor']) ? (string)$body['cursor'] : null));
    }

    /** @return WP_REST_Response|WP_Error */
    public function radar_compare(WP_REST_Request $request)
    {
        $limit = $this->rate_limit('public_compare', 90, MINUTE_IN_SECONDS);
        if (is_wp_error($limit)) {
            return $limit;
        }
        return $this->respond($this->radar->compare((array)$request->get_param('remedy_public_ids')));
    }

    /** @return WP_REST_Response|WP_Error */
    public function study_list(WP_REST_Request $request)
    {
        return $this->respond($this->studies->list_own(
            get_current_user_id(),
            (int)($request->get_param('limit') ?: 50),
            $request->get_param('cursor') ? (string)$request->get_param('cursor') : null
        ));
    }

    /** @return WP_REST_Response|WP_Error */
    public function study_get(WP_REST_Request $request)
    {
        return $this->respond($this->studies->get_own((string)$request['id'], get_current_user_id()));
    }

    /** @return WP_REST_Response|WP_Error */
    public function study_create(WP_REST_Request $request)
    {
        $limit = $this->rate_limit('study_write', 30, MINUTE_IN_SECONDS);
        if (is_wp_error($limit)) {
            return $limit;
        }
        return $this->respond($this->studies->create($request->get_json_params() ?: $request->get_params(), get_current_user_id()), 201);
    }

    /** @return WP_REST_Response|WP_Error */
    public function study_update(WP_REST_Request $request)
    {
        $body = $request->get_json_params() ?: $request->get_params();
        return $this->respond($this->studies->update((string)$request['id'], $body, get_current_user_id()));
    }

    /** @return WP_REST_Response|WP_Error */
    public function study_delete(WP_REST_Request $request)
    {
        $expected = (int)($request->get_param('version') ?: $request->get_header('If-Match'));
        return $this->respond($this->studies->delete((string)$request['id'], get_current_user_id(), $expected));
    }

    /** @return WP_REST_Response|WP_Error */
    public function study_export(WP_REST_Request $request)
    {
        return $this->respond($this->studies->export_own(get_current_user_id()));
    }

    public function trend_list(WP_REST_Request $request): WP_REST_Response
    {
        return $this->respond($this->trends->public_reports($request->get_params()));
    }

    /** @return WP_REST_Response|WP_Error */
    public function trend_get(WP_REST_Request $request)
    {
        return $this->respond($this->trends->get_report((string)$request['id'], false));
    }

    /** @return WP_REST_Response|WP_Error */
    public function source_create(WP_REST_Request $request)
    {
        return $this->respond($this->trends->save_source($request->get_json_params() ?: $request->get_params()), 201);
    }

    /** @return WP_REST_Response|WP_Error */
    public function source_update(WP_REST_Request $request)
    {
        $body = $request->get_json_params() ?: $request->get_params();
        return $this->respond($this->trends->save_source($body, (string)$request['id'], (int)($body['version'] ?? 0)));
    }

    /** @return WP_REST_Response|WP_Error */
    public function run_ingestion(WP_REST_Request $request)
    {
        $body = $request->get_json_params() ?: $request->get_params();
        $token = (string)($request->get_header('Idempotency-Key') ?: ($body['idempotency_key'] ?? ''));
        if ($token === '') {
            return new WP_Error('rsr_idempotency_key_required', __('An Idempotency-Key header is required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $context = [
            'anchor' => isset($body['anchor']) ? sanitize_text_field((string)$body['anchor']) : null,
            'retry' => !empty($body['retry']),
        ];
        if (isset($body['manual_rows']) && is_array($body['manual_rows'])) {
            $context['manual_rows'] = $body['manual_rows'];
        }
        return $this->respond($this->trends->run_ingestion(
            sanitize_text_field((string)($body['source_public_id'] ?? '')),
            sanitize_key((string)($body['window_type'] ?? 'daily')),
            sanitize_text_field((string)($body['display_timezone'] ?? 'UTC')),
            sanitize_key((string)($body['geography'] ?? 'global')),
            $token,
            $context
        ));
    }

    /** @return WP_REST_Response|WP_Error */
    public function report_transition(WP_REST_Request $request)
    {
        $body = $request->get_json_params() ?: $request->get_params();
        return $this->respond($this->trends->transition_report(
            (string)$request['id'],
            sanitize_key((string)($body['to_state'] ?? '')),
            (int)($body['version'] ?? 0),
            sanitize_textarea_field((string)($body['reason'] ?? ''))
        ));
    }

    /** @return WP_REST_Response|WP_Error */
    public function report_correction(WP_REST_Request $request)
    {
        $body = $request->get_json_params() ?: $request->get_params();
        return $this->respond($this->trends->correct_report(
            (string)$request['id'],
            sanitize_key((string)($body['action'] ?? '')),
            (int)($body['version'] ?? 0),
            sanitize_textarea_field((string)($body['reason'] ?? '')),
            sanitize_textarea_field((string)($body['public_notice'] ?? '')),
            isset($body['replacement_results']) && is_array($body['replacement_results']) ? $body['replacement_results'] : null
        ));
    }

    /** @return WP_REST_Response|WP_Error */
    public function schema_upsert(WP_REST_Request $request)
    {
        return $this->respond($this->radar->upsert_schema_value($request->get_json_params() ?: $request->get_params()));
    }

    /** @return WP_REST_Response|WP_Error */
    public function mapping_upsert(WP_REST_Request $request)
    {
        return $this->respond($this->radar->upsert_mapping($request->get_json_params() ?: $request->get_params()));
    }

    /** @return true|WP_Error */
    public function verified_doctor_permission()
    {
        return RSR_Capabilities::require_verified_doctor();
    }

    /** @param array<int, string> $capabilities @return true|WP_Error */
    private function any_capability_permission(array $capabilities)
    {
        foreach ($capabilities as $capability) {
            if (RSR_Capabilities::can($capability)) {
                return true;
            }
        }
        return new WP_Error('rsr_forbidden', __('You do not have the required File 15 capability.', RSR_TEXT_DOMAIN), ['status' => is_user_logged_in() ? 403 : 401]);
    }

    /** @return true|WP_Error */
    private function capability_permission(string $capability)
    {
        if (!is_user_logged_in()) {
            return new WP_Error('rsr_auth_required', __('Authentication is required.', RSR_TEXT_DOMAIN), ['status' => 401]);
        }
        if (!RSR_Capabilities::can($capability)) {
            return new WP_Error('rsr_forbidden', __('You do not have this File 15 capability.', RSR_TEXT_DOMAIN), ['status' => 403]);
        }
        return true;
    }

    /** @param mixed $data @return WP_REST_Response|WP_Error */
    private function respond($data, int $status = 200)
    {
        if (is_wp_error($data)) {
            return $data;
        }
        $response = new WP_REST_Response([
            'data' => $data,
            'meta' => [
                'trace_id' => RSR_Observability::trace_id(),
                'api_version' => '1',
                'plugin_version' => RSR_VERSION,
            ],
        ], $status);
        return $response;
    }

    /** @return true|WP_Error */
    private function rate_limit(string $bucket, int $maximum, int $period)
    {
        $subject = get_current_user_id() > 0 ? 'u:' . get_current_user_id() : 'i:' . (RSR_Observability::request_ip_hash() ?: 'unknown');
        $key = 'rsr_rl_' . substr(hash('sha256', $bucket . '|' . $subject), 0, 38);
        $count = (int)get_transient($key);
        if ($count >= $maximum) {
            RSR_DB::audit('rate_limit', 'api_bucket', $bucket, 'abuse_prevention', 'denied', 'too_many_requests');
            return new WP_Error('rsr_rate_limited', __('Too many requests. Try again shortly.', RSR_TEXT_DOMAIN), ['status' => 429]);
        }
        set_transient($key, $count + 1, $period);
        return true;
    }

    /**
     * Adds trace, cache, and disclosure headers without exposing private details.
     *
     * @param WP_HTTP_Response $response
     * @return WP_HTTP_Response
     */
    public function post_dispatch($response, WP_REST_Server $server, WP_REST_Request $request)
    {
        if (strpos($request->get_route(), '/' . self::NS . '/') !== 0) {
            return $response;
        }
        if ($response instanceof WP_REST_Response) {
            $response->header('X-RSR-Trace-Id', RSR_Observability::trace_id());
            $response->header('X-Content-Type-Options', 'nosniff');
            $response->header('Referrer-Policy', 'same-origin');
            if (strpos($request->get_route(), '/studies') !== false || strpos($request->get_route(), '/manage') !== false) {
                $response->header('Cache-Control', 'private, no-store, max-age=0');
                $response->header('X-Robots-Tag', 'noindex, noarchive, nofollow');
            } else {
                $response->header('Cache-Control', 'public, max-age=60, stale-while-revalidate=300');
            }
        }
        return $response;
    }
}
