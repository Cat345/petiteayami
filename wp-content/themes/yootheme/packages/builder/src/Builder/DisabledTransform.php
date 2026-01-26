<?php

namespace YOOtheme\Builder;

class DisabledTransform
{
    /**
     * Transform callback.
     */
    public function __invoke(object $node): bool
    {
        return ($node->props['status'] ?? '') !== 'disabled';
    }
}
