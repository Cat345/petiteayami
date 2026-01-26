<?php

namespace YOOtheme\GraphQL\Type;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\Node;
use YOOtheme\GraphQL\Type\Definition\ScalarType;

class ObjectScalarType extends ScalarType
{
    /**
     * @inheritdoc
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config + ['name' => 'Object']);
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    public function serialize($value)
    {
        return $value;
    }

    /**
     * @param mixed $value
     *
     * @return ?array<string, mixed>
     */
    public function parseValue($value): ?array
    {
        return is_array($value) ? $value : null;
    }

    /**
     * @param ?array<object> $variables
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null): void
    {
        throw new Error("Query error: Can't parse Object literal");
    }
}
