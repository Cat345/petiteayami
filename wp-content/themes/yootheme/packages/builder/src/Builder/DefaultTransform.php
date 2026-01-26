<?php

namespace YOOtheme\Builder;

class DefaultTransform
{
    /**
     * Transform callback.
     *
     * @param array<string, mixed>  $params
     */
    public function __invoke(object $node, array $params): void
    {
        $type = $params['type'];

        // Defaults
        if ($type->defaults && !empty($params['parent'])) {
            $node->props += $type->defaults;
        }
    }
}
