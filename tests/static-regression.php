<?php
/**
 * Static regression gates that can run without a WordPress installation.
 */

$root = dirname( __DIR__ );
$failures = array();

function srf_assert_contains( $path, $needle, $message ) {
	global $failures;
	$content = file_get_contents( $path );
	if ( false === strpos( $content, $needle ) ) {
		$failures[] = $message;
	}
}

function srf_assert_not_contains( $path, $needle, $message ) {
	global $failures;
	$content = file_get_contents( $path );
	if ( false !== strpos( $content, $needle ) ) {
		$failures[] = $message;
	}
}

srf_assert_contains( $root . '/radar-foundation/radar-foundation.php', "Version: 0.1.1", 'Plugin version was not promoted to 0.1.1.' );
srf_assert_contains( $root . '/radar-foundation/includes/class-srf-content.php', "publish_srf_radar_entries", 'Dedicated Radar publishing capability is missing.' );
srf_assert_contains( $root . '/radar-foundation/includes/class-srf-admin.php', "rest_after_insert_", 'REST publication enforcement is missing.' );
srf_assert_contains( $root . '/radar-foundation/includes/class-srf-admin.php', "reviewed_date", 'Review-date publication control is missing.' );
srf_assert_contains( $root . '/radar-foundation/includes/class-srf-frontend.php', "srf_keyword_meta.meta_value LIKE", 'Structured-field keyword search is missing.' );
srf_assert_contains( $root . '/radar-foundation/includes/class-srf-helpers.php', 'has_shortcode( $post->post_content, \'srf_radar_saved_studies\' )', 'Shortcode-aware private-page detection is missing.' );
srf_assert_contains( $root . '/radar-foundation/includes/class-srf-studies.php', "delete_user", 'Saved Studies user-deletion cleanup is missing.' );
srf_assert_not_contains( $root . '/radar-foundation/templates/radar.php', '<main', 'Radar template must not create a nested main landmark.' );
srf_assert_not_contains( $root . '/radar-foundation/templates/saved-studies.php', '<main', 'Saved Studies template must not create a nested main landmark.' );
srf_assert_not_contains( $root . '/radar-foundation/templates/radar.php', 'SRF_Helpers::navigation', 'Radar must not render a duplicate global navigation.' );
srf_assert_not_contains( $root . '/radar-foundation/templates/saved-studies.php', 'SRF_Helpers::navigation', 'Saved Studies must not render a duplicate global navigation.' );
srf_assert_contains( $root . '/radar-foundation/templates/entry-card.php', 'aria-label=', 'Entry selection requires a contextual accessible label.' );
srf_assert_contains( $root . '/radar-foundation/templates/radar.php', 'aria-live="polite"', 'Selection status must be announced to assistive technology.' );
srf_assert_contains( $root . '/radar-foundation/assets/css/radar.css', 'color: var(--srf-navy) !important;', 'Orange buttons must use accessible dark text.' );
srf_assert_contains( $root . '/radar-foundation/uninstall.php', "'post_status' => 'draft'", 'Managed pages must be drafted during uninstall.' );

if ( $failures ) {
	fwrite( STDERR, "Static regression failures:\n- " . implode( "\n- ", $failures ) . "\n" );
	exit( 1 );
}

echo "All static regression gates passed.\n";
