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

$assert(count(RSR_Domain::dimensions()) === 16, 'Exactly sixteen governed Radar dimensions must exist.');
$assert(in_array('cc-by-4.0', RSR_Domain::writable_source_licenses(), true), 'Current versioned CC licence must be writable.');
$assert(!in_array('cc-by', RSR_Domain::writable_source_licenses(), true), 'Legacy unversioned licence must not be writable.');
$assert(in_array('cc-by', RSR_Domain::allowed_source_licenses(), true), 'Legacy licence must remain readable for controlled migration.');

$valid = RSR_Domain::validate_radar_query([
    'mode' => 'AND',
    'keyword' => 'burning',
    'filters' => ['location' => ['throat'], 'aggravation' => ['cold air']],
]);
$assert($valid['valid'] === true, 'Normal structured query must validate.');
$assert(strlen((string)($valid['query']['hash'] ?? '')) === 64, 'Validated query must carry canonical SHA-256 hash.');

$collision = RSR_Domain::validate_radar_query([
    'keyword' => 'test',
    'filters' => [
        'body region' => ['head'],
        'body_region' => ['chest'],
    ],
]);
$assert($collision['valid'] === true, 'Normalized dimension collision must remain valid.');
$assert(($collision['query']['filters']['body_region'] ?? []) === ['head', 'chest'], 'Normalized dimension values must merge instead of overwrite.');

$empty = RSR_Domain::validate_radar_query(['filters' => []]);
$assert($empty['valid'] === false && in_array('empty_query', $empty['errors'], true), 'Empty query must fail.');
$unknown = RSR_Domain::validate_radar_query(['filters' => ['unknown' => ['x']]]);
$assert($unknown['valid'] === false, 'Unknown dimension must fail.');

$three = RSR_Domain::validate_comparison_ids(['a', 'b', 'c']);
$assert($three['valid'] === true && count($three['ids']) === 3, 'Three remedy IDs must validate.');
$four = RSR_Domain::validate_comparison_ids(['a', 'b', 'c', 'd']);
$assert($four['valid'] === false && in_array('maximum_three_remedies', $four['errors'], true), 'Fourth remedy must fail.');
$bad = RSR_Domain::validate_comparison_ids(['valid', '<script>']);
$assert($bad['valid'] === false && in_array('invalid_remedy_id', $bad['errors'], true), 'Malformed non-empty remedy ID must fail rather than disappear.');

$score = RSR_Domain::score_trend([
    'volume' => INF,
    'baseline' => NAN,
    'source_quality' => INF,
    'coverage' => -INF,
    'freshness' => 0.8,
]);
$assert(is_finite($score['score']) && $score['score'] >= 0.0 && $score['score'] <= 100.0, 'Non-finite metrics must yield finite bounded score.');
$assert(is_finite($score['confidence']) && $score['confidence'] >= 0.0 && $score['confidence'] <= 1.0, 'Non-finite metrics must yield finite bounded confidence.');

$assert(RSR_Hardening::parse_entity_version('W/"42"') === 42, 'Weak quoted ETag must parse exactly.');
$assert(RSR_Hardening::parse_entity_version('"7"') === 7, 'Quoted version must parse exactly.');
$assert(RSR_Hardening::parse_entity_version('7junk') === 0, 'Garbage-suffixed version must be rejected.');
$assert(RSR_Hardening::parse_entity_version('-1') === 0, 'Negative version must be rejected.');
$assert(RSR_Hardening::finite_float('12.5', 0, 20, null) === 12.5, 'Finite numeric value must be accepted.');
$assert(RSR_Hardening::finite_float(INF, 0, 20, 3.0) === 3.0, 'Infinite numeric value must use safe default.');
$assert(RSR_Hardening::contains_secret_material(['api_key' => 'abc']) === true, 'Secret-bearing configuration key must be rejected.');
$assert(RSR_Hardening::contains_secret_material(['header' => 'Bearer abcdefghijklmnopqrstuvwxyz123456']) === true, 'Bearer material must be rejected.');
$assert(RSR_Hardening::contains_secret_material(['endpoint' => 'aggregate-v1', 'timeout' => 10]) === false, 'Benign provider configuration must pass secret scan.');
$assert(strlen(RSR_Hardening::normalize_geography(str_repeat('a', 200))) === 100, 'Geography key must be bounded.');

$safe = RSR_PII_Scanner::scan(['title' => 'Morning cough comparison', 'notes' => 'Worse in cold air.']);
$assert($safe['safe'] === true, 'Non-patient research text must pass PII scan.');
$phone = RSR_PII_Scanner::scan('Patient phone +92 300 1234567');
$assert($phone['safe'] === false && in_array('phone', $phone['categories'], true), 'Phone number must be blocked.');
$cnic = RSR_PII_Scanner::scan('35202-1234567-1');
$assert($cnic['safe'] === false && in_array('pakistan_cnic', $cnic['categories'], true), 'CNIC must be blocked.');
$deep = ['x'];
for ($i = 0; $i < 12; $i++) {
    $deep = ['nested' => $deep];
}
$deepScan = RSR_PII_Scanner::scan($deep);
$assert($deepScan['safe'] === false && in_array('input_too_complex', $deepScan['categories'], true), 'Excessively deep structured input must fail closed.');
$largeScan = RSR_PII_Scanner::scan(str_repeat('a', 70000));
$assert($largeScan['safe'] === false && in_array('input_too_large', $largeScan['categories'], true), 'Oversized structured input must fail closed.');

$plain = '<p>Private non-patient research note.</p>';
$cipher = RSR_Crypto::encrypt($plain);
$assert(str_starts_with($cipher, 's2:') || str_starts_with($cipher, 'o2:'), 'New ciphertext must use v2 key-identified format.');
$assert(RSR_Crypto::decrypt($cipher) === $plain, 'Ciphertext must round-trip.');
$assert(strlen(RSR_Crypto::key_fingerprint()) === 16, 'Key fingerprint must be 16 hexadecimal characters.');
$tampered = substr($cipher, 0, -2) . 'AA';
$tamperRejected = false;
try {
    RSR_Crypto::decrypt($tampered);
} catch (Throwable $error) {
    $tamperRejected = true;
}
$assert($tamperRejected, 'Tampered authenticated ciphertext must fail.');

$reference = new DateTimeImmutable('2026-08-06 18:00:00', new DateTimeZone('Asia/Karachi'));
$daily = RSR_Domain::trend_window('daily', 'Asia/Karachi', $reference);
$assert($daily['start_utc'] === '2026-08-04 19:00:00' && $daily['end_utc'] === '2026-08-05 19:00:00', 'Daily window must represent prior local day in UTC.');
$monthly = RSR_Domain::trend_window('monthly', 'Asia/Karachi', $reference);
$assert($monthly['start_utc'] === '2026-06-30 19:00:00' && $monthly['end_utc'] === '2026-07-31 19:00:00', 'Monthly window must represent prior local month.');

$canonicalA = RSR_Domain::canonical_json(['b' => 2, 'a' => ['d' => 4, 'c' => 3]]);
$canonicalB = RSR_Domain::canonical_json(['a' => ['c' => 3, 'd' => 4], 'b' => 2]);
$assert($canonicalA === $canonicalB, 'Canonical JSON must be object-key order independent.');
$assert(RSR_Domain::can_transition_report('draft', 'analyst_review'), 'Draft must transition to analyst review.');
$assert(!RSR_Domain::can_transition_report('draft', 'published'), 'Draft must never publish directly.');

if ($failures !== []) {
    fwrite(STDERR, "FAILED {$tests} domain/security assertions\n- " . implode("\n- ", $failures) . "\n");
    exit(1);
}

echo "PASS {$tests} domain/security assertions\n";
