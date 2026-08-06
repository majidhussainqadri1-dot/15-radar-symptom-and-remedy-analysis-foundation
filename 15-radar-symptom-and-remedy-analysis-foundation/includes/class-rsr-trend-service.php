<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

require_once __DIR__ . '/trait-rsr-trend-source-ingestion.php';
require_once __DIR__ . '/trait-rsr-trend-reports.php';
require_once __DIR__ . '/trait-rsr-trend-processing.php';
require_once __DIR__ . '/trait-rsr-trend-serialization.php';

/**
 * Canonical trend-source, ingestion, scoring, report, and editorial workflow.
 *
 * The service accepts aggregated observations only. It never ingests private
 * Saved Studies, clinical records, messages, identity evidence, or payment data.
 */
final class RSR_Trend_Service
{
    /** @var array<int, string> */
    private const WINDOWS = ['daily', 'weekly', 'monthly', 'yearly'];

    /** @var array<int, string> */
    private const SOURCE_STATES = ['configured', 'healthy', 'degraded', 'quota_exhausted', 'disabled'];

    use RSR_Trend_Source_Ingestion_Trait;
    use RSR_Trend_Reports_Trait;
    use RSR_Trend_Processing_Trait;
    use RSR_Trend_Serialization_Trait;
}
