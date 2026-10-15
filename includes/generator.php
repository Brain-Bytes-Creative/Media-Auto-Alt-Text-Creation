<?php
/**
 * Core logic: turn a file name into readable text and write it to an attachment.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Convert a file name into readable text.
 *
 * "my_photo-of-the-BEACH-1024x768-scaled.jpg" becomes "My photo of the beach" with sentence case.
 *
 * @param string $filename       File name or path, with or without extension.
 * @param string $capitalization One of the keys from maatc_capitalization_styles().
 * @return string
 */
function maatc_text_from_filename( $filename, $capitalization = 'sentence' ) {
	$text = pathinfo( $filename, PATHINFO_FILENAME );

	// Drop suffixes WordPress or editors add: "-scaled", "-rotated", "-e1712345678", "-1024x768".
	$text = preg_replace( '/-(scaled|rotated)$/i', '', $text );
	$text = preg_replace( '/-e\d{10,}$/', '', $text );
	$text = preg_replace( '/-\d+x\d+$/', '', $text );

	// Separators become single spaces.
	$text = preg_replace( '/[\s\-_.+]+/u', ' ', $text );
	$text = trim( $text );

	$text = maatc_apply_capitalization( $text, $capitalization );

	/**
	 * Filter the generated text before it is saved.
	 *
	 * @param string $text     Generated text.
	 * @param string $filename Original file name.
	 */
	return (string) apply_filters( 'maatc_generated_text', $text, $filename );
}

/**
 * Apply a capitalization style, multibyte-safe when mbstring is available.
 *
 * @param string $text  Text.
 * @param string $style Style key.
 * @return string
 */
function maatc_apply_capitalization( $text, $style ) {
	$mb = function_exists( 'mb_convert_case' );

	switch ( $style ) {
		case 'lower':
			return $mb ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
		case 'upper':
			return $mb ? mb_strtoupper( $text, 'UTF-8' ) : strtoupper( $text );
		case 'title':
			return $mb ? mb_convert_case( $text, MB_CASE_TITLE, 'UTF-8' ) : ucwords( strtolower( $text ) );
		case 'sentence':
			$lower = $mb ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
			if ( $mb ) {
				return mb_strtoupper( mb_substr( $lower, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $lower, 1, null, 'UTF-8' );
			}
			return ucfirst( $lower );
		default:
			return $text;
	}
}

/**
 * Write generated text to an image attachment's fields.
 *
 * @param int  $attachment_id Attachment ID.
 * @param bool $overwrite     Replace fields that already have a value.
 * @return bool True if anything was written.
 */
function maatc_apply_to_attachment( $attachment_id, $overwrite = true ) {
	$attachment_id = (int) $attachment_id;
	if ( ! $attachment_id || ! wp_attachment_is_image( $attachment_id ) ) {
		return false;
	}

	$post = get_post( $attachment_id );
	$file = get_attached_file( $attachment_id );
	if ( ! $post || ! $file ) {
		return false;
	}

	$settings = maatc_get_settings();
	$fields   = $settings['fields'];
	if ( empty( $fields ) ) {
		return false;
	}

	// Prefer the original upload name over a "-scaled" copy.
	$original = function_exists( 'wp_get_original_image_path' ) ? wp_get_original_image_path( $attachment_id ) : '';
	$text     = maatc_text_from_filename( $original ? $original : $file, $settings['capitalization'] );
	if ( '' === $text ) {
		return false;
	}

	$update = array();
	foreach ( array( 'post_title', 'post_excerpt', 'post_content' ) as $field ) {
		if ( in_array( $field, $fields, true ) && ( $overwrite || '' === trim( (string) $post->$field ) ) ) {
			$update[ $field ] = $text;
		}
	}

	$changed = false;
	if ( $update ) {
		$update['ID'] = $attachment_id;
		$changed      = (bool) wp_update_post( wp_slash( $update ) );
	}

	if ( in_array( 'alt_text', $fields, true ) ) {
		$current_alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
		if ( $overwrite || '' === trim( $current_alt ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', wp_slash( $text ) );
			$changed = true;
		}
	}

	return $changed;
}

// Fill fields on every new image upload.
add_action( 'add_attachment', function ( $attachment_id ) {
	maatc_apply_to_attachment( $attachment_id, true );
} );

// Clean up the file name before the file is saved.
add_filter( 'wp_handle_upload_prefilter', 'maatc_rename_upload' );

/**
 * Rename an upload to a lowercase, hyphenated, ASCII file name.
 *
 * @param array $file Upload data from $_FILES.
 * @return array
 */
function maatc_rename_upload( $file ) {
	if ( empty( $file['name'] ) ) {
		return $file;
	}

	$mode = maatc_get_settings()['rename_files'];
	if ( 'none' === $mode ) {
		return $file;
	}

	if ( 'images' === $mode ) {
		$check = wp_check_filetype( $file['name'] );
		$type  = $check['type'] ? $check['type'] : ( isset( $file['type'] ) ? $file['type'] : '' );
		if ( 0 !== strpos( $type, 'image/' ) ) {
			return $file;
		}
	}

	$file['name'] = maatc_clean_filename( $file['name'] );
	return $file;
}

/**
 * Lowercase, transliterate accents, and hyphenate a file name, keeping its extension.
 *
 * @param string $name File name.
 * @return string
 */
function maatc_clean_filename( $name ) {
	$info      = pathinfo( $name );
	$base      = isset( $info['filename'] ) ? $info['filename'] : '';
	$extension = isset( $info['extension'] ) ? '.' . strtolower( $info['extension'] ) : '';

	$base = strtolower( remove_accents( $base ) );
	$base = preg_replace( '/[^a-z0-9]+/', '-', $base );
	$base = trim( $base, '-' );

	if ( '' === $base ) {
		$base = 'file';
	}

	return $base . $extension;
}
