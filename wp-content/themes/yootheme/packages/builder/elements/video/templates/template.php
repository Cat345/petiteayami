<?php

namespace YOOtheme;

use YOOtheme\Http\Uri;

/** @var ImageProvider $imageProvider */
$imageProvider = app(ImageProvider::class);

$el = $this->el('div', [

    'class' => [
        // Expand to column height
        'uk-flex-1 uk-flex {@height_expand}',
    ],

]);


// Video
if ($iframe = $this->iframeVideo($props['video'], [], false)) {

    $video = $this->el('iframe', [

        'src' => $iframe,
        'allow' => 'autoplay',
        'allowfullscreen' => true,
        'uk-responsive' => true,
        'loading' => ['lazy {@video_lazyload}'],
        'title' => $props['video_title'],

    ]);

    $ratio = $this->isYouTubeShorts($props['video']) ? 9 / 16 : 16 / 9;

    if ($props['video_width'] && !$props['video_height']) {
        $props['video_height'] = round((int) $props['video_width'] / $ratio);
    } elseif ($props['video_height'] && !$props['video_width']) {
        $props['video_width'] = round((int) $props['video_height'] * $ratio);
    }

} else {

    $video = $this->el('video', [

        'src' => $props['video'],
        'controls' => $props['video_controls'],
        'loop' => $props['video_loop'],
        'muted' => $props['video_muted'],
        'playsinline' => $props['video_playsinline'],
        'preload' => ['none {@video_lazyload}'],
        $props['video_autoplay'] === 'inview' ? 'uk-video' : 'autoplay' => $props['video_autoplay'],

        'class' => [
            'el-image',
            'uk-object-cover [uk-object-{image_focal_point}]' => $props['video_viewport_height'] || $props['height_expand'],
        ],

        'style' => [
            'height: 100vh; {@video_viewport_height} {@!height_expand}',
        ],

    ]);

    if ($props['video_poster']) {

        if ($props['video_width'] || $props['video_height']) {

            $thumbnail = [$props['video_width'], $props['video_height'], ''];
            if (!empty($props['video_poster_focal_point'])) {
                [$y, $x] = explode('-', $props['video_poster_focal_point']);
                $thumbnail += [3 => $x, 4 => $y];
            }

            $props['video_poster'] = (string) (new Uri($props['video_poster']))->withFragment('thumbnail=' . implode(',', $thumbnail));

        }

        $video->attr([
            'poster' => $imageProvider->getUrl($props['video_poster']),
        ]);

    } elseif ($props['video_width'] && $props['video_height']) {
        if (!$props['height_expand']) {
            $video->attr(['style' => ["aspect-ratio: {$props['video_width']} / {$props['video_height']};"]]);
        } else {
            // Fix bug in Safari not stretching an image beyond its intrinsic height
            $video->attr(['style' => ["aspect-ratio: auto;"]]);
        }
    }

}

$video->attr([

    'class' => [
        'uk-box-shadow-{video_box_shadow}',
        'uk-inverse-{text_color}',
    ],

    'width' => $props['video_width'],
    'height' => $props['video_height'],

]);

// Box decoration
$decoration = $this->el('div', [

    'class' => [
        'uk-box-shadow-bottom {@video_box_decoration: shadow}',
        'tm-mask-default {@video_box_decoration: mask}',
        'tm-box-decoration-{video_box_decoration: default|primary|secondary}',
        'tm-box-decoration-inverse {@video_box_decoration_inverse} {@video_box_decoration: default|primary|secondary}',
        'uk-inline {@!video_box_decoration: |shadow}',
        'uk-flex {@height_expand}',
    ],

]);

?>

<?= $el($props, $attrs) ?>

    <?php if ($props['video_box_decoration']) : ?>
    <?= $decoration($props) ?>
    <?php endif ?>

        <?= $video($props, '') ?>

    <?php if ($props['video_box_decoration']) : ?>
    <?= $decoration->end() ?>
    <?php endif ?>

<?= $el->end() ?>
