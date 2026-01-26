<?php

$image = $this->el('image', [

    'src' => $props['consent_placeholder_image'],
    'alt' => true,
    'width' => $props['width'],
    'height' => $props['height'],
    'thumbnail' => true,

]);

return $image;
