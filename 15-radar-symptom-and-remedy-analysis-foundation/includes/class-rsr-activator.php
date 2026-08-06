<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Activator
{
    public static function activate(): void
    {
        if (version_compare(PHP_VERSION, '8.1', '<')) {
            deactivate_plugins(plugin_basename(RSR_FILE));
            wp_die(esc_html__('File 15 requires PHP 8.1 or newer.', RSR_TEXT_DOMAIN));
        }

        RSR_DB::install();
        RSR_Capabilities::grant_administrator();
        self::ensure_settings();
        self::register_rewrite_rules();
        add_filter('cron_schedules', [self::class, 'cron_schedules']);
        self::schedule();
        update_option('rsr_plugin_version', RSR_VERSION, false);
        update_option('rsr_safe_mode', false, false);
        update_option('rsr_comparison_cache_generation', (int)get_option('rsr_comparison_cache_generation', 1), false);
        flush_rewrite_rules(false);
        RSR_DB::audit('activate_plugin', 'plugin', 'file-15', 'deployment', 'success', null, ['version' => RSR_VERSION]);
    }

    public static function deactivate(): void
    {
        foreach (['rsr_reconcile_trend_windows', 'rsr_process_outbox', 'rsr_retention_cleanup'] as $hook) {
            wp_clear_scheduled_hook($hook);
        }
        flush_rewrite_rules(false);
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled('rsr_reconcile_trend_windows')) {
            wp_schedule_event(time() + 120, 'hourly', 'rsr_reconcile_trend_windows');
        }
        if (!wp_next_scheduled('rsr_process_outbox')) {
            wp_schedule_event(time() + 60, 'rsr_five_minutes', 'rsr_process_outbox');
        }
        if (!wp_next_scheduled('rsr_retention_cleanup')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'rsr_retention_cleanup');
        }
    }

    /** @param array<string, array<string, mixed>> $schedules @return array<string, array<string, mixed>> */
    public static function cron_schedules(array $schedules): array
    {
        $schedules['rsr_five_minutes'] = [
            'interval' => 5 * MINUTE_IN_SECONDS,
            'display' => __('Every five minutes (File 15)', RSR_TEXT_DOMAIN),
        ];
        return $schedules;
    }

    private static function ensure_settings(): void
    {
        $defaults = [
            'observation_retention_days' => 1095,
            'job_retention_days' => 180,
            'audit_retention_days' => 2555,
            'outbox_retention_days' => 180,
            'deleted_study_retention_days' => 30,
            'public_reports_per_page' => 20,
            'wellbeing_notice' => true,
            'default_display_timezone' => wp_timezone_string() ?: 'UTC',
            'default_geography' => 'global',
        ];
        $current = (array)get_option('rsr_settings', []);
        update_option('rsr_settings', array_merge($defaults, $current), false);
    }

    private static function register_rewrite_rules(): void
    {
        add_rewrite_rule('^radar/?$', 'index.php?rsr_route=radar', 'top');
        add_rewrite_rule('^radar/compare/?$', 'index.php?rsr_route=compare', 'top');
        add_rewrite_rule('^radar/studies/?$', 'index.php?rsr_route=studies', 'top');
        add_rewrite_rule('^trends/?$', 'index.php?rsr_route=trends', 'top');
        add_rewrite_rule('^trends/([A-Za-z0-9._:-]+)/?$', 'index.php?rsr_route=trend_report&rsr_report_id=$matches[1]', 'top');
        add_rewrite_rule('^radar/manage/?$', 'index.php?rsr_route=manage', 'top');
    }
}
