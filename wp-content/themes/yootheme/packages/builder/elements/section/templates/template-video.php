<?php

$video = $this->el('video', [

    'src' => $props['video'],
    'playsinline' => true,
    'loop' => true,
    'muted' => true,
    'preload' => ['none {@!image_loading}'],

    'width' => $props['image_width'],
    'height' => $props['image_height'],

    'class' => [
        'uk-object-{media_focal_point}',
        'uk-blend-{media_blend_mode}',
        'uk-visible@{media_visibility}',
    ],

    'uk-cover' => true,

]);

return $video;
