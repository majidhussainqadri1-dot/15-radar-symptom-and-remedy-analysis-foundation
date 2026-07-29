<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Frontend {
	public function hooks() {
		add_shortcode( 'srf_radar', array( $this, 'radar' ) );
		add_shortcode( 'srf_radar_saved_studies', array( $this, 'saved_studies' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
		add_filter( 'the_content', array( $this, 'single_entry_content' ) );
		add_action( 'template_redirect', array( $this, 'private_headers' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
	}

	public function assets() {
		global $post;
		$pages  = SRF_Helpers::pages();
		$needed = is_singular( SRF_Helpers::TYPE ) || ( $post instanceof WP_Post && ( in_array( $post->ID, array_map( 'absint', $pages ), true ) || has_shortcode( $post->post_content, 'srf_radar' ) || has_shortcode( $post->post_content, 'srf_radar_saved_studies' ) ) );
		if ( ! $needed ) {
			return;
		}
		wp_enqueue_style( 'radar-foundation', SRF_URL . 'assets/css/radar.css', array(), SRF_VERSION );
		wp_enqueue_script( 'radar-foundation', SRF_URL . 'assets/js/radar.js', array(), SRF_VERSION, true );
	}

	public function radar() {
		$filters    = $this->filters();
		$query      = $this->query( $filters );
		$comparison = $this->remedy_comparison_ids();
		return SRF_Helpers::template(
			'radar',
			array(
				'filters'       => $filters,
				'query'         => $query,
				'comparison'    => $comparison,
				'categories'    => get_terms( array( 'taxonomy' => SRF_Helpers::TAX, 'hide_empty' => false ) ),
				'radar_url'     => SRF_Helpers::page_url( 'radar' ),
				'studies_url'   => SRF_Helpers::page_url( 'studies' ),
				'encyclopedia'  => SRF_Helpers::encyclopedia_url(),
				'doctors_url'   => SRF_Helpers::doctors_url(),
				'can_save'      => SRF_Helpers::is_verified_doctor(),
				'remedies'      => $this->remedies(),
			)
		);
	}

	public function saved_studies() {
		if ( ! is_user_logged_in() ) {
			return '<div class="srf-notice"><h1>Log In to View Saved Radar Studies</h1><p>Public Radar research does not require an account. A verified doctor account is required to save private studies.</p><a class="srf-button" href="' . esc_url( wp_login_url( get_permalink() ) ) . '">Log In</a></div>';
		}
		if ( ! SRF_Helpers::is_verified_doctor() ) {
			return '<div class="srf-notice"><h1>Verified Doctor Access Required</h1><p>Private Radar studies are currently available only to verified doctors.</p></div>';
		}
		global $wpdb;
		$items = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . SRF_Helpers::table() . ' WHERE user_id = %d ORDER BY updated_at DESC LIMIT 100', get_current_user_id() ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return SRF_Helpers::template( 'saved-studies', array( 'items' => $items, 'radar_url' => SRF_Helpers::page_url( 'radar' ) ) );
	}

	public function single_entry_content( $content ) {
		if ( ! is_singular( SRF_Helpers::TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$post_id = get_the_ID();
		$details = '<section class="srf-single-details"><h2>Structured Radar Information</h2><dl>';
		foreach ( SRF_Helpers::fields() as $key => $label ) {
			$value = SRF_Helpers::meta( $post_id, $key );
			if ( $value ) {
				$details .= '<div><dt>' . esc_html( $label ) . '</dt><dd>' . esc_html( $value ) . '</dd></div>';
			}
		}
		$details .= '</dl><p class="srf-safety">Radar is an educational research tool. It does not diagnose disease, select treatment, recommend dosage, or replace consultation with a qualified healthcare professional.</p></section>';
		return $content . $details;
	}

	public function private_headers() {
		$pages = SRF_Helpers::pages();
		if ( ! empty( $pages['studies'] ) && is_page( absint( $pages['studies'] ) ) ) {
			nocache_headers();
		}
	}

	public function robots( $robots ) {
		$pages = SRF_Helpers::pages();
		if ( ! empty( $pages['studies'] ) && is_page( absint( $pages['studies'] ) ) ) {
			$robots['noindex']   = true;
			$robots['noarchive'] = true;
		}
		return $robots;
	}

	private function filters() {
		$out = array(
			'keyword'  => isset( $_GET['radar_keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['radar_keyword'] ) ) : '',
			'category' => isset( $_GET['radar_category'] ) ? sanitize_title( wp_unslash( $_GET['radar_category'] ) ) : '',
		);
		foreach ( array( 'body_region', 'location', 'sensation', 'aggravation', 'amelioration', 'concomitants', 'causation', 'time', 'temperature', 'related_remedies' ) as $key ) {
			$out[ $key ] = isset( $_GET[ 'radar_' . $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ 'radar_' . $key ] ) ) : '';
		}
		return $out;
	}

	private function query( $filters ) {
		$args = array(
			'post_type'      => SRF_Helpers::TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 30,
			'orderby'        => 'modified',
			'order'          => 'DESC',
			'paged'          => isset( $_GET['radar_page'] ) ? max( 1, absint( $_GET['radar_page'] ) ) : 1,
		);
		if ( $filters['keyword'] ) {
			$args['s'] = $filters['keyword'];
		}
		if ( $filters['category'] ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => SRF_Helpers::TAX,
					'field'    => 'slug',
					'terms'    => $filters['category'],
				),
			);
		}
		$meta = array( 'relation' => 'AND' );
		foreach ( array( 'body_region', 'location', 'sensation', 'aggravation', 'amelioration', 'concomitants', 'causation', 'time', 'temperature', 'related_remedies' ) as $key ) {
			if ( $filters[ $key ] ) {
				$meta[] = array( 'key' => SRF_Helpers::meta_key( $key ), 'value' => $filters[ $key ], 'compare' => 'LIKE' );
			}
		}
		if ( count( $meta ) > 1 ) {
			$args['meta_query'] = $meta;
		}
		return new WP_Query( $args );
	}

	private function remedy_comparison_ids() {
		$raw = isset( $_GET['radar_remedy_compare'] ) ? (array) wp_unslash( $_GET['radar_remedy_compare'] ) : array();
		$ids = array_slice( array_values( array_unique( array_filter( array_map( 'absint', $raw ) ) ) ), 0, 3 );
		if ( ! class_exists( 'HE_Content' ) ) {
			return array();
		}
		return array_values( array_filter( $ids, static function ( $id ) { return HE_Content::TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ) && 'remedy' === HE_Content::type( $id, 'slug' ); } ) );
	}

	private function remedies() {
		if ( ! class_exists( 'HE_Content' ) ) {
			return array();
		}
		return get_posts(
			array(
				'post_type'      => HE_Content::TYPE,
				'post_status'    => 'publish',
				'posts_per_page' => 250,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'tax_query'      => array( array( 'taxonomy' => HE_Content::TAX, 'field' => 'slug', 'terms' => 'remedy' ) ),
			)
		);
	}
}
