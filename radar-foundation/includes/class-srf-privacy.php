<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Privacy {
	public function hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'admin_init', array( $this, 'policy' ) );
	}

	public function exporters( $exporters ) {
		$exporters['radar-foundation'] = array( 'exporter_friendly_name' => 'Radar Saved Studies', 'callback' => array( $this, 'export' ) );
		return $exporters;
	}

	public function erasers( $erasers ) {
		$erasers['radar-foundation'] = array( 'eraser_friendly_name' => 'Radar Saved Studies', 'callback' => array( $this, 'erase' ) );
		return $erasers;
	}

	public function export( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'data' => array(), 'done' => true );
		}
		global $wpdb;
		$limit  = 50;
		$offset = ( max( 1, absint( $page ) ) - 1 ) * $limit;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . SRF_Helpers::table() . ' WHERE user_id = %d ORDER BY id ASC LIMIT %d OFFSET %d', $user->ID, $limit, $offset ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$data   = array();
		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => 'radar-saved-studies',
				'group_label' => 'Radar Saved Studies',
				'item_id'     => 'radar-study-' . absint( $row->id ),
				'data'        => array(
					array( 'name' => 'Title', 'value' => $row->title ),
					array( 'name' => 'Radar Entry IDs', 'value' => $row->entry_ids ),
					array( 'name' => 'Private Notes', 'value' => $row->notes ),
					array( 'name' => 'Created', 'value' => $row->created_at ),
					array( 'name' => 'Updated', 'value' => $row->updated_at ),
				),
			);
		}
		return array( 'data' => $data, 'done' => count( $rows ) < $limit );
	}

	public function erase( $email, $page = 1 ) {
		$user = get_user_by( 'email', $email );
		if ( ! $user ) {
			return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
		}
		global $wpdb;
		$removed = false !== $wpdb->delete( SRF_Helpers::table(), array( 'user_id' => $user->ID ), array( '%d' ) );
		return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
	}

	public function policy() {
		if ( function_exists( 'wp_add_privacy_policy_content' ) ) {
			wp_add_privacy_policy_content( 'Radar Foundation', '<p>Verified doctors may save private Radar study titles, selected public entry identifiers, and private research notes. Doctors are instructed not to include patient names, phone numbers, addresses, record numbers, or other identifying information. Saved studies are private, excluded from indexing and caching, removed when the owning account is deleted, and can be exported or erased through WordPress privacy tools.</p>' );
		}
	}
}
