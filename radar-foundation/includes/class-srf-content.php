<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Content {
	public static function hooks() {
		add_filter( 'user_has_cap', array( __CLASS__, 'dynamic_capabilities' ), 20, 4 );
	}

	public static function register() {
		register_post_type(
			SRF_Helpers::TYPE,
			array(
				'labels' => array(
					'name'          => 'Radar Entries',
					'singular_name' => 'Radar Entry',
					'add_new_item'  => 'Add Radar Entry',
					'edit_item'     => 'Edit Radar Entry',
				),
				'public'             => true,
				'publicly_queryable' => true,
				'show_in_rest'       => true,
				'has_archive'        => false,
				'menu_icon'          => 'dashicons-search',
				'rewrite'            => array( 'slug' => 'radar-entry', 'with_front' => false ),
				'supports'           => array( 'title', 'editor', 'author', 'revisions' ),
				'capability_type'    => array( 'srf_radar_entry', 'srf_radar_entries' ),
				'map_meta_cap'       => true,
				'capabilities'       => array(
					'edit_post'              => 'edit_srf_radar_entry',
					'read_post'              => 'read_srf_radar_entry',
					'delete_post'            => 'delete_srf_radar_entry',
					'edit_posts'             => 'edit_srf_radar_entries',
					'edit_others_posts'      => 'edit_others_srf_radar_entries',
					'publish_posts'          => 'publish_srf_radar_entries',
					'read_private_posts'     => 'read_private_srf_radar_entries',
					'delete_posts'           => 'delete_srf_radar_entries',
					'delete_private_posts'   => 'delete_private_srf_radar_entries',
					'delete_published_posts' => 'delete_published_srf_radar_entries',
					'delete_others_posts'    => 'delete_others_srf_radar_entries',
					'edit_private_posts'     => 'edit_private_srf_radar_entries',
					'edit_published_posts'   => 'edit_published_srf_radar_entries',
					'create_posts'           => 'edit_srf_radar_entries',
				),
			)
		);

		register_taxonomy(
			SRF_Helpers::TAX,
			SRF_Helpers::TYPE,
			array(
				'labels' => array(
					'name'          => 'Radar Categories',
					'singular_name' => 'Radar Category',
				),
				'public'            => true,
				'hierarchical'      => true,
				'show_admin_column' => true,
				'show_in_rest'      => true,
				'rewrite'           => array( 'slug' => 'radar-category' ),
				'capabilities'      => array(
					'manage_terms' => 'manage_srf_radar_categories',
					'edit_terms'   => 'edit_srf_radar_categories',
					'delete_terms' => 'delete_srf_radar_categories',
					'assign_terms' => 'assign_srf_radar_categories',
				),
			)
		);

		self::register_meta();
	}

	private static function register_meta() {
		foreach ( SRF_Helpers::fields() as $field => $label ) {
			$sanitize = 'source_license' === $field ? array( __CLASS__, 'sanitize_license' ) : ( 'reviewed_date' === $field ? array( __CLASS__, 'sanitize_reviewed_date' ) : 'sanitize_text_field' );
			register_post_meta(
				SRF_Helpers::TYPE,
				SRF_Helpers::meta_key( $field ),
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => $sanitize,
					'auth_callback'     => array( __CLASS__, 'authorize_meta' ),
				)
			);
		}
		foreach ( array( '_srf_english_confirm', '_srf_medical_confirm' ) as $key ) {
			register_post_meta(
				SRF_Helpers::TYPE,
				$key,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => array( __CLASS__, 'sanitize_confirmation' ),
					'auth_callback'     => array( __CLASS__, 'authorize_meta' ),
				)
			);
		}
	}

	public static function authorize_meta( $allowed, $meta_key, $post_id, $user_id ) {
		return $user_id && user_can( $user_id, 'edit_post', $post_id );
	}

	public static function sanitize_confirmation( $value ) {
		return empty( $value ) || '0' === (string) $value ? '0' : '1';
	}

	public static function sanitize_license( $value ) {
		$value = sanitize_text_field( $value );
		return in_array( $value, SRF_Helpers::licenses(), true ) ? $value : '';
	}

	public static function sanitize_reviewed_date( $value ) {
		$value = sanitize_text_field( $value );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $matches ) ) {
			return '';
		}
		if ( ! checkdate( (int) $matches[2], (int) $matches[3], (int) $matches[1] ) || $value > gmdate( 'Y-m-d' ) ) {
			return '';
		}
		return $value;
	}

	public static function dynamic_capabilities( $allcaps, $caps, $args, $user ) {
		if ( ! $user instanceof WP_User || ! $user->ID ) {
			return $allcaps;
		}
		$is_founder = SRF_Helpers::is_founder( $user->ID );
		$is_verified = class_exists( 'SDD_Helpers' ) && SDD_Helpers::is_verified( $user->ID );
		if ( $is_founder ) {
			foreach ( SRF_Helpers::capabilities() as $capability ) {
				$allcaps[ $capability ] = true;
			}
		} elseif ( $is_verified ) {
			foreach ( SRF_Helpers::doctor_capabilities() as $capability ) {
				$allcaps[ $capability ] = true;
			}
		}
		return $allcaps;
	}
}
