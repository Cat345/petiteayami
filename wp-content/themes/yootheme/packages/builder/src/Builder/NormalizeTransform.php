<?php

namespace YOOtheme\Builder;

class NormalizeTransform
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

        $node->attrs ??= [];
        $node->children ??= [];

        // Default to "null", if field not exists
        $fields = $type->fields ?? [];

        foreach ($type->panels ?? [] as $panel) {
            if (isset($panel['fields'])) {
                $fields += $panel['fields'];
            }
        }

        foreach (array_diff_key($fields, $node->props) as $name => $value) {
            $node->props[$name] = null;
        }
    }
}
