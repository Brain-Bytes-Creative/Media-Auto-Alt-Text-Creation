<?php
/**
 * Bulk update alt text from a CSV of image URLs.
 *
 * The CSV needs a URL column (e.g. "Address" or "URL") and an alt text column
 * (e.g. "New Alt Text"). URLs are matched by their path inside the uploads
 * folder, so the domain doesn't matter: a CSV exported from staging works on
 * production and vice versa.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Render the CSV import form and the results of the last import.
 */
function maatc_render_csv_import_section() {
	$results = get_transient( 'maatc_csv_results_' . get_current_user_id() );
	if ( false !== $results ) {
		delete_transient( 'maatc_csv_results_' . get_current_user_id() );
	}
	?>
	<hr>
	<h2><?php esc_html_e( 'Bulk update alt text from a CSV', 'media-auto-alt-text-creation' ); ?></h2>
	<p>
		<?php esc_html_e( 'Upload a CSV with one column of image URLs and one column of new alt text, with a header row such as "Address,New Alt Text".', 'media-auto-alt-text-creation' ); ?>
		<?php esc_html_e( 'Images are matched by their path in the uploads folder, so URLs from a staging site or another domain still match.', 'media-auto-alt-text-creation' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
		<input type="hidden" name="action" value="maatc_csv_import">
		<?php wp_nonce_field( 'maatc_csv_import' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="maatc-csv"><?php esc_html_e( 'CSV file', 'media-auto-alt-text-creation' ); ?></label></th>
				<td><input type="file" id="maatc-csv" name="maatc_csv" accept=".csv,text/csv" required></td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Preview', 'media-auto-alt-text-creation' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="maatc_dry_run" value="1">
						<?php esc_html_e( 'Show what would change without saving anything', 'media-auto-alt-text-creation' ); ?>
					</label>
				</td>
			</tr>
		</table>
		<?php submit_button( __( 'Upload CSV and update alt text', 'media-auto-alt-text-creation' ), 'secondary' ); ?>
	</form>

	<?php
	if ( is_array( $results ) ) {
		maatc_render_csv_results( $results );
	}
}

/**
 * Render the results table of an import.
 *
 * @param array $results Results from maatc_import_alt_text_csv().
 */
function maatc_render_csv_results( $results ) {
	if ( ! empty( $results['error'] ) ) {
		printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $results['error'] ) );
		return;
	}

	$labels = array(
		'updated'   => $results['dry_run'] ? __( 'Would update', 'media-auto-alt-text-creation' ) : __( 'Updated', 'media-auto-alt-text-creation' ),
		'unchanged' => __( 'Already set', 'media-auto-alt-text-creation' ),
		'not_found' => __( 'No matching image', 'media-auto-alt-text-creation' ),
		'skipped'   => __( 'Skipped (missing URL or alt text)', 'media-auto-alt-text-creation' ),
	);

	$counts = array_fill_keys( array_keys( $labels ), 0 );
	foreach ( $results['rows'] as $row ) {
		$counts[ $row['status'] ]++;
	}

	$summary = array();
	foreach ( $counts as $status => $count ) {
		if ( $count ) {
			$summary[] = $labels[ $status ] . ': ' . $count;
		}
	}

	$class = $counts['not_found'] ? 'notice-warning' : 'notice-success';
	printf(
		'<div class="notice %1$s inline"><p><strong>%2$s</strong> %3$s</p></div>',
		esc_attr( $class ),
		$results['dry_run'] ? esc_html__( 'Preview only, nothing was saved.', 'media-auto-alt-text-creation' ) : esc_html__( 'Import finished.', 'media-auto-alt-text-creation' ),
		esc_html( implode( ' · ', $summary ) )
	);
	?>
	<table class="widefat striped" style="margin-top:1em">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Row', 'media-auto-alt-text-creation' ); ?></th>
				<th><?php esc_html_e( 'URL', 'media-auto-alt-text-creation' ); ?></th>
				<th><?php esc_html_e( 'Image', 'media-auto-alt-text-creation' ); ?></th>
				<th><?php esc_html_e( 'Old alt text', 'media-auto-alt-text-creation' ); ?></th>
				<th><?php esc_html_e( 'New alt text', 'media-auto-alt-text-creation' ); ?></th>
				<th><?php esc_html_e( 'Result', 'media-auto-alt-text-creation' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $results['rows'] as $row ) : ?>
				<tr>
					<td><?php echo (int) $row['line']; ?></td>
					<td style="word-break:break-all"><?php echo esc_html( $row['url'] ); ?></td>
					<td>
						<?php if ( $row['id'] ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $row['id'] ) ); ?>">#<?php echo (int) $row['id']; ?></a>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $row['old_alt'] ); ?></td>
					<td><?php echo esc_html( $row['alt'] ); ?></td>
					<td><?php echo esc_html( $labels[ $row['status'] ] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php

	if ( $results['dry_run'] && $counts['updated'] && false !== get_transient( maatc_csv_pending_key() ) ) :
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:1em">
			<input type="hidden" name="action" value="maatc_csv_confirm">
			<?php wp_nonce_field( 'maatc_csv_confirm' ); ?>
			<?php
			submit_button(
				sprintf(
					/* translators: %d: number of images. */
					_n( 'Confirm and update %d image', 'Confirm and update %d images', $counts['updated'], 'media-auto-alt-text-creation' ),
					$counts['updated']
				),
				'primary',
				'submit',
				false
			);
			?>
		</form>
		<?php
	endif;
}

/**
 * Transient key for the rows of the current user's last preview, waiting for confirmation.
 *
 * @return string
 */
function maatc_csv_pending_key() {
	return 'maatc_csv_pending_' . get_current_user_id();
}

/**
 * Save the results and return to the settings page.
 *
 * @param array $results Import results.
 */
function maatc_csv_redirect_with_results( $results ) {
	set_transient( 'maatc_csv_results_' . get_current_user_id(), $results, 10 * MINUTE_IN_SECONDS );
	wp_safe_redirect( admin_url( 'options-general.php?page=' . MAATC_SETTINGS_PAGE ) );
	exit;
}

// Handle the upload, then redirect back to the settings page with the results.
add_action( 'admin_post_maatc_csv_import', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'media-auto-alt-text-creation' ), 403 );
	}
	check_admin_referer( 'maatc_csv_import' );

	$dry_run = ! empty( $_POST['maatc_dry_run'] );
	$upload  = isset( $_FILES['maatc_csv'] ) ? $_FILES['maatc_csv'] : null; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only tmp_name is read, as a file path.

	if ( ! $upload || UPLOAD_ERR_OK !== (int) $upload['error'] || ! is_uploaded_file( $upload['tmp_name'] ) ) {
		$results = array( 'error' => __( 'The CSV file did not upload. Please choose a file and try again.', 'media-auto-alt-text-creation' ) );
	} else {
		$results = maatc_import_alt_text_csv( $upload['tmp_name'], $dry_run );
	}

	// Keep a preview's rows so it can be confirmed without uploading the CSV again.
	if ( $dry_run && ! empty( $results['rows'] ) ) {
		$pending = array();
		foreach ( $results['rows'] as $row ) {
			$pending[] = array(
				'line' => $row['line'],
				'url'  => $row['url'],
				'alt'  => $row['alt'],
			);
		}
		set_transient( maatc_csv_pending_key(), $pending, HOUR_IN_SECONDS );
	} else {
		delete_transient( maatc_csv_pending_key() );
	}

	maatc_csv_redirect_with_results( $results );
} );

// Apply the last preview for real.
add_action( 'admin_post_maatc_csv_confirm', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'media-auto-alt-text-creation' ), 403 );
	}
	check_admin_referer( 'maatc_csv_confirm' );

	$pending = get_transient( maatc_csv_pending_key() );
	delete_transient( maatc_csv_pending_key() );

	if ( ! is_array( $pending ) ) {
		maatc_csv_redirect_with_results( array( 'error' => __( 'That preview has expired. Please upload the CSV again.', 'media-auto-alt-text-creation' ) ) );
	}

	// Images are matched again, so the update reflects the Media Library as it is now.
	$rows = array();
	foreach ( $pending as $row ) {
		$rows[] = maatc_import_csv_row( array( $row['url'], $row['alt'] ), 0, 1, $row['line'], false );
	}

	maatc_csv_redirect_with_results(
		array(
			'dry_run' => false,
			'rows'    => $rows,
		)
	);
} );

/**
 * Read a CSV and update the alt text of each matching image.
 *
 * @param string $path    Path to the CSV file.
 * @param bool   $dry_run Report what would change without saving.
 * @return array { dry_run: bool, rows: array } or { error: string }.
 */
function maatc_import_alt_text_csv( $path, $dry_run = false ) {
	$handle = fopen( $path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
	if ( ! $handle ) {
		return array( 'error' => __( 'The CSV file could not be read.', 'media-auto-alt-text-creation' ) );
	}

	$header = fgetcsv( $handle, 0, ',', '"', '\\' );
	if ( ! is_array( $header ) ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return array( 'error' => __( 'The CSV file is empty.', 'media-auto-alt-text-creation' ) );
	}

	list( $url_col, $alt_col, $has_header ) = maatc_csv_columns( $header );
	if ( null === $url_col || null === $alt_col ) {
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return array( 'error' => __( 'Could not find a URL column and an alt text column. Use a header row such as "Address,New Alt Text".', 'media-auto-alt-text-creation' ) );
	}

	$rows = array();
	$line = 1;
	$data = $has_header ? fgetcsv( $handle, 0, ',', '"', '\\' ) : $header;
	if ( $has_header ) {
		$line = 2;
	}

	while ( false !== $data ) {
		$has_values = is_array( $data ) && array_filter( $data, function ( $value ) {
			return '' !== trim( (string) $value );
		} );
		if ( $has_values ) {
			$rows[] = maatc_import_csv_row( $data, $url_col, $alt_col, $line, $dry_run );
		}
		$data = fgetcsv( $handle, 0, ',', '"', '\\' );
		$line++;
	}

	fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

	return array(
		'dry_run' => $dry_run,
		'rows'    => $rows,
	);
}

/**
 * Work out which columns hold the URL and the alt text.
 *
 * Looks for header names containing "url", "address", "image", "src" or "link"
 * and "alt". Without a recognizable header, the first column is the URL and the
 * second the alt text, and the first row is treated as data.
 *
 * @param array $header First row of the CSV.
 * @return array [ url column index|null, alt column index|null, first row is a header ].
 */
function maatc_csv_columns( $header ) {
	$url_col = null;
	$alt_col = null;

	foreach ( $header as $index => $name ) {
		$name = strtolower( trim( preg_replace( '/^\xEF\xBB\xBF/', '', (string) $name ) ) );
		if ( null === $alt_col && false !== strpos( $name, 'alt' ) ) {
			$alt_col = $index;
		} elseif ( null === $url_col && preg_match( '/url|address|image|src|link|file/', $name ) ) {
			$url_col = $index;
		}
	}

	if ( null !== $url_col && null !== $alt_col ) {
		return array( $url_col, $alt_col, true );
	}

	// No usable header: assume "URL, alt text" and keep the first row as data.
	if ( count( $header ) >= 2 ) {
		$first = preg_replace( '/^\xEF\xBB\xBF/', '', (string) $header[0] );
		return array( 0, 1, ! maatc_upload_path_from_url( $first ) );
	}

	return array( null, null, true );
}

/**
 * Process one CSV row.
 *
 * @param array $data    Row values.
 * @param int   $url_col URL column index.
 * @param int   $alt_col Alt text column index.
 * @param int   $line    Line number in the file, for the report.
 * @param bool  $dry_run Don't save.
 * @return array
 */
function maatc_import_csv_row( $data, $url_col, $alt_col, $line, $dry_run ) {
	$url = isset( $data[ $url_col ] ) ? trim( $data[ $url_col ] ) : '';
	$alt = isset( $data[ $alt_col ] ) ? sanitize_text_field( $data[ $alt_col ] ) : '';

	$row = array(
		'line'    => $line,
		'url'     => $url,
		'alt'     => $alt,
		'id'      => 0,
		'old_alt' => '',
		'status'  => 'skipped',
	);

	if ( '' === $url || '' === $alt ) {
		return $row;
	}

	$id = maatc_find_attachment_by_url( $url );
	if ( ! $id ) {
		$row['status'] = 'not_found';
		return $row;
	}

	$row['id']      = $id;
	$row['old_alt'] = (string) get_post_meta( $id, '_wp_attachment_image_alt', true );

	if ( $row['old_alt'] === $alt ) {
		$row['status'] = 'unchanged';
		return $row;
	}

	if ( ! $dry_run ) {
		update_post_meta( $id, '_wp_attachment_image_alt', wp_slash( $alt ) );
	}
	$row['status'] = 'updated';

	return $row;
}

/**
 * Get a file's path relative to the uploads folder from any URL, ignoring the domain.
 *
 * "https://staging.example.com/wp-content/uploads/2026/10/photo.jpg" gives "2026/10/photo.jpg".
 *
 * @param string $url Image URL or path.
 * @return string Relative path, or '' if the URL isn't in an uploads folder.
 */
function maatc_upload_path_from_url( $url ) {
	$path = wp_parse_url( trim( $url ), PHP_URL_PATH );
	if ( ! $path ) {
		return '';
	}
	$path = rawurldecode( $path );

	// Use this site's uploads path first (handles custom upload folders), then the default.
	$markers = array();
	$uploads = wp_get_upload_dir();
	$base    = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
	if ( $base ) {
		$markers[] = trailingslashit( $base );
	}
	$markers[] = '/wp-content/uploads/';
	$markers[] = '/uploads/';

	foreach ( $markers as $marker ) {
		$pos = strpos( $path, $marker );
		if ( false !== $pos ) {
			return ltrim( substr( $path, $pos + strlen( $marker ) ), '/' );
		}
	}

	return '';
}

/**
 * Find an image attachment from its URL, on any domain.
 *
 * Also matches resized copies ("photo-300x200.jpg") and the original of a
 * scaled image ("photo.jpg" when WordPress stored "photo-scaled.jpg").
 *
 * @param string $url Image URL.
 * @return int Attachment ID, or 0.
 */
function maatc_find_attachment_by_url( $url ) {
	global $wpdb;

	$relative = maatc_upload_path_from_url( $url );
	if ( '' === $relative ) {
		return 0;
	}

	$candidates = array( $relative );
	$unsized    = preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $relative );
	if ( $unsized !== $relative ) {
		$candidates[] = $unsized;
	}
	foreach ( $candidates as $candidate ) {
		if ( ! preg_match( '/-(scaled|rotated)\.[a-z0-9]+$/i', $candidate ) ) {
			$candidates[] = preg_replace( '/(\.[a-z0-9]+)$/i', '-scaled$1', $candidate );
		}
	}

	foreach ( array_unique( $candidates ) as $candidate ) {
		$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->prepare(
				"SELECT pm.post_id FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = '_wp_attached_file' AND pm.meta_value = %s AND p.post_type = 'attachment'
				LIMIT 1",
				$candidate
			)
		);
		if ( $id ) {
			return $id;
		}
	}

	return 0;
}
