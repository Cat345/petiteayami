<?php

namespace YOOtheme\Builder;

class DefaultTransform
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

        // Defaults
        if ($type->defaults && !empty($params['parent'])) {
            $node->props += $type->defaults;
        }
    }
}
