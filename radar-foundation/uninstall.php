<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'srf_version' );
delete_option( 'srf_page_map' );

// Public Radar entries, managed pages, and private study records are preserved
// to prevent unintended knowledge or research loss during a reinstall.
