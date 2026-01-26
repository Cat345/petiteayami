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

});
