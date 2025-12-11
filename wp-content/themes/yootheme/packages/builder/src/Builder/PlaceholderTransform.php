<?php

namespace YOOtheme\Builder;

class PlaceholderTransform
{
    /**
     * Transform callback.
     *
     * @param object $node
     * @param array  $params
     */
    public function __invoke($node, array $params)
    {
        $type = $params['type'];

        // Placeholder props
        if (
            isset($type->placeholder['props']) &&
            !array_any($type->placeholder['props'], fn($value, $key) => isset($node->props[$key]))
        ) {
            $node->props = array_merge($node->props, $type->placeholder['props']);
        }

        // Placeholder children
        if (isset($type->placeholder['children']) && empty($node->children)) {
            $node->children = array_map(
                fn($value) => (object) $value,
                $type->placeholder['children'],
            );
        }
    }
}
