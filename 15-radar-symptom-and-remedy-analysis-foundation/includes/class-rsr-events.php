<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Events
{
    /** @param array<string, mixed> $payload */
    public static function publish(string $event_name, string $aggregate_type, string $aggregate_id, array $payload): string
    {
        global $wpdb;
        if (preg_match('/^[A-Za-z][A-Za-z0-9]+\.v[1-9][0-9]*$/', $event_name) !== 1) {
            throw new InvalidArgumentException('event_name_invalid');
        }
        $event_id = wp_generate_uuid4();
        $now = current_time('mysql', true);
        $envelope = [
            'event_id' => $event_id,
            'event_name' => $event_name,
            'event_version' => self::version_from_name($event_name),
            'occurred_at' => gmdate('c'),
            'trace_id' => RSR_Observability::trace_id(),
            'aggregate' => ['type' => sanitize_key($aggregate_type), 'id' => sanitize_text_field($aggregate_id)],
            'payload' => $payload,
        ];
        $encoded = wp_json_encode($envelope, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($encoded) || strlen($encoded) > RSR_Hardening::MAX_MANUAL_BODY_BYTES) {
            throw new RuntimeException('event_payload_invalid');
        }
        $inserted = $wpdb->insert(RSR_DB::table('outbox'), [
            'event_id' => $event_id,
            'event_name' => $event_name,
            'aggregate_type' => sanitize_key($aggregate_type),
            'aggregate_id' => sanitize_text_field($aggregate_id),
            'payload_json' => $encoded,
            'status' => 'pending',
            'attempts' => 0,
            'available_at' => $now,
            'created_at' => $now,
        ]);
        if (!$inserted) {
            throw new RuntimeException('outbox_insert_failed');
        }
        return $event_id;
    }

    public static function process_outbox(int $limit = 50): void
    {
        global $wpdb;
        $table = RSR_DB::table('outbox');
        $limit = max(1, min(200, $limit));
        $now = current_time('mysql', true);
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status IN ('pending','retry','processing') AND available_at <= %s ORDER BY id ASC LIMIT %d",
                $now,
                $limit
            ),
            ARRAY_A
        );
        foreach ((array)$rows as $row) {
            $id = (int)$row['id'];
            $lease_until = gmdate('Y-m-d H:i:s', time() + 120);
            $claimed = $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$table} SET status='processing', attempts=attempts+1, available_at=%s WHERE id=%d AND status IN ('pending','retry','processing') AND available_at<=%s",
                    $lease_until,
                    $id,
                    $now
                )
            );
            if ($claimed !== 1) {
                continue;
            }
            $attempts = (int)$row['attempts'] + 1;
            $envelope = json_decode((string)$row['payload_json'], true);
            if (!is_array($envelope)) {
                $wpdb->update($table, ['status' => 'dead', 'attempts' => $attempts], ['id' => $id]);
                continue;
            }
            try {
                do_action('rsr_event_published', (string)$row['event_name'], $envelope);
                do_action('rsr_event_' . sanitize_key((string)$row['event_name']), $envelope);
                $updated = $wpdb->update($table, [
                    'status' => 'delivered',
                    'delivered_at' => current_time('mysql', true),
                    'available_at' => current_time('mysql', true),
                ], ['id' => $id, 'status' => 'processing']);
                if ($updated !== 1) {
                    throw new RuntimeException('outbox_delivery_state_conflict');
                }
            } catch (Throwable $error) {
                $dead = $attempts >= 8;
                $delay = min(3600, 30 * (2 ** min(6, max(0, $attempts - 1))));
                $wpdb->update($table, [
                    'status' => $dead ? 'dead' : 'retry',
                    'available_at' => gmdate('Y-m-d H:i:s', time() + $delay),
                ], ['id' => $id, 'status' => 'processing']);
                RSR_Observability::log('error', 'outbox_delivery_failed', [
                    'event_id' => $row['event_id'],
                    'event_name' => $row['event_name'],
                    'attempts' => $attempts,
                    'error_class' => get_class($error),
                ]);
            }
        }
    }

    /** @param array<string, mixed> $payload @return true|WP_Error */
    public static function accept(string $event_name, array $payload)
    {
        $validation = self::validate_incoming($event_name, $payload);
        if (is_wp_error($validation)) {
            return $validation;
        }
        global $wpdb;
        $event_id = (string)$validation['event_id'];
        $table = RSR_DB::table('inbox');
        $now = current_time('mysql', true);
        $lease = gmdate('Y-m-d H:i:s', time() + 120);
        $payload_hash = hash('sha256', RSR_Domain::canonical_json($payload));

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (event_id,event_name,payload_hash,status,attempts,lease_expires_at,created_at,updated_at) VALUES (%s,%s,%s,'processing',1,%s,%s,%s)",
            $event_id,
            $event_name,
            $payload_hash,
            $lease,
            $now,
            $now
        ));
        if ($inserted !== 1) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE event_id=%s LIMIT 1", $event_id), ARRAY_A);
            if (!is_array($row)) {
                return new WP_Error('rsr_event_inbox_unavailable', __('The event inbox is unavailable.', RSR_TEXT_DOMAIN), ['status' => 503]);
            }
            if (!hash_equals((string)$row['payload_hash'], $payload_hash) || (string)$row['event_name'] !== $event_name) {
                return new WP_Error('rsr_event_id_collision', __('The event ID was reused with different content.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
            if ((string)$row['status'] === 'delivered') {
                return true;
            }
            $reclaimed = $wpdb->query($wpdb->prepare(
                "UPDATE {$table} SET status='processing',attempts=attempts+1,lease_expires_at=%s,last_error_code=NULL,updated_at=%s WHERE event_id=%s AND (status='failed' OR lease_expires_at<=%s)",
                $lease,
                $now,
                $event_id,
                $now
            ));
            if ($reclaimed !== 1) {
                return new WP_Error('rsr_event_in_progress', __('This event is already being processed.', RSR_TEXT_DOMAIN), ['status' => 409]);
            }
        }

        try {
            self::apply_incoming($event_name, $validation);
            $updated = $wpdb->update($table, [
                'status' => 'delivered',
                'processed_at' => current_time('mysql', true),
                'updated_at' => current_time('mysql', true),
            ], ['event_id' => $event_id, 'status' => 'processing']);
            if ($updated !== 1) {
                throw new RuntimeException('inbox_delivery_state_conflict');
            }
            RSR_DB::audit('consume_event', 'platform_event', $event_id, 'cross_file_contract', 'success', null, ['event_name' => $event_name]);
            return true;
        } catch (Throwable $error) {
            $code = sanitize_key($error->getMessage() ?: 'handler_failure');
            $wpdb->update($table, [
                'status' => 'failed',
                'last_error_code' => substr($code, 0, 100),
                'updated_at' => current_time('mysql', true),
            ], ['event_id' => $event_id, 'status' => 'processing']);
            RSR_DB::audit('consume_event', 'platform_event', $event_id, 'cross_file_contract', 'failed', 'handler_failure', [
                'event_name' => $event_name,
                'error_class' => get_class($error),
            ]);
            return new WP_Error('rsr_event_handler_failed', __('The platform event could not be applied.', RSR_TEXT_DOMAIN), ['status' => 500]);
        }
    }

    /** @param array<string,mixed> $payload @return array<string,mixed>|WP_Error */
    private static function validate_incoming(string $event_name, array $payload)
    {
        $allowed = [
            'EncyclopediaEntryPublished.v1',
            'EncyclopediaEntryCorrected.v1',
            'EncyclopediaEntryRetracted.v1',
            'DoctorSuspended.v1',
            'ProviderQuotaChanged.v1',
        ];
        if (!in_array($event_name, $allowed, true)) {
            return new WP_Error('rsr_event_unsupported', __('Unsupported File 15 event.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $event_id = sanitize_text_field((string)($payload['event_id'] ?? ''));
        if (preg_match('/^[A-Za-z0-9._:-]{8,191}$/', $event_id) !== 1) {
            return new WP_Error('rsr_event_id_required', __('A stable event ID is required.', RSR_TEXT_DOMAIN), ['status' => 400]);
        }
        $result = ['event_id' => $event_id];
        if (str_starts_with($event_name, 'EncyclopediaEntry')) {
            $remedy = sanitize_text_field((string)($payload['remedy_public_id'] ?? $payload['entry_public_id'] ?? ''));
            if ($remedy === '') {
                return new WP_Error('rsr_event_remedy_required', __('A canonical remedy identifier is required.', RSR_TEXT_DOMAIN), ['status' => 422]);
            }
            $result['remedy_public_id'] = $remedy;
        } elseif ($event_name === 'ProviderQuotaChanged.v1') {
            $source = sanitize_text_field((string)($payload['source_public_id'] ?? ''));
            if ($source === '' || !array_key_exists('quota_remaining', $payload) || !is_numeric($payload['quota_remaining']) || (int)$payload['quota_remaining'] < 0) {
                return new WP_Error('rsr_event_quota_invalid', __('A source and non-negative quota are required.', RSR_TEXT_DOMAIN), ['status' => 422]);
            }
            $result['source_public_id'] = $source;
            $result['quota_remaining'] = (int)$payload['quota_remaining'];
        } elseif ($event_name === 'DoctorSuspended.v1') {
            $user = absint($payload['user_id'] ?? 0);
            if ($user <= 0) {
                return new WP_Error('rsr_event_user_required', __('A valid user identifier is required.', RSR_TEXT_DOMAIN), ['status' => 422]);
            }
            $result['user_id'] = $user;
        }
        return $result;
    }

    /** @param array<string,mixed> $data */
    private static function apply_incoming(string $event_name, array $data): void
    {
        global $wpdb;
        if (str_starts_with($event_name, 'EncyclopediaEntry')) {
            $remedy = (string)$data['remedy_public_id'];
            if ($event_name !== 'EncyclopediaEntryPublished.v1') {
                $wpdb->query($wpdb->prepare(
                    'UPDATE ' . RSR_DB::table('mappings') . " SET status='review_required',version=version+1,updated_at=%s WHERE remedy_public_id=%s",
                    current_time('mysql', true),
                    $remedy
                ));
            }
            self::invalidate_comparison_cache($remedy);
            return;
        }
        if ($event_name === 'ProviderQuotaChanged.v1') {
            $table = RSR_DB::table('sources');
            $current = $wpdb->get_row($wpdb->prepare("SELECT status FROM {$table} WHERE public_id=%s LIMIT 1", $data['source_public_id']), ARRAY_A);
            if (!is_array($current)) {
                throw new RuntimeException('source_not_found');
            }
            $status = (string)$current['status'] === 'disabled'
                ? 'disabled'
                : ((int)$data['quota_remaining'] === 0 ? 'quota_exhausted' : 'healthy');
            $updated = $wpdb->update($table, [
                'quota_remaining' => (int)$data['quota_remaining'],
                'status' => $status,
                'updated_at' => current_time('mysql', true),
            ], ['public_id' => $data['source_public_id']]);
            if ($updated === false) {
                throw new RuntimeException('source_quota_update_failed');
            }
            return;
        }
        if ($event_name === 'DoctorSuspended.v1') {
            wp_cache_delete('rsr_doctor_claim_' . (int)$data['user_id'], 'rsr');
        }
    }

    private static function invalidate_comparison_cache(string $remedy_id): void
    {
        $generation = (int)get_option('rsr_comparison_cache_generation', 1) + 1;
        update_option('rsr_comparison_cache_generation', $generation, false);
        do_action('rsr_comparison_cache_invalidated', $remedy_id, $generation);
    }

    private static function version_from_name(string $event_name): string
    {
        return preg_match('/\.v([1-9][0-9]*)$/', $event_name, $matches) === 1 ? $matches[1] : '1';
    }
}
