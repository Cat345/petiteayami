<label class="customizer-text">
	<# if ( data.label ) { #><span class="customize-control-title er">{{{ data.label }}}</span><# } #>
	<# if ( data.description ) { #><span class="description customize-control-description">{{{ data.description }}}</span><# } #>
</label>

<div id="input_{{ data.id }}" class="image  wfocu_image_full">
	<# for ( key in data.choices ) { #>
	<input {{{ data.inputAttrs }}} class="image-select" type="radio" value="{{ key }}" name="_customize-radio-{{ data.id }}" id="{{ data.id }}{{ key }}" {{{ data.link }}}<# if ( data.value === key ) { #> checked="checked"<# } #>>
	<label for="{{ data.id }}{{ key }}" {{{ data.labelStyle }}}>
		<img class="" src="{{ data.choices[ key ] }}">
		<span class="image-clickable" title="{{ data.choices_titles[ key ] }}"></span>
	</label>
	</input>
	<# } #>
</div>