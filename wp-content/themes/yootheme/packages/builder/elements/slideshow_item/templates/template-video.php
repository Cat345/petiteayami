<?php

if ($iframe = $this->iframeVideo($src)) {

    $video = $this->el('iframe', [

        'src' => $iframe,
        'loading' => $element['image_loading'] && $i === 0 ? 'lazy' : null,
        'title' => $props['video_title'],

        'class' => [
            'uk-disabled',
        ],

        'uk-video' => [
            'automute: true;',
        ],

    ]);

} else {

    $video = $this->el('video', [

        'src' => $src,
        'controls' => false,
        'loop' => true,
        'autoplay' => true,
        'muted' => true,
        'playsinline' => true,
        'preload' => $element['image_loading'] && $i === 0 ? 'none' : null,

        'uk-video' => true,

        'class' =>  [
            'uk-object-{0}' => $focal,
        ],

    ]);

}

$video->attr([

    'width' => $element['image_width'],
    'height' => $element['image_height'],

]);

return $video;
