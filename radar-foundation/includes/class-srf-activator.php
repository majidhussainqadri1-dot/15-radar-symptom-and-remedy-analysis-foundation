<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Activator {
	public static function activate() {
		SRF_Content::register();
		self::table();
		self::categories();
		self::pages();
		update_option( 'srf_version', SRF_VERSION, false );
		set_transient( 'srf_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	public static function deactivate() {
		$pages = SRF_Helpers::pages();
		if ( ! empty( $pages['radar'] ) ) {
			$page = get_post( absint( $pages['radar'] ) );
			if ( $page instanceof WP_Post && '[srf_radar]' === trim( $page->post_content ) ) {
				wp_update_post( array( 'ID' => $page->ID, 'post_content' => '[sabri_platform_module key="radar"]' ) );
			}
		}
		flush_rewrite_rules();
	}

	private static function table() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$table   = SRF_Helpers::table();
		dbDelta( "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			user_id bigint(20) unsigned NOT NULL,
			title varchar(180) NOT NULL,
			entry_ids text NOT NULL,
			notes text NOT NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY user_id (user_id),
			KEY updated_at (updated_at)
		) {$charset};" );
	}

	private static function categories() {
		foreach ( array( 'Mind', 'General Symptoms', 'Particular Symptoms' ) as $name ) {
			if ( ! term_exists( $name, SRF_Helpers::TAX ) ) {
				wp_insert_term( $name, SRF_Helpers::TAX );
			}
		}
	}

	private static function pages() {
		$spf   = (array) get_option( 'spf_page_map', array() );
		$pages = SRF_Helpers::pages();
		$pages['radar'] = self::managed_page(
			! empty( $spf['radar'] ) ? absint( $spf['radar'] ) : ( ! empty( $pages['radar'] ) ? absint( $pages['radar'] ) : 0 ),
			'Radar',
			'homeopathy-radar',
			'[srf_radar]'
		);
		$pages['studies'] = self::managed_page(
			! empty( $pages['studies'] ) ? absint( $pages['studies'] ) : 0,
			'Radar Saved Studies',
			'radar-saved-studies',
			'[srf_radar_saved_studies]'
		);
		update_option( 'srf_page_map', $pages, false );
		$spf['radar'] = $pages['radar'];
		update_option( 'spf_page_map', $spf, false );
	}

	private static function managed_page( $id, $title, $slug, $shortcode ) {
		$page = $id ? get_post( $id ) : get_page_by_path( $slug, OBJECT, 'page' );
		if ( $page instanceof WP_Post ) {
			$managed = get_post_meta( $page->ID, '_spf_managed_page', true ) || get_post_meta( $page->ID, '_srf_managed_page', true );
			if ( $managed || '' === trim( $page->post_content ) || false !== strpos( $page->post_content, '[sabri_platform_module' ) || false !== strpos( $page->post_content, '[srf_' ) ) {
				wp_update_post( array( 'ID' => $page->ID, 'post_content' => $shortcode, 'post_status' => 'publish' ) );
				update_post_meta( $page->ID, '_srf_managed_page', '1' );
			}
			return $page->ID;
		}
		$id = wp_insert_post( array( 'post_title' => $title, 'post_name' => $slug, 'post_content' => $shortcode, 'post_status' => 'publish', 'post_type' => 'page' ), true );
		if ( is_wp_error( $id ) ) {
			return 0;
		}
		update_post_meta( $id, '_srf_managed_page', '1' );
		return absint( $id );
	}
}
