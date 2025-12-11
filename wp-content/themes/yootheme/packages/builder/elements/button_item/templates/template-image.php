<?php

$image = $this->el('image', [

    'src' => $src,
    'alt' => true,
    'width' => $props['image_width'],
    'height' => $props['image_height'],
    'focal_point' => $props['image_focal_point'],
    'thumbnail' => true,

]);

return $image;
