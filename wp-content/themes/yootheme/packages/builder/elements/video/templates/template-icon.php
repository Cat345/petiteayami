<?php

$icon = $this->el('image', [

    'src' => $props['play_icon'],
    'alt' => true,
    'loading' => !$props['video_loading'] ? false : null,
    'width' => $props['play_icon_width'],
    'height' => $props['play_icon_height'],
    'uk-svg' => $props['play_icon_svg_inline'],
    'thumbnail' => true,

    'class' => [
        'uk-icon-overlay {@play_icon_svg_inline}' => $this->isImage($props['play_icon']) == 'svg',
    ],

]);

return $icon;
