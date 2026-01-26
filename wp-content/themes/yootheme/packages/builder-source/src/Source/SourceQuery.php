<?php

namespace YOOtheme\Builder\Source;

use YOOtheme\Builder\Source\Query\Node;

class SourceQuery
{
    public const PARENT = '#parent';

    public Node $document;

    /**
     * Constructor.
     */
    public function __construct(?Node $document = null)
    {
        $this->document = $document ?? Node::document();
    }

    /**
     * Creates a source query.
     */
    public function create(object $node): Node
    {
        return $this->querySource($node->source, $this->document->query());
    }

    /**
     * Query source definition.
     */
    public function querySource(object $source, Node $node): Node
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
     */
    public function queryField(object $field, Node $node): Node
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
