<?php

// Add elements inline css above the content to ensure css is present when rendered
if (!empty($props['css'])) {
    echo $this->el('style', [
        'class' => 'uk-margin-remove-adjacent',
        'data-id' => !empty($attrs['data-id']) ? "{$attrs['data-id']}-style" : false,
    ])([], $props['css']);
}

$content = $builder->render($children);

if (!$props['root']) {
    $content = $this->el($props['html_element'] ?: 'div', [
        'class' => ['uk-panel']
    ])($props, $attrs, $content);
}

echo $content;

if (!empty($props['metadata'])) {
    echo "\n" . join("\n", $props['metadata']);
}
