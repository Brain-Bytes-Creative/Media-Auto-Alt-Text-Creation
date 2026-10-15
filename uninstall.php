<?php
/**
 * Remove plugin settings on uninstall. Titles and alt text already written to images are kept.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'maatc_settings' );
