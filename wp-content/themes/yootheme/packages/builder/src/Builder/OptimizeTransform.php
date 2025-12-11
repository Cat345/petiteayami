<?php

namespace YOOtheme\Builder;

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
        // remove empty name
        if (isset($node->name) && $node->name == '') {
            unset($node->name);
        }

        // remove empty children
        if (empty($node->children)) {
            unset($node->children);
        }

        $type = $params['type'];

        if (!$type) {
            return;
        }

        foreach ($node->props as $name => $value) {
            // skip default values
            if (isset($type->defaults[$name])) {
                continue;
            }

            // skip placeholder props
            if (isset($type->placeholder['props'][$name])) {
                continue;
            }

            // remove empty values
            if ($value === '') {
                unset($node->props[$name]);
            }
        }

        // remove null props
        $node->props = array_filter($node->props, fn($value) => isset($value));

        // sort props
        ksort($node->props);

        // remove empty props
        if (empty($node->props)) {
            unset($node->props);
        }
    }
}
