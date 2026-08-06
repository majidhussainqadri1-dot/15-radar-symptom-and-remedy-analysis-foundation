<?php

declare(strict_types=1);

if (!defined('ABSPATH') && !defined('RSR_TESTING')) {
    exit;
}

final class RSR_Privacy
{
    private RSR_Study_Service $studies;
    private RSR_Trend_Service $trends;

    public function __construct(RSR_Study_Service $studies, RSR_Trend_Service $trends)
    {
        $this->studies = $studies;
        $this->trends = $trends;
    }

    public function hooks(): void
    {
        add_filter('wp_privacy_personal_data_exporters', [$this, 'exporters']);
        add_filter('wp_privacy_personal_data_erasers', [$this, 'erasers']);
        add_action('delete_user', [$this, 'delete_user']);
        add_action('wpmu_delete_user', [$this, 'delete_user']);
        add_action('admin_init', [$this, 'privacy_policy_text']);
    }

    /** @param array<string, mixed> $exporters @return array<string, mixed> */
    public function exporters(array $exporters): array
    {
        $exporters['rsr-private-studies'] = [
            'exporter_friendly_name' => __('File 15 private Radar studies', RSR_TEXT_DOMAIN),
            'callback' => [$this, 'export_personal_data'],
        ];
        return $exporters;
    }

    /** @param array<string, mixed> $erasers @return array<string, mixed> */
    public function erasers(array $erasers): array
    {
        $erasers['rsr-private-studies'] = [
            'eraser_friendly_name' => __('File 15 private Radar studies', RSR_TEXT_DOMAIN),
            'callback' => [$this, 'erase_personal_data'],
        ];
        return $erasers;
    }

    /** @return array<string, mixed> */
    public function export_personal_data(string $email_address, int $page = 1): array
    {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return ['data' => [], 'done' => true];
        }
        global $wpdb;
        $per_page = 50;
        $offset = max(0, $page - 1) * $per_page;
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT * FROM ' . RSR_DB::table('studies') . " WHERE owner_user_id = %d AND status IN ('saved','archived') ORDER BY id ASC LIMIT %d OFFSET %d",
                (int)$user->ID,
                $per_page,
                $offset
            ),
            ARRAY_A
        );
        $data = [];
        foreach ((array)$rows as $row) {
            $notes = '';
            if (!empty($row['notes_encrypted'])) {
                try {
                    $notes = RSR_Crypto::decrypt((string)$row['notes_encrypted']);
                } catch (Throwable $error) {
                    $notes = __('Encrypted notes could not be decoded with the current key.', RSR_TEXT_DOMAIN);
                }
            }
            $data[] = [
                'group_id' => 'rsr-private-studies',
                'group_label' => __('Private Radar studies', RSR_TEXT_DOMAIN),
                'item_id' => 'rsr-study-' . (string)$row['public_id'],
                'data' => [
                    ['name' => __('Title', RSR_TEXT_DOMAIN), 'value' => (string)$row['title']],
                    ['name' => __('Query', RSR_TEXT_DOMAIN), 'value' => (string)$row['query_json']],
                    ['name' => __('Remedy references', RSR_TEXT_DOMAIN), 'value' => (string)$row['remedy_refs_json']],
                    ['name' => __('Tags', RSR_TEXT_DOMAIN), 'value' => (string)$row['tags_json']],
                    ['name' => __('Notes', RSR_TEXT_DOMAIN), 'value' => wp_strip_all_tags($notes)],
                    ['name' => __('Status', RSR_TEXT_DOMAIN), 'value' => (string)$row['status']],
                    ['name' => __('Updated at', RSR_TEXT_DOMAIN), 'value' => (string)$row['updated_at']],
                ],
            ];
        }
        RSR_DB::audit('privacy_export', 'radar_study_collection', (string)$user->ID, 'data_portability', 'success', null, ['page' => $page]);
        return ['data' => $data, 'done' => count((array)$rows) < $per_page];
    }

    /** @return array<string, mixed> */
    public function erase_personal_data(string $email_address, int $page = 1): array
    {
        $user = get_user_by('email', $email_address);
        if (!$user) {
            return [
                'items_removed' => false,
                'items_retained' => false,
                'messages' => [],
                'done' => true,
            ];
        }
        $count = $this->studies->purge_user((int)$user->ID);
        return [
            'items_removed' => $count > 0,
            'items_retained' => false,
            'messages' => $count > 0 ? [sprintf(__('Removed %d private Radar studies.', RSR_TEXT_DOMAIN), $count)] : [],
            'done' => true,
        ];
    }

    public function delete_user(int $user_id): void
    {
        $this->studies->purge_user($user_id);
    }

    public function privacy_policy_text(): void
    {
        if (!function_exists('wp_add_privacy_policy_content')) {
            return;
        }
        $content = '<p>' . esc_html__('File 15 provides public educational Radar searches and public, editorially approved trend reports. Public usage may produce minimal security logs and rate-limit counters.', RSR_TEXT_DOMAIN) . '</p>'
            . '<p>' . esc_html__('Verified doctors may save private research studies. Notes are encrypted at rest, limited to the owner, excluded from general search, analytics, AI retrieval, feeds, and public trends, and must not contain patient-identifying information. Users may export or delete their studies.', RSR_TEXT_DOMAIN) . '</p>'
            . '<p>' . esc_html__('Trend ingestion accepts aggregated, licensed source data only. It does not ingest clinical records, messages, identity evidence, payment data, or private studies.', RSR_TEXT_DOMAIN) . '</p>';
        wp_add_privacy_policy_content(__('File 15 — Radar and Trend Intelligence', RSR_TEXT_DOMAIN), wp_kses_post($content));
    }
}
