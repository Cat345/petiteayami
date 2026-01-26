<?php

if ($iframe = $this->iframeVideo($props['video'], [], false)) {

    $video = $this->el('iframe', [

        'src' => $iframe,
        'allowfullscreen' => true,
        'loading' => ['lazy {@video_loading}'],
        'title' => $props['video_title'],

        'uk-video' => $props['video_loading'] == 'click' ? true : null,

        'class' => ['uk-responsive-width'],

        'style' => ['aspect-ratio: {video_width} / {video_height}'],

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
        'playsinline' => $props['video_playsinline'],
        'loop' => $props['video_loop'],
        'muted' => $props['video_muted'],
        'preload' => ['none {@video_loading}'],

        'uk-video' => in_array($props['video_autoplay'], ['inview', 'hover'], true) ? [
            'autoplay: {video_autoplay};',
            'restart: true; {@video_autoplay_restart} {@video_autoplay}',
         ] : (($props['video_autoplay'] || $props['video_loading'] == 'click') ? true : 'false'),

    ]);

}

$video->attr([

    'width' => $props['video_width'],
    'height' => $props['video_height'],

]);

return $video;
