<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\FieldNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Type\Definition\Type;
use YOOtheme\GraphQL\Validator\QueryValidationContext;

class ScalarLeafs extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::FIELD => static function (FieldNode $node) use ($context): void {
                $type = $context->getType();
                if ($type === null) {
                    return;
                }

                if (Type::isLeafType(Type::getNamedType($type))) {
                    if ($node->selectionSet !== null) {
                        $context->reportError(new Error(
                            static::noSubselectionAllowedMessage($node->name->value, $type->toString()),
                            [$node->selectionSet]
                        ));
                    }
                } elseif ($node->selectionSet === null) {
                    $context->reportError(new Error(
                        static::requiredSubselectionMessage($node->name->value, $type->toString()),
                        [$node]
                    ));
                }
            },
        ];
    }

    public static function noSubselectionAllowedMessage(string $field, string $type): string
    {
        return "Field \"{$field}\" of type \"{$type}\" must not have a sub selection.";
    }

    public static function requiredSubselectionMessage(string $field, string $type): string
    {
        return "Field \"{$field}\" of type \"{$type}\" must have a sub selection.";
    }
}
