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

$files = [];
$all = '';
$phpFiles = [];
$jsFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($plugin, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $relative = substr($file->getPathname(), strlen($plugin) + 1);
    $content = $read($file->getPathname());
    $files[$relative] = $content;
    $extension = strtolower($file->getExtension());
    if ($extension === 'php') {
        $phpFiles[$relative] = $content;
    }
    if ($extension === 'js') {
        $jsFiles[$relative] = $content;
    }
    if (in_array($extension, ['php', 'js', 'css', 'txt'], true)) {
        $all .= "\n" . $content;
    }
}

$main = $files['radar-symptom-remedy-analysis.php'] ?? '';
$db = $files['includes/class-rsr-db.php'] ?? '';
$hardening = $files['includes/class-rsr-hardening.php'] ?? '';
$events = $files['includes/class-rsr-events.php'] ?? '';
$crypto = $files['includes/class-rsr-crypto.php'] ?? '';
$pii = $files['includes/class-rsr-pii-scanner.php'] ?? '';
$domain = $files['includes/class-rsr-domain.php'] ?? '';
$radar = ($files['includes/class-rsr-radar-service.php'] ?? '')
    . ($files['includes/trait-rsr-radar-public.php'] ?? '')
    . ($files['includes/trait-rsr-radar-governance.php'] ?? '')
    . ($files['includes/trait-rsr-radar-retrieval.php'] ?? '');
$trends = ($files['includes/class-rsr-trend-service.php'] ?? '')
    . ($files['includes/trait-rsr-trend-source-ingestion.php'] ?? '')
    . ($files['includes/trait-rsr-trend-reports.php'] ?? '')
    . ($files['includes/trait-rsr-trend-processing.php'] ?? '')
    . ($files['includes/trait-rsr-trend-serialization.php'] ?? '');
$studies = $files['includes/class-rsr-study-service.php'] ?? '';
$routes = $files['includes/class-rsr-routes.php'] ?? '';
$api = $files['includes/class-rsr-api.php'] ?? '';
$capabilities = $files['includes/class-rsr-capabilities.php'] ?? '';
$provider = $files['includes/class-rsr-provider-registry.php'] ?? '';
$manual = $files['includes/providers/class-rsr-manual-provider.php'] ?? '';
$observability = $files['includes/class-rsr-observability.php'] ?? '';
$radarTemplate = $files['templates/radar.php'] ?? '';
$compareTemplate = $files['templates/compare.php'] ?? '';
$studiesTemplate = $files['templates/studies.php'] ?? '';
$trendTemplate = $files['templates/trend-report.php'] ?? '';
$manageTemplate = $files['templates/manage.php'] ?? '';
$js = $files['assets/js/rsr.js'] ?? '';
$adminJs = $files['assets/js/rsr-admin.js'] ?? '';
$readme = $files['readme.txt'] ?? '';

$assert(is_dir($plugin), 'Canonical plugin directory is missing.');
$assert(str_contains($main, 'Version: 1.2.0'), 'Plugin header version must be 1.2.0.');
$assert(str_contains($main, "define('RSR_VERSION', '1.2.0')"), 'Runtime version must be 1.2.0.');
$assert(str_contains($main, "define('RSR_SCHEMA_VERSION', '1.2.0')"), 'Schema version must be 1.2.0.');
$assert(str_contains($readme, 'Stable tag: 1.2.0'), 'Stable tag must be 1.2.0.');
$assert(str_contains($main, "class-rsr-hardening.php"), 'Hardening class must be loaded.');
$assert(count($phpFiles) >= 30, 'Expected at least thirty plugin PHP files.');
$assert(count($jsFiles) === 2, 'Expected exactly public and operations JavaScript bundles.');

foreach (['schema','mappings','studies','sources','observations','reports','corrections','jobs','outbox','inbox','rate_limits','audit'] as $table) {
    $assert(str_contains($db, "'{$table}'"), "Table registry is missing {$table}.");
}
foreach (['before_hash char(64)', 'after_hash char(64)', 'entry_hash char(64)'] as $column) {
    $assert(str_contains($db, $column), "Integrity column is missing: {$column}.");
}
$assert(str_contains($db, 'rsr_schema_upgrade_lock'), 'Atomic schema upgrade lock is missing.');
$assert(str_contains($db, 'missing_tables'), 'Complete-table verification helper is missing.');
$assert(str_contains($db, 'verify_audit_row'), 'Audit hash verifier is missing.');
$assert(str_contains($db, "hash_hmac('sha256'"), 'Tamper-evident audit hash is missing.');

$assert(str_contains($hardening, 'ON DUPLICATE KEY UPDATE'), 'Atomic rate limiter SQL is missing.');
$assert(str_contains($hardening, 'parse_entity_version'), 'Exact entity-version parser is missing.');
$assert(str_contains($hardening, 'contains_secret_material'), 'Secret material scanner is missing.');
$assert(str_contains($hardening, 'START TRANSACTION') && str_contains($hardening, 'ROLLBACK'), 'Transaction helper is incomplete.');
$assert(str_contains($hardening, 'is_nan') && str_contains($hardening, 'is_infinite'), 'Finite numeric validation is missing.');

$assert(str_contains($crypto, "'s2:'") && str_contains($crypto, "'o2:'"), 'Version-two ciphertext formats are missing.');
$assert(str_contains($crypto, 'RSR_PRIVATE_STUDY_PREVIOUS_KEYS'), 'Previous-key rotation ring is missing.');
$assert(str_contains($crypto, 'production-grade private study encryption key'), 'Missing-key fail-closed behavior is absent.');
$assert(!str_contains($crypto, 'rsr-test-only-key-material'), 'Predictable production fallback key must not exist.');
$assert(str_contains($pii, 'MAX_DEPTH') && str_contains($pii, 'MAX_NODES') && str_contains($pii, 'MAX_BYTES'), 'PII scanner bounds are missing.');
$assert(str_contains($pii, 'input_too_complex') && str_contains($pii, 'input_too_large'), 'PII fail-closed categories are missing.');

$assert(str_contains($observability, 'RSR_PII_Scanner::scan'), 'Observability must scan values for PII.');
$assert(!str_contains($observability, 'rsr-ip-hash'), 'Predictable IP salt fallback must not exist.');
$assert(str_contains($observability, "$state['nodes'] > 1000") && str_contains($observability, 'array_slice($value, 0, 200'), 'Redaction bounds are missing.');

$assert(str_contains($events, "RSR_DB::table('inbox')"), 'Durable inbox is missing.');
$assert(str_contains($events, 'payload_hash'), 'Event collision hash is missing.');
$assert(str_contains($events, 'lease_expires_at'), 'Inbox/outbox lease handling is missing.');
$assert(str_contains($events, "SET status='processing'") && str_contains($events, "status IN ('pending','retry','processing')"), 'Atomic processing claim is missing.');
$assert(str_contains($events, 'validate_incoming'), 'Incoming event schema validation is missing.');
$assert(str_contains($events, 'rsr_event_id_collision'), 'Event payload collision rejection is missing.');

$assert(str_contains($domain, 'writable_source_licenses'), 'Strict writable licence registry is missing.');
$assert(str_contains($domain, 'invalid_remedy_id'), 'Malformed remedy identifier error is missing.');
$assert(str_contains($domain, 'array_merge($existing, $normalized)'), 'Normalized filter collision merge is missing.');
$assert(str_contains($domain, 'RSR_Hardening::finite_float'), 'Finite trend scoring is missing.');

$assert(str_contains($provider, 'Invalid File 15 trend provider contract') && str_contains($provider, "$capabilities['privacy'] === 'aggregated_only'"), 'Provider capability validation is missing.');
$assert(str_contains($provider, 'destination_allowlist'), 'Network provider destination allowlist contract is missing.');
$assert(str_contains($manual, 'MAX_MANUAL_ROWS') && str_contains($manual, 'MAX_MANUAL_BODY_BYTES'), 'Manual provider payload bounds are missing.');
$assert(str_contains($manual, 'finite_float'), 'Manual provider finite number validation is missing.');

$assert(str_contains($studies, 'notes_unavailable'), 'Private note key-unavailable state is missing.');
$assert(str_contains($studies, 'rsr_study_key_unavailable') && str_contains($studies, 'previous encryption key is restored'), 'Overwrite protection on decrypt failure is missing.');
$assert(str_contains($studiesTemplate, 'Load older studies'), 'Private study cursor UI is missing.');
$assert(str_contains($studiesTemplate, 'notes_unavailable'), 'Private note warning UI is missing.');

$assert(str_contains($radar, "JOIN ' . RSR_DB::table('sources')") || str_contains($radar, 'INNER JOIN'), 'Radar source-governance join is missing.');
$assert(str_contains($radar, 'writable_source_licenses'), 'Mapping writes must use strict licences.');
$assert(str_contains($radar, 'normalize_external_search'), 'External adapter normalization is missing.');
$assert(str_contains($radar, 'revalidate_results'), 'Cached remedy eligibility revalidation is missing.');
$assert(str_contains($radar, 'next_cursor'), 'Stable search pagination output is missing.');
$assert(!str_contains($radar, 'educational_match_score'), 'Misleading remedy percentage must remain removed.');

$assert(str_contains($trends, 'public_reports_page'), 'Cursor-based public report page is missing.');
$assert(str_contains($api, "'/trends/page'"), 'Paginated trends REST endpoint is missing.');
$assert(str_contains($trends, 'RSR_Hardening::transaction'), 'Transactional report operations are missing.');
$assert(str_contains($trends, 'RadarTrendReportRetracted.v1'), 'Distinct retraction event is missing.');
$assert(str_contains($trends, 'before_hash') && str_contains($trends, 'after_hash'), 'Correction before/after hashes are missing.');
$assert(str_contains($trends, 'minimum_confidence'), 'Minimum confidence release gate is missing.');
$assert(str_contains($trends, 'review_date') && str_contains($trends, 'territory') && str_contains($trends, 'restrictions'), 'Source provenance snapshot is incomplete.');
$assert(str_contains($trendTemplate, 'review_date') && str_contains($trendTemplate, 'territory'), 'Public provenance UI is incomplete.');

$assert(str_contains($capabilities, 'APPROVE_REPORTS'), 'Separate approval capability is missing.');
$assert(str_contains($manageTemplate, 'can_approve_reports'), 'Separate Approve UI action is missing.');
$assert(str_contains($routes, "'can_approve_reports'"), 'Route view lacks approval capability.');
$assert(str_contains($routes, "RSR_Hardening::rate_limit('html_' . $route"), 'Page-level Radar/Compare rate limit is missing.');
$assert(str_contains($routes, "'schema' => ['dimensions' => RSR_Domain::dimensions(), 'values' => []]") && str_contains($routes, "if ($route === 'radar')") && str_contains($routes, "$base['schema'] = $this->radar->schema()"), 'Lazy schema loading is missing.');
$assert(str_contains($radarTemplate, 'method="post"') && str_contains($compareTemplate, 'method="post"'), 'Health research forms must use POST.');
$assert(str_contains($routes, 'nocache_headers') && str_contains($routes, 'Referrer-Policy'), 'Health-query page privacy headers are incomplete.');
$assert(str_contains($routes, 'sabri_file20_context_controls_markup_v1'), 'File 20 context-control contract is missing.');

foreach (['deleted','exportPrepared','invalidStructuredQuery'] as $token) {
    $assert(str_contains($routes, "'{$token}'"), "Localized public client string {$token} is missing.");
}
$assert(!str_contains($adminJs, "window.prompt('Reason") && !str_contains($adminJs, "setStatus('Saved."), 'Operations JavaScript still contains hard-coded workflow text.');
$assert(str_contains($js, "cache: 'no-store'") && str_contains($js, "referrerPolicy: 'no-referrer'"), 'Client transport privacy options are missing.');

foreach (['RadarTrendReportPublished.v1','RadarTrendReportCorrected.v1','RadarTrendReportRetracted.v1','RadarSourceDegraded.v1'] as $event) {
    $assert(str_contains($all, $event), "Published event {$event} is missing.");
}
foreach (['EncyclopediaEntryPublished.v1','EncyclopediaEntryCorrected.v1','EncyclopediaEntryRetracted.v1','DoctorSuspended.v1','ProviderQuotaChanged.v1'] as $event) {
    $assert(str_contains($events, $event), "Consumed event {$event} is missing.");
}

$lower = strtolower($all);
$assert(str_contains($lower, 'does not diagnose') || str_contains($lower, 'not diagnosis'), 'No-diagnosis boundary is missing.');
$assert(str_contains($lower, 'potency') && str_contains($lower, 'dosage'), 'Potency/dosage boundary is missing.');
$assert(str_contains($all, 'sabri_file15_public_search_v1') && str_contains($all, 'sabri_file15_public_reports_v1'), 'Public integration contracts are missing.');
$assert(!preg_match('/(?:ghp_[A-Za-z0-9]{30,}|github_pat_[A-Za-z0-9_]{40,}|AKIA[0-9A-Z]{16}|-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----)/', $all), 'A secret-like credential signature exists in plugin source.');

foreach ($phpFiles as $relative => $content) {
    $allowsMarkup = str_starts_with($relative, 'templates/') || $relative === 'includes/class-rsr-admin.php';
    if (!$allowsMarkup) {
        $assert(!str_contains($content, "?>\n"), "Pure PHP file has a closing tag: {$relative}.");
    }
    $assert(!preg_match('/\b(?:eval|exec|shell_exec|system|passthru)\s*\(/', $content), "Dangerous execution primitive in {$relative}.");
}

if ($failures !== []) {
    fwrite(STDERR, "FAILED {$checks} static checks\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS {$checks} static requirement/regression checks\n";
