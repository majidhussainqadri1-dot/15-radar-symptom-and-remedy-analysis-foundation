<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('esc_url_raw')) {
    function esc_url_raw(string $url): string
    {
        return $url;
    }
}
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags(string $text): string
    {
        return strip_tags($text);
    }
}

$plugin = dirname(__DIR__) . '/15-radar-symptom-and-remedy-analysis-foundation/includes';
require_once $plugin . '/class-rsr-radar-service.php';
require_once $plugin . '/class-rsr-four-plan-compliance.php';

$failures = [];
$checks = 0;
$assert = static function (bool $valid, string $message) use (&$checks, &$failures): void {
    $checks++;
    if (!$valid) {
        $failures[] = $message;
    }
};

$radar = new RSR_Radar_Service();
$urgent = $radar->search(['keyword' => 'chest pain', 'filters' => []]);
$assert(is_array($urgent), 'Urgent research result must be an explicit response.');
$assert(($urgent['results'] ?? null) === [], 'No remedy suggestions may be returned for chest pain.');
$assert(($urgent['next_cursor'] ?? 'INVALID') === null, 'No emergency result pagination may leak.');
$assert(($urgent['emergency_diversion'] ?? false) === true, 'Emergency redirect must be explicit.');
$assert(($urgent['source'] ?? '') === 'file15_urgent_care_diversion', 'Emergency query must exit before a provider is queried.');

$adversarial = RSR_Four_Plan_Compliance::enhance_search_result([
    'results' => [
        ['remedy_public_id' => 'fake', 'match_count' => 999, 'mappings' => []],
    ],
    'next_cursor' => 'opaque-result-page',
    'zero_result_recovery' => ['alternate_query' => ['keyword' => 'test']],
], ['keyword' => 'shortness of breath', 'filters' => []]);
$assert(($adversarial['results'] ?? null) === [], 'Unsafe third-party adapter results must be cleared.');
$assert(($adversarial['next_cursor'] ?? 'INVALID') === null, 'Unsafe third-party pagination must be cleared.');
$assert(!isset($adversarial['zero_result_recovery']), 'Emergency results must not suggest alternative remedy search.');
$assert(($adversarial['emergency_diversion'] ?? false) === true, 'Emergency safety state must persist.');

$normal = RSR_Four_Plan_Compliance::enhance_search_result(['results' => []], [
    'keyword' => 'sneezing',
    'filters' => [],
]);
$assert(($normal['emergency_diversion'] ?? false) === false, 'Ordinary research must not be marked an emergency.');
$assert(isset($normal['zero_result_recovery']), 'Ordinary empty results retain governed recovery.');

$hardening = (string)file_get_contents($plugin . '/class-rsr-four-plan-compliance.php');
$retrieval = (string)file_get_contents($plugin . '/trait-rsr-radar-retrieval.php');
$public = (string)file_get_contents($plugin . '/trait-rsr-radar-public.php');
$assert(substr_count($hardening, "isset(\$_GET['keyword']) || isset(\$_POST['keyword'])") === 2, 'POST and GET single-keyword research must both be private/noindex.');
$assert(str_contains($retrieval, "'match_count' => count(\$mappings)"), 'External evidence count must be derived from reviewed mappings, not adapter claims.');
$assert(str_contains($retrieval, "if (\$mappings === [])"), 'External candidates without mapped sources must be excluded.');
$assert(str_contains($retrieval, "'rsr_file06_legacy_remedy_eligible'"), 'Legacy File 06 publication alone must not bypass eligibility.');
$assert(strpos($public, "safety_escalation(\$query)") < strpos($public, "apply_filters('rsr_file06_search'"), 'Urgent diversion must run before any File 06 search adapter.');

if ($failures !== []) {
    fwrite(STDERR, "FAIL {$checks} clinical-boundary checks:\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}
echo "PASS {$checks} clinical-boundary safety/privacy/provenance checks\n";
