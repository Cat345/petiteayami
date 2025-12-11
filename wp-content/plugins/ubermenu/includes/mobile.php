<?php

// Functions called when generating menu arguments
function ubermenu_get_mobile_header($config_id)
{
  $content = apply_filters('ubermenu_mobile_header', '', $config_id);
  if ($content) {
    return '<div class="ubermenu-mobile-header">' . $content . '</div>';
  }
  return '';
}
function ubermenu_get_mobile_footer($config_id)
{
  $content = apply_filters('ubermenu_mobile_footer', '', $config_id);
  if ($content) {
    return '<div class="ubermenu-mobile-footer">' . $content . '</div>';
  }
  return '';
}


// Filters for Mobile Header/Footer Content settings
add_filter('ubermenu_mobile_header', 'ubermenu_mobile_header_content', 10, 2);
function ubermenu_mobile_header_content($content, $config_id)
{
  $header_content = do_shortcode(ubermenu_op('mobile_modal_header_content', $config_id));
  return $content . $header_content;
}

add_filter('ubermenu_mobile_footer', 'ubermenu_mobile_footer_content', 10, 2);
function ubermenu_mobile_footer_content($content, $config_id)
{
  $footer_content = do_shortcode(ubermenu_op('mobile_modal_footer_content', $config_id));
  return $content . $footer_content;
}


// Filter that adds close button
add_filter('ubermenu_mobile_footer', 'ubermenu_mobile_footer_close', 10, 2);
function ubermenu_mobile_footer_close($content, $config_id)
{
  $btn_inner = ubermenu_get_mobile_close_button_x() . ' ' . __('Close', 'ubermenu');

  $custom_btn_text = ubermenu_op('mobile_modal_close_btn_text', $config_id);
  if ($custom_btn_text)
    $btn_inner = do_shortcode($custom_btn_text);

  $content .= ubermenu_mobile_close_button($btn_inner);
  return $content;
}

function ubermenu_mobile_close_button($content = '&times;', $classes = '')
{
  $atts = ubermenu_html_atts([
    'class' => trim("ubermenu-mobile-close-button $classes"),
  ]);
  return "<button {$atts}>{$content}</button>";
}

// Shortcode for mobile close button
add_shortcode('ubermenu_mobile_close_button', 'ubermenu_mobile_close_button_shortcode');
function ubermenu_mobile_close_button_shortcode($atts, $content = '')
{
  extract(
    shortcode_atts(
      array(
        'classes' => '',
      ),
      $atts,
      'ubermenu_mobile_close_button'
    )
  );

  if ($content === '') {
    $content = ubermenu_get_mobile_close_button_x();
  }


  return ubermenu_mobile_close_button($content, $classes);
}

function ubermenu_get_mobile_close_button_x()
{
  if (ubermenu_op('use_core_svgs', 'general') === 'on') {
    return ubermenu_get_essential_icon('times');
  }
  return '<i class="fas fa-times"></i>';
  // return '&times;';
}
