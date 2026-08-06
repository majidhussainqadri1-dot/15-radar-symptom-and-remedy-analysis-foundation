<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_DB
{
    /** @return array<string, string> */
    public static function tables(): array
    {
        global $wpdb;
        $prefix = $wpdb->prefix . 'rsr_';
        return [
            'schema' => $prefix . 'schema_values',
            'mappings' => $prefix . 'mappings',
            'studies' => $prefix . 'studies',
            'sources' => $prefix . 'sources',
            'observations' => $prefix . 'observations',
            'reports' => $prefix . 'reports',
            'corrections' => $prefix . 'corrections',
            'jobs' => $prefix . 'jobs',
            'outbox' => $prefix . 'outbox',
            'audit' => $prefix . 'audit',
        ];
    }

    public static function table(string $name): string
    {
        $tables = self::tables();
        if (!isset($tables[$name])) {
            throw new InvalidArgumentException('Unknown File 15 table.');
        }
        return $tables[$name];
    }

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t = self::tables();

        $sql = [];
        $sql[] = "CREATE TABLE {$t['schema']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            dimension_key varchar(64) NOT NULL,
            value_key varchar(191) NOT NULL,
            label varchar(191) NOT NULL,
            aliases_json longtext NULL,
            definition text NULL,
            source_public_id varchar(191) NULL,
            status varchar(32) NOT NULL DEFAULT 'active',
            version bigint(20) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY dimension_value (dimension_key,value_key),
            KEY status_dimension (status,dimension_key)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['mappings']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            rubric_hash char(64) NOT NULL,
            query_json longtext NOT NULL,
            remedy_public_id varchar(191) NOT NULL,
            source_public_id varchar(191) NOT NULL,
            reference_text text NOT NULL,
            license_code varchar(100) NOT NULL,
            review_date date NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'active',
            version bigint(20) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY mapping_unique (rubric_hash,remedy_public_id,source_public_id),
            KEY remedy_status (remedy_public_id,status),
            KEY rubric_status (rubric_hash,status)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['studies']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            owner_user_id bigint(20) unsigned NOT NULL,
            title varchar(191) NOT NULL,
            tags_json text NULL,
            query_json longtext NOT NULL,
            remedy_refs_json text NULL,
            notes_encrypted longtext NULL,
            status varchar(32) NOT NULL DEFAULT 'saved',
            version bigint(20) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            deleted_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            KEY owner_status_updated (owner_user_id,status,updated_at),
            KEY deleted_at (deleted_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['sources']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            provider_key varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            dataset varchar(191) NOT NULL,
            edition varchar(100) NULL,
            license_code varchar(100) NOT NULL,
            review_date date NOT NULL,
            territory varchar(100) NOT NULL DEFAULT 'global',
            restrictions text NULL,
            quality_score decimal(6,5) NOT NULL DEFAULT 0.50000,
            credentials_ref varchar(191) NULL,
            rate_limit_per_hour int(10) unsigned NOT NULL DEFAULT 1000,
            cost_model varchar(100) NOT NULL DEFAULT 'free',
            config_json longtext NULL,
            status varchar(32) NOT NULL DEFAULT 'configured',
            quota_remaining bigint(20) NULL,
            stale_after_seconds bigint(20) unsigned NOT NULL DEFAULT 86400,
            last_success_at datetime NULL,
            last_error_code varchar(100) NULL,
            version bigint(20) unsigned NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY provider_dataset (provider_key,dataset),
            KEY status_provider (status,provider_key)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['observations']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            source_id bigint(20) unsigned NOT NULL,
            topic_key varchar(191) NOT NULL,
            topic_label varchar(191) NOT NULL,
            geography varchar(100) NOT NULL DEFAULT 'global',
            window_type varchar(32) NOT NULL,
            window_start_utc datetime NOT NULL,
            window_end_utc datetime NOT NULL,
            volume decimal(20,4) NOT NULL DEFAULT 0,
            baseline decimal(20,4) NOT NULL DEFAULT 0,
            source_quality decimal(6,5) NOT NULL DEFAULT 0.5,
            confidence decimal(6,5) NOT NULL DEFAULT 0.5,
            normalized_json longtext NULL,
            trace_id varchar(64) NOT NULL,
            observed_at datetime NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY observation_unique (source_id,topic_key,geography,window_type,window_start_utc,window_end_utc),
            KEY window_lookup (window_type,window_start_utc,window_end_utc),
            KEY topic_geo (topic_key,geography)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['reports']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            window_type varchar(32) NOT NULL,
            display_timezone varchar(100) NOT NULL DEFAULT 'UTC',
            geography varchar(100) NOT NULL DEFAULT 'global',
            window_start_utc datetime NOT NULL,
            window_end_utc datetime NOT NULL,
            method_version varchar(100) NOT NULL,
            results_json longtext NOT NULL,
            sources_json longtext NOT NULL,
            confidence decimal(6,5) NOT NULL DEFAULT 0.5,
            status varchar(32) NOT NULL DEFAULT 'draft',
            version bigint(20) unsigned NOT NULL DEFAULT 1,
            stale_at datetime NULL,
            published_at datetime NULL,
            corrected_at datetime NULL,
            retracted_at datetime NULL,
            created_by bigint(20) unsigned NOT NULL DEFAULT 0,
            reviewed_by bigint(20) unsigned NOT NULL DEFAULT 0,
            approved_by bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY report_window (window_type,geography,display_timezone,window_start_utc,window_end_utc),
            KEY public_browse (status,window_type,published_at),
            KEY status_updated (status,updated_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['corrections']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            report_id bigint(20) unsigned NOT NULL,
            report_version bigint(20) unsigned NOT NULL,
            action varchar(32) NOT NULL,
            reason text NOT NULL,
            public_notice text NOT NULL,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            KEY report_created (report_id,created_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['jobs']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            public_id char(36) NOT NULL,
            idempotency_key char(64) NOT NULL,
            source_id bigint(20) unsigned NOT NULL,
            window_type varchar(32) NOT NULL,
            display_timezone varchar(100) NOT NULL DEFAULT 'UTC',
            geography varchar(100) NOT NULL DEFAULT 'global',
            status varchar(32) NOT NULL DEFAULT 'scheduled',
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            trace_id varchar(64) NOT NULL,
            started_at datetime NULL,
            finished_at datetime NULL,
            error_code varchar(100) NULL,
            result_json longtext NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY public_id (public_id),
            UNIQUE KEY idempotency_key (idempotency_key),
            KEY status_created (status,created_at),
            KEY source_window (source_id,window_type)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['outbox']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            event_id char(36) NOT NULL,
            event_name varchar(191) NOT NULL,
            aggregate_type varchar(100) NOT NULL,
            aggregate_id varchar(191) NOT NULL,
            payload_json longtext NOT NULL,
            status varchar(32) NOT NULL DEFAULT 'pending',
            attempts int(10) unsigned NOT NULL DEFAULT 0,
            available_at datetime NOT NULL,
            created_at datetime NOT NULL,
            delivered_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY event_id (event_id),
            KEY delivery_queue (status,available_at)
        ) {$charset};";

        $sql[] = "CREATE TABLE {$t['audit']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            trace_id varchar(64) NOT NULL,
            actor_id bigint(20) unsigned NOT NULL DEFAULT 0,
            action varchar(191) NOT NULL,
            object_type varchar(100) NOT NULL,
            object_id varchar(191) NOT NULL,
            purpose varchar(191) NOT NULL,
            result varchar(32) NOT NULL,
            reason_code varchar(100) NULL,
            metadata_json longtext NULL,
            ip_hash char(64) NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY trace_id (trace_id),
            KEY object_lookup (object_type,object_id,created_at),
            KEY actor_created (actor_id,created_at)
        ) {$charset};";

        foreach ($sql as $statement) {
            dbDelta($statement);
        }

        update_option('rsr_schema_version', RSR_SCHEMA_VERSION, false);
        self::seed_dimensions();
    }

    public static function maybe_upgrade(): void
    {
        $installed = (string)get_option('rsr_schema_version', '0');
        if (version_compare($installed, RSR_SCHEMA_VERSION, '<')) {
            self::install();
        }
    }

    public static function seed_dimensions(): void
    {
        global $wpdb;
        $table = self::table('schema');
        $now = current_time('mysql', true);

        foreach (RSR_Domain::dimensions() as $key => $definition) {
            $exists = (int)$wpdb->get_var(
                $wpdb->prepare(
                    "SELECT id FROM {$table} WHERE dimension_key = %s AND value_key = %s LIMIT 1",
                    $key,
                    '__dimension__'
                )
            );
            if ($exists > 0) {
                continue;
            }
            $wpdb->insert(
                $table,
                [
                    'public_id' => wp_generate_uuid4(),
                    'dimension_key' => $key,
                    'value_key' => '__dimension__',
                    'label' => (string)$definition['label'],
                    'aliases_json' => wp_json_encode($definition['aliases'] ?? []),
                    'definition' => (string)$definition['description'],
                    'status' => 'active',
                    'version' => 1,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
            );
        }
    }

    /** @param array<string, mixed> $context */
    public static function audit(
        string $action,
        string $object_type,
        string $object_id,
        string $purpose,
        string $result,
        ?string $reason_code = null,
        array $context = []
    ): void {
        global $wpdb;
        $trace_id = (string)($context['trace_id'] ?? RSR_Observability::trace_id());
        unset($context['trace_id']);
        $wpdb->insert(
            self::table('audit'),
            [
                'trace_id' => $trace_id,
                'actor_id' => get_current_user_id(),
                'action' => sanitize_key($action),
                'object_type' => sanitize_key($object_type),
                'object_id' => sanitize_text_field($object_id),
                'purpose' => sanitize_text_field($purpose),
                'result' => sanitize_key($result),
                'reason_code' => $reason_code ? sanitize_key($reason_code) : null,
                'metadata_json' => $context !== [] ? wp_json_encode(RSR_Observability::redact($context)) : null,
                'ip_hash' => RSR_Observability::request_ip_hash(),
                'created_at' => current_time('mysql', true),
            ]
        );
    }

    /** @return array<string, int> */
    public static function row_counts(): array
    {
        global $wpdb;
        $counts = [];
        foreach (self::tables() as $key => $table) {
            $counts[$key] = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        return $counts;
    }

    public static function drop_all(): void
    {
        global $wpdb;
        foreach (array_reverse(self::tables()) as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        }
        delete_option('rsr_schema_version');
        delete_option('rsr_plugin_version');
        delete_option('rsr_settings');
        delete_option('rsr_manual_trend_rows');
    }
}
