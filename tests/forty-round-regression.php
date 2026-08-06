<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$auditPath = $root . '/docs/FORTY-ROUND-AUDIT-2026-08-06.md';
$audit = (string)file_get_contents($auditPath);
$plugin = $root . '/15-radar-symptom-and-remedy-analysis-foundation';
$failures = [];
$checks = 0;
$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

preg_match_all('/^\|\s*(\d{2})\s*\|/m', $audit, $roundMatches);
$rounds = $roundMatches[1] ?? [];
$assert(count($rounds) === 40, 'Audit register must contain exactly forty numbered rounds.');
$assert($rounds === array_map(static fn(int $n): string => sprintf('%02d', $n), range(1, 40)), 'Audit rounds must be sequential 01–40.');

preg_match_all('/\*\*نقص ملا:\*\*/u', $audit, $defectMatches);
preg_match_all('/\*\*کوئی نیا نقص نہیں ملا:\*\*/u', $audit, $cleanMatches);
$assert(count($defectMatches[0]) === 32, 'Exactly thirty-two rounds must record defects.');
$assert(count($cleanMatches[0]) === 8, 'Exactly eight rounds must record no new defect.');
$assert(str_contains($audit, 'جن ادوار میں نقائص ملے:** 32'), 'Audit summary must state 32 defect rounds.');
$assert(str_contains($audit, 'جن ادوار میں کوئی نیا نقص نہیں ملا:** 8'), 'Audit summary must state 8 clean rounds.');
$assert(substr_count($audit, 'دوبارہ جانچ کامیاب') >= 32, 'Each defect round must record correction and successful retest.');

$requiredTokens = [
    "define('RSR_VERSION', '1.2.0')",
    "define('RSR_SCHEMA_VERSION', '1.2.0')",
    "'inbox' =>",
    "'rate_limits' =>",
    'RSR_PRIVATE_STUDY_PREVIOUS_KEYS',
    'input_too_complex',
    'writable_source_licenses',
    'invalid_remedy_id',
    'RadarTrendReportRetracted.v1',
    'APPROVE_REPORTS',
    'public_reports_page',
    'notes_unavailable',
];
$source = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if ($file->isFile() && in_array(strtolower($file->getExtension()), ['php','js','css','txt'], true)) {
        $source .= "\n" . (string)file_get_contents($file->getPathname());
    }
}
foreach ($requiredTokens as $token) {
    $assert(str_contains($source, $token), "Forty-round corrective token is missing: {$token}");
}

$negativePatterns = [
    'rsr-test-only-key-material',
    'rsr-ip-hash',
    "return true; // allowed rest_pre_dispatch",
];
foreach ($negativePatterns as $token) {
    $assert(!str_contains($source, $token), "Negative regression returned: {$token}");
}

$assert(str_contains((string)file_get_contents($plugin . '/templates/radar.php'), 'method="post"'), 'Radar health query must use POST.');
$assert(str_contains((string)file_get_contents($plugin . '/templates/compare.php'), 'method="post"'), 'Compare health query must use POST.');
$assert(str_contains((string)file_get_contents($plugin . '/includes/class-rsr-events.php'), "SET status='processing'"), 'Outbox/inbox atomic claim must remain present.');
$trendSource = '';
foreach (glob($plugin . '/includes/{class-rsr-trend-service,trait-rsr-trend-*}.php', GLOB_BRACE) ?: [] as $trendFile) {
    $trendSource .= (string)file_get_contents($trendFile);
}
$assert(str_contains($trendSource, 'RSR_Hardening::transaction'), 'Transactional report mutation must remain present.');

if ($failures !== []) {
    fwrite(STDERR, "FAILED {$checks} forty-round checks\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS {$checks} forty-round controls (32 defect rounds, 8 clean rounds)\n";
