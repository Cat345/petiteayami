<?php

$el = $this->el('div', [

    'uk-accordion' => [
        'multiple: {multiple};',
        'collapsible: {0};' => $props['collapsible'] ? 'true' : 'false',
    ],

]);

?>

<?= $el($props, $attrs) ?>

    <?php foreach ($children as $child) : ?>
    <?= $builder->render($child, ['element' => $props]) ?>
    <?php endforeach ?>

<?= $el->end() ?>
