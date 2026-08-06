<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Observability
{
    private static array $request_metrics = [];

    public static function trace_id(): string
    {
        static $trace = null;
        if (is_string($trace) && $trace !== '') {
            return $trace;
        }
        $incoming = isset($_SERVER['HTTP_X_RSR_TRACE_ID']) ? sanitize_text_field(wp_unslash((string)$_SERVER['HTTP_X_RSR_TRACE_ID'])) : '';
        $trace = $incoming !== '' && preg_match('/^[A-Za-z0-9-]{12,64}$/', $incoming)
            ? $incoming
            : 'rsr-' . bin2hex(random_bytes(12));
        return $trace;
    }

    public static function request_ip_hash(): ?string
    {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? trim((string)$_SERVER['REMOTE_ADDR']) : '';
        if ($ip === '') {
            return null;
        }
        $salt = function_exists('wp_salt') ? (string)wp_salt('auth') : (defined('AUTH_SALT') ? (string)AUTH_SALT : '');
        if (strlen($salt) < 16) {
            return null;
        }
        return hash_hmac('sha256', $ip, $salt);
    }

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

    public static function request_metrics(): array
    {
        return self::$request_metrics;
    }

    /** @param mixed $value @return mixed */
    public static function redact($value)
    {
        $state = ['nodes' => 0];
        return self::redact_bounded($value, 0, $state);
    }

    /** @param mixed $value @param array<string,int> $state @return mixed */
    private static function redact_bounded($value, int $depth, array &$state)
    {
        $state['nodes']++;
        if ($depth > 8 || $state['nodes'] > 1000) {
            return '[truncated]';
        }
        if (is_array($value)) {
            $out = [];
            foreach (array_slice($value, 0, 200, true) as $key => $item) {
                $key_string = strtolower((string)$key);
                $out[$key] = preg_match('/(?:secret|token|password|credential|authorization|cookie|nonce|notes_encrypted|email|phone|cnic|passport)/', $key_string)
                    ? '[redacted]'
                    : self::redact_bounded($item, $depth + 1, $state);
            }
            return $out;
        }
        if (is_object($value)) {
            return self::redact_bounded(get_object_vars($value), $depth + 1, $state);
        }
        if (is_string($value)) {
            if (RSR_Hardening::contains_secret_material($value) || !RSR_PII_Scanner::scan($value)['safe']) {
                return '[redacted]';
            }
            return strlen($value) > 500 ? substr($value, 0, 500) . '…' : $value;
        }
        return $value;
    }

    public static function diagnostics(): array
    {
        $missing = RSR_DB::missing_tables();
        $fingerprint = null;
        try {
            $fingerprint = RSR_Crypto::key_fingerprint();
        } catch (Throwable $error) {
            $fingerprint = 'unavailable';
        }
        return [
            'plugin_version' => RSR_VERSION,
            'schema_version' => (string)get_option('rsr_schema_version', '0'),
            'expected_schema_version' => RSR_SCHEMA_VERSION,
            'missing_tables' => $missing,
            'cron' => [
                'reconcile_trend_windows' => wp_next_scheduled('rsr_reconcile_trend_windows') ?: null,
                'process_outbox' => wp_next_scheduled('rsr_process_outbox') ?: null,
                'retention_cleanup' => wp_next_scheduled('rsr_retention_cleanup') ?: null,
            ],
            'providers' => array_keys(RSR_Provider_Registry::all()),
            'rows' => $missing === [] ? RSR_DB::row_counts() : [],
            'crypto_key_fingerprint' => $fingerprint,
            'safe_mode' => (bool)get_option('rsr_safe_mode', false),
            'request_metrics' => self::request_metrics(),
            'trace_id' => self::trace_id(),
        ];
    }
}
