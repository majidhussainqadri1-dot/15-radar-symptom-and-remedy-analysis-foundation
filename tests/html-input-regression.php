<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) { return $value; }
}
require_once dirname(__DIR__) . '/15-radar-symptom-and-remedy-analysis-foundation/includes/class-rsr-routes.php';

$routes = (new ReflectionClass('RSR_Routes'))->newInstanceWithoutConstructor();
$method = static function (string $name) use ($routes) {
    $reflection = new ReflectionMethod($routes, $name);
    $reflection->setAccessible(true);
    return static fn(...$arguments) => $reflection->invokeArgs($routes, $arguments);
};
$query = $method('request_query');
$remedies = $method('request_remedies');
$cursor = $method('request_scalar');
$fail = [];
$checks = 0;
$assert = static function ($ok, string $label) use (&$fail, &$checks): void {
    $checks++;
    if (!$ok) { $fail[] = $label; }
};

$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET = [];
$_POST = ['keyword' => ['nested' => 'chest pain']];
$q = $query();
$assert(!RSR_Domain::validate_radar_query($q)['valid'], 'Nested keyword should not launch search.');
$assert($q['keyword'] === '', 'Nested keyword should not become Array.');
$_POST = ['filter_' . array_key_first(RSR_Domain::dimensions()) => [['nested' => 'entry']]];
$q = $query();
$assert(!RSR_Domain::validate_radar_query($q)['valid'], 'Nested filter should fail closed.');
$_POST = ['keyword' => 'sneezing', 'mode' => ['OR']];
$q = $query();
$assert(!RSR_Domain::validate_radar_query($q)['valid'], 'Nested mode should fail closed.');
$_POST = ['remedies' => ['safe', ['injected' => 'other']]];
$assert($remedies() === [], 'Mixed nested remedy identifiers must reject full comparison.');
$_POST = ['cursor' => ['unexpected' => 'value']];
$assert($cursor('cursor') === null, 'Nested cursor must not be coerced.');
$_POST = ['keyword' => 'sneezing'];
$q = $query();
$assert(RSR_Domain::validate_radar_query($q)['valid'], 'Legitimate keyword must continue working.');
$_POST = ['remedies' => ['remedy:one','remedy:two']];
$assert($remedies() === ['remedy:one','remedy:two'], 'Legitimate remedy selection preserved.');
if ($fail !== []) {
    fwrite(STDERR, "FAIL {$checks} HTML-input guards: " . implode('; ', $fail) . "\n");
    exit(1);
}
echo "PASS {$checks} HTML-input guards\n";
