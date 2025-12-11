<label class="customizer-text" for="">
    <# if ( data.label ) { #>
    <span class="customize-control-title">{{{ data.label }}}</span>
    <ul class="wfocu-responsive-btns">
        <li class="desktop active">
            <button type="button" class="preview-desktop active" data-device="desktop">
                <i class="dashicons dashicons-desktop"></i>
            </button>
        </li>
        <li class="tablet">
            <button type="button" class="preview-tablet" data-device="tablet">
                <i class="dashicons dashicons-tablet"></i>
            </button>
        </li>
        <li class="mobile">
            <button type="button" class="preview-mobile" data-device="mobile">
                <i class="dashicons dashicons-smartphone"></i>
            </button>
        </li>
    </ul>
    <# } #>
    <# if ( data.description ) { #>
    <span class="description customize-control-description">{{{ data.description }}}</span>
    <# }

    value_desktop = '';
    value_tablet = '';
    value_mobile = '';

    if ( data.value['desktop'] ) {
    value_desktop = data.value['desktop'];
    }

    if ( data.value['tablet'] ) {
    value_tablet = data.value['tablet'];
    }

    if ( data.value['mobile'] ) {
    value_mobile = data.value['mobile'];
    } #>

    <div class="input-wrapper wfocu-responsive-wrapper">

        <input {{{ data.inputAttrs }}} data-id='desktop' class="wfocu-responsive-input desktop active" type="number" value="{{ value_desktop }}"/>
        <select class="wfocu-responsive-select desktop" data-id='desktop-unit' <# if ( _.size( data.units ) === 1 ) { #> disabled="disabled" <# } #>>
        <# _.each( data.units, function( value, key ) { #>
        <option value="{{{ key }}}"
        <# if ( data.value['desktop-unit'] === key ) { #> selected="selected" <# } #>>{{{ data.units[ key ]
        }}}</option>
        <# }); #>
        </select>

        <input {{{ data.inputAttrs }}} data-id='tablet' class="wfocu-responsive-input tablet" type="number" value="{{ value_tablet }}"/>
        <select class="wfocu-responsive-select tablet" data-id='tablet-unit' <# if ( _.size( data.units ) === 1 ) { #> disabled="disabled" <# } #>>
        <# _.each( data.units, function( value, key ) { #>
        <option value="{{{ key }}}"
        <# if ( data.value['tablet-unit'] === key ) { #> selected="selected" <# } #>>{{{ data.units[ key ]
        }}}</option>
        <# }); #>
        </select>

        <input {{{ data.inputAttrs }}} data-id='mobile' class="wfocu-responsive-input mobile" type="number" value="{{ value_mobile }}"/>
        <select class="wfocu-responsive-select mobile" data-id='mobile-unit' <# if ( _.size( data.units ) === 1 ) { #> disabled="disabled" <# } #>>
        <# _.each( data.units, function( value, key ) { #>
        <option value="{{{ key }}}"
        <# if ( data.value['mobile-unit'] === key ) { #> selected="selected" <# } #>>{{{ data.units[ key ]
        }}}</option>
        <# }); #>
        </select>

    </div>
</label>