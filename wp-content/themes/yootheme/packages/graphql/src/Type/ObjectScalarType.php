<?php

namespace YOOtheme\GraphQL\Type;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\Node;
use YOOtheme\GraphQL\Type\Definition\ScalarType;

class ObjectScalarType extends ScalarType
{
    /**
     * @param array $config
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
     * @return array|null
     */
    public function parseValue($value)
    {
        return is_array($value) ? $value : null;
    }

    /**
     * @param Node       $valueNode
     * @param null|array $variables
     */
    public function parseLiteral($valueNode, ?array $variables = null)
    {
        throw new Error("Query error: Can't parse Object literal");
    }
}
