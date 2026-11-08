<?php
/** File 15 canonical File 06 merge contract: isolated domain tests (not staging). */
declare(strict_types=1);
define('RSR_TESTING', true);
define('RSR_TEXT_DOMAIN', 'radar-symptom-remedy-analysis');
class WP_Error {
    private string $code;
    public function __construct(string $code, string $message = '', array $details = []) { $this->code = $code; }
    public function get_error_code(): string { return $this->code; }
}
class RSR_DB { public static function table(string $name): string { return 'wp_rsr_' . $name; } }
function is_wp_error($value): bool { return $value instanceof WP_Error; }
function sanitize_text_field($v): string { return trim((string)$v); }
function sanitize_key($v): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$v)); }
function __($text, $domain = ''): string { return $text; }
function current_time($format, $gmt = false): string { return '2026-11-08 12:00:00'; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value, $autoload = false): bool { $GLOBALS['options'][$name] = $value; return true; }
function do_action($name, ...$args): void { $GLOBALS['actions'][] = [$name, $args]; }
class BridgeDB {
    public array $sql = [];
    public bool $fail = false;
    public function prepare($sql, ...$args) { return ['sql' => $sql, 'args' => $args]; }
    public function query($prepared) {
        $this->sql[] = $prepared;
        return $this->fail ? false : 1;
    }
}
function assert_bridge($ok, string $label): void {
    if (!$ok) { fwrite(STDERR, "FAIL: {$label}\n"); exit(1); }
}
require dirname(__DIR__) . '/15-radar-symptom-and-remedy-analysis-foundation/includes/class-rsr-events.php';
$wpdb = new BridgeDB();
$GLOBALS['options'] = [];
$GLOBALS['actions'] = [];
$source = '11111111-1111-4111-8111-111111111111';
$target = '22222222-2222-4222-8222-222222222222';
$event = '33333333-3333-4333-8333-333333333333';
$reflection = new ReflectionClass('RSR_Events');
$validate = $reflection->getMethod('validate_incoming');
$validate->setAccessible(true);
$apply = $reflection->getMethod('apply_incoming');
$apply->setAccessible(true);
$payload = [
    'event_id' => $event, 'source_remedy_public_id' => $source,
    'target_remedy_public_id' => $target, 'contract_version' => 'v1',
];
$valid = $validate->invoke(null, 'KnowledgeConceptMerged.v1', $payload);
assert_bridge(is_array($valid), 'Canonical merge payload accepted');
assert_bridge($valid['event_id'] === $event && $valid['source_remedy_public_id'] === $source, 'Stable canonical identities');
foreach ([
    ['event_id' => $event, 'source_remedy_public_id' => '', 'target_remedy_public_id' => $target],
    ['event_id' => $event, 'source_remedy_public_id' => $source, 'target_remedy_public_id' => $source],
    ['event_id' => $event, 'source_remedy_public_id' => '12', 'target_remedy_public_id' => $target],
] as $invalid) {
    assert_bridge(is_wp_error($validate->invoke(null, 'KnowledgeConceptMerged.v1', $invalid)), 'Invalid merge identifiers rejected');
}
$apply->invoke(null, 'KnowledgeConceptMerged.v1', $valid);
assert_bridge(count($wpdb->sql) === 1, 'One targeted mapping mutation');
assert_bridge(strpos($wpdb->sql[0]['sql'], "status='review_required'") !== false, 'Map requires manual review');
assert_bridge(strpos($wpdb->sql[0]['sql'], 'SET remedy_public_id=') === false, 'No silent reassignment to merged target');
assert_bridge(in_array($source, $wpdb->sql[0]['args'], true), 'Only original remedy mappings targeted');
assert_bridge(get_option('rsr_comparison_cache_generation', 1) === 2, 'Comparison cache generation invalidated');
assert_bridge($GLOBALS['actions'][1][0] === 'rsr_remedy_merge_review_required_v1', 'Companion impact signal emitted');
assert_bridge($GLOBALS['actions'][1][1][1] === $target, 'Target lineage retained');
$wpdb->fail = true;
$failed = false;
try { $apply->invoke(null, 'KnowledgeConceptMerged.v1', $valid); } catch (RuntimeException $e) { $failed = true; }
assert_bridge($failed, 'Mapping database failure propagates for retry');
echo "File 15 canonical merge contract: 11 isolated assertions PASS.\n";
