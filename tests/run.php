<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

$tests = 0;
$failures = [];

$assert = static function (bool $condition, string $message) use (&$tests, &$failures): void {
    $tests++;
    if (!$condition) {
        $failures[] = $message;
    }
};

$assert(count(RSR_Domain::dimensions()) === 16, 'Radar must expose exactly sixteen governed dimensions.');
$assert(in_array('cc-by-4.0', RSR_Domain::allowed_source_licenses(), true), 'Versioned Creative Commons source licenses must be accepted.');
$assert(in_array('internal-reviewed-aggregate', RSR_Domain::allowed_source_licenses(), true), 'Reviewed internal aggregate sources must be supported.');
$assert(isset(RSR_Domain::dimensions()['location'], RSR_Domain::dimensions()['miasm']), 'Location and miasm dimensions must exist.');

$valid = RSR_Domain::validate_radar_query([
    'mode' => 'AND',
    'keyword' => 'burning',
    'filters' => [
        'location' => ['throat'],
        'aggravation' => ['cold air'],
    ],
]);
$assert($valid['valid'] === true, 'A normal structured Radar query must validate.');
$assert(($valid['query']['mode'] ?? '') === 'AND', 'Query mode must remain AND.');
$assert(strlen((string)($valid['query']['hash'] ?? '')) === 64, 'Validated query must have a canonical SHA-256 hash.');

$empty = RSR_Domain::validate_radar_query(['filters' => []]);
$assert($empty['valid'] === false && in_array('empty_query', $empty['errors'], true), 'Empty Radar query must fail.');

$unknown = RSR_Domain::validate_radar_query(['filters' => ['unknown' => ['x']]]);
$assert($unknown['valid'] === false, 'Unknown dimensions must fail.');

$three = RSR_Domain::validate_comparison_ids(['a', 'b', 'c']);
$assert($three['valid'] === true && count($three['ids']) === 3, 'Three remedies must be accepted.');
$four = RSR_Domain::validate_comparison_ids(['a', 'b', 'c', 'd']);
$assert($four['valid'] === false && in_array('maximum_three_remedies', $four['errors'], true), 'A fourth remedy must be rejected server-side.');

$scoreA = RSR_Domain::score_trend([
    'volume' => 120,
    'baseline' => 60,
    'source_quality' => 0.9,
    'coverage' => 0.85,
    'freshness' => 1,
    'minimum_volume' => 5,
]);
$scoreB = RSR_Domain::score_trend([
    'volume' => 120,
    'baseline' => 60,
    'source_quality' => 0.9,
    'coverage' => 0.85,
    'freshness' => 1,
    'minimum_volume' => 5,
]);
$assert($scoreA === $scoreB, 'Trend scoring must be deterministic.');
$assert($scoreA['score'] >= 0 && $scoreA['score'] <= 100, 'Trend score must be bounded to 0–100.');
$assert($scoreA['confidence'] >= 0 && $scoreA['confidence'] <= 1, 'Confidence must be bounded to 0–1.');
$assert($scoreA['qualified'] === true, 'Representative high-quality volume must meet threshold.');
$low = RSR_Domain::score_trend(['volume' => 1, 'baseline' => 1, 'source_quality' => 0.2, 'coverage' => 0.1, 'freshness' => 0.1, 'minimum_volume' => 5]);
$assert($low['qualified'] === false, 'Low-volume/low-confidence source activity must not qualify.');

$assert(RSR_Domain::can_transition_report('draft', 'analyst_review'), 'Draft must transition to analyst review.');
$assert(!RSR_Domain::can_transition_report('draft', 'published'), 'Draft must never transition directly to published.');
$assert(RSR_Domain::can_transition_report('approved', 'published'), 'Approved report must be publishable by the publisher capability.');
$assert(RSR_Domain::can_transition_report('published', 'retracted'), 'Published report must support retraction.');

$reference = new DateTimeImmutable('2026-08-06 18:00:00', new DateTimeZone('Asia/Karachi'));
$daily = RSR_Domain::trend_window('daily', 'Asia/Karachi', $reference);
$assert($daily['start_utc'] === '2026-08-04 19:00:00' && $daily['end_utc'] === '2026-08-05 19:00:00', 'Daily window must represent the previous local day in UTC.');
$weekly = RSR_Domain::trend_window('weekly', 'Asia/Karachi', $reference);
$assert($weekly['start_utc'] === '2026-07-29 19:00:00' && $weekly['end_utc'] === '2026-08-05 19:00:00', 'Weekly window must be the previous seven local days.');
$monthly = RSR_Domain::trend_window('monthly', 'Asia/Karachi', $reference);
$assert($monthly['start_utc'] === '2026-06-30 19:00:00' && $monthly['end_utc'] === '2026-07-31 19:00:00', 'Monthly window must be the previous local calendar month.');
$yearly = RSR_Domain::trend_window('yearly', 'Asia/Karachi', $reference);
$assert($yearly['start_utc'] === '2024-12-31 19:00:00' && $yearly['end_utc'] === '2025-12-31 19:00:00', 'Yearly window must be the previous local calendar year.');

$canonicalA = RSR_Domain::canonical_json(['b' => 2, 'a' => ['d' => 4, 'c' => 3]]);
$canonicalB = RSR_Domain::canonical_json(['a' => ['c' => 3, 'd' => 4], 'b' => 2]);
$assert($canonicalA === $canonicalB, 'Canonical JSON must be order-independent for object keys.');

$safe = RSR_PII_Scanner::scan(['title' => 'Morning cough comparison', 'notes' => 'Worse in cold air.']);
$assert($safe['safe'] === true, 'Non-patient research text must pass PII scan.');
$phone = RSR_PII_Scanner::scan('Patient phone +92 300 1234567');
$assert($phone['safe'] === false && in_array('phone', $phone['categories'], true), 'Phone number must be blocked.');
$cnic = RSR_PII_Scanner::scan('35202-1234567-1');
$assert($cnic['safe'] === false && in_array('pakistan_cnic', $cnic['categories'], true), 'Pakistan CNIC must be blocked.');
$nameLabel = RSR_PII_Scanner::scan('مریض کا نام: احمد');
$assert($nameLabel['safe'] === false && in_array('patient_name_label', $nameLabel['categories'], true), 'Patient-name labels must be blocked.');

$plain = '<p>Private non-patient research note.</p>';
$cipher = RSR_Crypto::encrypt($plain);
$assert($cipher !== $plain && strlen($cipher) > strlen($plain), 'Encrypted study note must not remain plaintext.');
$assert(RSR_Crypto::decrypt($cipher) === $plain, 'Encrypted study note must round-trip.');
$assert(strlen(RSR_Crypto::key_fingerprint()) === 16, 'Key fingerprint must be a short non-secret identifier.');
$tampered = substr($cipher, 0, -2) . 'AA';
$tamperRejected = false;
try {
    RSR_Crypto::decrypt($tampered);
} catch (Throwable $error) {
    $tamperRejected = true;
}
$assert($tamperRejected, 'Tampered authenticated ciphertext must be rejected.');

if ($failures !== []) {
    fwrite(STDERR, "FAILED {$tests} assertions\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS {$tests} domain/security assertions\n";
