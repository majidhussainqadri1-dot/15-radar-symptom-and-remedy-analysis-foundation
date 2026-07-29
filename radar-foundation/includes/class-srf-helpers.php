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
		return ! empty( $pages[ $key ] ) ? get_permalink( absint( $pages[ $key ] ) ) : home_url( '/' . $fallbacks[ $key ] . '/' );
	}

	public static function encyclopedia_url() {
		$spf = (array) get_option( 'spf_page_map', array() );
		return ! empty( $spf['encyclopedia'] ) ? get_permalink( absint( $spf['encyclopedia'] ) ) : home_url( '/homeopathy-encyclopedia/' );
	}

	public static function doctors_url() {
		$sdd = (array) get_option( 'sdd_page_map', array() );
		$spf = (array) get_option( 'spf_page_map', array() );
		$id  = ! empty( $sdd['directory'] ) ? absint( $sdd['directory'] ) : ( ! empty( $spf['doctors'] ) ? absint( $spf['doctors'] ) : 0 );
		return $id ? get_permalink( $id ) : home_url( '/homeopathy-doctors/' );
	}

	public static function is_verified_doctor( $user_id = 0 ) {
		$user_id = $user_id ? absint( $user_id ) : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}
		if ( user_can( $user_id, 'manage_options' ) ) {
			return true;
		}
		return class_exists( 'SDD_Helpers' ) && SDD_Helpers::is_verified( $user_id );
	}

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'srf_radar_studies';
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

	public static function navigation() {
		$specs = array(
			'home'         => array( 'Home', 'sabri-platform-home' ),
			'news'         => array( 'News', 'sabri-news' ),
			'founder'      => array( 'Founder', 'sabri-founder' ),
			'learn'        => array( 'Learn Sabri Classical Homeopathy', 'learn-sabri-classical-homeopathy' ),
			'encyclopedia' => array( 'Encyclopedia', 'homeopathy-encyclopedia' ),
			'doctors'      => array( 'Doctors', 'homeopathy-doctors' ),
			'clinic'       => array( 'Worldwide Clinic', 'worldwide-clinic' ),
			'videos'       => array( 'Video Wall', 'video-wall' ),
			'reels'        => array( 'Reels', 'reels' ),
			'pdf'          => array( 'PDF Library', 'pdf-library' ),
			'radar'        => array( 'Radar', 'homeopathy-radar' ),
			'ai'           => array( 'Sabri Classical Homeopathy AI', 'sabri-classical-homeopathy-ai' ),
			'network'      => array( 'Network', 'homeopathy-network' ),
			'marketplace'  => array( 'Marketplace', 'homeopathy-marketplace' ),
		);
		$pages = (array) get_option( 'spf_page_map', array() );
		$out   = '<nav class="srf-main-nav" aria-label="Main platform navigation">';
		foreach ( $specs as $key => $spec ) {
			$url  = ! empty( $pages[ $key ] ) ? get_permalink( absint( $pages[ $key ] ) ) : home_url( '/' . $spec[1] . '/' );
			$out .= '<a class="' . ( 'radar' === $key ? 'is-active' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $spec[0] ) . '</a>';
		}
		return $out . '</nav>';
	}
}
