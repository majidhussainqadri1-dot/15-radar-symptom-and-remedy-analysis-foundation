<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Admin
{
    private RSR_Radar_Service $radar;
    private RSR_Study_Service $studies;
    private RSR_Trend_Service $trends;

    public function __construct(RSR_Radar_Service $radar, RSR_Study_Service $studies, RSR_Trend_Service $trends)
    {
        $this->radar = $radar;
        $this->studies = $studies;
        $this->trends = $trends;
    }

    public function hooks(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_filter('option_page_capability_rsr_settings_group', static fn(): string => RSR_Capabilities::MANAGE_SOURCES);
        add_action('admin_post_rsr_toggle_safe_mode', [$this, 'toggle_safe_mode']);
        add_action('admin_post_rsr_rebuild_schema', [$this, 'rebuild_schema']);
        add_action('admin_post_rsr_process_outbox', [$this, 'process_outbox']);
        add_filter('plugin_action_links_' . plugin_basename(RSR_FILE), [$this, 'action_links']);
        add_action('admin_notices', [$this, 'notices']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('Radar & Trends', RSR_TEXT_DOMAIN),
            __('Radar & Trends', RSR_TEXT_DOMAIN),
            RSR_Capabilities::VIEW_DIAGNOSTICS,
            'rsr-file-15',
            [$this, 'render_dashboard'],
            'dashicons-chart-area',
            58
        );
        add_submenu_page(
            'rsr-file-15',
            __('File 15 Settings', RSR_TEXT_DOMAIN),
            __('Settings', RSR_TEXT_DOMAIN),
            RSR_Capabilities::MANAGE_SOURCES,
            'rsr-file-15-settings',
            [$this, 'render_settings']
        );
    }

    public function register_settings(): void
    {
        register_setting('rsr_settings_group', 'rsr_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
            'default' => [],
        ]);
        add_settings_section('rsr_retention', __('Retention and display', RSR_TEXT_DOMAIN), '__return_false', 'rsr-file-15-settings');
        foreach ([
            'observation_retention_days' => __('Observation retention days', RSR_TEXT_DOMAIN),
            'job_retention_days' => __('Job retention days', RSR_TEXT_DOMAIN),
            'audit_retention_days' => __('Audit retention days', RSR_TEXT_DOMAIN),
            'outbox_retention_days' => __('Outbox retention days', RSR_TEXT_DOMAIN),
            'deleted_study_retention_days' => __('Deleted-study retention days', RSR_TEXT_DOMAIN),
            'public_reports_per_page' => __('Public reports per page', RSR_TEXT_DOMAIN),
        ] as $key => $label) {
            add_settings_field($key, $label, [$this, 'number_field'], 'rsr-file-15-settings', 'rsr_retention', ['key' => $key]);
        }
        add_settings_field('default_display_timezone', __('Default display time zone', RSR_TEXT_DOMAIN), [$this, 'text_field'], 'rsr-file-15-settings', 'rsr_retention', ['key' => 'default_display_timezone']);
        add_settings_field('default_geography', __('Default geography key', RSR_TEXT_DOMAIN), [$this, 'text_field'], 'rsr-file-15-settings', 'rsr_retention', ['key' => 'default_geography']);
        add_settings_field('wellbeing_notice', __('Wellbeing notice', RSR_TEXT_DOMAIN), [$this, 'checkbox_field'], 'rsr-file-15-settings', 'rsr_retention', ['key' => 'wellbeing_notice']);
    }

    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function sanitize_settings(array $input): array
    {
        $current = (array)get_option('rsr_settings', []);
        $limits = [
            'observation_retention_days' => [90, 3650],
            'job_retention_days' => [30, 730],
            'audit_retention_days' => [180, 3650],
            'outbox_retention_days' => [30, 730],
            'deleted_study_retention_days' => [1, 180],
            'public_reports_per_page' => [5, 100],
        ];
        foreach ($limits as $key => [$min, $max]) {
            $current[$key] = max($min, min($max, (int)($input[$key] ?? $current[$key] ?? $min)));
        }
        $timezone = sanitize_text_field((string)($input['default_display_timezone'] ?? $current['default_display_timezone'] ?? 'UTC'));
        try {
            new DateTimeZone($timezone);
            $current['default_display_timezone'] = $timezone;
        } catch (Throwable $error) {
            add_settings_error('rsr_settings', 'rsr_invalid_timezone', __('The time zone was invalid and was not changed.', RSR_TEXT_DOMAIN));
        }
        $current['default_geography'] = sanitize_key((string)($input['default_geography'] ?? $current['default_geography'] ?? 'global')) ?: 'global';
        $current['wellbeing_notice'] = !empty($input['wellbeing_notice']);
        return $current;
    }

    public function render_dashboard(): void
    {
        if (!RSR_Capabilities::can(RSR_Capabilities::VIEW_DIAGNOSTICS)) {
            wp_die(esc_html__('You cannot view File 15 diagnostics.', RSR_TEXT_DOMAIN));
        }
        $diagnostics = RSR_Observability::diagnostics();
        $sources = $this->trends->list_sources(false);
        $reports = $this->trends->manage_reports(['limit' => 10]);
        $safe_mode = (bool)get_option('rsr_safe_mode', false);
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('File 15 — Radar & Trend Intelligence', RSR_TEXT_DOMAIN); ?></h1>
            <p><?php esc_html_e('This page shows operational evidence. It does not turn coding completion into staging or production acceptance.', RSR_TEXT_DOMAIN); ?></p>
            <?php if ($safe_mode) : ?>
                <div class="notice notice-warning inline"><p><?php esc_html_e('Safe mode is active: private study writes are paused.', RSR_TEXT_DOMAIN); ?></p></div>
            <?php endif; ?>
            <p>
                <a class="button button-primary" href="<?php echo esc_url(home_url('/radar/manage/')); ?>"><?php esc_html_e('Open Radar Operations', RSR_TEXT_DOMAIN); ?></a>
                <a class="button" href="<?php echo esc_url(home_url('/radar/')); ?>"><?php esc_html_e('Open Public Radar', RSR_TEXT_DOMAIN); ?></a>
                <a class="button" href="<?php echo esc_url(home_url('/trends/')); ?>"><?php esc_html_e('Open Public Trends', RSR_TEXT_DOMAIN); ?></a>
            </p>
            <h2><?php esc_html_e('Health snapshot', RSR_TEXT_DOMAIN); ?></h2>
            <table class="widefat striped"><tbody>
                <tr><th><?php esc_html_e('Plugin version', RSR_TEXT_DOMAIN); ?></th><td><?php echo esc_html((string)$diagnostics['plugin_version']); ?></td></tr>
                <tr><th><?php esc_html_e('Schema version', RSR_TEXT_DOMAIN); ?></th><td><?php echo esc_html((string)$diagnostics['schema_version']); ?></td></tr>
                <tr><th><?php esc_html_e('Missing tables', RSR_TEXT_DOMAIN); ?></th><td><?php echo esc_html($diagnostics['missing_tables'] === [] ? __('None', RSR_TEXT_DOMAIN) : implode(', ', $diagnostics['missing_tables'])); ?></td></tr>
                <tr><th><?php esc_html_e('Sources', RSR_TEXT_DOMAIN); ?></th><td><?php echo esc_html((string)count($sources)); ?></td></tr>
                <tr><th><?php esc_html_e('Reports in workflow', RSR_TEXT_DOMAIN); ?></th><td><?php echo esc_html((string)count($reports)); ?></td></tr>
                <tr><th><?php esc_html_e('Trace ID', RSR_TEXT_DOMAIN); ?></th><td><code><?php echo esc_html((string)$diagnostics['trace_id']); ?></code></td></tr>
            </tbody></table>
            <h2><?php esc_html_e('Guarded operations', RSR_TEXT_DOMAIN); ?></h2>
            <div style="display:flex;gap:8px;flex-wrap:wrap">
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('rsr_toggle_safe_mode'); ?>
                    <input type="hidden" name="action" value="rsr_toggle_safe_mode">
                    <button class="button" type="submit"><?php echo esc_html($safe_mode ? __('Disable safe mode', RSR_TEXT_DOMAIN) : __('Enable safe mode', RSR_TEXT_DOMAIN)); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('rsr_rebuild_schema'); ?>
                    <input type="hidden" name="action" value="rsr_rebuild_schema">
                    <button class="button" type="submit"><?php esc_html_e('Run idempotent schema upgrade', RSR_TEXT_DOMAIN); ?></button>
                </form>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <?php wp_nonce_field('rsr_process_outbox'); ?>
                    <input type="hidden" name="action" value="rsr_process_outbox">
                    <button class="button" type="submit"><?php esc_html_e('Process event outbox', RSR_TEXT_DOMAIN); ?></button>
                </form>
            </div>
        </div>
        <?php
    }

    public function render_settings(): void
    {
        if (!RSR_Capabilities::can(RSR_Capabilities::MANAGE_SOURCES)) {
            wp_die(esc_html__('You cannot manage File 15 settings.', RSR_TEXT_DOMAIN));
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('File 15 Settings', RSR_TEXT_DOMAIN); ?></h1>
            <form method="post" action="options.php">
                <?php settings_fields('rsr_settings_group'); do_settings_sections('rsr-file-15-settings'); submit_button(); ?>
            </form>
        </div>
        <?php
    }

    /** @param array<string, mixed> $args */
    public function number_field(array $args): void
    {
        $key = sanitize_key((string)$args['key']);
        $settings = (array)get_option('rsr_settings', []);
        echo '<input type="number" class="small-text" min="1" name="rsr_settings[' . esc_attr($key) . ']" value="' . esc_attr((string)($settings[$key] ?? '')) . '">';
    }

    /** @param array<string, mixed> $args */
    public function text_field(array $args): void
    {
        $key = sanitize_key((string)$args['key']);
        $settings = (array)get_option('rsr_settings', []);
        echo '<input type="text" class="regular-text" name="rsr_settings[' . esc_attr($key) . ']" value="' . esc_attr((string)($settings[$key] ?? '')) . '">';
    }

    /** @param array<string, mixed> $args */
    public function checkbox_field(array $args): void
    {
        $key = sanitize_key((string)$args['key']);
        $settings = (array)get_option('rsr_settings', []);
        echo '<label><input type="checkbox" name="rsr_settings[' . esc_attr($key) . ']" value="1" ' . checked(!empty($settings[$key]), true, false) . '> ' . esc_html__('Show a non-manipulative break reminder during long research sessions.', RSR_TEXT_DOMAIN) . '</label>';
    }

    public function toggle_safe_mode(): void
    {
        $this->guard_action('rsr_toggle_safe_mode', RSR_Capabilities::MANAGE_SOURCES);
        $next = !(bool)get_option('rsr_safe_mode', false);
        update_option('rsr_safe_mode', $next, false);
        RSR_DB::audit('toggle_safe_mode', 'plugin', 'file-15', 'incident_control', 'success', null, ['enabled' => $next]);
        wp_safe_redirect(add_query_arg('rsr_notice', 'safe-mode', admin_url('admin.php?page=rsr-file-15')));
        exit;
    }

    public function rebuild_schema(): void
    {
        $this->guard_action('rsr_rebuild_schema', RSR_Capabilities::MANAGE_SCHEMA);
        RSR_DB::install();
        wp_safe_redirect(add_query_arg('rsr_notice', 'schema', admin_url('admin.php?page=rsr-file-15')));
        exit;
    }

    public function process_outbox(): void
    {
        $this->guard_action('rsr_process_outbox', RSR_Capabilities::RUN_INGESTION);
        RSR_Events::process_outbox(200);
        wp_safe_redirect(add_query_arg('rsr_notice', 'outbox', admin_url('admin.php?page=rsr-file-15')));
        exit;
    }

    /** @param array<int, string> $links @return array<int, string> */
    public function action_links(array $links): array
    {
        array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=rsr-file-15')) . '">' . esc_html__('Diagnostics', RSR_TEXT_DOMAIN) . '</a>');
        return $links;
    }

    public function notices(): void
    {
        if (!isset($_GET['rsr_notice'])) {
            return;
        }
        $notice = sanitize_key(wp_unslash((string)$_GET['rsr_notice']));
        $messages = [
            'safe-mode' => __('Safe mode was changed.', RSR_TEXT_DOMAIN),
            'schema' => __('The idempotent schema upgrade completed.', RSR_TEXT_DOMAIN),
            'outbox' => __('The event outbox processing command completed.', RSR_TEXT_DOMAIN),
        ];
        if (isset($messages[$notice])) {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($messages[$notice]) . '</p></div>';
        }
    }

    private function guard_action(string $nonce_action, string $capability): void
    {
        if (!RSR_Capabilities::can($capability)) {
            wp_die(esc_html__('You cannot perform this File 15 operation.', RSR_TEXT_DOMAIN), '', ['response' => 403]);
        }
        check_admin_referer($nonce_action);
    }
}
