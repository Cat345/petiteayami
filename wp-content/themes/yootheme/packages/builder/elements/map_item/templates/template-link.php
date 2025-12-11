<?php

$link = $props['link'] ? $this->el('a', [
    'href' => $props['link'],
    'aria-label' => $props['link_aria_label'] ?: $element['link_aria_label'],
    'target' => ['_blank {@link_target}'],
    'uk-scroll' => str_contains((string) $props['link'], '#'),
]) : null;

if ($link && $props['title'] != '' && $element['title_link']) {

    $props['title'] = $link($element, [], $this->striptags($props['title'])); // title_hover_style is set on title

}

if ($link && $props['image'] && $element['image_link']) {

    $props['image'] = $link($element, $props['image']);

}

if ($link && ($props['link_text'] || $element['link_text'])) {

    $link->attr('class', [
        'el-link',
        'uk-{link_style: link-(muted|text)}',
        'uk-button uk-button-{!link_style: |link-muted|link-text} [uk-button-{link_size}] [uk-width-1-1 {@link_fullwidth}]',
    ]);

}

return $link;
