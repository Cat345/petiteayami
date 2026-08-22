<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<script type="text/html" id="tmpl-xoo-as-btntheme">

	<?php $id = $field_id.'[{{data.theme_id}}]' ?>

	<?php

	$units = array(
		'px' => 'px',
		'%'  => '%',
		'em' => 'em',
		'rem'=> 'rem'
	);


	$fontWeight = array(
		'300' => 300,
		'400' => 400,
		'500' => 500,
		'600' => 600,
		'700' => 700,
	);

	$fontStyle = array(
		'normal' => 'Normal',
		'italic' => 'Italic',
	);

	$styles = array(
		'none'   => 'None',
		'hidden' => 'Hidden',
		'solid'  => 'Solid',
		'dashed' => 'Dashed',
		'dotted' => 'Dotted',
		'double' => 'Double',
		'groove' => 'Groove',
		'ridge'  => 'Ridge',
		'inset'  => 'Inset',
		'outset' => 'Outset',
	);
	?>

	<div class="xoo-btntheme xoo-accordion xoo-btn-setting" data-field_id="<?php echo esc_attr($id) ?>">

		<div class="xoo-acc-head xoo-theme-head">
	
			<div class="xoo-btntheme-title">{{data.title}}</div>
			<span class="dashicons dashicons-trash xoo-btntheme-delete"></span>

			<div class="xoo-btn-preview-wrap">

				<div class="xoo-btn-preview">
					<button type="button">Button</button>
				</div>

				<style id="{{data.theme_id}}"></style>

			</div>

			<div class="xoo-acc-ctas">
				<div class="xoo-acc-ctaedit"><span class="xoo-as-icon xoo-icon-edit"></span>Edit</div>
				<div class="xoo-acc-ctaclose xoo-acc-ctaedit"><span class="xoo-as-icon xoo-icon-close"></span>Close</div>
				<div class="xoo-acc-ctacopy"><span class="xoo-as-icon xoo-icon-copy"></span>Duplicate</div>
				<div class="xoo-acc-ctadel"><span class="xoo-as-icon xoo-icon-delete"></span></div>
			</div>
		</div>

		<div class="xoo-acc-cont">

			<div class="xoo-btntheme-head">
				<span class="xoo-btnset-desc">Customize the appearance of your button</span>
				<input type="text" value="{{data.title}}" name="<?php echo esc_attr($id) ?>[title]" class="xoo-btntheme-title-input">
			</div>

			<div class="xoo-tabs-cont">

				<input type="hidden" value="{{data.theme_id}}" name="<?php echo esc_attr($id) ?>[theme_id]" class="xoo-btntheme-id">

					<div class="xoo-setting-tabs">

						<span class="xoo-set-tab xoo-tabactive" data-xootab="normal"><span class="xoo-icon-light xoo-icon"></span>Normal</span>

						<span class="xoo-set-tab" data-xootab="hover"><span class="xoo-icon-cursor xoo-icon"></span>Hover</span>

					</div>

					<!-- NORMAL -->
					<div class="xoo-btn-group xoo-tabgroup xoo-tabactive" data-xootab="normal">

						<!-- Colors -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-paint xoo-icon"></span>Colors</span>

							<div class="xoo-row-settings">

								<div>
									<i>Background</i>
									<input type="text" class="xoo-as-color-input" name="<?php echo esc_attr($id); ?>[bgColor]" value="{{data.bgColor}}" >
								</div>

								<div>
									<i>Text Color</i>
									<input type="text" class="xoo-as-color-input" name="<?php echo esc_attr($id); ?>[txtColor]" value="{{data.txtColor}}" >
								</div>

							</div>

						</div>

						<!-- Size -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-ruler xoo-icon"></span>Size</span>

							<div class="xoo-row-settings">

								<div class="xoo-btnrowset-sizetype">

									<i>Size Type</i>

									<select name="<?php echo esc_attr($id) ?>[size_type]">
										<?php $adminObj->templatejs_select_options( 'size_type', array(
											'auto' 		=> 'Auto width & height',
											'custom' 	=> 'Custom Size'
										) ) ?>
									</select>

								</div>

								<div data-size_type="auto">

									<i>Padding ↨ </i>
									<input type="number" name="<?php echo esc_attr($id); ?>[padding_v]" value="{{data.padding_v}}" >

								</div>

								<div data-size_type="auto">

									<i>Padding ⟷</i>
									<input type="number" name="<?php echo esc_attr($id); ?>[padding_h]" value="{{data.padding_h}}" >

								</div>

								<div data-size_type="custom">

									<i>Custom Width</i>
									<input type="number" name="<?php echo esc_attr($id); ?>[width]" value="{{data.width}}" >

								</div>

								<div data-size_type="custom">

									<i>Unit</i>

									<select name="<?php echo esc_attr($id) ?>[width_unit]">
										<?php $adminObj->templatejs_select_options( 'width_unit', $units ) ?>
									</select>

								</div>

								<div  data-size_type="custom">

									<i>Custom Height</i>
									<input type="number" name="<?php echo esc_attr($id); ?>[height]" value="{{data.height}}" >	

								</div>



								<div data-size_type="custom">

									<i>Unit</i>
									<select name="<?php echo esc_attr($id) ?>[height_unit]">
										<?php $adminObj->templatejs_select_options( 'height_unit', $units ) ?>
									</select>

								</div>

							</div>

						</div>

						<!-- Text -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-font xoo-icon"></span>Text</span>

							<div class="xoo-row-settings">

								<div>

									<i>Weight</i>

									<select name="<?php echo esc_attr($id) ?>[text][fontWeight]">
										<?php $adminObj->templatejs_select_options( 'text.fontWeight', $fontWeight ) ?>
									</select>

								</div>

								<div>

									<i>Style</i>

									<select name="<?php echo esc_attr($id) ?>[text][fontStyle]">
										<?php $adminObj->templatejs_select_options( 'text.fontStyle', $fontStyle ) ?>
									</select>

								</div>

								<div>

									<i>Font Size</i>

									<input type="number" name="<?php echo esc_attr($id); ?>[text][fontSize]" value="{{data.text.fontSize}}">

								</div>

								<div>

									<i>Unit</i>

									<select name="<?php echo esc_attr($id) ?>[text][fontSizeUnit]">
										<?php $adminObj->templatejs_select_options( 'text.fontSizeUnit', $units ) ?>
									</select>

								</div>

								<div>

									<i>Transform</i>

									<select name="<?php echo esc_attr($id) ?>[text][textTransform]">
										<?php $adminObj->templatejs_select_options( 'text.textTransform', array(
											'none' => 'None',
											'lowercase' => 'Lowercase',
											'uppercase' => 'Uppercase',
											'capitalize' => 'Capitalize'
										) ) ?>
									</select>

								</div>

							</div>

						</div>

						<!-- Border -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-border xoo-icon"></span>Border</span>
							
							<div class="xoo-row-settings">

								<div>
									<i>Size</i>
									<input name="<?php echo esc_attr($id); ?>[border][size]" type="number" min="0" value="{{data.border.size}}">
								</div>

								<div>
									<i>Color</i>
									<input name="<?php echo esc_attr($id); ?>[border][color]" type="text" class="xoo-as-color-input" value="{{data.border.color}}">
								</div>

								<div>

									<i>Style</i>
									<select name="<?php echo esc_attr($id); ?>[border][style]">

										<?php $adminObj->templatejs_select_options( 'border.style', $styles ) ?>

									</select>


								</div>

								<div>
									<i>Radius</i>
									<input name="<?php echo esc_attr($id); ?>[border][radius]" type="number" min="0" value="{{data.border.radius}}">
								</div>

							</div>
						</div>

						<!-- Align -->
						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-ruler xoo-icon"></span>Align</span>

							<div class="xoo-row-settings">

								<div class="xoo-btnrowset-aligntype">

									<i>Position</i>

									<select name="<?php echo esc_attr($id) ?>[position]">
										<?php $adminObj->templatejs_select_options( 'position', array(
											'left' 		=> 'Left',
											'center' 	=> 'Center',
											'right' 	=> 'Right'
										) ) ?>
									</select>

								</div>

								<div>

									<i>Margin ↨ </i>
									<input type="number" name="<?php echo esc_attr($id); ?>[margin_v]" value="{{data.margin_v}}" >

								</div>

								<div>

									<i>Margin ⟷</i>
									<input type="number" name="<?php echo esc_attr($id); ?>[margin_h]" value="{{data.margin_h}}" >

								</div>

							</div>

						</div>

					</div>

					<!-- HOVER -->
					<div class="xoo-btn-group xoo-tabgroup" data-xootab="hover">

						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-paint xoo-icon"></span>Colors</span>

							<div class="xoo-row-settings">

								<div>
									<i>Background</i>
									<input type="text" class="xoo-as-color-input" name="<?php echo esc_attr($id); ?>[hover][bgColor]" value="{{data.hover.bgColor}}">									
								</div>

								<div>
									<i>Text Color</i>
									<input type="text" class="xoo-as-color-input" name="<?php echo esc_attr($id); ?>[hover][txtColor]" value="{{data.hover.txtColor}}" >
								</div>

							</div>

						</div>

						<div class="xoo-btn-row">

							<span class="xoo-btnrow-head"><span class="xoo-icon-border xoo-icon"></span>Border</span>
							
							<div class="xoo-row-settings">

								<div>
									<i>Size</i>
									<input name="<?php echo esc_attr($id); ?>[hover][border][size]" type="number" min="0" value="{{data.hover.border.size}}">
								</div>

								<div>
									<i>Color</i>
									<input name="<?php echo esc_attr($id); ?>[hover][border][color]" type="text" class="xoo-as-color-input" value="{{data.hover.border.color}}">
								</div>

								<div>

									<i>Style</i>
									<select name="<?php echo esc_attr($id); ?>[hover][border][style]">

										<?php $adminObj->templatejs_select_options( 'hover.border.style', $styles ) ?>

									</select>


								</div>

								<div>
									<i>Radius</i>
									<input name="<?php echo esc_attr($id); ?>[hover][border][radius]" type="number" min="0" value="{{data.hover.border.radius}}">
								</div>

							</div>
						</div>

					</div>

				</div>

		</div>

	</div>

</script>