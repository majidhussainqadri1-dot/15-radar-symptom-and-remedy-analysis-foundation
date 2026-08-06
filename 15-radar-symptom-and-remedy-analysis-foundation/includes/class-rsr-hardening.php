<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

/**
 * Shared defensive primitives added by the forty-round review.
 *
 * The class owns no domain data. It supplies bounded validation, atomic abuse
 * controls, exact concurrency parsing, and transaction helpers to File 15.
 */
final class RSR_Hardening
{
    public const MAX_SOURCE_CONFIG_BYTES = 65536;
    public const MAX_MANUAL_ROWS = 5000;
    public const MAX_MANUAL_BODY_BYTES = 1048576;
    public const MAX_SOURCE_REFERENCE_LENGTH = 191;
    public const MAX_GEOGRAPHY_LENGTH = 100;
    public const MAX_ABSOLUTE_VOLUME = 1000000000000.0;

    /** @param mixed $value */
    public static function finite_float($value, float $minimum, float $maximum, ?float $default = null): ?float
    {
        if (!is_numeric($value)) {
            return $default;
        }
        $number = (float)$value;
        if (is_nan($number) || is_infinite($number)) {
            return $default;
        }
        return max($minimum, min($maximum, $number));
    }

    /** @param mixed $value */
    public static function parse_entity_version($value): int
    {
        if (is_int($value)) {
            return max(0, $value);
        }
        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }
        if (preg_match('/^(?:W\/)?"?([1-9][0-9]{0,18})"?$/', $value, $matches) !== 1) {
            return 0;
        }
        return (int)$matches[1];
    }

    /** @param mixed $value */
    public static function encoded_size($value): int
    {
        $encoded = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? strlen($encoded) : PHP_INT_MAX;
    }

    /** @param mixed $value */
    public static function contains_secret_material($value): bool
    {
        $encoded = wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded)) {
            return true;
        }
        $patterns = [
            '/"[^"\\n]*(?:secret|token|password|api[_-]?key|authorization|cookie|private[_-]?key)[^"\\n]*"\s*:/i',
            '/\b(?:ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,}|AKIA[0-9A-Z]{16})\b/',
            '/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/',
            '/\bBearer\s+[A-Za-z0-9._~+\/-]{20,}=*\b/i',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $encoded) === 1) {
                return true;
            }
        }
        return false;
    }

    public static function normalize_geography(string $value, string $fallback = 'global'): string
    {
        $value = sanitize_key($value);
        if ($value === '') {
            $value = sanitize_key($fallback) ?: 'global';
        }
        return substr($value, 0, self::MAX_GEOGRAPHY_LENGTH);
    }

    /**
     * Database-atomic fixed-window limiter. A failed limiter fails closed.
     *
     * @return true|WP_Error
     */
    public static function rate_limit(string $bucket, int $maximum, int $period)
    {
        global $wpdb;
        $maximum = max(1, $maximum);
        $period = max(1, $period);
        $subject = get_current_user_id() > 0
            ? 'u:' . get_current_user_id()
            : 'i:' . (RSR_Observability::request_ip_hash() ?: 'anonymous');
        $key = hash('sha256', sanitize_key($bucket) . '|' . $subject);
        $table = RSR_DB::table('rate_limits');
        $now = current_time('mysql', true);
        $expires = gmdate('Y-m-d H:i:s', time() + $period);

        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (bucket_key, counter, window_expires_at, updated_at)
             VALUES (%s, 1, %s, %s)
             ON DUPLICATE KEY UPDATE
               counter = IF(window_expires_at <= VALUES(updated_at), 1, counter + 1),
               window_expires_at = IF(window_expires_at <= VALUES(updated_at), VALUES(window_expires_at), window_expires_at),
               updated_at = VALUES(updated_at)",
            $key,
            $expires,
            $now
        );
        $written = $wpdb->query($sql);
        if ($written === false) {
            RSR_Observability::log('error', 'rate_limit_storage_failed', ['bucket' => $bucket]);
            return new WP_Error('rsr_rate_limit_unavailable', __('Request protection is temporarily unavailable.', RSR_TEXT_DOMAIN), ['status' => 503]);
        }

        $row = $wpdb->get_row($wpdb->prepare("SELECT counter, window_expires_at FROM {$table} WHERE bucket_key = %s LIMIT 1", $key), ARRAY_A);
        if (!is_array($row)) {
            return new WP_Error('rsr_rate_limit_unavailable', __('Request protection is temporarily unavailable.', RSR_TEXT_DOMAIN), ['status' => 503]);
        }
        if ((int)$row['counter'] > $maximum) {
            $retry = max(1, (int)strtotime((string)$row['window_expires_at'] . ' UTC') - time());
            RSR_DB::audit('rate_limit', 'api_bucket', $bucket, 'abuse_prevention', 'denied', 'too_many_requests', ['retry_after' => $retry]);
            return new WP_Error('rsr_rate_limited', __('Too many requests. Try again shortly.', RSR_TEXT_DOMAIN), ['status' => 429, 'retry_after' => $retry]);
        }
        return true;
    }

    /** @return mixed */
    public static function transaction(callable $callback)
    {
        global $wpdb;
        if ($wpdb->query('START TRANSACTION') === false) {
            throw new RuntimeException('transaction_start_failed');
        }
        try {
            $result = $callback();
            if (is_wp_error($result)) {
                throw new RuntimeException($result->get_error_code());
            }
            if ($wpdb->query('COMMIT') === false) {
                throw new RuntimeException('transaction_commit_failed');
            }
            return $result;
        } catch (Throwable $error) {
            $wpdb->query('ROLLBACK');
            throw $error;
        }
    }
}
