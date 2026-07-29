<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Admin {
	private $saving    = false;
	private $enforcing = false;

	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'meta_box' ) );
		add_action( 'save_post_' . SRF_Helpers::TYPE, array( $this, 'save_meta' ), 10, 2 );
		add_action( 'wp_after_insert_post', array( $this, 'after_insert' ), 99, 4 );
		add_action( 'rest_after_insert_' . SRF_Helpers::TYPE, array( $this, 'after_rest_insert' ), 99, 3 );
		add_action( 'transition_post_status', array( $this, 'transition' ), 99, 3 );
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
		echo '<p>Published entries require a reference source, an approved source license, and a valid review date. Use only original, licensed, or public-domain material.</p>';
		echo '<p><label><input type="checkbox" name="srf_confirm[english]" value="1" ' . checked( get_post_meta( $post->ID, '_srf_english_confirm', true ), '1', false ) . '> I confirm that this entry is written completely in American English.</label></p>';
		echo '<p><label><input type="checkbox" name="srf_confirm[medical]" value="1" ' . checked( get_post_meta( $post->ID, '_srf_medical_confirm', true ), '1', false ) . '> I confirm that this entry is educational, avoids personal prescriptions and cure guarantees, and does not delay emergency or qualified medical care.</label></p><table class="form-table" role="presentation">';
		foreach ( SRF_Helpers::fields() as $key => $label ) {
			$value = SRF_Helpers::meta( $post->ID, $key );
			echo '<tr><th><label for="srf_' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th><td>';
			if ( 'source_license' === $key ) {
				echo '<select class="widefat" id="srf_source_license" name="srf_fields[source_license]"><option value="">Choose a permitted license</option>';
				foreach ( SRF_Helpers::licenses() as $license ) {
					echo '<option value="' . esc_attr( $license ) . '" ' . selected( $value, $license, false ) . '>' . esc_html( $license ) . '</option>';
				}
				echo '</select>';
			} else {
				$type = 'reviewed_date' === $key ? 'date' : 'text';
				echo '<input class="widefat" id="srf_' . esc_attr( $key ) . '" name="srf_fields[' . esc_attr( $key ) . ']" type="' . esc_attr( $type ) . '" value="' . esc_attr( $value ) . '">';
			}
			echo '</td></tr>';
		}
		echo '</table>';
	}

	public function save_meta( $post_id, $post ) {
		if ( $this->saving || $this->enforcing || wp_is_post_revision( $post_id ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! isset( $_POST['srf_entry_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['srf_entry_nonce'] ) ), 'srf_save_entry' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input   = isset( $_POST['srf_fields'] ) && is_array( $_POST['srf_fields'] ) ? wp_unslash( $_POST['srf_fields'] ) : array();
		$confirm = isset( $_POST['srf_confirm'] ) && is_array( $_POST['srf_confirm'] ) ? wp_unslash( $_POST['srf_confirm'] ) : array();
		$changed = array();

		foreach ( SRF_Helpers::fields() as $key => $label ) {
			$raw = isset( $input[ $key ] ) ? $input[ $key ] : '';
			if ( 'source_license' === $key ) {
				$value = SRF_Content::sanitize_license( $raw );
			} elseif ( 'reviewed_date' === $key ) {
				$value = SRF_Content::sanitize_reviewed_date( $raw );
			} else {
				$value = sanitize_text_field( $raw );
			}
			$meta_key = SRF_Helpers::meta_key( $key );
			$old      = (string) get_post_meta( $post_id, $meta_key, true );
			if ( $old !== $value ) {
				$changed[] = $key;
			}
			update_post_meta( $post_id, $meta_key, $value );
		}

		foreach ( array( 'english', 'medical' ) as $confirmation ) {
			$value    = empty( $confirm[ $confirmation ] ) ? '0' : '1';
			$meta_key = '_srf_' . $confirmation . '_confirm';
			if ( (string) get_post_meta( $post_id, $meta_key, true ) !== $value ) {
				$changed[] = $confirmation . '_confirmation';
			}
			update_post_meta( $post_id, $meta_key, $value );
		}

		if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) && SRF_Helpers::can_publish() ) {
			update_post_meta( $post_id, '_srf_approved_by', get_current_user_id() );
			update_post_meta( $post_id, '_srf_approved_at', current_time( 'mysql', true ) );
		}

		if ( $changed ) {
			SRF_Helpers::audit( $post_id, 'metadata_updated', array( 'fields' => $changed ), $post->post_status, $post->post_status );
		}
		$this->enforce_publication( $post_id, get_post( $post_id ) );
	}

	public function after_insert( $post_id, $post, $update, $post_before ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return;
		}
		if ( $post instanceof WP_Post && SRF_Helpers::TYPE === $post->post_type ) {
			$this->enforce_publication( $post_id, $post );
		}
	}

	public function after_rest_insert( $post, $request, $creating ) {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) && SRF_Helpers::can_publish() ) {
			update_post_meta( $post->ID, '_srf_approved_by', get_current_user_id() );
			update_post_meta( $post->ID, '_srf_approved_at', current_time( 'mysql', true ) );
		}
		$this->enforce_publication( $post->ID, get_post( $post->ID ) );
	}

	public function transition( $new_status, $old_status, $post ) {
		if ( $this->enforcing || ! $post instanceof WP_Post || SRF_Helpers::TYPE !== $post->post_type || $new_status === $old_status ) {
			return;
		}
		SRF_Helpers::audit( $post->ID, 'status_transition', array(), $old_status, $new_status );
		// Ordinary and REST saves are validated after all metadata has been persisted.
		// This transition-level gate is reserved for the cron promotion of an already
		// approved scheduled entry from future to publish.
		if ( 'future' === $old_status && 'publish' === $new_status ) {
			$this->enforce_publication( $post->ID, $post );
		}
	}

	private function enforce_publication( $post_id, $post ) {
		if ( $this->enforcing || ! $post instanceof WP_Post || SRF_Helpers::TYPE !== $post->post_type || ! in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
			return;
		}

		$reasons = $this->publication_failures( $post );
		if ( ! $reasons ) {
			return;
		}

		$this->enforcing = true;
		delete_post_meta( $post_id, '_srf_approved_by' );
		delete_post_meta( $post_id, '_srf_approved_at' );
		wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );
		$this->enforcing = false;
		SRF_Helpers::audit( $post_id, 'publication_blocked', array( 'reasons' => $reasons ), $post->post_status, 'pending' );
		if ( get_current_user_id() ) {
			set_transient( 'srf_publication_requirements_' . get_current_user_id(), implode( '|', $reasons ), 90 );
		}
	}

	private function publication_failures( $post ) {
		$failures = array();
		$source   = trim( (string) SRF_Helpers::meta( $post->ID, 'reference_source' ) );
		$license  = (string) SRF_Helpers::meta( $post->ID, 'source_license' );
		$reviewed = (string) SRF_Helpers::meta( $post->ID, 'reviewed_date' );
		if ( strlen( $source ) < 5 ) {
			$failures[] = 'reference_source';
		}
		if ( ! in_array( $license, SRF_Helpers::licenses(), true ) ) {
			$failures[] = 'source_license';
		}
		if ( $reviewed !== SRF_Content::sanitize_reviewed_date( $reviewed ) || ! $reviewed ) {
			$failures[] = 'reviewed_date';
		}
		if ( '1' !== get_post_meta( $post->ID, '_srf_english_confirm', true ) ) {
			$failures[] = 'english_confirmation';
		}
		if ( '1' !== get_post_meta( $post->ID, '_srf_medical_confirm', true ) ) {
			$failures[] = 'medical_confirmation';
		}

		$text = $post->post_title . ' ' . wp_strip_all_tags( $post->post_content );
		foreach ( SRF_Helpers::fields() as $key => $label ) {
			$text .= ' ' . SRF_Helpers::meta( $post->ID, $key );
		}
		if ( preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}\x{0900}-\x{097F}\x{0400}-\x{04FF}\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}]/u', $text ) ) {
			$failures[] = 'american_english';
		}

		$approved_by = absint( get_post_meta( $post->ID, '_srf_approved_by', true ) );
		$author_can_publish = SRF_Helpers::can_publish( $post->post_author );
		if ( ! $author_can_publish && ( ! $approved_by || ! SRF_Helpers::can_publish( $approved_by ) ) ) {
			$failures[] = 'authorized_approval';
		}
		return array_values( array_unique( $failures ) );
	}

	public function menu() {
		add_submenu_page( 'edit.php?post_type=' . SRF_Helpers::TYPE, 'Radar Management', 'Radar Management', 'edit_srf_radar_entries', 'srf-management', array( $this, 'management' ) );
	}

	public function management() {
		if ( ! current_user_can( 'edit_srf_radar_entries' ) ) {
			wp_die( esc_html__( 'You are not allowed to manage Radar entries.', 'radar-foundation' ), '', array( 'response' => 403 ) );
		}
		$counts = wp_count_posts( SRF_Helpers::TYPE );
		?>
		<div class="wrap"><h1>Radar Management</h1><p>Manage educational symptom and remedy relationships with explicit source, license, review-date, approval, and audit records.</p><ul><li><strong>Published:</strong> <?php echo absint( $counts->publish ); ?></li><li><strong>Pending review:</strong> <?php echo absint( $counts->pending ); ?></li><li><strong>Draft:</strong> <?php echo absint( $counts->draft ); ?></li></ul><p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . SRF_Helpers::TYPE ) ); ?>">Add Radar Entry</a> <a class="button" href="<?php echo esc_url( SRF_Helpers::page_url( 'radar' ) ); ?>" target="_blank" rel="noopener">View Public Radar</a></p><p><strong>Publication rule:</strong> Do not copy copyrighted repertory text. Publish only original, licensed, or public-domain material with a recorded source, approved license, valid review date, confirmations, and authorized approval.</p></div>
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
			echo '<div class="notice notice-warning"><p>The Radar entry was moved to Pending Review because one or more mandatory publication controls failed. Check the reference source, approved license, review date, confirmations, American English requirement, and authorized approval.</p></div>';
		}
	}

	public function columns( $columns ) {
		$columns['srf_source']   = 'Reference Source';
		$columns['srf_license']  = 'Source License';
		$columns['srf_reviewed'] = 'Last Reviewed';
		return $columns;
	}

	public function column( $column, $post_id ) {
		if ( 'srf_source' === $column ) {
			echo esc_html( SRF_Helpers::meta( $post_id, 'reference_source', 'Not supplied' ) );
		} elseif ( 'srf_license' === $column ) {
			echo esc_html( SRF_Helpers::meta( $post_id, 'source_license', 'Not supplied' ) );
		} elseif ( 'srf_reviewed' === $column ) {
			echo esc_html( SRF_Helpers::meta( $post_id, 'reviewed_date', 'Not reviewed' ) );
		}
	}
}
