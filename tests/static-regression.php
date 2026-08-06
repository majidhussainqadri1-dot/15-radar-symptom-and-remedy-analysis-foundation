<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$plugin = $root . '/15-radar-symptom-and-remedy-analysis-foundation';
$failures = [];
$checks = 0;

$assert = static function (bool $condition, string $message) use (&$failures, &$checks): void {
    $checks++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$read = static fn(string $path): string => (string)file_get_contents($path);
$all = '';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS));
$phpFiles = [];
$jsFiles = [];
foreach ($iterator as $file) {
    if (!$file->isFile()) continue;
    $path = $file->getPathname();
    $relative = substr($path, strlen($plugin) + 1);
    if ($file->getExtension() === 'php') $phpFiles[$relative] = $path;
    if ($file->getExtension() === 'js') $jsFiles[$relative] = $path;
    if (in_array($file->getExtension(), ['php', 'js', 'css', 'txt'], true)) $all .= "\n" . $read($path);
}

$main = $read($plugin . '/radar-symptom-remedy-analysis.php');
$db = $read($plugin . '/includes/class-rsr-db.php');
$domain = $read($plugin . '/includes/class-rsr-domain.php');
$trends = $read($plugin . '/includes/class-rsr-trend-service.php');
$studies = $read($plugin . '/includes/class-rsr-study-service.php');
$routes = $read($plugin . '/includes/class-rsr-routes.php');
$api = $read($plugin . '/includes/class-rsr-api.php');
$events = $read($plugin . '/includes/class-rsr-events.php');
$reportTemplate = $read($plugin . '/templates/trend-report.php');
$manageTemplate = $read($plugin . '/templates/manage.php');
$traceability = $read($root . '/docs/REQUIREMENTS-TRACEABILITY.md');

$assert(is_dir($plugin), 'Canonical plugin folder is missing.');
$assert(str_contains($main, 'Version: 1.1.0') && str_contains($main, "define('RSR_VERSION', '1.1.0')"), 'Plugin/header version must be 1.1.0.');
$assert(str_contains($main, 'Text Domain: radar-symptom-remedy-analysis'), 'Canonical text domain is missing.');
$assert(!str_contains($main, 'SRF_'), 'Historical SRF symbols must not remain in canonical main plugin file.');
$assert(count($phpFiles) >= 26, 'Canonical implementation unexpectedly contains too few PHP files.');
$assert(count($jsFiles) === 2, 'Expected public and operations JavaScript bundles.');

foreach (['schema_values','mappings','studies','sources','observations','reports','corrections','jobs','outbox','audit'] as $table) {
    $assert(str_contains($db, "'{$table}'"), "Canonical table {$table} is missing.");
}
foreach (['location','body_region','sensation','aggravation','amelioration','concomitants','causation','time','temperature','thirst','appetite','sleep','mind','constitution','miasm','discharges'] as $dimension) {
    $assert(str_contains($domain, "'{$dimension}'"), "Radar dimension {$dimension} is missing.");
}
foreach (['^radar/?$','^radar/compare/?$','^radar/studies/?$','^trends/?$','^trends/([A-Za-z0-9._:-]+)/?$','^radar/manage/?$'] as $route) {
    $assert(str_contains($routes, $route), "Canonical route {$route} is missing.");
}
foreach (['/schema','/radar/search','/radar/compare','/studies','/studies/export','/trends','/manage/sources','/manage/ingestion','/manage/reports','/manage/diagnostics'] as $endpoint) {
    $assert(str_contains($api, $endpoint), "REST endpoint {$endpoint} is missing.");
}
foreach (['RadarTrendReportPublished.v1','RadarTrendReportCorrected.v1','RadarSourceDegraded.v1'] as $event) {
    $assert(str_contains($trends . $events, $event), "Published event {$event} is missing.");
}
foreach (['EncyclopediaEntryPublished.v1','EncyclopediaEntryCorrected.v1','EncyclopediaEntryRetracted.v1','DoctorSuspended.v1','ProviderQuotaChanged.v1'] as $event) {
    $assert(str_contains($events, $event), "Consumed event {$event} is missing.");
}

$assert(str_contains($domain, 'MAX_COMPARE_REMEDIES = 3'), 'Server-side three-remedy maximum is missing.');
$assert(str_contains($domain, "'draft' => ['analyst_review']"), 'Draft must not auto-publish.');
$assert(str_contains($trends, "'status' => 'draft'"), 'Ingestion/report builder must create draft status.');
$assert(str_contains($studies, 'RSR_PII_Scanner::scan'), 'Study PII scanner is missing.');
$assert(str_contains($studies, 'RSR_Crypto::encrypt') && str_contains($studies, 'RSR_Crypto::decrypt'), 'Study encryption lifecycle is incomplete.');
$assert(str_contains($routes, 'noindex, noarchive, nofollow') && str_contains($routes, 'nocache_headers'), 'Private route noindex/no-cache controls are missing.');
$assert(str_contains($trends, 'idempotency_key') && str_contains($trends, 'rsr_ingest_'), 'Ingestion idempotency/lock controls are missing.');
$assert(str_contains($trends, '$canonical_command') && str_contains($trends, "'retry'"), 'Canonical command idempotency and controlled retry are missing.');
$assert(str_contains($trends, "['published', 'corrected', 'retracted']"), 'Retracted report permanent URLs must remain publicly resolvable.');
$assert(str_contains($reportTemplate, 'This report has been withdrawn') && str_contains($reportTemplate, '$is_retracted'), 'Public retraction notice and suppression view are missing.');
$assert(str_contains($events, 'was_consumed') && str_contains($events, 'rsr_consume_lock_'), 'Durable event deduplication and concurrency lock are missing.');
$assert(str_contains($api, 'any_capability_permission') && str_contains($api, 'PUBLISH_REPORTS'), 'Publisher-aware report transition permission is missing.');
$assert(str_contains($routes, "is_rtl() ? '→' : '←'") && str_contains($routes, 'status_header($status'), 'RTL context navigation or missing-report status handling is incomplete.');
$assert(str_contains($manageTemplate, 'review_date') && str_contains($manageTemplate, 'rate_limit_per_hour') && str_contains($manageTemplate, 'cost_model'), 'Source governance fields are missing from the operations UI.');
$assert(str_contains($trends, 'spam_probability') && str_contains($trends, 'bot_probability') && str_contains($trends, 'repost_factor'), 'Normalization controls are missing.');
$assert(str_contains($db, 'review_date date NOT NULL') && str_contains($db, 'rate_limit_per_hour') && str_contains($db, 'cost_model'), 'Source governance fields are incomplete.');
$assert(str_contains($trends, 'rsr_raw_credentials_prohibited'), 'Raw provider credential rejection is missing.');
$assert(str_contains($events, "'dead'") && str_contains($events, "'retry'"), 'Outbox retry/dead-letter handling is missing.');
$assert(str_contains($all, 'educational research tool') || str_contains(strtolower($all), 'educational research'), 'Educational safety disclosure is missing.');
$assert(str_contains($all, 'does not diagnose') && str_contains($all, 'potency') && str_contains($all, 'dosage'), 'Clinical-safety boundary language is incomplete.');
$assert(str_contains($main, 'rsr_accept_platform_event'), 'Versioned public event entry point is missing.');
$assert(str_contains($all, 'sabri_file15_public_search_v1') && str_contains($all, 'sabri_file15_public_reports_v1'), 'Public Search/AI/feed contracts are missing.');
$assert(!preg_match('/(?:ghp_[A-Za-z0-9]{30,}|AKIA[0-9A-Z]{16}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----)/', $all), 'A secret-like credential pattern was found.');

for ($i = 1; $i <= 18; $i++) {
    $id = sprintf('F15-FR-%03d', $i);
    $assert(str_contains($traceability, $id), "Traceability is missing {$id}.");
}
for ($i = 1; $i <= 10; $i++) {
    $id = sprintf('F15-NFR-%03d', $i);
    $assert(str_contains($traceability, $id), "Traceability is missing {$id}.");
}

foreach ($phpFiles as $relative => $path) {
    $content = $read($path);
    $allowsTemplateMarkup = str_starts_with($relative, 'templates/') || $relative === 'includes/class-rsr-admin.php';
    if (!$allowsTemplateMarkup) {
        $assert(!str_contains($content, "?>\n"), "PHP closing tag is prohibited in pure PHP file {$relative}.");
    }
    $assert(!preg_match('/\b(?:eval|exec|shell_exec|system|passthru)\s*\(/', $content), "Dangerous execution primitive found in {$relative}.");
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED {$checks} static checks\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS {$checks} static requirement/regression checks\n";
