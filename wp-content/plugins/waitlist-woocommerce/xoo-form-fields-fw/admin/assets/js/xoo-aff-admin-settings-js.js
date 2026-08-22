jQuery(document).ready(function($){
	'use strict';

	//Initialize Color Picker
	$(function(){
		$('.color-field').wpColorPicker();
	});

	if( xoo_aff_admin_settings_localize.enable_preview ){

		var Customizer = {

			$form: '',
			$styleTag: $('.xoo-as-field-preview-style'),
			previewTemplate: '',
			formValues: {},
			getPreviewCSS: function() {},
			getPreviewHTMLData: function() {},
			pageLoading: true,

			init: function(){
				this.initColorPicker();
				this.initSortable();
				this.initTemplates();
				this.events();
				this.build();
			},

			events: function(){
				Customizer.$form.on('change', '[name^="'+xoo_aff_admin_settings_localize.field_option_key+'["]', this.onFormChange );
			},

			initTemplates: function(){
				this.previewTemplate = wp.template('xoo-as-field-preview');
			},

			initColorPicker: function(){
				$('.xoo-as-color-input').wpColorPicker({
					change: function(event, ui){
						$(event.target).val(ui.color.toString()).trigger('change')
					}
				});
			},

			initSortable: function(){
				$('.xoo-as-sortable-list').sortable({
					update: function(){
						Customizer.build();
					}
				});
			},

			onFormChange: function(e){
				console.log('changed');
				Customizer.build();
			},


			setFormValues: function(){
				this.formValues = this.$form.serializeJSON();
			},

			build: function(){
				if( this.pageLoading ) return; // prevent multiple building event on page load due to 'change' event
				this.setFormValues();
				this.buildHTML();
				this.buildCSS();
			},


			buildCSS: function(){

				var css = '';

				$.each( Customizer.getPreviewCSS(), function( selector, properties ){
					css += selector+'{';
					$.each( properties, function(property, value){
						css += property+': '+value+';';
					} );
					css += '}';
				} );

				Customizer.$styleTag.html('<style>'+css+'</style>')	
			},

			buildHTML: function(){
				$('.xoo-as-field-preview').html(Customizer.previewTemplate(Customizer.getPreviewHTMLData()));

			}
		}



		var Field = {

			settingsInPreview: [],
			previewSettingsRecorded: false,

			init: function(){
				this.initCustomizer();
				this.events();
			},


			initCustomizer: function(){
				Customizer.$form 				=  $('form.xoo-as-form');
				Customizer.getPreviewCSS 		= this.getPreviewCSS;
				Customizer.getPreviewHTMLData 	= this.getPreviewHTMLData;
				Customizer.init();
			},

			events: function(){

				$(document.body)
                  .on(
                    'focusin',
                    '.xoo-aff-group input, .xoo-aff-group textarea, .xoo-aff-group select',
                    this.onFocus
                  )
                  .on(
                    'focusout',
                    '.xoo-aff-group input, .xoo-aff-group textarea, .xoo-aff-group select',
                    this.onFocusOut
                  );

			},


			setting: function( key, unit = '' ){
				const value = this.option( xoo_aff_admin_settings_localize.field_option_key, key );
				return unit ? value + unit : value;
			},

			option: function( option, key ){
				if( !this.previewSettingsRecorded ){
					this.settingsInPreview.push( option+'['+key+']' )
				}
				return Customizer.formValues[option][key];
			},


			getPreviewCSS: function(){
				return Field.setPreviewCSS();
			},

			setPreviewCSS: function(){

                /* === PHP variable equivalents === */
                   const iconBgColor     = this.setting('s-icon-bgcolor');
                   const iconColor       = this.setting('s-icon-color');
                   const iconSize        = this.setting('s-icon-size', 'px');
                   const iconWidth       = this.setting('s-icon-width', 'px');
                   const fieldMargin     = this.setting('s-field-bmargin', 'px');
                   const inputBgColor    = this.setting('s-input-bgcolor');
                   const inputTxtColor   = this.setting('s-input-txtcolor');
                   const inputRadius     = this.setting('s-input-borradius', 'px');
                   const focusBgColor    = this.setting('s-input-focusbgcolor');
                   const focusTxtColor   = this.setting('s-input-focustxtcolor');
                   const inputHeight     = this.setting('s-input-height', 'px');
                   const iconAlign       = this.setting('s-icon-align');
                   const showIcons       = this.setting('s-show-icons') === 'yes';

                   const inputBorder       = generateBorderCSS( this.setting('s-input-border') );
                   const inputBorderFocus  = generateBorderCSS( this.setting('s-input-border-focus') );
                   const iconColorFocus    = this.setting('s-icon-color-focus');
                   const inputFontSize     = this.setting('s-input-fsize', 'px');
                   const labelFontSize     = this.setting('s-label-fsize', 'px');
                   const iconBgFocus       = this.setting('s-icon-bgcolor-focus');

                   const iconPadding =
                     parseInt(this.setting('s-icon-width') || 0) +
                     (iconBgColor ? 10 : 0);

                   /* === CSS SELECTORS === */
                   const selectors = {

                     '.xoo-aff-input-group .xoo-aff-input-icon': {
                       'background-color': iconBgColor,
                       'color': iconColor,
                       'max-width': iconWidth,
                       'min-width': iconWidth,
                       'font-size': iconSize,
                       'position': 'absolute',
                       'z-index': 4,
                       'top': 0,
                       'bottom': 0,
                       [iconAlign]: 0,
                       'border-radius': iconBgColor ? inputBorder.borderRadius : 0,
                       'border': iconBgColor ? inputBorder.border : '0'
                     },

                     '.xoo-aff-isfocused.xoo-aff-group .xoo-aff-input-icon': {
                       'color': iconColorFocus,
                       'background-color': iconBgFocus,
                       'border-radius': inputBorderFocus.borderRadius,
                       'border': iconBgFocus ? inputBorderFocus.border : 0
                     },

                     

                     '.xoo-aff-group label': {
                       'font-size': labelFontSize
                     },

                     '.xoo-aff-group input[type="text"], .xoo-aff-group input[type="password"], .xoo-aff-group input[type="email"], .xoo-aff-group input[type="number"], .xoo-aff-group input[type="tel"], .xoo-aff-group input[type="file"], .xoo-aff-group select, .xoo-aff-group select + .select2': {
                       'background-color': inputBgColor,
                       'color': inputTxtColor,
                       'height': inputHeight,
                       'line-height': inputHeight,
                       'border-radius': inputBorder.borderRadius,
                       'border': inputBorder.border,
                       'font-size': inputFontSize,
                       'padding': '12px',
                     },

                     '.xoo-aff-input-group input[type="text"], .xoo-aff-input-group input[type="password"], .xoo-aff-input-group input[type="email"], .xoo-aff-input-group input[type="number"], .xoo-aff-input-group select, .xoo-aff-input-group select + .select2, .xoo-aff-input-group input[type="tel"], .xoo-aff-input-group input[type="file"]': {
                       'padding-left': iconAlign === 'left' && showIcons ? iconPadding + 'px' : '12px',
                       'padding-right': iconAlign === 'right' && showIcons ? iconPadding + 'px' : '12px'
                     },

                     '.xoo-aff-group input:focus, .xoo-aff-group select:focus, .xoo-aff-group select + .select2:focus': {
                       'background-color': focusBgColor,
                       'color': focusTxtColor,
                       'border': inputBorderFocus.border,
                       'border-radius': inputBorderFocus.borderRadius
                     },

                     '.xoo-aff-group input::placeholder, .xoo-aff-group select::placeholder, .xoo-aff-group .select2-selection__rendered': {
                       'color': inputTxtColor
                     },

                     '[placeholder]:focus::-webkit-input-placeholder': {
                       'color': focusTxtColor + '!important'
                     }
                   };

                   /* === Icon border trimming === */
                   selectors['.xoo-aff-isfocused.xoo-aff-group .xoo-aff-input-icon, .xoo-aff-input-group .xoo-aff-input-icon'] = iconAlign === 'left'
                     ? {
                         'border-right': 0,
                         'border-top-right-radius': 0,
                         'border-bottom-right-radius': 0
                       }
                     : {
                         'border-left': 0,
                         'border-top-left-radius': 0,
                         'border-bottom-left-radius': 0
                       };

                   /* === Hide icons === */
                   if (!showIcons) {
                     selectors['.xoo-aff-input-group .xoo-aff-input-icon'] = {
                       'display': 'none!important'
                     };
                   }

                   /* === Required symbol === */
                   if (this.setting('s-show-reqicon') === 'yes') {
                     selectors['.xoo-aff-cont-required:after'] = {
                       'content': '"*"',
                       'position': 'absolute',
                       'right': '5px',
                       'top': '2px',
                       'z-index': 10,
                       'font-weight': 600,
                       'opacity': 0.5
                     };

                     selectors['body.rtl .xoo-aff-cont-required:after'] = {
                       'left': '5px',
                       'right': 'auto'
                     };
                   }
			
				return selectors;

			},

			getPreviewHTMLData: function(){
				return Field.setPreviewHTMLData();
			},

			setPreviewHTMLData: function(){
				var data = {
					showIcons: this.setting('s-show-icons') === "yes",
					inputValue: $('.xoo-aff-input-preview').val()
				}

				return data;
			},


			onFocus: function(){
				$(this).closest('.xoo-aff-group').addClass('xoo-aff-isfocused');
			},

			onFocusOut: function(){
				$(this).closest('.xoo-aff-group').removeClass('xoo-aff-isfocused');
			}
		}

		Field.init();

		Customizer.pageLoading = false;
		Customizer.build();

	}

	/**
	 * Generate CSS border styles as an object.
	 *
	 * @param {Object} border
	 * @param {string|number} border.size
	 * @param {string} border.color
	 * @param {string} border.style
	 * @param {string|number} border.radius
	 * @param {'border'|'radius'|'all'} [returnType='all']
	 *
	 * @returns {Object} CSS style object
	 */
	function generateBorderCSS(border = {}, returnType = 'all') {
	    const defaults = {
	        size: 0,
	        color: 'transparent',
	        style: 'none',
	        radius: 0,
	    };

	    const b = { ...defaults, ...border };

	    const size   = Math.max(0, parseFloat(b.size) || 0);
	    const radius = Math.max(0, parseFloat(b.radius) || 0);
	    const style  = String(b.style).toLowerCase().replace(/[^a-z]/g, '');
	    const color  = String(b.color).trim();

	    const css = {};

	    // Border
	    if (['border', 'all'].includes(returnType)) { 
	        css.border = `${size}px ${style} ${color}`;
	    }

	    // Radius
	    if (['radius', 'all'].includes(returnType)) {
	        css.borderRadius = `${radius}px`;
	    }

	    return css;
	}


	$('input[name="xoo-easy-login-woocommerce-fields-options[s-new-layout]"]').on('change', function(){
		var $preview = $('.xoo-as-field-preview');
		if( $(this).is(':checked')){
			$preview.show();
		}
		else{
			$preview.hide();
		}
	}).trigger('change')

});
