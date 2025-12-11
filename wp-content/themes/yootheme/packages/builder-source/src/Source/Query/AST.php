<?php

namespace YOOtheme\Builder\Source\Query;

class AST
{
    public static function build(Node $node)
    {
        $build = [static::class, $node->kind];

        return $build($node);
    }

    public static function field(Node $node)
    {
        $result = [
            'kind' => 'Field',
            'name' => static::name($node->name),
            'arguments' => static::arguments($node->arguments),
            'directives' => array_map([static::class, 'directive'], $node->directives),
        ];

        if ($node->alias) {
            $result['alias'] = static::name($node->alias);
        }

        if ($node->children) {
            $result['selectionSet'] = static::selections($node->children);
        }

        return $result;
    }

    public static function query(Node $node)
    {
        $result = [
            'kind' => 'OperationDefinition',
            'operation' => 'query',
            'selectionSet' => static::selections($node->children),
            'variableDefinitions' => [],
        ];

        if ($node->name) {
            $result['name'] = static::name($node->name);
        }

        return $result;
    }

    public static function document(Node $node)
    {
        return [
            'kind' => 'Document',
            'definitions' => array_map([static::class, 'build'], $node->children),
        ];
    }

    public static function directive(Node $node)
    {
        return [
            'kind' => 'Directive',
            'name' => static::name($node->name),
            'arguments' => static::arguments($node->arguments),
        ];
    }

    public static function name($name)
    {
        return [
            'kind' => 'Name',
            'value' => $name,
        ];
    }

    public static function value($value)
    {
        switch (gettype($value)) {
            case 'NULL':
                return ['kind' => 'NullValue'];
            case 'string':
                return ['kind' => 'StringValue', 'value' => $value];
            case 'boolean':
                return ['kind' => 'BooleanValue', 'value' => $value];
            case 'integer':
                return ['kind' => 'IntValue', 'value' => strval($value)];
            case 'double':
                return ['kind' => 'FloatValue', 'value' => strval($value)];
            case 'array':
                return [
                    'kind' => 'ListValue',
                    'values' => array_map([static::class, 'value'], $value),
                ];
            case 'object':
                $fields = [];

                foreach (get_object_vars($value) as $key => $val) {
                    $fields[] = static::objectField($key, $val);
                }

                return [
                    'kind' => 'ObjectValue',
                    'fields' => $fields,
                ];
        }
    }

    public static function objectField($name, $value)
    {
        return [
            'kind' => 'ObjectField',
            'name' => static::name($name),
            'value' => static::value($value),
        ];
    }

    public static function arguments(array $arguments)
    {
        $result = [];

        foreach ($arguments as $name => $value) {
            $result[] = [
                'kind' => 'Argument',
                'name' => static::name($name),
                'value' => static::value($value),
            ];
        }

        return $result;
    }

    public static function selections(array $selections)
    {
        $result = [
            'kind' => 'SelectionSet',
            'selections' => [],
        ];

        foreach ($selections as $selection) {
            $result['selections'][] = static::build($selection);
        }

        return $result;
    }
}
