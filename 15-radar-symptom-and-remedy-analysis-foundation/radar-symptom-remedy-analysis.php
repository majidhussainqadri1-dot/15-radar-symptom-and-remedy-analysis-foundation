<?php
/**
 * Plugin Name: Radar, Symptom/Remedy Research and Trend Intelligence
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: File 15 of the Sabri Social Homeopathy Platform: source-linked Radar research, private verified-doctor studies, and editorially governed trend intelligence.
 * Version: 1.1.0
 * Requires at least: 6.5
 * Requires PHP: 8.1
 * Author: Dr. Allamah Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: radar-symptom-remedy-analysis
 * Domain Path: /languages
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('RSR_VERSION', '1.1.0');
define('RSR_SCHEMA_VERSION', '1.0.0');
define('RSR_FILE', __FILE__);
define('RSR_DIR', plugin_dir_path(__FILE__));
define('RSR_URL', plugin_dir_url(__FILE__));
define('RSR_BASENAME', plugin_basename(__FILE__));
define('RSR_TEXT_DOMAIN', 'radar-symptom-remedy-analysis');

$rsr_files = [
    'includes/class-rsr-domain.php',
    'includes/class-rsr-pii-scanner.php',
    'includes/class-rsr-crypto.php',
    'includes/class-rsr-db.php',
    'includes/class-rsr-capabilities.php',
    'includes/class-rsr-observability.php',
    'includes/class-rsr-events.php',
    'includes/class-rsr-provider-registry.php',
    'includes/providers/class-rsr-manual-provider.php',
    'includes/class-rsr-radar-service.php',
    'includes/class-rsr-study-service.php',
    'includes/class-rsr-trend-service.php',
    'includes/class-rsr-api.php',
    'includes/class-rsr-four-plan-compliance.php',
    'includes/class-rsr-routes.php',
    'includes/class-rsr-admin.php',
    'includes/class-rsr-privacy.php',
    'includes/class-rsr-cli.php',
    'includes/class-rsr-activator.php',
    'includes/class-rsr-plugin.php',
];

foreach ($rsr_files as $rsr_file) {
    require_once RSR_DIR . $rsr_file;
}
unset($rsr_file, $rsr_files);

register_activation_hook(RSR_FILE, [RSR_Activator::class, 'activate']);
register_deactivation_hook(RSR_FILE, [RSR_Activator::class, 'deactivate']);

add_action(
    'plugins_loaded',
    static function (): void {
        load_plugin_textdomain(
            RSR_TEXT_DOMAIN,
            false,
            dirname(RSR_BASENAME) . '/languages'
        );

        (new RSR_Plugin())->run();
    },
    80
);

/**
 * Versioned public integration entry point for other platform modules.
 *
 * Consumers should call this function rather than writing to Radar tables.
 *
 * @param string               $event_name Past-tense versioned event name.
 * @param array<string, mixed> $payload    Event payload.
 * @return true|WP_Error
 */
function rsr_accept_platform_event(string $event_name, array $payload)
{
    return RSR_Events::accept($event_name, $payload);
}
