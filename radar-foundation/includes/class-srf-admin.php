<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Admin {
	private $saving = false;

	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'save_post_' . SRF_Helpers::TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_notices', array( $this, 'notices' ) );
		add_filter( 'manage_' . SRF_Helpers::TYPE . '_posts_columns', array( $this, 'columns' ) );
		add_action( 'manage_' . SRF_Helpers::TYPE . '_posts_custom_column', array( $this, 'column' ), 10, 2 );
	}

	public function meta_box() {
		add_meta_box( 'srf_details', 'Structured Radar Information', array( $this, 'render_meta' ), SRF_Helpers::TYPE, 'normal', 'high' );
	}

	public function render_meta( $post ) {
		wp_nonce_field( 'srf_save_entry', 'srf_entry_nonce' );
		echo '<p>Published entries require a reference source and source license. Use only original, licensed, or public-domain material.</p>';
		echo '<p><label><input type="checkbox" name="srf_confirm[english]" value="1" ' . checked( get_post_meta( $post->ID, '_srf_english_confirm', true ), '1', false ) . '> I confirm that this entry is written completely in American English.</label></p>';
		echo '<p><label><input type="checkbox" name="srf_confirm[medical]" value="1" ' . checked( get_post_meta( $post->ID, '_srf_medical_confirm', true ), '1', false ) . '> I confirm that this entry is educational, avoids personal prescriptions and cure guarantees, and does not delay emergency or qualified medical care.</label></p><table class="form-table" role="presentation">';
		foreach ( SRF_Helpers::fields() as $key => $label ) {
			$value = SRF_Helpers::meta( $post->ID, $key );
			$type  = 'reviewed_date' === $key ? 'date' : 'text';
			echo '<tr><th><label for="srf_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td><input class="widefat" id="srf_' . esc_attr( $key ) . '" name="srf_fields[' . esc_attr( $key ) . ']" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '"></td></tr>';
		}
		echo '</table>';
	}

	public function save_meta( $post_id, $post ) {
		if ( $this->saving || wp_is_post_revision( $post_id ) || ! isset( $_POST['srf_entry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['srf_entry_nonce'] ) ), 'srf_save_entry' ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		$input = isset( $_POST['srf_fields'] ) && is_array( $_POST['srf_fields'] ) ? wp_unslash( $_POST['srf_fields'] ) : array();
		$confirm = isset( $_POST['srf_confirm'] ) && is_array( $_POST['srf_confirm'] ) ? wp_unslash( $_POST['srf_confirm'] ) : array();
		$english = empty( $confirm['english'] ) ? '0' : '1';
		$medical = empty( $confirm['medical'] ) ? '0' : '1';
		update_post_meta( $post_id, '_srf_english_confirm', $english );
		update_post_meta( $post_id, '_srf_medical_confirm', $medical );
		foreach ( SRF_Helpers::fields() as $key => $label ) {
			$value = isset( $input[ $key ] ) ? sanitize_text_field( $input[ $key ] ) : '';
			update_post_meta( $post_id, SRF_Helpers::meta_key( $key ), $value );
		}
		$source  = isset( $input['reference_source'] ) ? trim( sanitize_text_field( $input['reference_source'] ) ) : '';
		$license = isset( $input['source_license'] ) ? trim( sanitize_text_field( $input['source_license'] ) ) : '';
		$text = $post->post_title . ' ' . wp_strip_all_tags( $post->post_content ) . ' ' . implode( ' ', array_map( 'sanitize_text_field', $input ) );
		$non_latin = (bool) preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{0900}-\x{097F}\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}]/u', $text );
		$founder_id = absint( get_option( 'spf_founder_user_id', 0 ) );
		$can_publish_directly = current_user_can( 'manage_options' ) || ( $founder_id && get_current_user_id() === $founder_id );
		if ( 'publish' === $post->post_status && ( ! $source || ! $license || '1' !== $english || '1' !== $medical || $non_latin || ! $can_publish_directly ) ) {
			$this->saving = true;
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );
			$this->saving = false;
			set_transient( 'srf_publication_requirements_' . get_current_user_id(), '1', 60 );
		}
	}

	public function menu() {
		add_submenu_page( 'edit.php?post_type=' . SRF_Helpers::TYPE, 'Radar Management', 'Radar Management', 'edit_posts', 'srf-management', array( $this, 'management' ) );
	}

	public function management() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}
		$counts = wp_count_posts( SRF_Helpers::TYPE );
		?>
		<div class="wrap"><h1>Radar Management</h1><p>Manage educational symptom and remedy relationships with explicit source and license records.</p><ul><li><strong>Published:</strong> <?php echo absint( $counts->publish ); ?></li><li><strong>Pending review:</strong> <?php echo absint( $counts->pending ); ?></li><li><strong>Draft:</strong> <?php echo absint( $counts->draft ); ?></li></ul><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . SRF_Helpers::TYPE ) ); ?>">Add Radar Entry</a> <a class="button" href="<?php echo esc_url( SRF_Helpers::page_url( 'radar' ) ); ?>" target="_blank" rel="noopener">View Public Radar</a></p><p><strong>Publication rule:</strong> Do not copy copyrighted repertory text. Publish only original, licensed, or public-domain material with a recorded source and license.</p></div>
		<?php
	}

	public function notices() {
		if ( get_transient( 'srf_activation_notice' ) && current_user_can( 'manage_options' ) ) {
			delete_transient( 'srf_activation_notice' );
			echo '<div class="notice notice-success is-dismissible"><p><strong>Radar Foundation activated.</strong> The Radar page and private Saved Studies page are ready.</p></div>';
		}
		$key = 'srf_publication_requirements_' . get_current_user_id();
		if ( get_transient( $key ) ) {
			delete_transient( $key );
			echo '<div class="notice notice-warning"><p>The Radar entry was moved to Pending Review. Publication requires Founder or administrator approval, American English, both confirmations, a Reference Source, and a Source License.</p></div>';
		}
	}

	public function columns( $columns ) {
		$columns['srf_source']   = 'Reference Source';
		$columns['srf_reviewed'] = 'Last Reviewed';
		return $columns;
	}

	public function column( $column, $post_id ) {
		if ( 'srf_source' === $column ) {
			echo esc_html( SRF_Helpers::meta( $post_id, 'reference_source', 'Not supplied' ) );
		}
		if ( 'srf_reviewed' === $column ) {
			echo esc_html( SRF_Helpers::meta( $post_id, 'reviewed_date', 'Not reviewed' ) );
		}
	}
}
