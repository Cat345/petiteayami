<?php

$video = $this->el('video', [

    'src' => $src,
    'playsinline' => true,
    'loop' => true,
    'muted' => true,
    'preload' => ['none {@!image_loading}'],

    'width' => $props['image_width'],
    'height' => $props['image_height'],

    'uk-video' => $props['video_autoplay'] ? [
        'autoplay: {video_autoplay};',
        'hover-target: !.tm-video-toggle {@video_autoplay: hover};',
        'restart: true; {@video_autoplay_restart} {@video_autoplay}',
    ] : true,

]);

return $video;
