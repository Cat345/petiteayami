<?php

// Config
$section = "~theme.{$name}";

// Empty ?
if (!is_active_sidebar($name) || !$config($section)) {
    return;
}

$class = ["tm-{$name}"];
$style = [
    "background-color: {$config("$section.background_color")};" => $config("$section.background_color") && !$config("$section.media_background") && !$config("$section.video") && !$config("$section.style"),
];
$attrs = [];
$attrs_overlay = [];
$attrs_container = [];
$attrs_viewport_height = [];
$attrs_image = [];
$attrs_video = [];
$attrs_section = [];

$attrs['tm-header-transparent-noplaceholder'] = $config("$section.header_transparent_noplaceholder");

// Section
$class[] = $config("$section.style") ? "uk-section-{$config("$section.style")}" : '';
$class[] = $config("$section.overlap") ? 'uk-section-overlap' : '';
$attrs_section['class'][] = 'uk-section';

// Image
if ($config("$section.image")) {

    $attrs_image = $this->bgImage($config("$section.image"), [
        'width' => $config("$section.image_width"),
        'height' => $config("$section.image_height"),
        'focal_point' => $config("$section.media_focal_point"),
        'loading' => $config("$section.image_loading") ? 'eager' : null,
        'size' => $config("$section.image_size"),
        'position' => $config("$section.image_position"),
        'visibility' => $config("$section.media_visibility"),
        'blend_mode' => $config("$section.media_blend_mode"),
        'background' => $config("$section.media_background"),
        'effect' => $config("$section.image_fixed") ? 'fixed' : false,
    ]);

    // Overlay
    if ($config("$section.media_overlay")) {
        $class[] = 'uk-position-relative';
        $attrs_overlay['style'] = "background-color: {$config("$section.media_overlay")};";
    }

}

// Video
if ($config("$section.video") && !$config("$section.image")) {

    // Settings
    $style[] = $config("$section.media_background") ? "background-color: {$config("$section.media_background")};" : '';
    $attrs_video['class'][] = $config("$section.media_blend_mode") ? "uk-blend-{$config("$section.media_blend_mode")}" : '';
    $attrs_video['class'][] = $config("$section.media_visibility") ? "uk-visible@{$config("$section.media_visibility")}" : '';

    // Cover
    $class[] = 'uk-cover-container';

    // Overlay
    $attrs_overlay['style'] = $config("$section.media_overlay") ? "background-color: {$config("$section.media_overlay")};" : '';

    // Video
    $attrs_video['width'] = $config("$section.video_width");
    $attrs_video['height'] = $config("$section.video_height");
    $attrs_video['uk-cover'] = true;

    if ($iframe = $this->iframeVideo($config("$section.video"))) {

        $attrs_video['class'][] = 'uk-disabled';

        $attrs_video['src'] = $iframe;

        $video = "<iframe{$this->attrs($attrs_video)}></iframe>";

    } else {

        $attrs_video['src'] = $config("$section.video");
        $attrs_video['controls'] = false;
        $attrs_video['loop'] = true;
        $attrs_video['autoplay'] = true;
        $attrs_video['muted'] = true;
        $attrs_video['playsinline'] = true;

        $attrs_video['class'][] = $config("$section.media_focal_point") ? "uk-object-{$config("$section.media_focal_point")}" : '';

        $video = "<video{$this->attrs($attrs_video)}></video>";
    }

} else {
    $video = '';
}

// Text color
$class[] = $config("$section.preserve_color") || ($config("$section.text_color") && ($config("$section.image") || $config("$section.video"))) ? 'uk-preserve-color' : '';
if (!$config("$section.style") || $config("$section.image") || $video) {
    $class[] = $config("$section.text_color") ? "uk-{$config("$section.text_color")}" : '';
}
if ($config("$section.header_transparent") && ($config("$section.text_color") != $config("$section.header_transparent_text_color") || !($config("$section.style") || $config("$section.image") || $video))) {
    $class[] = $config("$section.header_transparent_text_color") ? "uk-inverse-{$config("$section.header_transparent_text_color")}" : '';
}

// Padding
switch ($config("$section.padding")) {
    case '':
        break;
    case 'none':
        $attrs_section['class'][] = 'uk-padding-remove-vertical';
        break;
    default:
        $attrs_section['class'][] = "uk-section-{$config("$section.padding")}";
}

if ($config("$section.padding") != 'none') {
    if ($config("$section.padding_remove_top")) {
        $attrs_section['class'][] = 'uk-padding-remove-top';
    }
    if ($config("$section.padding_remove_bottom")) {
        $attrs_section['class'][] = 'uk-padding-remove-bottom';
    }
}

// Height Viewport
if ($config("$section.height")) {

    if ($config("$section.height") == 'page') {
        $attrs_section['uk-height-viewport'] = 'expand: true';
    } else {

        // Vertical alignment
        if ($config("$section.vertical_align") && $config("$section.height")) {
            $attrs_section['class'][] = "uk-flex uk-flex-{$config("$section.vertical_align")}";
            $attrs_viewport_height['class'][] = 'uk-width-1-1';
        }

        $offsetTop = $config("$section.height_offset_top");
        if ($config("$section.height") == 'viewport' && $config("$section.height_viewport") > 100) {
            $offsetTop = false;
        }

        if ($config("$section.height") == 'viewport' && !$offsetTop) {
            if (in_array($config("$section.height_viewport"), ['', 100])) {
                $attrs_section['class'][] = 'uk-height-viewport';
            } elseif (in_array($config("$section.height_viewport"), [200, 300, 400])) {
                $attrs_section['class'][] = 'uk-height-viewport-' . ((int) $config("$section.height_viewport") / 100);
            } else {
                $attrs_section['style'] = sprintf('min-height: %dvh;', $config("$section.height_viewport"));
            }
        } else {

            $options = $offsetTop ? ['offset-top: true'] : [];

            switch ($config("$section.height")) {
                case 'viewport':
                    if ($config("$section.height_viewport") && $config("$section.height_viewport") < 100) {
                        $options[] = 'offset-bottom: ' . (100 - (int) $config("$section.height_viewport"));
                    }
                    break;
                case 'section':
                    $options[] = $config("$section.image") ? 'offset-bottom: ! +' : 'offset-bottom: ~ * > [class*="uk-section"]';
                    break;
            }

            $attrs_section['uk-height-viewport'] = implode(';', array_filter($options));
        }
    }

}

// Container and width
switch ($config("$section.width")) {
    case 'default':
        $attrs_container['class'][] = 'uk-container';
        break;
    case 'xsmall':
    case 'small':
    case 'large':
    case 'xlarge':
    case 'expand':
        $attrs_container['class'][] = "uk-container uk-container-{$config("$section.width")}";
}

// Make sure overlay and video is always below content
if ($attrs_overlay || $video) {
    $attrs_container['class'][] = $config("$section.width") ? 'uk-position-relative' : 'uk-position-relative uk-panel';
}
?>

<div<?= $this->attrs(compact('class', 'style'), $attrs, !$attrs_image ? $attrs_section : []) ?>>

    <?php if ($attrs_image) : ?>
    <div<?= $this->attrs($attrs_image, $attrs_section) ?>>
    <?php endif ?>

        <?= $video ?>

        <?php if ($attrs_overlay) : ?>
        <div class="uk-position-cover"<?= $this->attrs($attrs_overlay) ?>></div>
        <?php endif ?>

        <?php if ($attrs_viewport_height) : ?>
        <div<?= $this->attrs($attrs_viewport_height) ?>>
        <?php endif ?>

            <?php if ($attrs_container) : ?>
            <div<?= $this->attrs($attrs_container) ?>>
            <?php endif ?>

                <?= $view('~theme/templates/position', ['style' => 'grid']) ?>

            <?php if ($attrs_container) : ?>
            </div>
            <?php endif ?>

        <?php if ($attrs_viewport_height) : ?>
        </div>
        <?php endif ?>

    <?php if ($attrs_image) : ?>
    </div>
    <?php endif ?>

</div>
