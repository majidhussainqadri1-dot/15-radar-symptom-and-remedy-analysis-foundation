<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!defined('HOUR_IN_SECONDS')) { define('HOUR_IN_SECONDS', 3600); }
if (!defined('DAY_IN_SECONDS')) { define('DAY_IN_SECONDS', 86400); }
if (!defined('YEAR_IN_SECONDS')) { define('YEAR_IN_SECONDS', 31536000); }
if (!class_exists('WP_Error')) {
    class WP_Error {
        private string $code;
        public function __construct(string $code, string $message = '', array $data = []) { $this->code = $code; }
        public function get_error_code(): string { return $this->code; }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($value): bool { return $value instanceof WP_Error; }
}
if (!function_exists('do_action')) {
    function do_action(string $action, ...$args): void {}
}
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($text): string { return sanitize_text_field($text); }
}
if (!function_exists('sanitize_title')) {
    function sanitize_title($text): string {
        return strtolower(trim((string)preg_replace('/[^a-z0-9_-]+/i', '-', (string)$text), '-'));
    }
}

$root = dirname(__DIR__) . '/15-radar-symptom-and-remedy-analysis-foundation/includes';
require_once $root . '/class-rsr-provider-registry.php';
require_once $root . '/providers/class-rsr-manual-provider.php';
require_once $root . '/class-rsr-trend-service.php';

$failures = [];
$checks = 0;
$assert = static function (bool $ok, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$ok) { $failures[] = $message; }
};

$service = new RSR_Trend_Service();
$validate = new ReflectionMethod($service, 'validate_source');
$validate->setAccessible(true);
$base = [
    'provider_key' => 'manual',
    'name' => 'Reviewed source',
    'dataset' => 'Synthetic education signals',
    'license_code' => 'internal-reviewed-aggregate',
    'review_date' => gmdate('Y-m-d'),
    'status' => 'configured',
    'config' => [],
];
foreach (RSR_Domain::source_signal_types() as $type) {
    $result = $validate->invoke($service, ['signal_type' => $type] + $base);
    $assert(is_array($result) && ($result['signal_type'] ?? null) === $type,
        'Each approved activity category must preserve its exact reviewed type: ' . $type);
}
$legacy = $validate->invoke($service, $base);
$assert(is_array($legacy) && ($legacy['signal_type'] ?? null) === 'source_activity',
    'Legacy source input must have a neutral activity-only classification.');
foreach (['epidemiology', 'surveillance', 'prevalence', ''] as $unapproved) {
    $result = $validate->invoke($service, ['signal_type' => $unapproved] + $base);
    $assert(is_wp_error($result) && $result->get_error_code() === 'rsr_signal_type_unapproved',
        'Clinical or empty types must fail closed: ' . $unapproved);
}
$result = $validate->invoke($service, ['signal_type' => ['nested']] + $base);
$assert(is_wp_error($result) && $result->get_error_code() === 'rsr_signal_type_unapproved',
    'Nested non-scalar type must fail without PHP coercion.');

$sanitize = new ReflectionMethod($service, 'sanitize_report_results');
$sanitize->setAccessible(true);
$topic = [
    'topic_key' => 'topic-1',
    'topic_label' => 'Topic 1',
    'score' => 70,
    'confidence' => 0.8,
    'volume' => 20,
    'baseline' => 10,
    'source_refs' => ['search-source', 'news-source'],
];
$corrected = $sanitize->invoke($service, [
    'topics' => [
        ['signal_type' => 'search_interest'] + $topic,
        ['signal_type' => 'news_interest', 'topic_key' => 'topic-2', 'source_refs' => ['search-source']] + $topic,
        ['signal_type' => 'epidemiology', 'topic_key' => 'topic-3'] + $topic,
    ],
    'limitations' => ['Not disease prevalence'],
], ['search-source', 'news-source'], ['search-source' => 'search_interest', 'news-source' => 'news_interest']);
$assert(count($corrected['topics'] ?? []) === 1,
    'Rejected and mismatched clinical categories must not be public report corrections.');
$assert(($corrected['signal_taxonomy_version'] ?? '') === 'rsr-source-signals-v1',
    'Editorial corrections must retain the source-signal taxonomy version.');
$first = $corrected['topics'][0] ?? [];
$assert(($first['signal_type'] ?? null) === 'search_interest' &&
    ($first['source_refs'] ?? null) === ['search-source'] && ($first['source_count'] ?? null) === 1,
    'Correction must retain only canonical same-category source references.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL {$checks} source-signal regression checks:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS {$checks} source-signal governance checks\n";
