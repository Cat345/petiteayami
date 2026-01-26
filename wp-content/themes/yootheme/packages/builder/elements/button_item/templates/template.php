<?php

$props['id'] = "js-{$this->uid()}";

// Button
$el = $this->el('a', [

    'class' => [
        'el-content',
        'uk-width-1-1 {@fullwidth}',
        'uk-{button_style: link-\w+}' => ['button_style' => $props['button_style']],
        'uk-button uk-button-{!button_style: |link-\w+} [uk-button-{button_size}]' => ['button_style' => $props['button_style']],
        'uk-flex-inline uk-flex-center uk-flex-middle' => $props['content'] && $props['icon'],
    ],

    'title' => $props['link_title'],
    'aria-label' => $props['link_aria_label'],

]);

if (($props['link'] && $props['lightbox']) ||
    (!$props['link'] && $props['dialog'] && in_array($props['dialog_layout'], ['modal', 'offcanvas']))) {

    $el->attr([
        'href' => "#{$props['id']}",
        'uk-toggle' => true,
    ]);

} else {

    $el->attr([
        'href' => $props['link'],
        'target' => $props['link_target'] ? '_blank' : false,
        'download' => $props['link_download'],
        'rel' => [
            'nofollow' => $props['link_rel_nofollow'],
            'noreferrer' => $props['link_rel_noreferrer']
        ],
        'uk-scroll' => str_contains((string) $props['link'], '#'),
    ]);

}

// Icon
$icon = $this->el('span', [

    'class' => [
        'uk-margin-xsmall-right' => $props['content'] && $props['icon_align'] == 'left',
        'uk-margin-xsmall-left' => $props['content'] && $props['icon_align'] == 'right',
    ],
    'uk-icon' => $props['icon'],

]);

?>

<?= $el($element, $attrs) ?>

    <?php if ($props['icon'] && $props['icon_align'] == 'left') : ?>
    <?= $icon($props, '') ?>
    <?php endif ?>

    <?php if ($props['content'] != '') : ?>
    <?= $props['content'] ?>
    <?php endif ?>

    <?php if ($props['icon'] && $props['icon_align'] == 'right') : ?>
    <?= $icon($props, '') ?>
    <?php endif ?>

<?= $el->end() ?>

<?php if (($props['link'] && $props['lightbox']) || (!$props['link'] && $props['dialog'] && $props['dialog_layout'] == 'modal')) : ?>
<?= $this->render("{$__dir}/template-modal", compact('props')) ?>
<?php endif ?>

<?php if (!$props['link'] && $props['dialog'] && $props['dialog_layout'] == 'offcanvas') : ?>
<?= $this->render("{$__dir}/template-offcanvas", compact('props')) ?>
<?php endif ?>
