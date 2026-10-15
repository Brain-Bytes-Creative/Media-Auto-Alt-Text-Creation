/* global maatcAdmin, wp */
jQuery( function ( $ ) {
	// Put new values into the form on the attachment edit screen, so clicking
	// Update afterwards doesn't save the old values over them.
	function updateEditScreen( data ) {
		$( '#title' ).val( data.title );
		$( '#attachment_alt' ).val( data.alt );
		$( '#attachment_caption' ).val( data.caption );
		$( '#attachment_content' ).val( data.description );
	}

	// Refresh the media modal so Attachment Details shows the new values.
	function refreshModal( id ) {
		if ( window.wp && wp.media && wp.media.attachment ) {
			wp.media.attachment( id ).fetch();
		}
	}

	$( document ).on( 'click', '.maatc-generate', function ( e ) {
		e.preventDefault();

		var $button = $( this );
		var $status = $button.siblings( '.maatc-status' );
		var id = $button.data( 'id' );

		$button.prop( 'disabled', true );
		$status.text( maatcAdmin.i18n.working );

		$.post( maatcAdmin.ajaxUrl, {
			action: 'maatc_generate',
			nonce: maatcAdmin.nonce,
			id: id
		} )
			.done( function ( response ) {
				if ( ! response || ! response.success ) {
					$status.text( ( response && response.data ) || maatcAdmin.i18n.error );
					return;
				}

				var data = response.data;
				$status.text( data.changed ? maatcAdmin.i18n.done : maatcAdmin.i18n.nothing );

				// Media Library list view.
				var $alt = $button.closest( 'tr' ).find( '.maatc-alt-value' );
				if ( $alt.length ) {
					$alt.text( data.alt );
					return;
				}

				if ( $( 'body' ).hasClass( 'post-type-attachment' ) && $( '#title' ).length ) {
					updateEditScreen( data );
				} else {
					refreshModal( id );
				}
			} )
			.fail( function ( xhr ) {
				var message = xhr.responseJSON && xhr.responseJSON.data;
				$status.text( message || maatcAdmin.i18n.error );
			} )
			.always( function () {
				$button.prop( 'disabled', false );
			} );
	} );
} );
