<?php

namespace YOOtheme\Builder\Source;

use YOOtheme\Arr;
use YOOtheme\Builder\Source;
use YOOtheme\Config;
use YOOtheme\Event;
use function YOOtheme\app;

class SourceTransform
{
    use SourceFilter;

    /**
     * Transform callback "preload".
     *
     * @param object $node
     * @param array  $params
     *
     * @return void
     */
    public function preload($node, array &$params)
    {
        if ($params['context'] !== 'render') {
            return;
        }

        if (empty($node->source->query->name)) {
            return;
        }

        if ($node->source->query->name === SourceQuery::PARENT) {
            if (!empty($params['source'])) {
                $params['source']->source->children[] = $node;
            }
            if (!empty($node->source->query->field->name)) {
                $params['source'] = $node;
            }
        } else {
            $params['source'] = $node;
        }
    }

    /**
     * Transform callback "prerender".
     *
     * @param object $node
     * @param array  $params
     *
     * @return bool|void
     */
    public function prerender($node, array &$params)
    {
        if (isset($node->source->data)) {
            $params['data'] = $node->source->data;
        }

        if (empty($node->source->query->name)) {
            return;
        }

        if ($node->source->query->name === SourceQuery::PARENT) {
            // Ignore if no field is mapped
            if (empty($node->source->props) && empty($node->source->children)) {
                return;
            }

            return $this->resolveSource($node, $params);
        }

        if ($result = $this->querySource($node, $params)) {
            $params['data'] = $result['data'] ?? null;
            return $this->resolveSource($node, $params);
        }
    }

    /**
     * Create source query.
     *
     * @param object $node
     *
     * @return ?Query\Node
     */
    public function createQuery($node)
    {
        $query = new SourceQuery();
        $parent = $query->create($node);
        $props = !empty($node->source->props);

        // extend source query
        foreach ($node->source->children ?? [] as $child) {
            $props = $this->createChildQuery($query, $parent, $child) || $props;
        }

        return $props ? $query->document : null;
    }

    /**
     * Add child queries
     *
     * @param SourceQuery $query
     * @param Query\Node  $parent
     * @param object      $node
     *
     * @return bool
     */
    protected function createChildQuery($query, $parent, $node)
    {
        $p = $query->querySource($node->source, $parent);
        $props = !empty($node->source->props);
        foreach ($node->source->children ?? [] as $child) {
            $props = $this->createChildQuery($query, $p, $child) || $props;
        }
        return $props;
    }

    /**
     * Query source data.
     *
     * @param object $node
     * @param array  $params
     *
     * @return array|void
     */
    public function querySource($node, array $params)
    {
        if (!($query = $this->createQuery($node))) {
            return;
        }

        // execute query without validation rules
        $result = app(Source::class)->query(
            $query->toAST(),
            $params,
            new \ArrayObject(),
            null,
            null,
            null,
            app(Config::class)->get('app.debug') ? null : [],
        );

        if (!empty($result->errors)) {
            Event::emit('source.error', $result->errors, $node);
        }

        return $result->toArray();
    }

    /**
     * Map source properties.
     *
     * @param object $node
     * @param array  $params
     *
     * @return ?object
     */
    public function mapSource($node, array $params)
    {
        foreach ($node->source->props ?? [] as $name => $prop) {
            $value = trim($this->toString(Arr::get($params, "data.{$prop->name}")));
            $filters = (array) ($prop->filters ?? []);

            // apply value filters
            foreach (array_intersect_key($this->filters, $filters) as $key => $filter) {
                $value = $filter($value, $filters[$key], $filters, $params);
            }

            // check condition value
            if ($name === '_condition' && $value === false) {
                return null;
            }

            // set filtered value
            $node->props[$name] = $value;
        }

        return $node;
    }

    /**
     * Repeat node for each source item.
     *
     * @param object $node
     * @param array  $params
     *
     * @return array
     */
    public function repeatSource($node, array $params)
    {
        $nodes = [];

        // clone and map node for each item
        foreach ($params['data'] as $index => $data) {
            $data = (array) $data;
            $data['#index'] = $index;
            $data['#first'] = $index === 0;
            $data['#last'] = $index === array_key_last($params['data']);

            if ($clone = $this->mapSource($this->cloneNode($node), ['data' => $data] + $params)) {
                $clone->source = (object) ['data' => $data];
                $nodes[] = $clone;
            }
        }

        // insert cloned nodes after current node
        array_splice($params['parent']->children, $params['i'] + 1, 0, $nodes);

        return $nodes;
    }

    /**
     * Resolve source data.
     *
     * @param object $node
     * @param array  $params
     *
     * @return bool
     */
    public function resolveSource($node, array &$params)
    {
        $name = 'data';

        // add query name
        if ($node->source->query->name !== SourceQuery::PARENT) {
            $name .= ".{$node->source->query->name}";
        }

        // add field name
        if (isset($node->source->query->field)) {
            $name .= ".{$node->source->query->field->name}";
        }

        // get source data
        $params['data'] = Arr::get($params, $name);

        if (!empty($node->source->props->_condition->filters->show_empty)) {
            return !$params['data'];
        }

        if ($params['data'] && is_array($params['data'])) {
            if (!array_is_list($params['data'])) {
                return (bool) $this->mapSource($node, $params);
            }

            $this->repeatSource($node, $params);
        }

        return false;
    }

    /**
     * Clone node recursively.
     *
     * @param object $node
     *
     * @return object
     */
    protected function cloneNode($node)
    {
        $clone = clone $node;

        // recursively clone children
        if (isset($node->children)) {
            $clone->children = array_map(fn($child) => $this->cloneNode($child), $node->children);
        }

        return $clone;
    }

    protected function toString($value)
    {
        if (is_scalar($value) || is_callable([$value, '__toString'])) {
            return (string) $value;
        }

        return '';
    }
}
