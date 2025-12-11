<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\NameNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\SDLValidationContext;

/**
 * Unique type names.
 *
 * A GraphQL document is only valid if all defined types have unique names.
 */
class UniqueTypeNames extends ValidationRule
{
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        $schema = $context->getSchema();
        /** @var array<string, NameNode> $knownTypeNames */
        $knownTypeNames = [];
        $checkTypeName = static function ($node) use ($context, $schema, &$knownTypeNames): ?VisitorOperation {
            $typeName = $node->name->value;

            if ($schema !== null && $schema->getType($typeName) !== null) {
                $context->reportError(
                    new Error(
                        "Type \"{$typeName}\" already exists in the schema. It cannot also be defined in this type definition.",
                        $node->name,
                    ),
                );

                return null;
            }

            if (array_key_exists($typeName, $knownTypeNames)) {
                $context->reportError(
                    new Error(
                        "There can be only one type named \"{$typeName}\".",
                        [
                            $knownTypeNames[$typeName],
                            $node->name,
                        ]
                    ),
                );
            } else {
                $knownTypeNames[$typeName] = $node->name;
            }

            return Visitor::skipNode();
        };

        return [
            NodeKind::SCALAR_TYPE_DEFINITION => $checkTypeName,
            NodeKind::OBJECT_TYPE_DEFINITION => $checkTypeName,
            NodeKind::INTERFACE_TYPE_DEFINITION => $checkTypeName,
            NodeKind::UNION_TYPE_DEFINITION => $checkTypeName,
            NodeKind::ENUM_TYPE_DEFINITION => $checkTypeName,
            NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $checkTypeName,
        ];
    }
}
