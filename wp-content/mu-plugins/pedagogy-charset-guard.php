<?php
/**
 * Plugin Name: Pedagogy Charset Guard
 * Description: Enforce UTF-8/utf8mb4 settings consistently across migrated environments.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure WordPress advertises UTF-8 charset for frontend and editor contexts.
 */
add_filter(
	'pre_option_blog_charset',
	static function () {
		return 'UTF-8';
	}
);

/**
 * Force utf8mb4 connection charset/collation to avoid host-default downgrades.
 */
add_action(
	'init',
	static function () {
		global $wpdb;

		if ( ! isset( $wpdb ) || ! ( $wpdb instanceof wpdb ) || empty( $wpdb->dbh ) ) {
			return;
		}

		$target_collation = 'utf8mb4_unicode_520_ci';
		$collation_exists = $wpdb->get_var( "SHOW COLLATION LIKE 'utf8mb4_unicode_520_ci'" );
		if ( empty( $collation_exists ) ) {
			$target_collation = 'utf8mb4_unicode_ci';
		}

		if ( method_exists( $wpdb, 'set_charset' ) ) {
			$wpdb->set_charset( $wpdb->dbh, 'utf8mb4', $target_collation );
		}

		$wpdb->query( 'SET NAMES utf8mb4 COLLATE ' . $target_collation );
	},
	1
);
