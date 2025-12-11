<?php
/**
 * The template for displaying a search form.
 */

namespace YOOtheme;

/**
 * @var Config $config
 * @var View   $view
 */
[$config, $view] = app(Config::class, View::class);

$fields = [];
$postTypes = $config->get('~theme.search_filter', []);
foreach ($postTypes ?: [] as $postType) {
    $fields[] = [
        'tag' => 'input',
        'type' => 'hidden',
        'name' => 'post_type' . ($config->get('~theme.search_page') === 'archive' && count($postTypes) === 1 ? '' : '[]'),
        'value' => $postType,
    ];
}

$result = get_view('~theme/templates/search', [

    'position' => get_current_sidebar() ?? '',
    'attrs' => [

        'id' => 'search-' . $view->uid(),
        'action' => home_url('/'),
        'method' => 'get',
        'role' => 'search',
        'class' => '',

    ],
    'fields' => [

        [
            'tag' => 'input',
            'name' => 's',
            'placeholder' => __('Search', 'yootheme'),
            'value' => get_search_query(),
            'required' => true,
            'aria-label' => __('Search', 'yootheme'),
        ],

        ...$fields
    ],
    'language' => $config('locale.code'),

]);

if ($echo) {
    echo $result;
} else {
    return $result;
}
