<?php

// Title
$title = $this->el($element['title_element'], [

    'class' => [
        'el-title',
        'uk-{title_style}',
        'uk-heading-{title_decoration}',
        'uk-font-{title_font_family}',
        'uk-text-{title_color} {@!title_color: background}',
        'uk-link-{title_hover_style} {@title_link}', // Set here to style links which already come with dynamic content (WP taxonomy links)
        'uk-transition-{title_transition} {@overlay_hover}',
        'uk-margin[-{title_margin}]-top',
        'uk-margin-remove-bottom {@!title_margin_auto}',
        'uk-margin-auto-bottom {@title_margin_auto}',
    ],

]);

// Meta
$meta = $this->el($element['meta_element'], [

    'class' => [
        'el-meta',
        'uk-transition-{meta_transition} {@overlay_hover}',
        'uk-{meta_style}',
        'uk-text-{meta_color}',
        'uk-margin[-{meta_margin}]-top',
        'uk-margin-remove-bottom {@!meta_margin_auto}' => str_starts_with($element['meta_style'] ?? '', 'h') || $element['meta_element'] != 'div',
        'uk-margin-auto-bottom {@meta_margin_auto}',
    ],

]);

// Content
$content = $this->el('div', [

    'class' => [
        'el-content uk-panel',
        'uk-transition-{content_transition} {@overlay_hover}',
        'uk-{content_style}',
        'uk-margin[-{content_margin}]-top',
        'uk-margin-remove-bottom {@!content_margin_auto}' => str_starts_with($element['content_style'] ?? '', 'h'),
        'uk-margin-auto-bottom {@content_margin_auto}',
    ],

]);

// Link
$link_container = $this->el('div', [

    'class' => [
        'uk-margin[-{link_margin}]-top {@!link_margin: remove}',
        'uk-transition-{link_transition} {@overlay_hover}', // Not on link element to prevent conflicts with link style
    ],

]);

?>

<?php if ($props['meta'] != '' && $element['meta_align'] == 'above-title') : ?>
<?= $meta($element, $props['meta']) ?>
<?php endif ?>

<?php if ($props['title'] != '') : ?>
<?= $title($element) ?>
    <?php if ($element['title_color'] == 'background') : ?>
    <span class="uk-text-background"><?= $props['title'] ?></span>
    <?php elseif ($element['title_decoration'] == 'line') : ?>
    <span><?= $props['title'] ?></span>
    <?php else : ?>
    <?= $props['title'] ?>
    <?php endif ?>
<?= $title->end() ?>
<?php endif ?>

<?php if ($props['meta'] != '' && $element['meta_align'] == 'below-title') : ?>
<?= $meta($element, $props['meta']) ?>
<?php endif ?>

<?php if ($props['content'] != '') : ?>
<?= $content($element, $props['content']) ?>
<?php endif ?>

<?php if ($props['meta'] != '' && $element['meta_align'] == 'below-content') : ?>
<?= $meta($element, $props['meta']) ?>
<?php endif ?>

<?php if ($props['link'] && ($props['link_text'] || $element['link_text'])) : ?>
<?= $link_container($element, $link($element, $props['link_text'] ?: $element['link_text'])) ?>
<?php endif ?>
