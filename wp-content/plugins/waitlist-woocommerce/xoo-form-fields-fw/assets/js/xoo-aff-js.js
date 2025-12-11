jQuery(document).ready(function($){



	(function(){

		//initialize datepicker
		$('.xoo-aff-input-date').each( function( index, dateEl ){
			var $dateEl = $(dateEl),
				dateData = $dateEl.data('date');
			$dateEl.datepicker( dateData );
		} );
		
	}());


	var all_states 			= xoo_aff_localize.states ? JSON.parse(xoo_aff_localize.states) : {};
	var countries_locale 	= xoo_aff_localize.countries_locale ?  JSON.parse(xoo_aff_localize.countries_locale) : {};

	//-------------------- XXXXXXXXXXXXXXX -------------------- //

	var SelectCountry = function( $select ){
		
		var self = this;
		self.$selectCountry 	= $select; 
		self.id 				= self.$selectCountry.attr('name');
		self.$form 				= self.$selectCountry.closest( 'form' );
		self.$selectState 		= self.$form.find('.xoo-aff-states[data-country_field="'+self.id+'"]');
		self.$selectPhoneCode 	= self.$form.find('.xoo-aff-phone_code[data-country_field="'+self.id+'"]');

		//Methods
		self.statesHandler = self.$selectState.length ? self.states() : false;

		//Events
		self.$selectCountry.on( 'change', { country: self }, this.onChange );
		self.$selectCountry.trigger('change'); //on load

	}

	SelectCountry.prototype.onChange = function( event ){
		var self = event.data.country;
		if( self.statesHandler ){
			self.statesHandler.updateStateField( event );
		}

		if( self.$selectPhoneCode.length ){
			self.updatePhoneCodeField();
		}
	}

	//PhoneCode field handler
	SelectCountry.prototype.updatePhoneCodeField = function(){
		var country 	= this,
			$codeOption = country.$selectPhoneCode.find( 'option[data-country_code="'+country.$selectCountry.val()+'"]' );
		
		if( $codeOption ){
			$codeOption.prop('selected','selected');
		}

		country.$selectPhoneCode.trigger('change');
	}

	//State field handler
	SelectCountry.prototype.states = function(){

		var country 		= this,
			$selectState 	= country.$selectState,
			defaultValue 	= country.$selectState.data( 'default_state' );

		var Handler =  {

			init: function(){
				Handler.$statePlaceholder 	= $selectState.find('option[value="placeholder"]');
				Handler.$selectStateCont 	= $selectState.parent();
				Handler.$inputState 		= Handler.createStateInput();
			},

			getStates: function(){
				var states = all_states[ country.$selectCountry.val() ];
				return states === undefined || states.length === 0  ? null : states;
			},

			createStateInput: function(){
				$stateInput =  $( '<input type="text" />' )
					.prop( 'name', $selectState.attr('name') )
					.prop('placeholder', Handler.$statePlaceholder.html() )
					.addClass( $selectState.attr('class') );

				var excludeID = ['select2', 'select2Id' ,'country_field'];

				$.each($selectState.data(), function( id, value ){
					if( !excludeID.includes(id) ){
						$stateInput.attr('data-'+id, value);
					}
					
				});

				return $stateInput;
			},

			updateStateField: function( event ){

				var country 	= event.data.country,
					countryVal 	= country.$selectCountry.val();

				//Remove all current states
				$selectState.find('option').not(Handler.$statePlaceholder).remove();

				
				var active_states = Handler.getStates();

				if( !active_states ){

					Handler.$selectStateCont.find('.select2-container').remove();
					Handler.$selectStateCont.append( Handler.$inputState );
					
				}
				else{
					Handler.$inputState.remove();
					Handler.$selectStateCont.append( $selectState );
					$.each( active_states, function( state_key, label ){
						$selectState.append( '<option value="'+state_key+'">'+label+'</option>' );
					} )

					if( defaultValue ){
						Handler.$selectStateCont.find( 'option[value='+defaultValue+']' ).prop( 'selected', 'selected' );
					}

					if( $selectState.attr('select2') === 'yes' ){
						$selectState.select2();
					}
					
				}
	

			}

		}

		Handler.init();

		return Handler;
	}

	$.each( $('select.xoo-aff-country'), function( index, el ){
		new SelectCountry( $(el));
	} );


	//-------------------- XXXXXXXXXXXXXXX -------------------- //

	var password_strength_meter = {

		/**
		 * Initialize strength meter actions.
		 */
		init: function() {
			$( document.body )
				.on(
					'keyup change',
					'input.xoo-aff-password[check_strength="yes"]',
					this.strengthMeter
				);
		},

		/**
		 * Strength Meter.
		 */
		strengthMeter: function() {
			var wrapper       = $(this).parents('.xoo-aff-group'),
				form 		  = wrapper.parents('form');
				submit        = form.length ? $( 'button[type="submit"]', form ) : false,
				field         = $(this),
				strength      = 1,
				passStrength  = $(this).attr('strength_pass').length ? $(this).attr('strength_pass') : xoo_aff_localize.password_strength.min_password_strength,
				fieldValue    = field.val();

			password_strength_meter.includeMeter( wrapper, field );

			strength = password_strength_meter.checkPasswordStrength( wrapper, field, passStrength );


			if (
				submit &&
				fieldValue.length > 0 &&
				strength < passStrength  &&
				-1 !== strength
			) {
				submit.attr( 'disabled', 'disabled' ).addClass( 'disabled' );
			} else {
				submit.removeAttr( 'disabled', 'disabled' ).removeClass( 'disabled' );
			}
		},

		/**
		 * Include meter HTML.
		 *
		 * @param {Object} wrapper
		 * @param {Object} field
		 */
		includeMeter: function( wrapper, field ) {
			var meter = wrapper.find( '.xoo-aff-password-strength' );

			if ( '' === field.val() ) {
				meter.hide();
				$( document.body ).trigger( 'xoo-aff-password-strength-hide' );
			} else if ( 0 === meter.length ) {
				wrapper.append( '<div class="xoo-aff-password-strength" aria-live="polite"></div>' );
				$( document.body ).trigger( 'xoo-aff-password-strength-added' );
			} else {
				meter.show();
				$( document.body ).trigger( 'xoo-aff-password-strength-show' );
			}
		},

		/**
		 * Check password strength.
		 *
		 * @param {Object} field
		 *
		 * @return {Int}
		 */
		checkPasswordStrength: function( wrapper, field, passStrength ) {
			var meter     = wrapper.find( '.xoo-aff-password-strength' ),
				hint      = wrapper.find( '.xoo-aff-password-hint' ),
				hint_html = '<small class="xoo-aff-password-hint">' + xoo_aff_localize.password_strength.i18n_password_hint + '</small>',
				strength  = wp.passwordStrength.meter( field.val(), wp.passwordStrength.userInputBlacklist() ),
				error     = '';

			// Reset.
			meter.removeClass( 'short bad good strong' );
			hint.remove();

			if ( meter.is( ':hidden' ) ) {
				return strength;
			}

			// Error to append
			if ( strength < passStrength ) {
				error = ' - ' + xoo_aff_localize.password_strength.i18n_password_error;
			}

			switch ( strength ) {
				case 0 :
					meter.addClass( 'short' ).html( pwsL10n['short'] + error );
					meter.after( hint_html );
					break;
				case 1 :
					meter.addClass( 'bad' ).html( pwsL10n.bad + error );
					meter.after( hint_html );
					break;
				case 2 :
					meter.addClass( 'bad' ).html( pwsL10n.bad + error );
					meter.after( hint_html );
					break;
				case 3 :
					meter.addClass( 'good' ).html( pwsL10n.good + error );
					break;
				case 4 :
					meter.addClass( 'strong' ).html( pwsL10n.strong + error );
					break;
				case 5 :
					meter.addClass( 'short' ).html( pwsL10n.mismatch );
					break;
			}

			return strength;
		}
	};

	password_strength_meter.init();

	if( $.fn.select2 ){
		$('select.xoo-aff-select_list[select2="yes"] , select.xoo-aff-country[select2="yes"]').each(function( key, el ){
			$(el).select2();
		});
	}


	if( $.fn.select2 ){

		function formatState (state) {

			if (!state.id) {
				return state.text;
			}

			var cc = state.element.getAttribute('data-country_code');
				cc = cc ? cc.toLowerCase() : cc;

			var $state = $( '<div class="xoo-aff-flag-cont"><span class="flag ' + cc +'"></span>' + '<span>' + state.text + '</span></div>' );

			return $state;

		};

		

		$('select.xoo-aff-phone_code[select2="yes"]').each(function( key, el ){
			$(el).select2({ templateResult: formatState, templateSelection: formatState, dropdownCssClass: "xoo-aff-select2-dropdown" });
		});
	}


	//Password toggle visiblity
	$( 'body' ).on( 'click', '.xoo-aff-pw-toggle', function(){

		var $password = $(this).closest('.xoo-aff-group').find('input.xoo-aff-password');

		$(this).toggleClass('active');

		if( $(this).hasClass('active') ){
			$password.attr('type','text');
			$(this).find('.xoo-aff-pwtog-hide').show();
			$(this).find('.xoo-aff-pwtog-show').hide();
		}
		else{
			$password.attr('type','password');
			$(this).find('.xoo-aff-pwtog-show').show();
			$(this).find('.xoo-aff-pwtog-hide').hide();
		}

	} )

	 $( 'body' ).on('change', '.xoo-aff-file-profile input[type="file"]', function() {

        var file 		= this.files[0],
        	$cont 		=  $(this).closest('.xoo-aff-group');

        if (file) {
            const reader = new FileReader();

            reader.onload = function(e) {
            	profileImage.init( $cont, e.target.result);
                profileImage.onImageLoad();

                if( $cont.find('.xoo-ff-file-remove') ){
	            	$cont.find('.xoo-ff-files').remove();
	            }

            };

            reader.readAsDataURL(file);
        }
        else{
        	profileImage.init( $cont, '');
        	profileImage.onImageRemove();

        }
    });

	var profileImage = {

		init: function( $cont, imgSrc = '' ){
			this.$cont  		= $cont;
			this.imgSrc  		= imgSrc;
			this.$preview 		= $cont.find('.xoo-ff-file-preview');
        	this.$icon 			= $cont.find('.xoo-aff-input-icon ');
        	this.$checkIcon 	= $cont.find('.xoo-ff-file-plcheck');
        	this.$addIcon 		= $cont.find('.xoo-ff-file-pladd');
		},

		onImageLoad: function(){
        	this.$preview.attr('src', profileImage.imgSrc).show();
            this.$icon.hide();
            this.$addIcon.hide();
            this.$checkIcon.show();

		},

		onImageRemove: function(){
			this.$icon.show();
        	this.$checkIcon.hide();
        	this.$addIcon.show();
        	this.$preview.attr('src', '').hide();
		}

	}


	$('.xoo-aff-file-profile-cont').each(function(index, el){

		var $el 			= $(el),
			$attachedFiled 	= $el.find('.xoo-ff-file-link');

		if( $attachedFiled.length ){
			profileImage.init( $el, $attachedFiled.attr('href') );
			profileImage.onImageLoad();
		}
	})

	$('body').on( 'click', '.xoo-aff-file-profile-cont .xoo-ff-file-remove', function(){
		var $cont = $(this).closest('.xoo-aff-file-profile-cont');
		profileImage.init( $cont );
		profileImage.onImageRemove();
	} );

	$( 'body' ).on('click', '.xoo-ff-file-remove', function(){
		$(this).closest('div').remove();
	});



	class AutoComplete{

		constructor( $input ){
			this.$input 	= $input;
			this.$form 		= $input.closest('form');
			this.hasParts 	= this.$form.find('[data-autocompad_parent="'+this.$input.attr('name')+'"]').length;
			this.inputInit 	= false;

			this.$browserFetch = this.$input.closest('.xoo-aff-group').find('.xoo-aff-auto-fetch-loc');

			this.events();
		}


		events(){
			this.$input.on('focus', this.init.bind(this) );
			if( this.$browserFetch.length ){
				this.$browserFetch.on('click', { handler: this }, this.browserGetLocation );
			}
		}


		browserGetLocation(event){

			var handler = event.data.handler;

			if( !navigator.geolocation ){
				$(this).html('Not supported by browser');
				return;
			}

			var $fetchEl = $(this);

			navigator.geolocation.getCurrentPosition(

				(position) => {
					handler.googleReverseGeolocate( position.coords.latitude, position.coords.longitude );
				},
				(error) => {
					$fetchEl.find('span').text( error.message );
				}
			);
		}

		googleReverseGeolocate(lat, long){

			const geocoder = new google.maps.Geocoder();

			const latlng = { lat: parseFloat(lat), lng: parseFloat(long) };

			var autocompleteHandler = this;

			geocoder.geocode({ location: latlng }, function (results, status) {

				if (status === "OK") {

					if (results[0]) {
						autocompleteHandler.fillAddress( results[0].address_components, results[0].formatted_address );
					}
					else {
						console.log("No results found");
					}
				}
				else {
					autocompleteHandler.$browserFetch.find('span').text( status );
				}
			});
		}

		

		init(){

			if( this.inputInit ) return;

			var placesArgs = {
				fields: ["address_components" ],
				types: ["address"],
			}

			if( xoo_aff_localize.geolocate_countries ){
				placesArgs.componentRestrictions = {country: xoo_aff_localize.geolocate_countries}
			}

			this.location = new google.maps.places.Autocomplete(this.$input.get(0), placesArgs);

			if( this.hasParts ){
				this.location.addListener('place_changed', this.onAddressChange.bind(this) );
			}

			this.inputInit = true;

		}

		onAddressChange(){
			this.fillAddress( this.location.getPlace().address_components );
		}


		fillAddress( components, formatted_address = '' ){

				var autocompleteHandler = this,
					$form  				= this.$form,
					addressParts 		= { country: '', postal_code: '', address: '', states: '', city: '', states_longname: '', country_longname: '' };

			$.each( components, function( index, component ){

				var componentType = component.types[0];


				switch(componentType) {

					case 'country':
						addressParts.country 			= component.short_name;
						addressParts.country_longname 	= component.long_name;
						break;

					
					case "postal_code_suffix":
						addressParts.postal_code += component.long_name;
						break;

					case "postal_code":
						addressParts.postal_code += component.long_name;
						break;

					case "locality": // Most common
					case "postal_town": // UK, Ireland, some EU countries
					case "administrative_area_level_3": // Brazil, Japan, some regions
						addressParts.city = component.long_name;
						break;

					case 'administrative_area_level_1':
						addressParts.states 		= component.short_name;
						addressParts.states_longname = component.long_name;
						break;

					default:
						addressParts.address += ( component.long_name + ' ' );
						break;
				}

			} );


			//If states are not available for country
			if(  !addressParts.city && addressParts.states && (!countries_locale[ addressParts.country ] || !countries_locale[ addressParts.country ][ 'state' ] || countries_locale[ addressParts.country ][ 'state' ][ 'hidden' ] ) ){
				addressParts.city = addressParts.states;
				addressParts.states = null;
			}

			var partsNotUsed = {},
				partsUsedCounter = 0;

			$.each( addressParts, function( part, value ){

				var $inputParts = $form.find('[data-autocompad_type="'+part+'"][data-autocompad_parent="'+autocompleteHandler.$input.attr('name')+'"]');

				if( !$inputParts.length ){
					partsNotUsed[part] = value;
					return true; //do not proceed further as address part element does not exist
				}

				partsUsedCounter++;

				$inputParts.each( function( index, inputPart ){

					var $inputPart = $(inputPart);

					if( (part === 'states' || part === 'country') && $inputPart.is('input') ){ // if its an input and not select, we use long name
						$inputPart.val( addressParts[part+'_longname'] );
					}
					else{

						//scan through states as the name returned by google does not exactly match the local states data we have
						if( part === 'states' && !$inputPart.find('option[value="'+value+'"]').length ){

							var optionFound;

							$inputPart.find('option').each(function( index, option ){
								var $option = $(option);
								if( $option.val().includes('-'+value) ){
									optionFound = $option;
									return false;
								}
							})


							if( !optionFound ){
								optionFound = $inputPart.find("option:contains('" + addressParts.states_longname + "')");
							}
						
							if( optionFound.length ){
								$inputPart.val( optionFound.val() );
							}

						}
						else{
							$inputPart.val( value );
						}

						$inputPart.trigger('change');

					}
						
				});

			} );

			if( partsUsedCounter > 0 ){

				var inputAddress = addressParts.address;

				$.each( [ 'city', 'states', 'postal_code', 'country' ], function( index, leftPart ){
					if( partsNotUsed[ leftPart ] ){
						var partLongName = partsNotUsed[ leftPart + '_longname' ];
						partLongName = partLongName ? partLongName : partsNotUsed[ leftPart ];
						inputAddress += ( ', ' + partLongName  );
					}
				} );

				this.$input.val(inputAddress);

				
			}
			else if(formatted_address){
				this.$input.val(formatted_address);
			}
			
		}

	}


	if( xoo_aff_localize.geolocate_apikey ){

		$('input[data-autocompadd="yes"]').each(function(){
			new AutoComplete( $(this) );
		});

	}	
	
})