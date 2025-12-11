<?php

if ($iframe = $this->iframeVideo($props['video'])) {

    $video = $this->el('iframe', [

        'class' => [
            'uk-disabled',
        ],

        'src' => $iframe,
        'title' => $props['video_title'],

    ]);

} else {

    $video = $this->el('video', [

        'src' => $props['video'],
        'controls' => false,
        'loop' => true,
        'autoplay' => true,
        'muted' => true,
        'playsinline' => true,

        'class' => [
            'uk-object-{media_focal_point}',
        ],

    ]);

}

$video->attr([

    'width' => $props['video_width'],
    'height' => $props['video_height'],

    'class' => [
        'uk-blend-{media_blend_mode}',
        'uk-visible@{media_visibility}',
    ],

    'uk-cover' => true,

]);

return $video;
