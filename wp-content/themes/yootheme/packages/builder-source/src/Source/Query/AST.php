<?php

namespace YOOtheme\Builder\Source\Query;

/**
 * @phpstan-type Name array{kind: string, value: string}
 * @phpstan-type Argument array{kind: string, name: Name, value: ?array<string, mixed>}
 * @phpstan-type SelectionSet array{kind: string, selections: list<array<string, mixed>>}
 */
class AST
{
    /**
     * @return array<object>
     */
    public static function build(Node $node)
    {
        $build = [static::class, $node->kind];

        return $build($node);
    }

    /**
     * @return array{kind: string, name: Name, arguments: list<Argument>, directives: list<array<string, mixed>>, alias?: Name, selectionSet?: SelectionSet}
     */
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

    /**
     * @return array{kind: string, operation: string, selectionSet: SelectionSet, variableDefinitions: list<array<string, mixed>>, name?: Name}
     */
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

    /**
     * @return array{kind: string, definitions: list<array<string, mixed>>}
     */
    public static function document(Node $node)
    {
        return [
            'kind' => 'Document',
            'definitions' => array_map([static::class, 'build'], $node->children),
        ];
    }

    /**
     * @return array{kind: string, name: Name, arguments: list<Argument>}
     */
    public static function directive(Node $node)
    {
        return [
            'kind' => 'Directive',
            'name' => static::name($node->name),
            'arguments' => static::arguments($node->arguments),
        ];
    }

    /**
     * @return Name
     */
    public static function name(string $name)
    {
        return [
            'kind' => 'Name',
            'value' => $name,
        ];
    }

    /**
     * @param mixed $value
     * @return ?array<string, mixed>
     */
    public static function value($value): ?array
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

        return null;
    }

    /**
     * @param mixed $value
     * @return array{kind: string, name: Name, value: ?array<string, mixed>}
     */
    public static function objectField(string $name, $value)
    {
        return [
            'kind' => 'ObjectField',
            'name' => static::name($name),
            'value' => static::value($value),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     * @return list<Argument>
     */
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

    /**
     * @param list<Node> $selections
     * @return SelectionSet
     */
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
