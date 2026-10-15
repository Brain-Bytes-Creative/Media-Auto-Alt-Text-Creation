<?php
/**
 * Plugin Name:       Media Auto Alt Text Creation
 * Plugin URI:        https://github.com/brainbytescreative1/Media-Auto-Alt-Text-Creation
 * Description:       Automatically sets a clean title and alt text (and optionally caption and description) from the file name when an image is uploaded to the Media Library.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Brain Bytes Creative
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       media-auto-alt-text-creation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MAATC_VERSION', '1.0.0' );
define( 'MAATC_FILE', __FILE__ );
define( 'MAATC_DIR', plugin_dir_path( __FILE__ ) );
define( 'MAATC_URL', plugin_dir_url( __FILE__ ) );
define( 'MAATC_SETTINGS_PAGE', 'media-auto-alt-text-creation' );

require_once MAATC_DIR . 'includes/settings.php';
require_once MAATC_DIR . 'includes/generator.php';
require_once MAATC_DIR . 'includes/admin.php';

// Add a Settings link on the Plugins screen.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( $links ) {
	$url = admin_url( 'options-general.php?page=' . MAATC_SETTINGS_PAGE );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'media-auto-alt-text-creation' ) . '</a>' );
	return $links;
} );
