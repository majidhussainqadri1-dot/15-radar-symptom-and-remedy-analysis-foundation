<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Helpers {
	const TYPE = 'srf_radar_entry';
	const TAX  = 'srf_radar_category';

	public static function fields() {
		return array(
			'body_region'      => 'Body Region',
			'location'         => 'Location',
			'sensation'        => 'Sensation',
			'aggravation'      => 'Aggravation',
			'amelioration'     => 'Amelioration',
			'concomitants'     => 'Concomitants',
			'causation'        => 'Causation',
			'time'             => 'Time',
			'temperature'      => 'Temperature',
			'related_remedies' => 'Related Remedies',
			'reference_source' => 'Reference Source',
			'source_license'   => 'Source License',
			'reviewed_date'    => 'Last Reviewed Date',
		);
	}

	public static function searchable_fields() {
		return array( 'body_region', 'location', 'sensation', 'aggravation', 'amelioration', 'concomitants', 'causation', 'time', 'temperature', 'related_remedies', 'reference_source' );
	}

	public static function licenses() {
		return array(
			'Original work',
			'Public domain',
			'CC0 1.0',
			'CC BY 4.0',
			'CC BY-SA 4.0',
			'Other documented license',
		);
	}

	public static function capabilities() {
		return array(
			'edit_srf_radar_entry',
			'read_srf_radar_entry',
			'delete_srf_radar_entry',
			'edit_srf_radar_entries',
			'edit_others_srf_radar_entries',
			'publish_srf_radar_entries',
			'read_private_srf_radar_entries',
			'delete_srf_radar_entries',
			'delete_private_srf_radar_entries',
			'delete_published_srf_radar_entries',
			'delete_others_srf_radar_entries',
			'edit_private_srf_radar_entries',
			'edit_published_srf_radar_entries',
			'manage_srf_radar_categories',
			'edit_srf_radar_categories',
			'delete_srf_radar_categories',
			'assign_srf_radar_categories',
		);
	}

	public static function doctor_capabilities() {
		return array(
			'read',
			'edit_srf_radar_entries',
			'delete_srf_radar_entries',
			'assign_srf_radar_categories',
		);
	}

	public static function meta_key( $field ) {
		return '_srf_' . sanitize_key( $field );
	}

	public static function meta( $post_id, $field, $default = '' ) {
		$value = get_post_meta( absint( $post_id ), self::meta_key( $field ), true );
		return '' === $value ? $default : $value;
	}

	public static function pages() {
		return (array) get_option( 'srf_page_map', array() );
	}

	public static function page_url( $key ) {
		$pages = self::pages();
		$fallbacks = array(
			'radar'   => 'homeopathy-radar',
			'studies' => 'radar-saved-studies',
		);
		$id = ! empty( $pages[ $key ] ) ? absint( $pages[ $key ] ) : 0;
		if ( $id && 'publish' === get_post_status( $id ) ) {
			$url = get_permalink( $id );
			if ( $url ) {
				return $url;
			}
		}
		return home_url( '/' . $fallbacks[ $key ] . '/' );
	}

	public static function encyclopedia_url() {
		$spf = (array) get_option( 'spf_page_map', array() );
		$id  = ! empty( $spf['encyclopedia'] ) ? absint( $spf['encyclopedia'] ) : 0;
		return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/homeopathy-encyclopedia/' );
	}

	public static function doctors_url() {
		$sdd = (array) get_option( 'sdd_page_map', array() );
		$spf = (array) get_option( 'spf_page_map', array() );
		$id  = ! empty( $sdd['directory'] ) ? absint( $sdd['directory'] ) : ( ! empty( $spf['doctors'] ) ? absint( $spf['doctors'] ) : 0 );
		return $id && 'publish' === get_post_status( $id ) ? get_permalink( $id ) : home_url( '/homeopathy-doctors/' );
	}

	public static function is_founder( $user_id ) {
		return absint( $user_id ) && absint( $user_id ) === absint( get_option( 'spf_founder_user_id', 0 ) );
	}

	public static function is_verified_doctor( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) || self::is_founder( $user_id ) ) {
			return true;
		}
		return class_exists( 'SDD_Helpers' ) && SDD_Helpers::is_verified( $user_id );
	}

	public static function can_publish( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		return $user_id && ( user_can( $user_id, 'manage_options' ) || self::is_founder( $user_id ) || user_can( $user_id, 'publish_srf_radar_entries' ) );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'srf_radar_studies';
	}

	public static function audit_table() {
		global $wpdb;
		return $wpdb->prefix . 'srf_radar_audit';
	}

	public static function audit( $post_id, $event, $details = array(), $old_status = '', $new_status = '', $actor_user_id = 0 ) {
		global $wpdb;
		$wpdb->insert(
			self::audit_table(),
			array(
				'post_id'       => absint( $post_id ),
				'actor_user_id' => $actor_user_id ? absint( $actor_user_id ) : get_current_user_id(),
				'event'         => sanitize_key( $event ),
				'old_status'    => sanitize_key( $old_status ),
				'new_status'    => sanitize_key( $new_status ),
				'details'       => wp_json_encode( $details ),
				'created_at'    => current_time( 'mysql', true ),
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	public static function is_saved_studies_request() {
		$pages = self::pages();
		if ( ! empty( $pages['studies'] ) && is_page( absint( $pages['studies'] ) ) ) {
			return true;
		}
		$post = get_queried_object();
		return $post instanceof WP_Post && has_shortcode( $post->post_content, 'srf_radar_saved_studies' );
	}

	public static function template( $name, array $vars = array() ) {
		$allowed = array( 'radar', 'entry-card', 'comparison', 'saved-studies' );
		if ( ! in_array( $name, $allowed, true ) ) {
			return '';
		}
		$path = SRF_DIR . 'templates/' . $name . '.php';
		if ( ! file_exists( $path ) ) {
			return '';
		}
		extract( $vars, EXTR_SKIP );
		ob_start();
		include $path;
		return (string) ob_get_clean();
	}
}
