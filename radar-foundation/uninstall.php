<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$pages = (array) get_option( 'srf_page_map', array() );
foreach ( $pages as $page_id ) {
	$page_id = absint( $page_id );
	if ( ! $page_id || ! get_post_meta( $page_id, '_srf_managed_page', true ) ) {
		continue;
	}
	wp_update_post( array( 'ID' => $page_id, 'post_status' => 'draft' ) );
}

delete_option( 'srf_version' );
delete_option( 'srf_schema_version' );
delete_option( 'srf_page_map' );

// Public Radar entries, private study records, audit records, and managed pages are
// preserved to prevent unintended knowledge or research loss. Plugin-managed pages
// are moved to Draft so raw shortcodes are not exposed after removal.
