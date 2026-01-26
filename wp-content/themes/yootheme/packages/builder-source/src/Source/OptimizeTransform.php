<?php

namespace YOOtheme\Builder\Source;

class OptimizeTransform
{
    /**
     * Transform callback.
     */
    public function __invoke(object $node): void
    {
        if (empty($node->source->query) && isset($node->source->props)) {
            unset($node->source);
        }
    }
}
