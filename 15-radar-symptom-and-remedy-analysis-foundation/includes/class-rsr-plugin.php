<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Plugin
{
    private RSR_Radar_Service $radar;
    private RSR_Study_Service $studies;
    private RSR_Trend_Service $trends;

    public function __construct()
    {
        $this->radar = new RSR_Radar_Service();
        $this->studies = new RSR_Study_Service();
        $this->trends = new RSR_Trend_Service();
    }

    public function run(): void
    {
        RSR_DB::maybe_upgrade();
        RSR_Capabilities::hooks();
        add_filter('cron_schedules', [RSR_Activator::class, 'cron_schedules']);
        RSR_Activator::schedule();

        (new RSR_API($this->radar, $this->studies, $this->trends))->hooks();
        (new RSR_Four_Plan_Compliance($this->radar))->hooks();
        (new RSR_Routes($this->radar, $this->studies, $this->trends))->hooks();
        (new RSR_Admin($this->radar, $this->studies, $this->trends))->hooks();
        (new RSR_Privacy($this->studies, $this->trends))->hooks();
        (new RSR_CLI($this->trends))->hooks();

        add_action('rsr_reconcile_trend_windows', [$this->trends, 'reconcile_due_windows']);
        add_action('rsr_process_outbox', [RSR_Events::class, 'process_outbox']);
        add_action('rsr_retention_cleanup', [$this, 'retention_cleanup']);
        add_action('sabri_platform_event_v1', [$this, 'consume_platform_event'], 10, 2);

        add_filter('sabri_file15_public_search_v1', [$this, 'public_search_contract'], 10, 2);
        add_filter('sabri_file15_public_reports_v1', [$this, 'public_reports_contract'], 10, 2);
        add_filter('sabri_file15_health_v1', fn() => RSR_Observability::diagnostics());
        add_action('admin_init', [$this, 'upgrade_and_capabilities']);
        add_action('init', [$this, 'register_integration_status'], 20);
    }

    public function upgrade_and_capabilities(): void
    {
        RSR_DB::maybe_upgrade();
        RSR_Capabilities::grant_administrator();
        if ((string)get_option('rsr_plugin_version', '') !== RSR_VERSION) {
            update_option('rsr_plugin_version', RSR_VERSION, false);
        }
    }

    public function retention_cleanup(): void
    {
        $settings = (array)get_option('rsr_settings', []);
        $study_days = max(1, (int)($settings['deleted_study_retention_days'] ?? 30));
        $study_count = $this->studies->retention_cleanup($study_days);
        $trend_counts = $this->trends->retention_cleanup();
        RSR_DB::audit('retention_cleanup', 'file15_data', 'scheduled', 'retention', 'success', null, [
            'deleted_studies' => $study_count,
            'trend_counts' => $trend_counts,
        ]);
    }

    /** @param array<string, mixed> $payload */
    public function consume_platform_event(string $event_name, array $payload): void
    {
        $result = RSR_Events::accept($event_name, $payload);
        if (is_wp_error($result)) {
            RSR_Observability::log('warning', 'platform_event_rejected', [
                'event_name' => $event_name,
                'error_code' => $result->get_error_code(),
            ]);
        }
    }

    /**
     * Versioned read-only contract for File 26 search and File 16 retrieval.
     * Private studies and unpublished reports can never enter this result.
     *
     * @param mixed $current
     * @param array<string, mixed> $query
     * @return mixed
     */
    public function public_search_contract($current, array $query)
    {
        if ($current !== null) {
            return $current;
        }
        $type = sanitize_key((string)($query['type'] ?? 'radar'));
        if ($type === 'trends') {
            return $this->trends->public_reports([
                'window_type' => sanitize_key((string)($query['window_type'] ?? '')),
                'geography' => sanitize_key((string)($query['geography'] ?? '')),
                'limit' => max(1, min(50, (int)($query['limit'] ?? 20))),
            ]);
        }
        return $this->radar->search((array)($query['radar_query'] ?? $query), max(1, min(50, (int)($query['limit'] ?? 20))), null);
    }

    /** @param mixed $current @param array<string, mixed> $filters @return mixed */
    public function public_reports_contract($current, array $filters)
    {
        if ($current !== null) {
            return $current;
        }
        return $this->trends->public_reports($filters);
    }

    public function register_integration_status(): void
    {
        do_action('sabri_module_registered_v1', [
            'file_number' => 15,
            'slug' => 'radar-symptom-remedy-analysis',
            'version' => RSR_VERSION,
            'routes' => ['/radar/', '/radar/compare/', '/radar/studies/', '/trends/', '/trends/{report_id}/', '/radar/manage/'],
            'commands' => [
                'radar.search.v1',
                'radar.compare.v1',
                'radar.study.create.v1',
                'radar.study.update.v1',
                'radar.study.delete.v1',
                'radar.trend.ingest.v1',
                'radar.report.transition.v1',
                'radar.report.correct.v1',
            ],
            'read_contracts' => [
                'sabri_file15_public_search_v1',
                'sabri_file15_public_reports_v1',
                'sabri_file15_health_v1',
            ],
            'consumers' => [
                'file06' => 'canonical remedy/source linkage',
                'file16' => 'public approved retrieval only',
                'file21' => 'public trend/editorial discovery only',
                'file26' => 'federated search, explanation and freshness',
            ],
            'commands_owner' => 'file15',
            'search_projection_owner' => 'file26',
            'shell_owner' => 'file20',
            'visual_owner' => 'file25',
            'assurance_owner' => 'file24',
            'events_published' => [
                'RadarTrendReportPublished.v1',
                'RadarTrendReportCorrected.v1',
                'RadarTrendReportRetracted.v1',
                'RadarSourceDegraded.v1',
            ],
            'events_consumed' => [
                'EncyclopediaEntryPublished.v1',
                'EncyclopediaEntryCorrected.v1',
                'EncyclopediaEntryRetracted.v1',
                'DoctorSuspended.v1',
                'ProviderQuotaChanged.v1',
            ],
            'private_data_excluded_from_search' => true,
            'private_studies_excluded_from_ai' => true,
            'clinical_authority' => false,
            'paid_or_donor_ranking_bias' => false,
        ]);
    }
}
