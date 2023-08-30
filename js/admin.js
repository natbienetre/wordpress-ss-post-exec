jQuery( document ).ready( function( $ ) {
	const buttons = $( '#sspostexec-options-check-button, #sspostexec-trigger-button' )
		.prop( "disabled", false )
		.on( 'click', function ( e ) {
			e.preventDefault();
			e.stopPropagation();

			const currentButton = $( this );

			const url = new URL( currentButton.data( 'ajax-url' ) );
			const data = new URLSearchParams( currentButton.parents( 'form' ).serialize() );
			
			data.set( 'action', currentButton.data( 'action' ) );

			$( '.sspostexec-status' ).remove();
		
			buttons.prop( "disabled", true );
			fetch( url, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'Cache-Control': 'no-cache',
				},
				body: data,
			}).then(response => {
				buttons.prop( "disabled", false );
				
				response.json().then( data => {
					if ( data.success ) {
						currentButton.parents( 'p' ).first().before( $( '<p class="sspostexec-status notice notice-success notice-alt inline is-dismissible"></p>' ).append( $( '<p></p>' ).html( data.data.message ) ) );

						return;
					}

					for (const [_, error] of Object.entries(data.data)) {
						currentButton.parents( 'p' ).first().before( $( '<p class="sspostexec-status notice notice-error notice-alt inline is-dismissible"></p>' ).append( $( '<p></p>' ).html( error.message ) ) );
					}
				});
			} );
		} );
} );

jQuery( document ).ready( function( $ ) {
	$( '#sspostexec-use-local-credentials' ).on( 'change', function() {
		if ( $( this ).is( ':checked' ) ) {
			$( '.non-local-credentials' ).hide();
		} else {
			$( '.non-local-credentials' ).show();
		}
	});
} );

jQuery( document ).ready( function( $ ) {
	$( '#sspostexec-manifest, .post-type-sspostexec-job #content' ).add( $( 'input[value=k8s_manifest]' ).parents( 'tr' ).first().find( 'textarea' ) ).each( function( index, element ) {
		wp.codeEditor.initialize(
			element,
			sspostexec_codeeditor_settings,
		);
	} );
} );
