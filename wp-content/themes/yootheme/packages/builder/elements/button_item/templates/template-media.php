<?php

$src = $props['link'];

if ($this->isImage($src)) {
    $media = include "{$__dir}/template-image.php";
} elseif ($this->isVideo($src) || $this->iframeVideo($src)) {
    $media = include "{$__dir}/template-video.php";
} else {

    // Open linked page in modal
    $media = $this->el('iframe', [

        'src' => $src,
        'width' => $props['image_width'],
        'height' => $props['image_height'],
        'allow' => 'autoplay',
        'allowfullscreen' => true,
        'uk-responsive' => true,
        'loading' => 'lazy',

    ]);

}

// Media
$media->attr([

    'class' => [
        'el-dialog',

        'uk-object-cover [uk-object-{image_focal_point}] {@link_target: offcanvas}' => $this->isImage($src),
    ],

    'style' => [
        'width: 100%; height: 100%; {@link_target: offcanvas}',
    ],

]);

?>

<?php if ($media) : ?>
<?= $media($props, '') ?>
<?php endif ?>
