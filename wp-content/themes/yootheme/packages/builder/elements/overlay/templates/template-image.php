<?php

$image = $this->el('image', [

    'src' => $src,
    'alt' => $props['image_alt'],
    'loading' => $props['image_loading'] ? false : null,
    'width' => $props['image_width'],
    'height' => $props['image_height'],
    'focal_point' => $focal,
    'thumbnail' => true,

]);

return $image;
