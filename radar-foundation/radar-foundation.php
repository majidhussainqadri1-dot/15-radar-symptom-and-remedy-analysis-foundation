<?php
/**
 * Plugin Name: Radar Symptom and Remedy Analysis Foundation
 * Plugin URI: https://www.sabrihomeopathy.com/
 * Description: A structured educational symptom, modality, reference, and remedy-comparison foundation with private verified-doctor studies.
 * Version: 0.1.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Dr. Allama Majid Hussain Sabri
 * License: GPL-2.0-or-later
 * Text Domain: radar-foundation
 */

defined( 'ABSPATH' ) || exit;

define( 'SRF_VERSION', '0.1.1' );
define( 'SRF_SCHEMA_VERSION', '2' );
define( 'SRF_FILE', __FILE__ );
define( 'SRF_DIR', plugin_dir_path( __FILE__ ) );
define( 'SRF_URL', plugin_dir_url( __FILE__ ) );

require_once SRF_DIR . 'includes/class-srf-helpers.php';
require_once SRF_DIR . 'includes/class-srf-content.php';
require_once SRF_DIR . 'includes/class-srf-activator.php';
require_once SRF_DIR . 'includes/class-srf-frontend.php';
require_once SRF_DIR . 'includes/class-srf-studies.php';
require_once SRF_DIR . 'includes/class-srf-admin.php';
require_once SRF_DIR . 'includes/class-srf-privacy.php';
require_once SRF_DIR . 'includes/class-srf-seo.php';
require_once SRF_DIR . 'includes/class-srf-plugin.php';

register_activation_hook( SRF_FILE, array( 'SRF_Activator', 'activate' ) );
register_deactivation_hook( SRF_FILE, array( 'SRF_Activator', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		load_plugin_textdomain( 'radar-foundation', false, dirname( plugin_basename( SRF_FILE ) ) . '/languages' );
		( new SRF_Plugin() )->run();
	},
	90
);
