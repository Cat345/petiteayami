<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Type\Definition;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Error\SerializationError;
use YOOtheme\GraphQL\Language\AST\FloatValueNode;
use YOOtheme\GraphQL\Language\AST\IntValueNode;
use YOOtheme\GraphQL\Language\AST\Node;
use YOOtheme\GraphQL\Language\Printer;
use YOOtheme\GraphQL\Utils\Utils;

class FloatType extends ScalarType
{
    public string $name = Type::FLOAT;

    public ?string $description
        = 'The `Float` scalar type represents signed double-precision fractional
values as specified by
[IEEE 754](http://en.wikipedia.org/wiki/IEEE_floating_point). ';

    /** @throws SerializationError */
    public function serialize($value): float
    {
        $float = is_numeric($value) || is_bool($value)
            ? (float) $value
            : null;

        if ($float === null || ! is_finite($float)) {
            $notFloat = Utils::printSafe($value);
            throw new SerializationError("Float cannot represent non numeric value: {$notFloat}");
        }

        return $float;
    }

    /** @throws Error */
    public function parseValue($value): float
    {
        $float = is_float($value) || is_int($value)
            ? (float) $value
            : null;

        if ($float === null || ! is_finite($float)) {
            $notFloat = Utils::printSafeJson($value);
            throw new Error("Float cannot represent non numeric value: {$notFloat}");
        }

        return $float;
    }

    /**
     * @throws \JsonException
     * @throws Error
     */
    public function parseLiteral(Node $valueNode, ?array $variables = null)
    {
        if ($valueNode instanceof FloatValueNode || $valueNode instanceof IntValueNode) {
            return (float) $valueNode->value;
        }

        $notFloat = Printer::doPrint($valueNode);
        throw new Error("Float cannot represent non numeric value: {$notFloat}", $valueNode);
    }
}
