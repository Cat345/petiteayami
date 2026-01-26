<?php

$image = $this->el('image', [

    'src' => $src,
    'alt' => $props['image_alt'],
    'loading' => $props['image_loading'] ? false : null,
    'width' => $props['image_width'],
    'height' => $props['image_height'],
    'focal_point' => $focal,
    'uk-svg' => $props['image_svg_inline'],
    'thumbnail' => true,

    'class' => [
        'uk-text-{image_svg_color} {@image_svg_inline}' => $this->isImage($src) == 'svg',
    ],

]);

return $image;
