<?php

$image = $this->el('image', [

    'src' => $src,
    'alt' => $props['image_alt'],
    'loading' => $element['image_loading'] && $i === 0 ? false : null,
    'width' => $element['image_width'],
    'height' => $element['image_height'],
    'focal_point' => $focal,
    'thumbnail' => true,

]);

return $image;
