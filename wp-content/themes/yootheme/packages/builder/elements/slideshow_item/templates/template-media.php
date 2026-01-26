<?php

if ($props['video']) {
    $src = $props['video'];
    $focal = $props['image_focal_point'];
    $media = include "{$__dir}/template-video.php";
} elseif ($props['image']) {
    $src = $props['image'];
    $focal = $props['image_focal_point'];
    $media = include "{$__dir}/template-image.php";
} else {
    return;
}

// Media
$media->attr([

    'class' => [
        'el-image',
        'uk-blend-{media_blend_mode} {@!slideshow_animation: push|pull} {@!slideshow_kenburns}',
    ],

    'uk-cover' => true,
    'uk-video' => false,

]);

echo $media($element, '');
