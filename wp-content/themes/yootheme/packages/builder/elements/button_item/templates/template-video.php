<?php

if ($iframe = $this->iframeVideo($src)) {

    $video = $this->el('iframe', [

        'src' => $iframe,
        'allowfullscreen' => true,
        'uk-responsive' => true,
        'loading' => 'lazy',

    ]);

} else {

    $video = $this->el('video', [

        'src' => $src,
        'controls' => true,

        'class' => [
            // Imitate cropping like an image
            'uk-object-cover [uk-object-{image_focal_point}] {@image_width} {@image_height}',
        ],

        'style' => [
            // Keep video responsiveness but with new proportions (because video isn't cropped like an image)
            'aspect-ratio: {image_width} / {image_height};',
        ],

    ]);

}

$video->attr([

    'width' => $props['image_width'],
    'height' => $props['image_height'],

    'uk-video' => true,

]);

return $video;
