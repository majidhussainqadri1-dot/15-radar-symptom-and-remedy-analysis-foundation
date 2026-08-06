<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_CLI
{
    private RSR_Trend_Service $trends;

    public function __construct(RSR_Trend_Service $trends)
    {
        $this->trends = $trends;
    }

    public function hooks(): void
    {
        if (defined('WP_CLI') && WP_CLI) {
            WP_CLI::add_command('rsr', $this);
        }
    }

    /**
     * Displays redacted File 15 health evidence.
     *
     * ## EXAMPLES
     *     wp rsr health
     */
    public function health(array $args, array $assoc_args): void
    {
        WP_CLI::line((string)wp_json_encode(RSR_Observability::diagnostics(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Lists governed trend sources without raw credentials.
     *
     * ## EXAMPLES
     *     wp rsr sources --format=table
     */
    public function sources(array $args, array $assoc_args): void
    {
        $rows = $this->trends->list_sources(false);
        WP_CLI\Utils\format_items($assoc_args['format'] ?? 'table', $rows, ['public_id', 'provider_key', 'name', 'dataset', 'license_code', 'status', 'last_success_at']);
    }

    /**
     * Runs one idempotent ingestion command.
     *
     * ## OPTIONS
     * <source_public_id>
     * [--window=<daily|weekly|monthly|yearly>]
     * [--timezone=<timezone>]
     * [--geography=<key>]
     * [--idempotency=<token>]
     */
    public function ingest(array $args, array $assoc_args): void
    {
        $source = (string)($args[0] ?? '');
        if ($source === '') {
            WP_CLI::error('A source public ID is required.');
        }
        $window = (string)($assoc_args['window'] ?? 'daily');
        $timezone = (string)($assoc_args['timezone'] ?? (wp_timezone_string() ?: 'UTC'));
        $geography = (string)($assoc_args['geography'] ?? 'global');
        $token = (string)($assoc_args['idempotency'] ?? 'cli|' . $source . '|' . $window . '|' . gmdate('Y-m-d-H'));
        $result = $this->trends->run_ingestion($source, $window, $timezone, $geography, $token, ['cli' => true]);
        if (is_wp_error($result)) {
            WP_CLI::error($result->get_error_code() . ': ' . $result->get_error_message());
        }
        WP_CLI::line((string)wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Processes pending integration events.
     */
    public function outbox(array $args, array $assoc_args): void
    {
        RSR_Events::process_outbox((int)($assoc_args['limit'] ?? 200));
        WP_CLI::success('File 15 outbox processed.');
    }

    /**
     * Runs idempotent database upgrades.
     */
    public function upgrade(array $args, array $assoc_args): void
    {
        RSR_DB::install();
        WP_CLI::success('File 15 schema is current.');
    }
}
