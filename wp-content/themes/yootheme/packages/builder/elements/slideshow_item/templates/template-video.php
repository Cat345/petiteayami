<?php

$video = $this->el('video', [

    'src' => $src,
    'playsinline' => true,
    'loop' => true,
    'muted' => true,
    'preload' => $element['image_loading'] && $i === 0 ? null : 'none',

    'class' =>  [
        'uk-object-{0}' => $focal,
    ],

    'width' => $element['image_width'],
    'height' => $element['image_height'],

    'uk-video' => true,

]);

return $video;
