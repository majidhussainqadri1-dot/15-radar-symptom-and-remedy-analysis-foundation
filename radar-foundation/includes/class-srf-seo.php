<?php

defined( 'ABSPATH' ) || exit;

final class SRF_SEO {
	public function hooks() {
		add_action( 'wp_head', array( $this, 'schema' ), 40 );
	}

	public function schema() {
		if ( ! is_singular( SRF_Helpers::TYPE ) ) {
			return;
		}
		$post_id = get_queried_object_id();
		$data = array(
			'@context'      => 'https://schema.org',
			'@type'         => 'DefinedTerm',
			'name'          => get_the_title( $post_id ),
			'description'   => wp_strip_all_tags( get_the_excerpt( $post_id ) ),
			'url'           => get_permalink( $post_id ),
			'inDefinedTermSet' => array(
				'@type' => 'DefinedTermSet',
				'name'  => 'Radar Educational Symptom and Remedy Research',
				'url'   => SRF_Helpers::page_url( 'radar' ),
			),
			'dateModified'  => get_post_modified_time( DATE_W3C, true, $post_id ),
		);
		$source = SRF_Helpers::meta( $post_id, 'reference_source' );
		if ( $source ) {
			$data['citation'] = $source;
		}
		echo '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
