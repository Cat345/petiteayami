<?php

// Title
$title = $this->el($props['title_element'], [

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
$meta = $this->el($props['meta_element'], [

    'class' => [
        'el-meta',
        'uk-transition-{meta_transition} {@overlay_hover}',
        'uk-{meta_style}',
        'uk-text-{meta_color}',
        'uk-margin[-{meta_margin}]-top',
        'uk-margin-remove-bottom {@!meta_margin_auto}' => str_starts_with($props['meta_style'] ?? '', 'h') || $props['meta_element'] != 'div',
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
        'uk-margin-remove-bottom {@!content_margin_auto}' => str_starts_with($props['content_style'] ?? '', 'h'),
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

<?php if ($props['meta'] != '' && $props['meta_align'] == 'above-title') : ?>
<?= $meta($props, $props['meta']) ?>
<?php endif ?>

<?php if ($props['title'] != '') : ?>
<?= $title($props) ?>
    <?php if ($props['title_color'] == 'background') : ?>
    <span class="uk-text-background"><?= $props['title'] ?></span>
    <?php elseif ($props['title_decoration'] == 'line') : ?>
    <span><?= $props['title'] ?></span>
    <?php else : ?>
    <?= $props['title'] ?>
    <?php endif ?>
<?= $title->end() ?>
<?php endif ?>

<?php if ($props['meta'] != '' && $props['meta_align'] == 'below-title') : ?>
<?= $meta($props, $props['meta']) ?>
<?php endif ?>

<?php if ($props['content'] != '') : ?>
<?= $content($props, $props['content']) ?>
<?php endif ?>

<?php if ($props['meta'] != '' && $props['meta_align'] == 'below-content') : ?>
<?= $meta($props, $props['meta']) ?>
<?php endif ?>

<?php if ($props['link'] && $props['link_text']) : ?>
<?= $link_container($props, $link($props, $props['link_text'])) ?>
<?php endif ?>
