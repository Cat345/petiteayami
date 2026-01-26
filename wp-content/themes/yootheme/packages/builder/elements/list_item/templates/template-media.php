<?php

if ($props['image']) {
    $src = $props['image'];
    $focal = $props['image_focal_point'];
    $media = include "{$__dir}/template-image.php";
} elseif ($props['icon']) {
    $media = include "{$__dir}/template-icon.php";
} else {
    return;
}

// Media
$media->attr([

    'class' => [
        'el-image',
    ],

]);

if ($props['image']) {

    $media->attr([

        'class' => [
            'uk-border-{image_border}',
        ],

    ]);

}

echo $media($element, '');
