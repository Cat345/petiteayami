jQuery(document).ready(function( $ ){



	var Popup = {

		$self: $('.xoo-wl-popup'),

		init: function(){

			Popup.$noticeCont 	= Popup.$self.find('.xoo-wl-notices');
			Popup.$header 		= Popup.$self.find('.xoo-wl-header');
			Popup.$form 		= Popup.$self.find('.xoo-wl-form');

			$('body').on( 'click', '.xoo-wl-btn-popup', this.open );
			$('.xoo-wl-modal').on( 'click', this.close );
		},

		open: function(e){
			Popup.$self.add( $('html, body') ).addClass('xoo-wl-popup-active');
			Popup.$self.find( 'input[name="_xoo_wl_product_id"]' ).val( $(this).attr('data-product_id') );
			Popup.$form.add(Popup.$header).show();
			Popup.$noticeCont.hide();
		},

		close: function( event ){
			$.each( event.target.classList, function( key, value ){
				if( value == 'xoo-wl-modal' || value == 'xoo-wl-close' ){
					$('html, body').add( Popup.$self ).removeClass('xoo-wl-popup-active');
					$('body').trigger('xoo_wl_popup_closed');
					setTimeout(function(){
						Popup.$self.removeClass('xoo-wl-user-added');
					}, 400)
					
					return false;
				}
			})
		}
	}

	Popup.init();


	var Form = function( $form ){

		var self 				= this;
		self.$form 				= $form;
		self.$productIDInput 	= self.$form.find( 'input[name="_xoo_wl_product_id"]' );
		self.productID 			= self.$productIDInput.val();
		self.$noticeCont 		= self.$form.siblings( '.xoo-wl-notices' );
		self.$header 			= self.$form.siblings( '.xoo-wl-header' );

		self.validationPassed 	= self.validationPassed.bind(this);
		self.showNotice 		= self.showNotice.bind(this);

		self.$form.on( 'submit', { form: self }, self.submit );

	}

	Form.prototype.submit = function( event ){

		event.preventDefault();
		var form = event.data.form;
		if( !form.validationPassed() ) return;

		var formData 	= form.$form.serialize()+'&action=xoo_wl_form_submit',
			$button 	= form.$form.find('button[type="submit"]'),
			buttonHTML 	= $button.html();

			$button.html( xoo_wl_localize.html.spinner ).addClass('xoo-wl-processing');

		$.ajax({
			url: xoo_wl_localize.adminurl,
			type: 'POST',
			data: formData,
			success: function(response){

				$button.removeClass('xoo-wl-processing').html(buttonHTML);

				if( response.notice ){
					form.showNotice(response.notice);
				}
				else{
					console.log(response);
				}

				if( response.error === 0){
					if( Popup.$self.hasClass('xoo-wl-popup-active') ){
						Popup.$self.addClass('xoo-wl-user-added');
					}
					form.$form.add(form.$header).hide();
				}
			}
		});

	}


	Form.prototype.validationPassed = function(){

		var form = this,
			errors = [],
			errorHTML = '';

		if( !form.productID ){
			errors.push( xoo_wl_localize.notices.empty_id );
		}

		$.each( errors, function( index, error ){
			errorHTML += error;
		} )

		form.showNotice(errorHTML);

		return errors.length ? false : true;
	}


	Form.prototype.showNotice = function( notice ){
		var form = this
		form.$noticeCont.html(notice).show();
	}



	$.each( $( 'body' ).find( '.xoo-wl-form' ), function( index, el ){
		new Form( $(el) );
	} );


		//WooCommerce Product Variation on select
	$('body').on( 'change', 'form.variations_form .variation_id', function(){

		var $atcForm 			= $(this).closest('form.variations_form'),
			variationsData 		= $atcForm.data('product_variations'),
			selectedVariation 	= $(this).val(),
			$waitlistContainer 	= $atcForm.siblings('.xoo-wl-btn-container');

			if( !$waitlistContainer.length && $waitlistContainer.closest('.product').length ){
				$waitlistContainer 	= $($waitlistContainer.closest('.product').find('.xoo-wl-btn-container').get(0));
			}

			if( !$waitlistContainer.length ) return;

		var $waitlistBtn = $waitlistContainer.find( 'button.xoo-wl-open-form-btn' ),
			$productIDinput = $waitlistContainer.find('input[name="_xoo_wl_product_id"]');

			$waitlistContainer.hide();


		if( !selectedVariation || selectedVariation == 0 ){
			selectedVariation = $atcForm.find('input[name="product_id"]').val();
		}


		$.each( variationsData, function ( index, variation ) {

			if ( variation.variation_id != selectedVariation ) return;

			let showBtn = false;

			// outofstock
			if ( xoo_wl_localize.waitlist_show.includes( 'outofstock' ) && ! variation.is_in_stock ) {
				showBtn = true;
			}

			// backorder
			else if ( xoo_wl_localize.waitlist_show.includes( 'backorder' ) && variation.backorders_allowed ) {
				showBtn = true;
			}

			// backorder but out of stock
			else if ( xoo_wl_localize.waitlist_show.includes( 'backorder_out' )  && variation.backorders_allowed && variation.availability_html.includes('available-on-backorder') ) {
				showBtn = true;
			}

			// instock
			else if (xoo_wl_localize.waitlist_show.includes( 'instock' ) && variation.is_in_stock ) {
				showBtn = true;
			}

			if ( showBtn ) $waitlistContainer.show();
			

			return false;

		});



		//Set product IDS
		if( $waitlistBtn.length ){
			$waitlistBtn.attr( 'data-product_id', selectedVariation )
		}
		if( $productIDinput.length ){
			$productIDinput.val( selectedVariation );
		}
		
	})

	$('body .variation_id').trigger('change');

	$('body').on( 'click', 'button.xoo-wl-btn-toggle', function(){

		var toggleClass = 'xoo-wl-active',
			$container 	= $(this).parents('.xoo-wl-btn-container');

		if( $container.hasClass( toggleClass ) ){
			$container.removeClass( toggleClass )
		}
		else{
			$container.addClass( toggleClass ).
			$container.find( 'input[name="_xoo_wl_product_id"]' ).val( $(this).data('product_id') );
		}

	} );


})