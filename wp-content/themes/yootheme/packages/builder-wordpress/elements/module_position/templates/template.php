<?php

use YOOtheme\Theme\Widgets\WidgetsListener;
use function YOOtheme\app;

app(WidgetsListener::class)->position = $props;

$el = $this->el('div');

?>

<?= $el($props, $attrs) ?>
    <?php dynamic_sidebar("{$props['content']}:grid" . ($props['layout'] === 'stack' ? '-stack' : '')) ?>
<?= $el->end() ?>
