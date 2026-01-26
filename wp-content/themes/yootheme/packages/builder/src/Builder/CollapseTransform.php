<?php

namespace YOOtheme\Builder;

class CollapseTransform
{
    /**
     * Transform "preload" callback.
     *
     * @param array<string, mixed> $params
     */
    public static function preload(object $node, array $params): void
    {
        if ($params['context'] !== 'render') {
            return;
        }

        $node->parent = !empty($node->children) && $params['type']->container;
    }

    /**
     * Transform "render" callback.
     */
    public static function render(object $node): bool
    {
        return empty($node->parent) ||
            !empty($node->children) ||
            ($node->props['prevent_collapse'] ?? false);
    }
}
