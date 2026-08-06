<?php

declare(strict_types=1);

define('RSR_TESTING', true);
define('RSR_TEST_KEY', 'file-15-v1.2.0-deterministic-test-key-material-with-sufficient-entropy');
define('RSR_TEXT_DOMAIN', 'radar-symptom-remedy-analysis');

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($value, int $flags = 0, int $depth = 512)
    {
        return json_encode($value, $flags, $depth);
    }
}
if (!function_exists('sanitize_key')) {
    function sanitize_key($key): string
    {
        $key = strtolower((string)$key);
        return trim((string)preg_replace('/[^a-z0-9_\-]/', '', $key));
    }
}
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value): string
    {
        return trim(strip_tags((string)$value));
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        return $value;
    }
}
if (!function_exists('__')) {
    function __(string $text, ?string $domain = null): string
    {
        return $text;
    }
}

$plugin = dirname(__DIR__) . '/15-radar-symptom-and-remedy-analysis-foundation/includes';
require_once $plugin . '/class-rsr-hardening.php';
require_once $plugin . '/class-rsr-domain.php';
require_once $plugin . '/class-rsr-pii-scanner.php';
require_once $plugin . '/class-rsr-crypto.php';
