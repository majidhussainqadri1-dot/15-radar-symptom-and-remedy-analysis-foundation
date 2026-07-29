<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Studies {
	public function hooks() {
		add_action( 'admin_post_srf_save_study', array( $this, 'save' ) );
		add_action( 'admin_post_srf_delete_study', array( $this, 'delete' ) );
	}

	public function save() {
		if ( ! is_user_logged_in() || ! SRF_Helpers::is_verified_doctor() ) {
			wp_die( esc_html__( 'Verified doctor access is required.', 'radar-foundation' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'srf_save_study', 'srf_nonce' );
		$title   = isset( $_POST['study_title'] ) ? $this->limit( sanitize_text_field( wp_unslash( $_POST['study_title'] ) ), 180 ) : '';
		$notes   = isset( $_POST['study_notes'] ) ? $this->limit( sanitize_textarea_field( wp_unslash( $_POST['study_notes'] ) ), 3000 ) : '';
		$confirm = ! empty( $_POST['no_patient_identity'] );
		$ids     = isset( $_POST['entry_ids'] ) ? array_slice( array_values( array_unique( array_filter( array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_POST['entry_ids'] ) ) ) ) ) ) ), 0, 3 ) : array();
		$ids     = array_values( array_filter( $ids, static function ( $id ) { return SRF_Helpers::TYPE === get_post_type( $id ) && 'publish' === get_post_status( $id ); } ) );
		if ( ! $title || ! $ids || ! $confirm ) {
			$this->redirect( 'invalid' );
		}
		global $wpdb;
		$now = current_time( 'mysql', true );
		$ok  = $wpdb->insert(
			SRF_Helpers::table(),
			array( 'user_id' => get_current_user_id(), 'title' => $title, 'entry_ids' => implode( ',', $ids ), 'notes' => $notes, 'created_at' => $now, 'updated_at' => $now ),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);
		$this->redirect( false === $ok ? 'failed' : 'saved' );
	}

	public function delete() {
		if ( ! is_user_logged_in() || ! SRF_Helpers::is_verified_doctor() ) {
			wp_die( esc_html__( 'Verified doctor access is required.', 'radar-foundation' ), '', array( 'response' => 403 ) );
		}
		$id = isset( $_POST['study_id'] ) ? absint( $_POST['study_id'] ) : 0;
		check_admin_referer( 'srf_delete_study_' . $id );
		global $wpdb;
		$wpdb->delete( SRF_Helpers::table(), array( 'id' => $id, 'user_id' => get_current_user_id() ), array( '%d', '%d' ) );
		wp_safe_redirect( add_query_arg( 'radar_notice', 'deleted', SRF_Helpers::page_url( 'studies' ) ) );
		exit;
	}

	private function redirect( $notice ) {
		wp_safe_redirect( add_query_arg( 'radar_notice', sanitize_key( $notice ), SRF_Helpers::page_url( 'studies' ) ) );
		exit;
	}

	private function limit( $text, $length ) {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $length ) : substr( $text, 0, $length );
	}
}
