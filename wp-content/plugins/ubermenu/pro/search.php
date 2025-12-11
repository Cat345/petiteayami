<?php
//<input type="submit" class="ubermenu-search-submit" value="&#xf002;" />
function ubermenu_searchbar($placeholder = null, $post_type = '', $search_field_id = 'ubermenu-search-field', $search_label_sr = 'Search', $button_text_sr = 'Search', $autofocus = true, $autocomplete = "on")
{
	if (is_null($placeholder)) {
		$placeholder = __('Search...', 'ubermenu');
	}

	$toggle_icon_tag = ubermenu_op('icon_tag', 'main'); //just grab from the main config
	if (!$toggle_icon_tag || !in_array($toggle_icon_tag, ['span', 'i']))
		$toggle_icon_tag = 'i';

	$text_input_classes = 'ubermenu-search-input';
	if ($autofocus) {
		$text_input_classes .= ' ubermenu-search-input-autofocus';
	}


	?>
	<!-- UberMenu Search Bar -->
	<div class="ubermenu-search">
		<form role="search" method="get" class="ubermenu-searchform" action="<?php echo home_url('/'); ?>">
			<label for="<?php echo esc_attr($search_field_id); ?>">
				<span class="ubermenu-sr-only"><?php echo esc_attr($search_label_sr); ?></span>
			</label>
			<input type="text" placeholder="<?php echo esc_attr($placeholder); ?>" value="" name="s"
				class="<?php echo esc_attr($text_input_classes); ?>" id="<?php echo esc_attr($search_field_id); ?>"
				autocomplete="<?php echo $autocomplete; ?>" />
			<?php if ($post_type): ?>
				<input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>" />
			<?php endif; ?>
			<?php do_action('wpml_add_language_form_field'); ?>
			<button type="submit" class="ubermenu-search-submit">
				<<?php echo $toggle_icon_tag; ?> class="fas fa-search" title="Search" aria-hidden="true"></<?php echo $toggle_icon_tag; ?>>
				<span class="ubermenu-sr-only"><?php echo esc_attr($button_text_sr); ?></span>
			</button>
		</form>
	</div>
	<!-- end .ubermenu-search -->
	<?php
}

function ubermenu_searchbar_shortcode($atts, $content)
{

	extract(
		shortcode_atts(
			array(
				'placeholder' => __('Search...', 'ubermenu'),
				'post_type' => '',
				'search_field_id' => 'ubermenu-search-field',
				'search_label_sr' => __('Search', 'ubermenu'),
				'button_text_sr' => __('Search', 'ubermenu'),
				'autofocus' => 'on',
				'autocomplete' => 'on',

			),
			$atts
		)
	);

	$autofocus = $autofocus === 'on' ? true : false;

	ob_start();
	ubermenu_searchbar($placeholder, $post_type, $search_field_id, $search_label_sr, $button_text_sr, $autofocus, $autocomplete);
	$s = ob_get_clean();

	return $s;
}
add_shortcode('ubermenu-search', 'ubermenu_searchbar_shortcode');
