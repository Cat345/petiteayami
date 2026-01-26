<?php

namespace YOOtheme\Builder;

class IndexTransform
{
    /**
     * Transform callback.
     *
     * @param array<string, mixed>  $params
     */
    public function __invoke(object $node, array &$params): void
    {
        if ($params['context'] !== 'render') {
            return;
        }

        if (empty($params['prefix'])) {
            return;
        }

        $node->attrs ??= [];

        if (empty($params['parent'])) {
            $node->attrs['data-id'] = "{$params['prefix']}#root";
            return;
        }

        $prefix = empty($params['data-id']) ? "{$params['prefix']}#" : "{$params['data-id']}-";

        $node->attrs['data-id'] = $params['data-id'] = $prefix . $params['i'];
        $node->attrs['data-element'] = $params['type']->element ?? null;
    }
}
