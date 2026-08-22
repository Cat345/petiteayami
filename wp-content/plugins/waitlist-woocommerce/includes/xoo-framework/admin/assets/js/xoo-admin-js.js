jQuery(document).ready(function($){

	window.xoo_admin_params.debounce = function( fn, delay ) {

		let timer;

		return function() {

			clearTimeout( timer );

			const args = arguments;
			const context = this;

			timer = setTimeout( function() {
				fn.apply( context, args );
			}, delay );

		};

	};

	//Form reset
	$('.xoo-as-form-reset').click(function(e){
		if( !confirm( 'Are you sure?' ) )
			e.preventDefault();
	})

	//Toggle pro
	$('.xoo-as-pro-toggle').click(function(e){
		$('.xoo-settings-container').toggleClass('xoo-as-disable-pro');
	})

	$('.xoo-settings-container').addClass('xoo-as-disable-pro');

	var sectionScrollPositions = {}

	//Setting default position to 0
	$('ul.xoo-sc-tbar-tabs li').each( function(){
		sectionScrollPositions[ $(this).data('tab') ] = $('.xoo-sc-tbar-tabs').offset().top;

	} );


	var firstClick = true;

	function updateActiveSection(){

	    const hash = window.location.hash;

	    $('.xoo-as-setsbar-section').removeClass('xoo-active');

	    $('.xoo-as-setsbar-section[href="' + hash + '"]').addClass('xoo-active');
	}

	$(window).on('hashchange', updateActiveSection);

	updateActiveSection();

	//Switch Tabs
	$('ul.xoo-sc-tbar-tabs li').click(function(){

		if( !firstClick ){
			sectionScrollPositions[$('ul.xoo-sc-tbar-tabs li.xoo-sct-active').data('tab')] = $(window).scrollTop();
		}

		const activeClass 		= 'xoo-sct-active',
			  selectedTabID  	= $(this).data('tab'); 

		$('ul.xoo-sc-tbar-tabs li, .xoo-sc-tab-content, .xoo-as-setsections').removeClass(activeClass);

		$(this).addClass(activeClass);

		$('.xoo-as-container').attr( 'data-active_tab', selectedTabID );

		$('.xoo-as-setsections[data-tab="'+selectedTabID+'"]').addClass(activeClass); //activating section

		$('.xoo-sc-tab-content[data-tab="'+selectedTabID+'"]').addClass(activeClass);

		if( !firstClick ){
			$(window).scrollTop( sectionScrollPositions[ selectedTabID ] );
		}
		
		firstClick = false;

	})

	$('ul.xoo-sc-tbar-tabs li:nth-child(1)').trigger('click');

	$('.xoo-as-form').on( 'submit', function(e){

		e.preventDefault();

		var $button = $(this).find('.xoo-as-form-save');
			$buttonHTML = $button.html();

		$button.text( 'Saving....' );

		var data = {
			'form': $(this).serialize(),
			'action': 'xoo_admin_settings_save',
			'xoo_ff_nonce': xoo_admin_params.nonce,
			'slug': xoo_admin_params.slug
		}

		$.ajax({
			url: xoo_admin_params.adminurl,
			type: 'POST',
			data: data,
			success: function(response){
				$button.text('Settings Saved');
				setTimeout(function(){
					$button.html( $buttonHTML )
				},2000)
			}
		});

	})



	//Media

	function renderMediaUploader(upload_btn) {
	 
	    var file_frame, image_data;
	 
	    /**
	     * If an instance of file_frame already exists, then we can open it
	     * rather than creating a new instance.
	     */
	    if ( undefined !== file_frame ) {
	 
	        file_frame.open();
	        return;
	 
	    }
	 
	    /**
	     * If we're this far, then an instance does not exist, so we need to
	     * create our own.
	     *
	     * Here, use the wp.media library to define the settings of the Media
	     * Uploader. We're opting to use the 'post' frame which is a template
	     * defined in WordPress core and are initializing the file frame
	     * with the 'insert' state.
	     *
	     * We're also not allowing the user to select more than one image.
	     */
	    file_frame = wp.media.frames.file_frame = wp.media({
	        frame:    'post',
	        state:    'insert',
	        multiple: false
	    });
	 
	    /**
	     * Setup an event handler for what to do when an image has been
	     * selected.
	     *
	     * Since we're using the 'view' state when initializing
	     * the file_frame, we need to make sure that the handler is attached
	     * to the insert event.
	     */
	    file_frame.on( 'insert', function() {
	 	
	        // Read the JSON data returned from the Media Uploader
   		 	var json = file_frame.state().get( 'selection' ).first().toJSON();

   		 	upload_btn.siblings('.xoo-upload-url').val(json.url);
   		 	upload_btn.siblings('.xoo-upload-title').html(json.filename);
   		
	 
	    });
	 
	    // Now display the actual file_frame
	    file_frame.open();
 
	}





	
    $( '.xoo-upload-icon' ).on( 'click', function( evt ) {

        // Stop the anchor's default behavior
        evt.preventDefault();

        // Display the media uploader
        renderMediaUploader($(this));

    });
 
   


    //Get media uploaded name
	$('.xoo-upload-url').each(function(){
		var media_url = $(this).val();
		if(!media_url) return true; // Skip to next if no value is set

		var index = media_url.lastIndexOf('/') + 1;
		var media_name = media_url.substr(index);

		$(this).siblings('.xoo-upload-title').html(media_name);
	})


	//Remove uploaded file
	$('.xoo-remove-media').on('click',function(){
		$(this).siblings('.xoo-upload-url').val('');
		$(this).siblings('.xoo-upload-title').html('');
	})


	//Initialize color picker
	$('.xoo-as-color-input').wpColorPicker();

	//initialize sortable
	$('.xoo-as-sortable-list').each( function( index, sortEl ){
		var $sortEl = $(sortEl),
			sortData = $sortEl.data('sort');
		$sortEl.sortable( sortData );
	} );


	$( 'select[data-select2box="yes"]' ).each(function(index, el){
		var $el = $(el);
		$el.select2({
			multiple: $el.attr('data-multiple')
		});
	});


	$('.xoo-as-exim').on( 'click', function(){
		$(this).toggleClass('xoo-as-active');
	} );


	//On export settings click
	$('.xoo-as-setexport').on( 'click', function(){
		var $form = $(this).closest('form.xoo-as-form');
		$('.xoo-as-exim').removeClass('xoo-as-active');
		$('body').addClass('xoo-as-exmodal-active');
		$('.xoo-as-excont textarea').val( JSON.stringify($form.serializeArray()) ).select();

		$('.xoo-as-impcont').hide();
		$('.xoo-as-excont').show();
	} );


	//Close import/export modal
	$('.xoo-as-exipclose').on( 'click', function(){
		$('body').removeClass('xoo-as-exmodal-active');
	} );



	//On import settings click
	$('.xoo-as-setimport').on( 'click', function(){
		$('.xoo-as-exim, .xoo-as-imported').removeClass('xoo-as-active');
		$('.xoo-as-impcont').show();
		$('.xoo-as-excont').hide();
		$('body').addClass('xoo-as-exmodal-active');
	} );


	$('.xoo-as-run-export').click( function(){

		$('.xoo-as-expdone').hide();

		var options = [];

		$('.xoo-as-expcheck input[type="checkbox"]:checked').each( function( index, el ){
			var $el = $(el);
			options.push($el.attr('value'));
		} )

		if( !options.length ) return;

		var $button = $('button.xoo-as-run-export ');

		$button.addClass('xoo-as-processing');
		$button.text( 'Please wait....' );


		var data = {
			'action': 'xoo_admin_settings_export',
			'xoo_ff_nonce': xoo_admin_params.nonce,
			'slug': xoo_admin_params.slug,
			'options': options
		}

		$.ajax({
			url: xoo_admin_params.adminurl,
			type: 'POST',
			data: data,
			success: function(response){
				$button.text('Export Success');
				

				setTimeout(function(){
					$button.text( 'Export' )
				},5000)
				$('.xoo-as-expdone').show();
				$('.xoo-as-expdone textarea').val(JSON.stringify(response)).select();
			}
		});

	} );

	$('button.xoo-as-run-import').click( function(){

		if( !confirm( 'This will override your current settings. Are you sure?' ) ) return;

		var textValue 	= $('.xoo-as-impcont textarea').val(),
			$button  	= $(this);

		$button.addClass('xoo-as-processing');
		$button.text( 'Please wait....' );

		var data = {
			'action': 'xoo_admin_settings_import',
			'xoo_ff_nonce': xoo_admin_params.nonce,
			'slug': xoo_admin_params.slug,
			'import': textValue
		}

		$.ajax({
			url: xoo_admin_params.adminurl,
			type: 'POST',
			data: data,
			success: function(response){
				$('.xoo-as-imported').addClass('xoo-as-active');
				$('.xoo-as-impcont textarea').val('');
				$button.text('Import Success');
				setTimeout(function(){
					$button.text( 'Import' );
					location.reload();
				},3000)
			}
		});

	})


	$('img.xoo-as-patimg').on('click', function(){

		var $cont 		= $(this).closest('.xoo-as-pattern-cont'),
			$checkbox  	= $(this).siblings('input[type="checkbox"]'),
			hasMultiple = $cont.data('multiple') === "yes",
			isRequired 	= $cont.data('required') === "yes"; 

		if( hasMultiple ){
			if( isRequired && $cont.find('input[type="checkbox"]:checked').length === 1 && $checkbox.is(':checked')  ) return; //cannot uncheck last checked option if required
			$(this).toggleClass('xoo-as-patactive');
			$checkbox.prop('checked', function (i, val) { //toggle
				return !val;
			}).trigger('change');
		}
		else{
			$cont.find('img.xoo-as-patimg').removeClass('xoo-as-patactive');
			$(this).addClass('xoo-as-patactive');
			$cont.find('input[type="checkbox"]').prop('checked', false).trigger('change');
			$checkbox.prop('checked',true).trigger('change');
		}

	});

	$('.xoo-as-patcheckbox').each(function(index, el){
		var $el = $(el);
		if( $el.prop('checked') ){
			$el.siblings('img.xoo-as-patimg').addClass('xoo-as-patactive');
		}
	});

	$('.xoo-as-info-hover').hover(
		function() {
			$(this).closest('.xoo-as-pattern-cont').find('.xoo-as-info[data-key="'+$(this).data('key')+'"]').show();
		},
		function() {
			$('.xoo-as-info').hide();
		}
	);


	$('.xoo-as-form').on('change', ':input', function() {

		//Value based description
		let $fieldCont 		= $(this).closest('.xoo-as-field'),
			$settingCont 	= $(this).closest( '.xoo-as-setting' ),
			fieldVal 		= $(this).val(),
			fieldId 		= $settingCont.data('field_id');

		if( $(this).is(':checkbox') && !$(this).is(':checked') ){
			fieldVal = 'unchecked';
		}

		if( $fieldCont.length && $fieldCont.find('.xoo-as-val-desc').length ){


			let $valueDesc 	= $fieldCont.find('.xoo-as-val-desc'),
				descData 	= $valueDesc.data('desc');

			$valueDesc.text('');

			if( descData[ $(this).val() ] ){
				$valueDesc.text( descData[ $(this).val() ] );
			}

		}

		//Toggle settings

		let toggleSettings = $settingCont.data('togglesettings');

		if( toggleSettings ){
			$.each( toggleSettings, function( settingID, settingValues ){

				let $setting = $('.xoo-as-setting[data-field_id="'+settingID+'"]');

				if( !$setting.length  ) return;

				let hiddenby = $setting.data('hiddenby' ) || {};

				if( settingValues.includes(fieldVal) ){
					hiddenby[ fieldId ] = 1;
				}
				else{
					delete hiddenby[ fieldId ];
				}
				

				$setting.attr('data-hiddenby', JSON.stringify(hiddenby));

				if( Object.keys(hiddenby).length ){
					$setting.hide();
				}
				else{
					$setting.show();
				}

				
				
			} )
		}

	});

	$('.xoo-as-setting[data-togglesettings] :input').trigger('change');


	setTimeout( function(){


		$(window).resize(function(){

			const $form 		= $('form.xoo-as-form');
			const $container 	= $('.xoo-as-container');

			if( $form.length ){

				if( $form.innerWidth() <= 600 ){
					$('.xoo-as-sidebar').addClass('xoo-as-sbar-collapsed');
					$form.addClass('xoo-as-break');
				}
				else{
					$form.removeClass('xoo-as-break');
				}

			}

			if( $container.length ){
				if( $container.innerWidth() <= 950 ){
					if( !$('body').hasClass('folded') ){
						$('#collapse-button').trigger('click');
					}
					$container.addClass('xoo-as-smaller');
				}
				else{
					$container.removeClass('xoo-as-smaller');
				}
			}

		}).trigger('resize');

	}, 400 );


	$('.xoo-as-sbar-close').on( 'click', function(){
		$('.xoo-as-sidebar').toggleClass('xoo-as-sbar-collapsed');
	} );


	$(document).on( 'click', '.xoo-set-tab', function(){

		var $trigger 	= $(this),
			target 		= $trigger.data('xootab'),
			$wrapper 	= $trigger.closest('.xoo-tabs-cont');

		$trigger.addClass('xoo-tabactive').siblings('[data-xootab]').removeClass('xoo-tabactive');

		$wrapper.find('[data-xootab]').removeClass('xoo-tabactive');

		$wrapper.find('[data-xootab="' + target + '"]').addClass('xoo-tabactive');

	});


	var BtnTheme = {

		themeTemplate: '',

		themeInputNames: {},

		$cont: $('.xoo-btntheme-cont'),

		init: function(){
			this.initTemplates();
			this.events(); 
			this.renderThemes(); //creates theme and settings on load
			this.loadThemeSelectorOptions(); // Button theme selector options added
		},


		events: function(){

			$('button.xoo-add-btntheme').on('click', BtnTheme.addTheme );

			$('body').on('click', '.xoo-acc-ctadel', BtnTheme.deleteTheme);

			$('body').on('click', '.xoo-acc-ctacopy', BtnTheme.copyTheme);

			$('body').on( 'input', '.xoo-btntheme-title-input', BtnTheme.onThemeTitleInput );

			$('body').on( 'change', '.xoo-btntheme-title-input', BtnTheme.loadThemeSelectorOptions );

			$('button.xoo-as-form-save').on( 'click', BtnTheme.beforeSettingsSave );

			$(document).ajaxComplete(BtnTheme.onSettingsSave);

			$(document).on( 'xoo_accordion_toggled', BtnTheme.onAccordionToggled );

			$(document).on( 'input change','.xoo-btn-setting input, .xoo-btn-setting select', xoo_admin_params.debounce( BtnTheme.onThemeSettingsChange, 200 ) );

			$('body').on( 'change', '.xoo-btnrowset-sizetype select', BtnTheme.onThemeSettingSizeChange );
			
		},


		onThemeSettingSizeChange: function(){
			BtnTheme.toggleSizeTypeFields( $(this).closest('.xoo-btntheme') );
		},

		toggleSizeTypeFields: function( $theme ){
			var $sizeSelectField = $theme.find( '.xoo-btnrowset-sizetype select');
			$theme.find('[data-size_type]').hide();
			$theme.find('[data-size_type="'+$sizeSelectField.val()+'"]').show();
		},

		onThemeSettingsChange: function(){
			BtnTheme.renderPreview( $(this).closest('.xoo-btntheme') );
		},

		getThemes: function( theme_id ) {

		    var formValues = BtnTheme.$cont.closest('form').serializeJSON(),
		        field_id   = BtnTheme.$cont.closest('.xoo-as-button_theme_creator').data('field_id');

		    const themes = field_id
		        .match(/[^[\]]+/g)
		        .reduce((current, key) => current?.[key], formValues);

		    if( theme_id === undefined ) {
		        return themes;
		    }

		    if( theme_id instanceof jQuery ) {
		        theme_id = theme_id.find('input.xoo-btntheme-id').val();
		    }


		    return themes?.[theme_id] || null;
		},

		loadThemeSelectorOptions: function(){

			console.log('loaded');

			var themes = BtnTheme.getThemes();

			if( !themes ) return;

			var optionsHTML = '';

			$.each( themes, function( theme_id, theme_data ){
				optionsHTML += '<option value="'+theme_id+'">'+theme_data['title']+'</option>';
			} );

			$('.xoo-as-setting[data-setting="button_theme_selector"]').each(function(index, el){

				const $select 		= $(el).find('select');

				const selectedVal 	= $select.val() || $select.data('default');

				$select.html(optionsHTML);

				if( $select.find('option').length ){
					if( selectedVal && $select.find('option[value="'+selectedVal+'"]').length ){
						$select.val( selectedVal );
					}
					else{
						$select.val( $select.find('option:first-child').val() );
					}
				}

			})
		},

		onAccordionToggled: function( e, $container, isOpened ){

		
		},


		themeNumbering: function(){
			$.each( $('.xoo-btntheme'), function( index, el ){
				var $el 		= $(el),
					$titleInput = $el.find('.xoo-btntheme-title-input');
				$titleInput.val( $titleInput.val().replace( '[%^]','#'+ (index + 1) ) ).trigger('input');
			} )
		},

		renderThemes(){

			var themes = JSON.parse( BtnTheme.$cont.attr('data-value') );

			if( !themes ) return;

			$.each( themes, function( index, themeData ){

				var $theme 	= $(BtnTheme.themeTemplate( themeData ) );

				$('.xoo-btnthemes').append( $theme );

				BtnTheme.onThemeAdd( $theme, false );
				
			} )

			BtnTheme.globalThemeInit();
			
		},


		onThemeAdd: function( $theme, callGlobal = true ){

			BtnTheme.initColorPicker($theme);

			BtnTheme.renderPreview($theme);

			BtnTheme.toggleSizeTypeFields( $theme );
			
			if( callGlobal ){
				BtnTheme.globalThemeInit();
				BtnTheme.loadThemeSelectorOptions();
			}
			
			
		},


		renderPreview: function( $theme ){

		    var css = BtnTheme.getCSS(
		        BtnTheme.getThemes( $theme.find('input.xoo-btntheme-id').val() ),
		        '.xoo-btn-setting[data-field_id="' + $theme.data('field_id') + '"] .xoo-btn-preview-wrap button'
		    );

		    $theme.find('.xoo-btn-preview-wrap style').html( css );

	
		},


		generateID: function(){
			return 'theme_'+crypto.randomUUID().replace(/-/g, '');
		},

		globalThemeInit: function(){
			BtnTheme.initSortable();
			BtnTheme.themeNumbering();
		},


		

		createTheme: function( values ){

			$('.xoo-btntheme').removeClass('xoo-acc-active');	

			values.theme_id = BtnTheme.generateID();

			var $theme = $(BtnTheme.themeTemplate( values ) );
	
			$('.xoo-btnthemes').append($theme);

			$theme.addClass('xoo-acc-active');

			BtnTheme.onThemeAdd($theme);
		},

		addTheme: function(){

			var defaults = JSON.parse( BtnTheme.$cont.attr('data-defaults') );

			BtnTheme.createTheme( defaults );
		},


	
		copyTheme: function(){

			var values = BtnTheme.getThemes( $(this).closest('.xoo-btntheme') );

			BtnTheme.createTheme( values );


		},

		onThemeTitleInput: function(){

			var $theme = $(this).closest('.xoo-btntheme');

			$theme.find('.xoo-btntheme-title').text($(this).val());

			

		},



		onSettingsSave: function(event,xhr,options){

			if( $(event.target.activeElement).hasClass('xoo-as-form-save') ){

				BtnTheme.$cont.removeClass('xoo-as-processing');

			}
		},

		beforeSettingsSave: function(){

			var $cont 	= BtnTheme.$cont,
				id 		= '[%$]';

			$cont.addClass('xoo-as-processing');

			
		},


		initColorPicker: function($theme){
			$theme.find('.xoo-as-color-input:not(.wp-color-picker)').wpColorPicker({
				change: function(event, ui){
					$(event.target).val(ui.color.toString()).trigger('change')
				}
			});
		},


		initSortable: function(){
			$('.xoo-btntheme-cont').sortable({
				handle: '.xoo-btntheme-head'
			});
		},

		initTemplates: function(){
			this.themeTemplate 	= wp.template('xoo-as-btntheme');
		},

		

		deleteTheme: function(e){
			if( !confirm( 'Are you sure you want to delete this Button Theme?' ) ){
				e.preventDefault();
				return;
			}
			$(this).closest('.xoo-btntheme').remove();

			BtnTheme.loadThemeSelectorOptions();
		},

		initIconPicker( $checkpoint ){

			$checkpoint.find('.xoo-wsc-bar-icon:not(.iconpicker-input)').iconpicker({
				hideOnSelect: true,
			}).on('iconpickerSelected', function(e){
			  $(e.target).next().attr('class',e.iconpickerValue || $(e.target).val() );
			}).trigger('iconpickerSelected');
			
		},

		getCSS: function( values, selectors ) {

		    selectors = Array.isArray( selectors ) ? selectors : [ selectors ];

		    var border      = values.border || {},
		        hover       = values.hover || {},
		        hoverBorder = hover.border || {},
		        text        = values.text || {};

		    var normalSelectors = selectors.join(','),
		        hoverSelectors = $.map( selectors, function( selector ) {
		            return selector + ':hover';
		        }).join(',');

		    var isAuto = values.size_type === 'auto';

		    return normalSelectors + '{' +

		        'max-width:' + ( isAuto ? 'none' : ( values.width || '' ) + ( values.width_unit || '' ) ) + ';' +
		        'width:' + ( isAuto ? 'auto' : '100%' ) + ';' +
		        'height:' + ( isAuto ? 'auto' : ( values.height || '' ) + ( values.height_unit || '' ) ) + ';' +
		        'padding:' + ( isAuto
		            ? ( values.padding_v || 0 ) + 'px ' + ( values.padding_h || 0 ) + 'px'
		            : '5px 10px'
		        ) + ';' +

		        'background-color:' + ( values.bgColor || '' ) + ';' +
		        'color:' + ( values.txtColor || '' ) + ';' +

		        'font-weight:' + ( text.fontWeight || 500 ) + ';' +
		        'font-style:' + ( text.fontStyle || 'normal' ) + ';' +
		        'font-size:' + ( text.fontSize || 15 ) + ( text.fontSizeUnit || 'px' ) + ';' +
		        'text-transform:' + ( text.textTransform || 'none' ) + ';' +

		        'border:' + ( border.size || 0 ) + 'px ' + ( border.style || 'solid' ) + ' ' + ( border.color || 'transparent' ) + ';' +
		        'border-radius:' + ( border.radius || 0 ) + 'px;' +
		        'display: flex;' +
				'align-items:center;' +
				'justify-content:center;'+

		    '}' +

		    hoverSelectors + '{' +

		        'background-color:' + ( hover.bgColor || values.bgColor || '' ) + ';' +
		        'color:' + ( hover.txtColor || values.txtColor || '' ) + ';' +
		        'border:' + ( hoverBorder.size || border.size || 0 ) + 'px ' + ( hoverBorder.style || border.style || 'solid' ) + ' ' + ( hoverBorder.color || border.color || 'transparent' ) + ';' +
		        'border-radius:' + ( hoverBorder.radius || border.radius || 0 ) + 'px;' +

		    '}';
		}

	}

	BtnTheme.init();

	xoo_admin_params.BtnTheme = BtnTheme;


	$('body').on( 'click', '.xoo-as-resetval', function(){

		var $settingCont = $(this).closest('.xoo-as-setting');

		if( $settingCont.data('setting') === 'wp_editor' && $settingCont.find('.wp-editor-area').length ){


			var editorId 	= $settingCont.find('.wp-editor-area').attr('id'),
			 	editor 		= tinymce.get(editorId);

			if (editor) {
			    editor.setContent(JSON.parse($(this).data('default')));
			    editor.save();
			    $('#' + editorId).trigger('change');
			}
		}

		
	} )



	function toggleAccordion( $container ){

		$content 	= $container.children('.xoo-acc-cont');

		$container.toggleClass('xoo-acc-active');

		$container.trigger( 'xoo_accordion_toggled', [ $container, $container.hasClass('xoo-acc-active') ] );
	}


	$('body').on('click', '.xoo-acc-ctaedit', function(){

		var $container 	= $(this).closest('.xoo-accordion');

		toggleAccordion( $container );

	});


	

	
})