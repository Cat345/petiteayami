<?php

namespace YOOtheme\Builder;

class CollapseTransform
{
    /**
     * Transform "preload" callback.
     *
     * @param object $node
     * @param array  $params
     */
    public static function preload($node, array $params)
    {
        if ($params['context'] !== 'render') {
            return;
        }

        $node->parent = !empty($node->children) && $params['type']->container;
    }

    /**
     * Transform "render" callback.
     *
     * @param object $node
     * @param array  $params
     *
     * @return bool
     */
    public static function render($node, array $params)
    {
        return empty($node->parent) ||
            !empty($node->children) ||
            ($node->props['prevent_collapse'] ?? false);
    }
}
