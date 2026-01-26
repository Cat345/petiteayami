<?php


// Config
$logo = '~theme.logo';
$header = '~theme.header';
$mobile = '~theme.mobile';

// Link
$attrs_link = [];
$attrs_link['href'] = $config('~theme.site_url');
$attrs_link['aria-label'] = __('Back to home', 'yootheme');
$attrs_link['class'][] = $class ?? '';
$attrs_link['class'][] = 'uk-logo';

if ($position == 'logo' && preg_match('/^(horizontal|stacked-center-split-[ab])/', $config("$header.layout"))) {
    $attrs_link['class'][] = 'uk-navbar-item';

    if ($config("$header.logo_padding_remove") && $config("$header.width") == 'expand' && preg_match('/^horizontal-(left|center|right|justify)$/', $config("$header.layout"))) {
        $attrs_link['class'][] = 'uk-padding-remove-left';
    }
}

if ($position == 'logo-mobile') {
    $attrs_link['class'][] = 'uk-navbar-item';

    if ($config("$mobile.header.logo_padding_remove") && $config("$mobile.header.layout") != 'horizontal-center-logo') {
        $attrs_link['class'][] = 'uk-padding-remove-left';
    }
}

// Function
$logo_img = fn($image, $width, $height, array $attrs = []) => $this->image([$image, 'thumbnail' => true], array_merge($attrs, [
    'width' => $width,
    'height' => $height,
    'loading' => '',
    'alt' => __($config("$logo.text", '', 'yootheme')),
    'uk-svg' => $config("$logo.image_svg_inline")
]));

// Logo
$logo_el = '';

if (in_array($position, ['dialog', 'dialog-mobile'])) {

    if ($config("$logo.image_dialog")) {
        $logo_el = $logo_img($config("$logo.image_dialog"), $config("$logo.image_dialog_width"), $config("$logo.image_dialog_height"));
    }

} elseif ($position == 'logo-mobile' && $config("$logo.image_mobile")) {

    $logo_el = $logo_img($config("$logo.image_mobile"), $config("$logo.image_mobile_width"), $config("$logo.image_mobile_height"));

    // Inverse
    if ($config("$logo.image_mobile_inverse")) {
        $logo_el .= $logo_img($config("$logo.image_mobile_inverse"), $config("$logo.image_mobile_width"), $config("$logo.image_mobile_height"), ['class' => ['uk-logo-inverse']]);
    }

} elseif ($config("$logo.image")) {

    $logo_el = $logo_img($config("$logo.image"), $config("$logo.image_width"), $config("$logo.image_height"));

    // Inverse
    if ($config("$logo.image_inverse")) {
        $logo_el .= $logo_img($config("$logo.image_inverse"), $config("$logo.image_width"), $config("$logo.image_height"), ['class' => ['uk-logo-inverse']]);
    }

} else {
    $logo_el = __($config("$logo.text", '', 'yootheme'));
}

?>

<?php if ($logo_el) : ?>
<a<?= $this->attrs($attrs_link) ?>>
    <?= $logo_el ?>
</a>
<?php endif ?>
