<?php

namespace YOOtheme\Builder\Source;

class OptimizeTransform
{
    /**
     * Transform callback.
     *
     * @param object $node
     * @param array  $params
     */
    public function __invoke($node, array $params)
    {
        if (empty($node->source->query) && isset($node->source->props)) {
            unset($node->source);
        }
    }
}
