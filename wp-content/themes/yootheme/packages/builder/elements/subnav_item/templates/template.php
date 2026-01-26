<?php

// Image
$props['image'] = $this->render("{$__dir}/template-media", compact('props', 'element'));

// Link
$el = $this->el('a');

if ($props['link']) {

    $el->attr([

        'class' => [
            'el-link',
            'uk-link-{link_style}',
        ],

        'href' => $props['link'],
        'target' => $props['link_target'] ? '_blank' : false,
        'download' => $props['link_download'],
        'rel' => [
            'nofollow' => $props['link_rel_nofollow'],
            'noreferrer' => $props['link_rel_noreferrer']
        ],
        'uk-scroll' => str_contains((string) $props['link'], '#'),

    ]);

} else {

    $el->attr([

        'class' => [
            'el-content uk-disabled',
        ],

    ]);

}

?>

<?= $el($element, $attrs) ?>

    <?= $props['image'] ?>
    <?= $props['content'] ?>

<?= $el->end() ?>
