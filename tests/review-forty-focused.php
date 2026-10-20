<?php

declare(strict_types=1);

/**
 * Forty DISTINCT source-contract lenses, executed independently per round.
 * Presence checks are not runtime, staging or deployment verification.
 */
$round = isset($argv[1]) ? (int)$argv[1] : 0;
$root = dirname(__DIR__);
$plugin = $root . '/15-radar-symptom-and-remedy-analysis-foundation';
$checks = json_decode(<<<'JSON'
[
  [
    "Canonical runtime identity",
    "radar-symptom-remedy-analysis.php",
    [
      "define('RSR_VERSION'",
      "define('RSR_SCHEMA_VERSION'"
    ]
  ],
  [
    "Domain dimensions",
    "includes/class-rsr-domain.php",
    [
      "function dimensions",
      "validate_radar_query"
    ]
  ],
  [
    "Schema completeness",
    "includes/class-rsr-db.php",
    [
      "function missing_tables",
      "rsr_schema_version"
    ]
  ],
  [
    "Migration lock",
    "includes/class-rsr-db.php",
    [
      "rsr_schema_upgrade_lock",
      "function maybe_upgrade"
    ]
  ],
  [
    "Atomic rate limits",
    "includes/class-rsr-hardening.php",
    [
      "ON DUPLICATE KEY UPDATE",
      "bucket_key"
    ]
  ],
  [
    "File 00 doctor assertion",
    "includes/class-rsr-capabilities.php",
    [
      "rsr_file00_claim",
      "doctor.verified"
    ]
  ],
  [
    "File 00 suspension/hold",
    "includes/class-rsr-capabilities.php",
    [
      "doctor.verification_revoked",
      "account.security_hold"
    ]
  ],
  [
    "Study role gate",
    "includes/class-rsr-study-service.php",
    [
      "require_verified_doctor",
      "find_owned"
    ]
  ],
  [
    "Current remedy DTO contract",
    "includes/trait-rsr-radar-retrieval.php",
    [
      "rsr_file06_get_remedies",
      "eligible"
    ]
  ],
  [
    "Legacy remedy bridge proof",
    "includes/trait-rsr-radar-retrieval.php",
    [
      "rsr_file06_legacy_remedy_eligible",
      "get_post_status"
    ]
  ],
  [
    "Encrypted studies",
    "includes/class-rsr-crypto.php",
    [
      "sodium_crypto_secretbox",
      "aes-256-gcm"
    ]
  ],
  [
    "Study rotation/recovery",
    "includes/class-rsr-crypto.php",
    [
      "PREVIOUS_KEYS",
      "decrypt_with_key"
    ]
  ],
  [
    "PII scanner",
    "includes/class-rsr-pii-scanner.php",
    [
      "input_too_complex",
      "phone"
    ]
  ],
  [
    "Study versioned updates",
    "includes/class-rsr-study-service.php",
    [
      "version = version + 1",
      "owner_user_id"
    ]
  ],
  [
    "Data portability",
    "includes/class-rsr-privacy.php",
    [
      "export_personal_data",
      "erase_personal_data"
    ]
  ],
  [
    "Radar AND/OR filters",
    "includes/class-rsr-domain.php",
    [
      "AND",
      "OR",
      "filters"
    ]
  ],
  [
    "Up-to-three comparison",
    "includes/class-rsr-domain.php",
    [
      "MAX_COMPARE_REMEDIES",
      "validate_comparison_ids"
    ]
  ],
  [
    "Symptom/referrer privacy",
    "includes/class-rsr-four-plan-compliance.php",
    [
      "protect_query_routes",
      "no-referrer"
    ]
  ],
  [
    "Emergency short circuit",
    "includes/trait-rsr-radar-public.php",
    [
      "file15_urgent_care_diversion",
      "safety_escalation"
    ]
  ],
  [
    "Emergency adapter scrub",
    "includes/class-rsr-four-plan-compliance.php",
    [
      "emergency_diversion",
      "next_cursor"
    ]
  ],
  [
    "Source-linked Radar evidence",
    "includes/trait-rsr-radar-retrieval.php",
    [
      "source_id",
      "mapping_id",
      "reference"
    ]
  ],
  [
    "External evidence deduplication",
    "includes/trait-rsr-radar-retrieval.php",
    [
      "seen_mapping_evidence",
      "match_count' => count($mappings)"
    ]
  ],
  [
    "Governed Radar mappings",
    "includes/trait-rsr-radar-governance.php",
    [
      "upsert_mapping",
      "review_date"
    ]
  ],
  [
    "Source licence governance",
    "includes/trait-rsr-trend-serialization.php",
    [
      "writable_source_licenses",
      "review_date"
    ]
  ],
  [
    "Aggregated provider registration",
    "includes/class-rsr-provider-registry.php",
    [
      "aggregated_only",
      "destination_allowlist"
    ]
  ],
  [
    "Manual provider safety limits",
    "includes/providers/class-rsr-manual-provider.php",
    [
      "MAX_MANUAL_ROWS",
      "MAX_MANUAL_BODY_BYTES"
    ]
  ],
  [
    "Daily/weekly/monthly/yearly",
    "includes/class-rsr-trend-service.php",
    [
      "daily",
      "weekly",
      "monthly",
      "yearly"
    ]
  ],
  [
    "Ingestion source idempotency",
    "includes/trait-rsr-trend-source-ingestion.php",
    [
      "idempotency_key",
      "run_ingestion"
    ]
  ],
  [
    "Spam/repost normalization",
    "includes/trait-rsr-trend-processing.php",
    [
      "spam_probability",
      "repost_factor"
    ]
  ],
  [
    "Geo/time normalization",
    "includes/trait-rsr-trend-processing.php",
    [
      "normalize_geography",
      "window_start_utc"
    ]
  ],
  [
    "Stable public report paging",
    "includes/trait-rsr-trend-reports.php",
    [
      "parse_report_cursor",
      "encode_report_cursor"
    ]
  ],
  [
    "Editorial separation",
    "includes/trait-rsr-trend-reports.php",
    [
      "rsr_independent_approval_required",
      "rsr_independent_publisher_required"
    ]
  ],
  [
    "Corrections and retractions",
    "includes/trait-rsr-trend-reports.php",
    [
      "correct_report",
      "retract"
    ]
  ],
  [
    "Durable inbound event dedupe",
    "includes/class-rsr-events.php",
    [
      "payload_hash",
      "rsr_event_id_collision"
    ]
  ],
  [
    "Reliable outbound delivery",
    "includes/class-rsr-events.php",
    [
      "lease_expires_at",
      "dead"
    ]
  ],
  [
    "File 20 shell delegation",
    "includes/class-rsr-routes.php",
    [
      "sabri_file20_context_controls_markup_v1",
      "route"
    ]
  ],
  [
    "File 26 public read boundary",
    "includes/class-rsr-plugin.php",
    [
      "sabri_file15_public_search_v1",
      "sabri_file15_public_reports_v1"
    ]
  ],
  [
    "File 16 approved retrieval boundary",
    "docs/ARCHITECTURE.md",
    [
      "File 16",
      "approved public"
    ]
  ],
  [
    "File 24 assurance boundary",
    "docs/ARCHITECTURE.md",
    [
      "File 24",
      "security/privacy"
    ]
  ],
  [
    "Release and CI evidence",
    "../scripts/build.sh",
    [
      "four-plan-compliance.php",
      "Deterministic"
    ]
  ]
]
JSON, true); 
if ($round < 1 || $round > count($checks)) {
    fwrite(STDERR, "Invalid review round.\n");
    exit(2);
}
[$name, $path, $needles] = $checks[$round - 1];
$file = str_starts_with($path, '../') ? $root . '/' . substr($path, 3) : $plugin . '/' . $path;
$text = is_file($file) ? (string)file_get_contents($file) : '';
$missing = [];
foreach ($needles as $needle) {
    if (!str_contains($text, $needle)) {
        $missing[] = $needle;
    }
}
if ($missing !== []) {
    fwrite(STDERR, sprintf("FAIL round %02d: %s missing %s\n", $round, $name, implode(', ', $missing)));
    exit(1);
}
printf("PASS round %02d: %s (%d source anchors)\n", $round, $name, count($needles));
