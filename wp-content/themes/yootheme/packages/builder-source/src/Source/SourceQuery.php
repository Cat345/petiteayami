<?php

namespace YOOtheme\Builder\Source;

use YOOtheme\Builder\Source\Query\Node;

class SourceQuery
{
    public const PARENT = '#parent';

    /**
     * @var Node
     */
    public $document;

    /**
     * Constructor.
     */
    public function __construct(?Node $document = null)
    {
        $this->document = $document ?? Node::document();
    }

    /**
     * Creates a source query.
     *
     * @param object $node
     *
     * @return Node
     */
    public function create($node)
    {
        return $this->querySource($node->source, $this->document->query());
    }

    /**
     * Query source definition.
     *
     * @param object $source
     * @param Node   $node
     *
     * @return Node
     */
    public function querySource($source, Node $node)
    {
        $field = $node;

        // add query selection
        if ($source->query->name !== self::PARENT) {
            $field = $this->queryField($source->query, $field);
        }

        // add field selection
        if (isset($source->query->field)) {
            $field = $this->queryField($source->query->field, $field);
        }

        // add source properties
        foreach ((array) ($source->props ?? []) as $prop) {
            if (!str_starts_with($prop->name, '#')) {
                $this->queryField($prop, $field);
            }
        }

        return $field;
    }

    /**
     * Create nested field nodes.
     *
     * @param object $field
     * @param Node   $node
     *
     * @return Node
     */
    public function queryField($field, Node $node)
    {
        $parts = explode('.', $field->name);
        $name = array_pop($parts);
        $arguments = (array) ($field->arguments ?? []);
        $directives = (array) ($field->directives ?? []);

        foreach ($parts as $part) {
            $node = is_null($_node = $node->get($part)) ? $node->field($part) : $_node;
        }

        // check if field already exists
        $nodeExists = $node->get($name);

        // create node for field
        $node = $node->field($name, $arguments);

        // add directives
        foreach ($directives as $directive) {
            $node->directive($directive->name, (array) ($directive->arguments ?? []));
        }

        // add alias
        if ($nodeExists && $nodeExists->toHash() !== ($hash = $node->toHash())) {
            $node->alias = "{$name}_{$hash}";
            $field->name .= "_{$hash}";
        }

        return $node;
    }
}
