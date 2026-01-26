<?php

$video = $this->el('video', [

    'src' => $src,
    'playsinline' => true,
    'loop' => true,
    'muted' => true,
    'preload' => ['none {@!image_loading}'],

    'width' => $element['image_width'],
    'height' => $element['image_height'],

    'uk-video' => $element['video_autoplay'] ? [
        'autoplay: {video_autoplay};',
        'hover-target: !.tm-video-toggle {@video_autoplay: hover};',
        'restart: true; {@video_autoplay_restart} {@video_autoplay}',
    ] : true,

]);

return $video;
