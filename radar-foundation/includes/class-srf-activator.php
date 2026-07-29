<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Activator {
	public static function activate() {
		SRF_Content::register();
		self::schema();
		self::capabilities();
		self::categories();
		self::pages();
		update_option( 'srf_version', SRF_VERSION, false );
		update_option( 'srf_schema_version', SRF_SCHEMA_VERSION, false );
		set_transient( 'srf_activation_notice', '1', 120 );
		flush_rewrite_rules();
	}

	public static function maybe_upgrade() {
		if ( SRF_SCHEMA_VERSION === (string) get_option( 'srf_schema_version', '' ) && SRF_VERSION === (string) get_option( 'srf_version', '' ) ) {
			return;
		}
		self::schema();
		self::capabilities();
		self::pages();
		update_option( 'srf_version', SRF_VERSION, false );
		update_option( 'srf_schema_version', SRF_SCHEMA_VERSION, false );
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	private static function schema() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$studies = SRF_Helpers::table();
		$audit   = SRF_Helpers::audit_table();
		dbDelta( "CREATE TABLE {$studies} (
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
		dbDelta( "CREATE TABLE {$audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			post_id bigint(20) unsigned NOT NULL,
			actor_user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			event varchar(60) NOT NULL,
			old_status varchar(20) NOT NULL DEFAULT '',
			new_status varchar(20) NOT NULL DEFAULT '',
			details longtext NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY actor_user_id (actor_user_id),
			KEY created_at (created_at)
		) {$charset};" );
	}

	private static function capabilities() {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( SRF_Helpers::capabilities() as $capability ) {
				$administrator->add_cap( $capability );
			}
		}
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
		if ( $pages['radar'] ) {
			$spf['radar'] = $pages['radar'];
			update_option( 'spf_page_map', $spf, false );
		}
	}

	private static function managed_page( $id, $title, $slug, $shortcode ) {
		$page = $id ? get_post( $id ) : null;
		if ( ! self::is_compatible_page( $page ) ) {
			$page = get_page_by_path( $slug, OBJECT, 'page' );
		}
		if ( self::is_compatible_page( $page ) ) {
			wp_update_post( array( 'ID' => $page->ID, 'post_content' => $shortcode, 'post_status' => 'publish' ) );
			update_post_meta( $page->ID, '_srf_managed_page', '1' );
			return absint( $page->ID );
		}
		$new_id = wp_insert_post(
			array(
				'post_title'   => $title,
				'post_name'    => $slug,
				'post_content' => $shortcode,
				'post_status'  => 'publish',
				'post_type'    => 'page',
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			return 0;
		}
		update_post_meta( $new_id, '_srf_managed_page', '1' );
		return absint( $new_id );
	}

	private static function is_compatible_page( $page ) {
		if ( ! $page instanceof WP_Post || 'page' !== $page->post_type ) {
			return false;
		}
		$managed = get_post_meta( $page->ID, '_srf_managed_page', true ) || get_post_meta( $page->ID, '_spf_managed_page', true );
		$content = trim( (string) $page->post_content );
		return $managed || '' === $content || false !== strpos( $content, '[sabri_platform_module' ) || false !== strpos( $content, '[srf_radar' );
	}
}
