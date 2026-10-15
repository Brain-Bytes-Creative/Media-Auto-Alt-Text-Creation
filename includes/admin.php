<?php
/**
 * Admin UI for existing images: a "Generate" button in the media modal and
 * edit screen, a Media Library list column, a bulk action, and the AJAX handler.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue the admin script with its settings.
 */
function maatc_enqueue_admin_script() {
	if ( wp_script_is( 'maatc-admin', 'enqueued' ) ) {
		return;
	}

	wp_enqueue_script( 'maatc-admin', MAATC_URL . 'assets/admin.js', array( 'jquery' ), MAATC_VERSION, true );
	wp_localize_script(
		'maatc-admin',
		'maatcAdmin',
		array(
			'ajaxUrl' => admin_url( 'admin-ajax.php' ),
			'nonce'   => wp_create_nonce( 'maatc_generate' ),
			'i18n'    => array(
				'working' => __( 'Generating…', 'media-auto-alt-text-creation' ),
				'done'    => __( 'Updated', 'media-auto-alt-text-creation' ),
				'nothing' => __( 'Nothing to change', 'media-auto-alt-text-creation' ),
				'error'   => __( 'Could not update this image.', 'media-auto-alt-text-creation' ),
			),
		)
	);
}

// The media modal can open on any admin screen that loads it.
add_action( 'wp_enqueue_media', function () {
	if ( is_admin() ) {
		maatc_enqueue_admin_script();
	}
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( 'upload.php' === $hook ) {
		maatc_enqueue_admin_script();
		return;
	}
	if ( 'post.php' === $hook && 'attachment' === get_post_type() ) {
		maatc_enqueue_admin_script();
	}
} );

/**
 * Markup for the Generate button.
 *
 * @param int $attachment_id Attachment ID.
 * @return string
 */
function maatc_button_html( $attachment_id ) {
	return sprintf(
		'<button type="button" class="button maatc-generate" data-id="%1$d">%2$s</button> <span class="maatc-status" aria-live="polite"></span>',
		(int) $attachment_id,
		esc_html__( 'Generate title & alt text', 'media-auto-alt-text-creation' )
	);
}

// Button in the media modal's Attachment Details and on the attachment edit screen.
add_filter( 'attachment_fields_to_edit', function ( $form_fields, $post ) {
	if ( ! wp_attachment_is_image( $post->ID ) || ! current_user_can( 'edit_post', $post->ID ) ) {
		return $form_fields;
	}

	$form_fields['maatc_generate'] = array(
		'label' => __( 'Auto alt text', 'media-auto-alt-text-creation' ),
		'input' => 'html',
		'html'  => maatc_button_html( $post->ID ),
		'helps' => sprintf(
			/* translators: %s: settings page URL. */
			__( 'Builds the text from the file name using the <a href="%s">plugin settings</a>.', 'media-auto-alt-text-creation' ),
			esc_url( admin_url( 'options-general.php?page=' . MAATC_SETTINGS_PAGE ) )
		),
	);

	return $form_fields;
}, 10, 2 );

// Media Library list view: show the current alt text with a Generate button.
add_filter( 'manage_media_columns', function ( $columns ) {
	$columns['maatc_alt'] = __( 'Alt text', 'media-auto-alt-text-creation' );
	return $columns;
} );

add_action( 'manage_media_custom_column', function ( $column, $attachment_id ) {
	if ( 'maatc_alt' !== $column || ! wp_attachment_is_image( $attachment_id ) ) {
		return;
	}

	$alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
	printf(
		'<div class="maatc-alt-value">%s</div>',
		'' === $alt ? '<em>' . esc_html__( '(none)', 'media-auto-alt-text-creation' ) . '</em>' : esc_html( $alt )
	);

	if ( current_user_can( 'edit_post', $attachment_id ) ) {
		echo '<p>' . maatc_button_html( $attachment_id ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in maatc_button_html().
	}
}, 10, 2 );

// Bulk action in the Media Library list view.
add_filter( 'bulk_actions-upload', function ( $actions ) {
	$actions['maatc_generate'] = __( 'Generate title & alt text', 'media-auto-alt-text-creation' );
	return $actions;
} );

add_filter( 'handle_bulk_actions-upload', function ( $redirect, $action, $ids ) {
	if ( 'maatc_generate' !== $action ) {
		return $redirect;
	}

	$overwrite = maatc_get_settings()['overwrite'];
	$updated   = 0;
	foreach ( (array) $ids as $id ) {
		if ( current_user_can( 'edit_post', $id ) && maatc_apply_to_attachment( $id, $overwrite ) ) {
			$updated++;
		}
	}

	return add_query_arg( 'maatc_updated', $updated, $redirect );
}, 10, 3 );

add_action( 'admin_notices', function () {
	if ( ! isset( $_GET['maatc_updated'] ) || 'upload' !== get_current_screen()->id ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		return;
	}

	$count = absint( $_GET['maatc_updated'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	printf(
		'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
		esc_html(
			sprintf(
				/* translators: %d: number of images. */
				_n( 'Generated title and alt text for %d image.', 'Generated title and alt text for %d images.', $count, 'media-auto-alt-text-creation' ),
				$count
			)
		)
	);
} );

// AJAX: regenerate one image's fields and return the new values.
add_action( 'wp_ajax_maatc_generate', function () {
	check_ajax_referer( 'maatc_generate', 'nonce' );

	$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
	if ( ! $id || ! wp_attachment_is_image( $id ) ) {
		wp_send_json_error( __( 'That is not an image.', 'media-auto-alt-text-creation' ), 400 );
	}
	if ( ! current_user_can( 'edit_post', $id ) ) {
		wp_send_json_error( __( 'You are not allowed to edit this image.', 'media-auto-alt-text-creation' ), 403 );
	}

	$changed = maatc_apply_to_attachment( $id, maatc_get_settings()['overwrite'] );
	$post    = get_post( $id );

	wp_send_json_success(
		array(
			'changed'     => $changed,
			'title'       => $post->post_title,
			'alt'         => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
			'caption'     => $post->post_excerpt,
			'description' => $post->post_content,
		)
	);
} );
