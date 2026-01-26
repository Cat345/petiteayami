<?php

$image = $this->el('image', [

    'src' => $props['poster'],
    'alt' => true,
    'loading' => !$props['video_loading'] ? false : null,
    'width' => $props['video_width'],
    'height' => $props['video_height'],
    'focal_point' => $props['video_focal_point'],
    'thumbnail' => true,

]);

return $image;
