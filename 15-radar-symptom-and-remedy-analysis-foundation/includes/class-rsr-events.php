<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Events
{
    /** @param array<string, mixed> $payload */
    public static function publish(
        string $event_name,
        string $aggregate_type,
        string $aggregate_id,
        array $payload
    ): string {
        global $wpdb;
        $event_id = wp_generate_uuid4();
        $envelope = [
            'event_id' => $event_id,
            'event_name' => $event_name,
            'event_version' => self::version_from_name($event_name),
            'occurred_at' => gmdate('c'),
            'trace_id' => RSR_Observability::trace_id(),
            'aggregate' => [
                'type' => $aggregate_type,
                'id' => $aggregate_id,
            ],
            'payload' => $payload,
        ];

        $wpdb->insert(
            RSR_DB::table('outbox'),
            [
                'event_id' => $event_id,
                'event_name' => $event_name,
                'aggregate_type' => $aggregate_type,
                'aggregate_id' => $aggregate_id,
                'payload_json' => wp_json_encode($envelope),
                'status' => 'pending',
                'attempts' => 0,
                'available_at' => current_time('mysql', true),
                'created_at' => current_time('mysql', true),
            ]
        );
        return $event_id;
    }

    public static function process_outbox(int $limit = 50): void
    {
        global $wpdb;
        if (get_transient('rsr_outbox_lock')) {
            return;
        }
        set_transient('rsr_outbox_lock', 1, 55);

        try {
            $table = RSR_DB::table('outbox');
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$table} WHERE status IN ('pending','retry') AND available_at <= %s ORDER BY id ASC LIMIT %d",
                    current_time('mysql', true),
                    max(1, min(200, $limit))
                ),
                ARRAY_A
            );

            foreach ((array)$rows as $row) {
                $id = (int)$row['id'];
                $attempts = (int)$row['attempts'] + 1;
                $envelope = json_decode((string)$row['payload_json'], true);
                if (!is_array($envelope)) {
                    $wpdb->update($table, ['status' => 'dead', 'attempts' => $attempts], ['id' => $id]);
                    continue;
                }

                try {
                    do_action('rsr_event_published', (string)$row['event_name'], $envelope);
                    do_action('rsr_event_' . sanitize_key((string)$row['event_name']), $envelope);
                    $wpdb->update(
                        $table,
                        [
                            'status' => 'delivered',
                            'attempts' => $attempts,
                            'delivered_at' => current_time('mysql', true),
                        ],
                        ['id' => $id]
                    );
                } catch (Throwable $error) {
                    $dead = $attempts >= 8;
                    $delay = min(3600, 30 * (2 ** min(6, $attempts - 1)));
                    $wpdb->update(
                        $table,
                        [
                            'status' => $dead ? 'dead' : 'retry',
                            'attempts' => $attempts,
                            'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
                        ],
                        ['id' => $id]
                    );
                    RSR_Observability::log('error', 'outbox_delivery_failed', [
                        'event_id' => $row['event_id'],
                        'event_name' => $row['event_name'],
                        'attempts' => $attempts,
                        'error_class' => get_class($error),
                    ]);
                }
            }
        } finally {
            delete_transient('rsr_outbox_lock');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return true|WP_Error
     */
    public static function accept(string $event_name, array $payload)
    {
        $allowed = [
            'EncyclopediaEntryPublished.v1',
            'EncyclopediaEntryCorrected.v1',
            'EncyclopediaEntryRetracted.v1',
            'DoctorSuspended.v1',
            'ProviderQuotaChanged.v1',
        ];
        if (!in_array($event_name, $allowed, true)) {
            return new WP_Error('rsr_event_unsupported', __('Unsupported File 15 event.', RSR_TEXT_DOMAIN));
        }

        $event_id = sanitize_text_field((string)($payload['event_id'] ?? ''));
        if ($event_id === '' || !preg_match('/^[A-Za-z0-9._:-]{8,191}$/', $event_id)) {
            return new WP_Error('rsr_event_id_required', __('A stable event ID is required.', RSR_TEXT_DOMAIN));
        }

        $dedupe_key = 'rsr_consumed_' . hash('sha256', $event_name . '|' . $event_id);
        if (get_transient($dedupe_key) || self::was_consumed($event_id)) {
            set_transient($dedupe_key, 1, 30 * DAY_IN_SECONDS);
            return true;
        }

        $lock_key = 'rsr_consume_lock_' . substr(hash('sha256', $event_name . '|' . $event_id), 0, 38);
        if (get_transient($lock_key)) {
            return new WP_Error('rsr_event_in_progress', __('This event is already being processed.', RSR_TEXT_DOMAIN), ['status' => 409]);
        }
        set_transient($lock_key, 1, 60);

        try {
            // Recheck after acquiring the lock to close the concurrent-delivery gap.
            if (self::was_consumed($event_id)) {
                set_transient($dedupe_key, 1, 30 * DAY_IN_SECONDS);
                return true;
            }

            switch ($event_name) {
                case 'EncyclopediaEntryPublished.v1':
                case 'EncyclopediaEntryCorrected.v1':
                case 'EncyclopediaEntryRetracted.v1':
                    self::handle_encyclopedia_event($event_name, $payload);
                    break;
                case 'ProviderQuotaChanged.v1':
                    self::handle_provider_quota($payload);
                    break;
                case 'DoctorSuspended.v1':
                    self::handle_doctor_suspension($payload);
                    break;
            }

            RSR_DB::audit(
                'consume_event',
                'platform_event',
                $event_id,
                'cross_file_contract',
                'success',
                null,
                ['event_name' => $event_name]
            );
            set_transient($dedupe_key, 1, 30 * DAY_IN_SECONDS);
            return true;
        } catch (Throwable $error) {
            RSR_DB::audit(
                'consume_event',
                'platform_event',
                $event_id,
                'cross_file_contract',
                'failed',
                'handler_failure',
                ['event_name' => $event_name, 'error_class' => get_class($error)]
            );
            return new WP_Error('rsr_event_handler_failed', __('The platform event could not be applied.', RSR_TEXT_DOMAIN), ['status' => 500]);
        } finally {
            delete_transient($lock_key);
        }
    }

    private static function was_consumed(string $event_id): bool
    {
        global $wpdb;
        return (int)$wpdb->get_var(
            $wpdb->prepare(
                'SELECT COUNT(*) FROM ' . RSR_DB::table('audit') . " WHERE action = 'consume_event' AND object_type = 'platform_event' AND object_id = %s AND result = 'success'",
                $event_id
            )
        ) > 0;
    }

    /** @param array<string, mixed> $payload */
    private static function handle_encyclopedia_event(string $event_name, array $payload): void
    {
        global $wpdb;
        $remedy_id = sanitize_text_field((string)($payload['remedy_public_id'] ?? $payload['entry_public_id'] ?? ''));
        if ($remedy_id === '') {
            return;
        }

        if ($event_name !== 'EncyclopediaEntryPublished.v1') {
            $wpdb->query(
                $wpdb->prepare(
                    'UPDATE ' . RSR_DB::table('mappings') . " SET status = 'review_required', version = version + 1, updated_at = %s WHERE remedy_public_id = %s",
                    current_time('mysql', true),
                    $remedy_id
                )
            );
        }
        self::invalidate_comparison_cache($remedy_id);
    }

    /** @param array<string, mixed> $payload */
    private static function handle_provider_quota(array $payload): void
    {
        global $wpdb;
        $source_id = sanitize_text_field((string)($payload['source_public_id'] ?? ''));
        if ($source_id === '') {
            return;
        }
        $quota = isset($payload['quota_remaining']) ? max(0, (int)$payload['quota_remaining']) : null;
        $status = $quota === 0 ? 'quota_exhausted' : 'healthy';
        $wpdb->update(
            RSR_DB::table('sources'),
            [
                'quota_remaining' => $quota,
                'status' => $status,
                'updated_at' => current_time('mysql', true),
            ],
            ['public_id' => $source_id]
        );
    }

    /** @param array<string, mixed> $payload */
    private static function handle_doctor_suspension(array $payload): void
    {
        $user_id = absint($payload['user_id'] ?? 0);
        if ($user_id <= 0) {
            return;
        }
        // File 00 remains authoritative. Purge only short-lived File 15 caches.
        wp_cache_delete('rsr_doctor_claim_' . $user_id, 'rsr');
    }

    private static function invalidate_comparison_cache(string $remedy_id): void
    {
        // Generation-based invalidation avoids unbounded transient scans.
        $generation = (int)get_option('rsr_comparison_cache_generation', 1);
        update_option('rsr_comparison_cache_generation', $generation + 1, false);
        do_action('rsr_comparison_cache_invalidated', $remedy_id, $generation + 1);
    }

    private static function version_from_name(string $event_name): string
    {
        if (preg_match('/\.v(\d+)$/', $event_name, $matches)) {
            return $matches[1];
        }
        return '1';
    }
}
