<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<div class="xoo-as-field-preview-style"></div>
<div class="xoo-as-field-preview"></div>

<script type="text/html" id="tmpl-xoo-as-field-preview">
	<div class="xoo-aff-field-preview">
		<div class="xoo-aff-group xoo-aff-cont-text one xoo-aff-cont-required xoo-el-username_cont">
			<div class="xoo-aff-input-group">
				<# if ( data.showIcons ) { #>
				<span class="dashicons dashicons-email xoo-aff-input-icon"></span>
				<# } #>
				<input type="text" class="xoo-aff-required xoo-aff-text xoo-aff-input-preview" placeholder="Email"  autocomplete="email" value="{{data.inputValue}}">
			</div>
		</div>
	</div>
</script>