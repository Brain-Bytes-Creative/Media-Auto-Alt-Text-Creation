<?php
/**
 * Settings: option definitions, the Settings > Media Alt Text page, and sanitizing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fields the generated text can be written to.
 *
 * @return array<string, string> Field key => label.
 */
function maatc_available_fields() {
	return array(
		'post_title'   => __( 'Title', 'media-auto-alt-text-creation' ),
		'alt_text'     => __( 'Alt text', 'media-auto-alt-text-creation' ),
		'post_excerpt' => __( 'Caption', 'media-auto-alt-text-creation' ),
		'post_content' => __( 'Description', 'media-auto-alt-text-creation' ),
	);
}

/**
 * Capitalization styles.
 *
 * @return array<string, string> Style key => label.
 */
function maatc_capitalization_styles() {
	return array(
		'title'    => __( 'Title Case', 'media-auto-alt-text-creation' ),
		'sentence' => __( 'Sentence case (default)', 'media-auto-alt-text-creation' ),
		'lower'    => __( 'lowercase', 'media-auto-alt-text-creation' ),
		'upper'    => __( 'UPPERCASE', 'media-auto-alt-text-creation' ),
		'none'     => __( 'Leave as is', 'media-auto-alt-text-creation' ),
	);
}

/**
 * File rename modes.
 *
 * @return array<string, string> Mode key => label.
 */
function maatc_rename_modes() {
	return array(
		'images' => __( 'Rename images only', 'media-auto-alt-text-creation' ),
		'all'    => __( 'Rename all uploads (images, PDFs, videos, etc.)', 'media-auto-alt-text-creation' ),
		'none'   => __( 'Do not rename files', 'media-auto-alt-text-creation' ),
	);
}

/**
 * Default settings.
 *
 * @return array
 */
function maatc_default_settings() {
	return array(
		'fields'         => array( 'post_title', 'alt_text' ),
		'capitalization' => 'sentence',
		'rename_files'   => 'images',
		'overwrite'      => true,
	);
}

/**
 * Saved settings merged over the defaults.
 *
 * @return array
 */
function maatc_get_settings() {
	$saved = get_option( 'maatc_settings', array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( maatc_default_settings(), $saved );
}

/**
 * Sanitize the settings form submission against the allowed values.
 *
 * @param mixed $input Raw input.
 * @return array
 */
function maatc_sanitize_settings( $input ) {
	$defaults = maatc_default_settings();
	$input    = is_array( $input ) ? $input : array();

	$fields = isset( $input['fields'] ) && is_array( $input['fields'] ) ? $input['fields'] : array();
	$fields = array_values( array_intersect( array_keys( maatc_available_fields() ), array_map( 'sanitize_key', $fields ) ) );

	$capitalization = isset( $input['capitalization'] ) ? sanitize_key( $input['capitalization'] ) : '';
	if ( ! array_key_exists( $capitalization, maatc_capitalization_styles() ) ) {
		$capitalization = $defaults['capitalization'];
	}

	$rename = isset( $input['rename_files'] ) ? sanitize_key( $input['rename_files'] ) : '';
	if ( ! array_key_exists( $rename, maatc_rename_modes() ) ) {
		$rename = $defaults['rename_files'];
	}

	return array(
		'fields'         => $fields,
		'capitalization' => $capitalization,
		'rename_files'   => $rename,
		'overwrite'      => ! empty( $input['overwrite'] ),
	);
}

add_action( 'admin_init', function () {
	register_setting(
		'maatc_settings_group',
		'maatc_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'maatc_sanitize_settings',
			'default'           => maatc_default_settings(),
		)
	);
} );

add_action( 'admin_menu', function () {
	add_options_page(
		__( 'Media Auto Alt Text', 'media-auto-alt-text-creation' ),
		__( 'Media Alt Text', 'media-auto-alt-text-creation' ),
		'manage_options',
		MAATC_SETTINGS_PAGE,
		'maatc_render_settings_page'
	);
} );

/**
 * Render the settings page.
 */
function maatc_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$settings = maatc_get_settings();
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Media Auto Alt Text', 'media-auto-alt-text-creation' ); ?></h1>
		<p><?php esc_html_e( 'When an image is uploaded, its file name is cleaned up (dashes and underscores become spaces, size suffixes are removed) and written to the fields you choose below.', 'media-auto-alt-text-creation' ); ?></p>

		<form method="post" action="options.php">
			<?php settings_fields( 'maatc_settings_group' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><?php esc_html_e( 'Fields to fill', 'media-auto-alt-text-creation' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( maatc_available_fields() as $key => $label ) : ?>
								<label>
									<input type="checkbox" name="maatc_settings[fields][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $settings['fields'], true ) ); ?>>
									<?php echo esc_html( $label ); ?>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="maatc-capitalization"><?php esc_html_e( 'Capitalization', 'media-auto-alt-text-creation' ); ?></label></th>
					<td>
						<select id="maatc-capitalization" name="maatc_settings[capitalization]">
							<?php foreach ( maatc_capitalization_styles() as $key => $label ) : ?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $settings['capitalization'], $key ); ?>><?php echo esc_html( $label ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Existing values', 'media-auto-alt-text-creation' ); ?></th>
					<td>
						<label>
							<input type="checkbox" name="maatc_settings[overwrite]" value="1" <?php checked( $settings['overwrite'] ); ?>>
							<?php esc_html_e( 'Overwrite fields that already have a value when regenerating existing images', 'media-auto-alt-text-creation' ); ?>
						</label>
						<p class="description"><?php esc_html_e( 'Unchecked, the Media Library buttons and bulk action only fill fields that are empty. New uploads are always filled.', 'media-auto-alt-text-creation' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Rename uploaded files', 'media-auto-alt-text-creation' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( maatc_rename_modes() as $key => $label ) : ?>
								<label>
									<input type="radio" name="maatc_settings[rename_files]" value="<?php echo esc_attr( $key ); ?>" <?php checked( $settings['rename_files'], $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</label><br>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'Renaming makes file names lowercase, converts accented characters, and replaces everything else with hyphens, e.g. "Café Photo (1).JPG" becomes "cafe-photo-1.jpg".', 'media-auto-alt-text-creation' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button(); ?>
		</form>
	</div>
	<?php
}
