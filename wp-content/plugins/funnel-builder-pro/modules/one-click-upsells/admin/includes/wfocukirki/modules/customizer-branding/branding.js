/* global wfocukirkiBranding */
jQuery( document ).ready( function() {

	'use strict';

	if ( '' !== wfocukirkiBranding.logoImage ) {
		jQuery( 'div#customize-info .preview-notice' ).replaceWith( '<img src="' + wfocukirkiBranding.logoImage + '">' );
	}

	if ( '' !== wfocukirkiBranding.description ) {
		jQuery( 'div#customize-info > .customize-panel-description' ).replaceWith( '<div class="customize-panel-description">' + wfocukirkiBranding.description + '</div>' );
	}

} );
