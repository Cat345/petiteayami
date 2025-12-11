<?php

use YOOtheme\Metadata;
use function YOOtheme\app;

$el = $this->el('nav', [
    'class' => 'uk-margin-medium-bottom',
    'aria-label' => __('Breadcrumb', 'yootheme'),
]);

$list = $this->el('ul', ['class' => ['uk-breadcrumb']]);
$li = $this->el('li');
$span = $this->el('span');

$position = 1;

// Structured data as JSON
$data = [
    '@context' => 'https://schema.org',
    '@type' => 'BreadcrumbList',
    'itemListElement' => []
];

if (!empty($schemaorgId)) {
    $data['@id'] = $schemaorgId;
}

foreach ($items as $key => $item) {
    if (!empty($item->link)) {
        $data['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'item' => [
                '@type' => 'WebPage',
                '@id' => $item->link,
                'name' => $item->name,
            ],
        ];
    } elseif ($key === array_key_last($items)) {
        $data['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position++,
            'item' => [
                'name' => $item->name,
            ],
        ];
    }
}

app(Metadata::class)->set(
    'script:schemaorg-breadcrumb',
    json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
    ['type' => 'application/ld+json']
);

?>

<?php if ($items) : ?>

<?= $el() ?>

    <?= $list() ?>

    <?php foreach ($items as $key => $item) : ?>

    <?php if (!empty($item->link)) : ?>
        <?= $li() ?>
            <a href="<?= $item->link ?>"><?= $span([], $item->name) ?></a>
    <?php elseif ($key !== array_key_last($items)) : ?>
        <li class="uk-disabled">
            <span><?= $item->name ?></span>
    <?php else : ?>
        <?= $li() ?>
            <?= $span([], ['aria-current' => 'page'], $item->name) ?>
    <?php endif ?>
        <?= $li->end() ?>
    <?php endforeach ?>

    <?= $list->end() ?>

<?= $el->end() ?>

<?php endif ?>
