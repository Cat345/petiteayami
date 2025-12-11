<?php

if (isset($prefix)) {
    echo "<!-- Builder #{$prefix} -->";
}

// Add elements inline css above the content to ensure css is present when rendered
if (!empty($props['css'])) {
    echo $this->el('style', [
        'class' => 'uk-margin-remove-adjacent',
        'data-id' => !empty($attrs['data-id']) ? "{$attrs['data-id']}-style" : false,
    ])([], $props['css']);
}

echo $builder->render($children);
