<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$plugin = $root . '/15-radar-symptom-and-remedy-analysis-foundation';

$checks = [
    'main version 1.1.0' => [$plugin . '/radar-symptom-remedy-analysis.php', "Version: 1.1.0"],
    'compliance class loaded' => [$plugin . '/radar-symptom-remedy-analysis.php', 'class-rsr-four-plan-compliance.php'],
    'transport no-store' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', 'private, no-store, max-age=0, must-revalidate'],
    'query no-referrer' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', "Referrer-Policy: no-referrer"],
    'REST sensitive route prefix' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', 'strpos($route, \'/rsr/v1/studies\') === 0'],
    'rate limit does not short circuit allowed REST requests' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', 'return is_wp_error($limited) ? $limited : $result;'],
    'File 26 wrapper query unwrapped' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', 'isset($query[\'radar_query\'])'],
    'search explanation' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', 'why_this_result'],
    'zero result recovery' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', 'zero_result_recovery'],
    'no donor bias declaration' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', "'paid_or_donor_bias' => false"],
    'red flag escalation' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', 'safety_escalation'],
    'robust entity version parser' => [$plugin . '/includes/class-rsr-four-plan-compliance.php', 'parse_entity_version'],
    'study File 06 validation' => [$plugin . '/includes/class-rsr-study-service.php', 'rsr_validate_study_remedy_refs'],
    'study fail closed validation' => [$plugin . '/includes/class-rsr-study-service.php', 'rsr_study_remedy_validation_unavailable'],
    'encryption fail closed' => [$plugin . '/includes/class-rsr-study-service.php', 'rsr_study_encryption_unavailable'],
    'internal owner id omitted from export' => [$plugin . '/includes/class-rsr-study-service.php', "'owner_scope' => 'current_authenticated_account'"],
    'suspension recheck' => [$plugin . '/includes/class-rsr-capabilities.php', 'doctor.verification_revoked'],
    'security hold recheck' => [$plugin . '/includes/class-rsr-capabilities.php', 'account.security_hold'],
    'File 20 context owner contract' => [$plugin . '/includes/class-rsr-routes.php', 'sabri_file20_context_controls_markup_v1'],
    'canonical schema option value' => [$plugin . '/templates/radar.php', 'value="<?php echo esc_attr($option_key); ?>"'],
    'evidence matches not remedy probability' => [$plugin . '/templates/radar.php', 'evidence matches'],
    'study keyword clear fix' => [$plugin . '/assets/js/rsr.js', 'query.keyword = String'],
    'client no-store' => [$plugin . '/assets/js/rsr.js', "cache: 'no-store'"],
    'If-Match client support' => [$plugin . '/assets/js/rsr.js', "'If-Match'"],
];

$failed = [];
foreach ($checks as $name => [$file, $needle]) {
    $content = is_file($file) ? file_get_contents($file) : false;
    if (!is_string($content) || strpos($content, $needle) === false) {
        $failed[] = $name;
    }
}

$radar_template = file_get_contents($plugin . '/templates/radar.php');
if (is_string($radar_template) && strpos($radar_template, "educational_match_score") !== false) {
    $failed[] = 'misleading percentage removed from public Radar template';
}

$study_service = file_get_contents($plugin . '/includes/class-rsr-study-service.php');
if (is_string($study_service) && strpos($study_service, "'owner_user_id' => \$user_id,\n            'items'") !== false) {
    $failed[] = 'raw internal owner ID removed from portable export';
}

if ($failed !== []) {
    fwrite(STDERR, "FAIL four-plan compliance:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

fwrite(STDOUT, 'PASS four-plan compliance: ' . count($checks) . " positive controls and 2 negative regressions\n");
