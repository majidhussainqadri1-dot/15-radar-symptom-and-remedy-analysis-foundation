<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Observability
{
    /** @var array<string, int> */
    private static array $request_metrics = [];

    public static function trace_id(): string
    {
        static $trace = null;
        if (is_string($trace) && $trace !== '') {
            return $trace;
        }

        $incoming = isset($_SERVER['HTTP_X_RSR_TRACE_ID'])
            ? sanitize_text_field(wp_unslash((string)$_SERVER['HTTP_X_RSR_TRACE_ID']))
            : '';
        if ($incoming !== '' && preg_match('/^[A-Za-z0-9-]{12,64}$/', $incoming)) {
            $trace = $incoming;
        } else {
            $trace = 'rsr-' . bin2hex(random_bytes(12));
        }
        return $trace;
    }

    public static function request_ip_hash(): ?string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
        if ($ip === '') {
            return null;
        }
        $salt = defined('AUTH_SALT') ? (string)AUTH_SALT : 'rsr-ip-hash';
        return hash_hmac('sha256', $ip, $salt);
    }

    /** @param array<string, mixed> $context */
    public static function log(string $level, string $event, array $context = []): void
    {
        if (!(bool)apply_filters('rsr_enable_structured_logs', defined('WP_DEBUG_LOG') && WP_DEBUG_LOG)) {
            return;
        }

        $payload = [
            'component' => 'file-15-radar-trends',
            'level' => sanitize_key($level),
            'event' => sanitize_key($event),
            'trace_id' => self::trace_id(),
            'time_utc' => gmdate('c'),
            'context' => self::redact($context),
        ];
        error_log(wp_json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
    }

    public static function increment(string $metric, int $amount = 1): void
    {
        $metric = sanitize_key($metric);
        self::$request_metrics[$metric] = (self::$request_metrics[$metric] ?? 0) + $amount;
    }

    /** @return array<string, int> */
    public static function request_metrics(): array
    {
        return self::$request_metrics;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public static function redact($value)
    {
        if (is_array($value)) {
            $out = [];
            foreach ($value as $key => $item) {
                $key_string = strtolower((string)$key);
                if (preg_match('/(?:secret|token|password|credential|authorization|cookie|nonce|notes_encrypted|email|phone|cnic|passport)/', $key_string)) {
                    $out[$key] = '[redacted]';
                } else {
                    $out[$key] = self::redact($item);
                }
            }
            return $out;
        }
        if (is_object($value)) {
            return self::redact(get_object_vars($value));
        }
        if (is_string($value) && strlen($value) > 500) {
            return substr($value, 0, 500) . '…';
        }
        return $value;
    }

    /** @return array<string, mixed> */
    public static function diagnostics(): array
    {
        global $wpdb;
        $tables = RSR_DB::tables();
        $missing = [];
        foreach ($tables as $key => $table) {
            $found = (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
            if ($found !== $table) {
                $missing[] = $key;
            }
        }

        $next_ingestion = wp_next_scheduled('rsr_reconcile_trend_windows');
        $next_outbox = wp_next_scheduled('rsr_process_outbox');
        $next_retention = wp_next_scheduled('rsr_retention_cleanup');

        return [
            'plugin_version' => RSR_VERSION,
            'schema_version' => (string)get_option('rsr_schema_version', '0'),
            'expected_schema_version' => RSR_SCHEMA_VERSION,
            'missing_tables' => $missing,
            'cron' => [
                'reconcile_trend_windows' => $next_ingestion ?: null,
                'process_outbox' => $next_outbox ?: null,
                'retention_cleanup' => $next_retention ?: null,
            ],
            'providers' => array_keys(RSR_Provider_Registry::all()),
            'rows' => $missing === [] ? RSR_DB::row_counts() : [],
            'crypto_key_fingerprint' => RSR_Crypto::key_fingerprint(),
            'safe_mode' => (bool)get_option('rsr_safe_mode', false),
            'request_metrics' => self::request_metrics(),
            'trace_id' => self::trace_id(),
        ];
    }
}
