<?php

defined( 'ABSPATH' ) || exit;

final class SRF_Plugin {
	public function run() {
		add_action( 'init', array( 'SRF_Content', 'register' ) );
		( new SRF_Frontend() )->hooks();
		( new SRF_Studies() )->hooks();
		( new SRF_Admin() )->hooks();
		( new SRF_Privacy() )->hooks();
		( new SRF_SEO() )->hooks();
	}
}
