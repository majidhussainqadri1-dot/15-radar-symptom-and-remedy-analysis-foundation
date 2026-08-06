<?php
/**
 * File 15 uninstall is non-destructive by default.
 * Data is purged only after an explicit administrator-controlled option is set.
 */

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

foreach (['rsr_reconcile_trend_windows', 'rsr_process_outbox', 'rsr_retention_cleanup'] as $hook) {
    wp_clear_scheduled_hook($hook);
}

foreach (['administrator'] as $role_name) {
    $role = get_role($role_name);
    if ($role) {
        foreach ([
            'rsr_manage_own_studies',
            'rsr_export_own_studies',
            'rsr_manage_schema',
            'rsr_manage_sources',
            'rsr_run_ingestion',
            'rsr_review_reports',
            'rsr_publish_reports',
            'rsr_correct_reports',
            'rsr_view_diagnostics',
            'rsr_purge_data',
        ] as $capability) {
            $role->remove_cap($capability);
        }
    }
}

if ((bool)get_option('rsr_purge_on_uninstall', false) !== true) {
    return;
}

global $wpdb;
$tables = [
    $wpdb->prefix . 'rsr_audit',
    $wpdb->prefix . 'rsr_outbox',
    $wpdb->prefix . 'rsr_jobs',
    $wpdb->prefix . 'rsr_corrections',
    $wpdb->prefix . 'rsr_reports',
    $wpdb->prefix . 'rsr_observations',
    $wpdb->prefix . 'rsr_sources',
    $wpdb->prefix . 'rsr_studies',
    $wpdb->prefix . 'rsr_mappings',
    $wpdb->prefix . 'rsr_schema_values',
];
foreach ($tables as $table) {
    $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

foreach ([
    'rsr_schema_version',
    'rsr_plugin_version',
    'rsr_settings',
    'rsr_safe_mode',
    'rsr_comparison_cache_generation',
    'rsr_manual_trend_rows',
    'rsr_purge_on_uninstall',
] as $option) {
    delete_option($option);
}
