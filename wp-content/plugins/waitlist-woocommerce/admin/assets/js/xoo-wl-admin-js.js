jQuery(document).ready(function($){
	'use strict';


	$('input[name="xoo-wl-email-options[bis-new-layout]"]').on('change', function(){

		var emStyleFooterSection = $('.xoo-ass-email-style-emsy_footer');

		if( $(this).is(':checked') ){
			emStyleFooterSection.hide();
		}
		else{
			emStyleFooterSection.show();
		}
	}).trigger('change');

	$( document ).on( 'click', '.xoo-placeholder', function() {

		const $item = $( this );
		const value = $item.data( 'placeholder' );
		const $icon = $item.find( '.xoo-placeholder__copy i' );

		navigator.clipboard.writeText( value ).then( function() {

			$icon
				.removeClass( 'xoo-icon-copy' )
				.addClass( 'xoo-icon-check' );

			setTimeout( function() {
				$icon
					.removeClass( 'xoo-icon-check' )
					.addClass( 'xoo-icon-copy' );
			}, 1500 );

		} );

	} );

});
