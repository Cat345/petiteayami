<?php

namespace YOOtheme;

$el = $this->el('div');

// Form
$form = $this->el('form', [

    'id' => "search-{$this->uid()}",
    'action' => home_url('/'),
    'method' => 'get',
    'role' => 'search',
    'class' => [
        'uk-search',
        'uk-search-default {@!search_style}',
        'uk-search-{search_style}',
        'uk-width-1-1',
    ],

]);

// Search
$search = $this->el('input', [

    'name' => 's',
    'value' => get_search_query(),
    'type' => 'search',
    'placeholder' => __('Search', 'yootheme'),
    'class' => [
        'uk-search-input',
        'uk-form-{search_size} {@!search_style}',
    ],
    'required' => true,
    'aria-label' => __('Search', 'yootheme'),

]);

// Icon
$icon = $props['search_icon'] ? $this->el($props['search_icon'] == 'right' ? 'button' : 'span', [

    'uk-search-icon' => true,

    'class' => [
        'uk-search-icon-flip {@search_icon: right}',
    ],

]) : null;

if ($icon && $icon->name === 'button') {
    $icon->attr('type', 'submit');
}

$postTypes = app(Config::class)->get('~theme.search_filter', []);
$input = $this->el('input', [
    'tag' => 'input',
    'type' => 'hidden',
    'name' => 'post_type' . (app(Config::class)->get('~theme.search_page') === 'archive' && count($postTypes) === 1 ? '' : '[]'),
]);

?>

<?= $el($props, $attrs) ?>

    <?= $form($props) ?>

        <?php if ($props['search_icon'] == 'left') : ?>
        <?= $icon($props, '') ?>
        <?php endif ?>

        <?= $search($props) ?>

        <?php foreach (app(Config::class)->get('~theme.search_filter', []) ?: [] as $postType) : ?>
        <?= $input([], ['value' => $postType]) ?>
        <?php endforeach ?>

        <?php if ($props['search_icon'] == 'right') : ?>
        <?= $icon($props, '') ?>
        <?php endif ?>

    <?= $form->end() ?>

<?= $el->end() ?>
