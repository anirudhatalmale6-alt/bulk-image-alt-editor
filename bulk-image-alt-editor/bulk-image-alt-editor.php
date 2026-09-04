<?php
/**
 * Plugin Name:       Bulk Image ALT Editor
 * Description:       Overwrite the ALT text of many Media Library images at once. Type the text, tick exactly the images you want, press Apply. The value you type replaces the existing ALT completely - no prefixes, no suffixes.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.2
 * Author:            Anirudha Talmale
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bulk-image-alt-editor
 * Domain Path:       /languages
 *
 * @package Bulk_Image_Alt_Editor
 */

/*
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation; either version 2 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 */

defined( 'ABSPATH' ) || exit;

define( 'WPBIAE_VERSION', '1.0.0' );
define( 'WPBIAE_FILE', __FILE__ );
define( 'WPBIAE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPBIAE_URL', plugin_dir_url( __FILE__ ) );

/** Menu slug for the Media > Bulk Alt Editor screen. */
define( 'WPBIAE_SLUG', 'bulk-image-alt-editor' );

/** Capability required to reach the screen. Per-image edit rights are checked separately. */
define( 'WPBIAE_CAP', 'upload_files' );

/** The meta key WordPress itself uses for image ALT text. */
define( 'WPBIAE_META_KEY', '_wp_attachment_image_alt' );

require_once WPBIAE_DIR . 'includes/class-wpbiae-updater.php';
require_once WPBIAE_DIR . 'includes/class-wpbiae-query.php';
require_once WPBIAE_DIR . 'includes/class-wpbiae-admin-page.php';
require_once WPBIAE_DIR . 'includes/class-wpbiae-media-bulk-action.php';

/**
 * Boot the admin-only parts of the plugin.
 */
function wpbiae_init() {
	if ( ! is_admin() ) {
		return;
	}

	WPBIAE_Admin_Page::instance()->hooks();
	WPBIAE_Media_Bulk_Action::instance()->hooks();
}
add_action( 'plugins_loaded', 'wpbiae_init' );

/**
 * Load translations.
 */
function wpbiae_load_textdomain() {
	load_plugin_textdomain( 'bulk-image-alt-editor', false, dirname( plugin_basename( WPBIAE_FILE ) ) . '/languages' );
}
add_action( 'init', 'wpbiae_load_textdomain' );

/**
 * Add a "Bulk ALT editor" link to the plugin row on the Plugins screen.
 *
 * @param array $links Existing action links.
 * @return array
 */
function wpbiae_plugin_action_links( $links ) {
	if ( current_user_can( WPBIAE_CAP ) ) {
		$url = admin_url( 'upload.php?page=' . WPBIAE_SLUG );

		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html__( 'Bulk ALT editor', 'bulk-image-alt-editor' ) . '</a>'
		);
	}

	return $links;
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wpbiae_plugin_action_links' );
