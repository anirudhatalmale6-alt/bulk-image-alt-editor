<?php
/**
 * Uninstall routine.
 *
 * Removes the only two things this plugin ever stores: the per-page screen
 * option, and any not-yet-expired undo snapshots.
 *
 * It does NOT touch _wp_attachment_image_alt. ALT text belongs to WordPress,
 * not to this plugin, so deleting the plugin leaves your images exactly as you
 * last saved them.
 *
 * @package Bulk_Image_Alt_Editor
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Per-user screen option ("Images per page").
delete_metadata( 'user', 0, 'wpbiae_per_page', '', true );

// Undo snapshots, which are transients named wpbiae_undo_<user>_<token>.
$like = $wpdb->esc_like( '_transient_wpbiae_undo_' ) . '%';

$names = $wpdb->get_col(
	$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
);

foreach ( (array) $names as $name ) {
	delete_option( $name );
	delete_option( str_replace( '_transient_', '_transient_timeout_', $name ) );
}

// Multisite: repeat per site.
if ( is_multisite() ) {
	$site_ids = get_sites(
		array(
			'fields'                 => 'ids',
			'number'                 => 0,
			'update_site_meta_cache' => false,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( $site_id );

		$names = $wpdb->get_col(
			$wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like )
		);

		foreach ( (array) $names as $name ) {
			delete_option( $name );
			delete_option( str_replace( '_transient_', '_transient_timeout_', $name ) );
		}

		restore_current_blog();
	}
}
