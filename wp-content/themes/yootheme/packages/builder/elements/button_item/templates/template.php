<?php

$props['id'] = "js-{$this->uid()}";

// Button
$button = $this->el('a', [

    'class' => $this->expr([
        'el-content',
        'uk-width-1-1 {@fullwidth}',
        'uk-{button_style: link-\w+}' => ['button_style' => $props['button_style']],
        'uk-button uk-button-{!button_style: |link-\w+} [uk-button-{button_size}]' => ['button_style' => $props['button_style']],
        'uk-flex-inline uk-flex-center uk-flex-middle' => $props['content'] && $props['icon'],
    ], $element),

    'title' => ['{link_title}'],
    'aria-label' => ['{link_aria_label}'],

]);


if (($props['link'] && $props['link_target'] == 'modal') ||
    (!$props['link'] && $props['dialog'] && in_array($props['dialog_layout'], ['modal', 'offcanvas']))) {

    $button->attr([
        'href' => ['#{id}'],
        'uk-toggle' => true,
    ]);

} else {

    $button->attr([
        'href' => ['{link}'],
        'target' => ['_blank {@link_target}'],
        'uk-scroll' => str_contains((string) $props['link'], '#'),
    ]);

}

// Icon
$icon = $this->el('span', [

    'class' => [
        'uk-margin-small-right' => $props['content'] && $props['icon_align'] == 'left',
        'uk-margin-small-left' => $props['content'] && $props['icon_align'] == 'right',
    ],
    'uk-icon' => $props['icon'],

]);

?>

<?= $button($props, $attrs) ?>

    <?php if ($props['icon'] && $props['icon_align'] == 'left') : ?>
    <?= $icon($props, '') ?>
    <?php endif ?>

    <?php if ($props['content'] != '') : ?>
    <?= $props['content'] ?>
    <?php endif ?>

    <?php if ($props['icon'] && $props['icon_align'] == 'right') : ?>
    <?= $icon($props, '') ?>
    <?php endif ?>

<?= $button->end() ?>

<?php if (($props['link'] && $props['link_target'] == 'modal') || (!$props['link'] && $props['dialog'] && $props['dialog_layout'] == 'modal')) : ?>
<?= $this->render("{$__dir}/template-modal", compact('props')) ?>
<?php endif ?>

<?php if (!$props['link'] && $props['dialog'] && $props['dialog_layout'] == 'offcanvas') : ?>
<?= $this->render("{$__dir}/template-offcanvas", compact('props')) ?>
<?php endif ?>
