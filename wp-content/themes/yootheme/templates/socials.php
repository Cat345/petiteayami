<?php

$mobile = str_ends_with($position, '-mobile');
$header = '~theme.' . ($mobile ? 'mobile.header' :'header');
$links = (array) $config("{$header}.social_items", []);

$list = $this->el('ul', [
    'class' => [
        'uk-grid uk-flex-inline uk-flex-middle uk-flex-nowrap',
        'uk-grid-{social_gap}'
    ],
]);

$anchor = $this->el('a', [

    'href' => ['{link}'],

    'class' => [
        'uk-preserve-width',
        $config("{$header}.social_style") ? 'uk-icon-button' : 'uk-icon-link'
    ],

    'rel' => 'noreferrer',
    'target' => ['_blank' => $config("{$header}.social_target")],
    'aria-label' => ['{link_aria_label}'],

]);

?>

<?php if (count($links)) : ?>
    <?= $list($config($header)) ?>
        <?php foreach ($links as $link) :

            // Image
            if (!empty($link['image'])) {

                $icon = $this->el('image', [
                    'src' => $link['image'],
                    'alt' => true,
                    'loading' => false,
                    'width' => $config("{$header}.social_width") ?: 20,
                    'height' => $config("{$header}.social_width") ?: 20,
                    'uk-svg' => $config("{$header}.social_image_svg_inline"),
                    'thumbnail' => true,
                ]);

            // Icon
            } else {

                $icon = $this->el('span', [

                    'uk-icon' => [
                        'icon: {0};' => ($link['icon'] ?? '') ?: $this->e($link['link'] ?? '', 'social'),
                        'width: {0};' => $config("{$header}.social_width"),
                        'height: {0};' => $config("{$header}.social_width"),
                    ],

                ]);

            }

            ?>
            <li><?= $anchor($link, $icon([], '')) ?></li>
        <?php endforeach ?>
    <?= $list->end() ?>
<?php endif ?>
