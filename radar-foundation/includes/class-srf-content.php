<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Content {
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
				'map_meta_cap'       => true,
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
			)
		);
	}
}
