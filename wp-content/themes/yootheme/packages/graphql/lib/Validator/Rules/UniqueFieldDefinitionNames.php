<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Error\InvariantViolation;
use YOOtheme\GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use YOOtheme\GraphQL\Language\AST\InputObjectTypeExtensionNode;
use YOOtheme\GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use YOOtheme\GraphQL\Language\AST\InterfaceTypeExtensionNode;
use YOOtheme\GraphQL\Language\AST\NameNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\AST\ObjectTypeDefinitionNode;
use YOOtheme\GraphQL\Language\AST\ObjectTypeExtensionNode;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Type\Definition\InputObjectType;
use YOOtheme\GraphQL\Type\Definition\InterfaceType;
use YOOtheme\GraphQL\Type\Definition\NamedType;
use YOOtheme\GraphQL\Type\Definition\ObjectType;
use YOOtheme\GraphQL\Validator\SDLValidationContext;

/**
 * Unique field definition names.
 *
 * A GraphQL complex type is only valid if all its fields are uniquely named.
 */
class UniqueFieldDefinitionNames extends ValidationRule
{
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        $schema = $context->getSchema();

        /** @var array<string, array<int, NameNode>> $knownFieldNames */
        $knownFieldNames = [];

        $checkFieldUniqueness = static function ($node) use ($context, $schema, &$knownFieldNames): VisitorOperation {
            assert(
                $node instanceof InputObjectTypeDefinitionNode
                || $node instanceof InputObjectTypeExtensionNode
                || $node instanceof InterfaceTypeDefinitionNode
                || $node instanceof InterfaceTypeExtensionNode
                || $node instanceof ObjectTypeDefinitionNode
                || $node instanceof ObjectTypeExtensionNode
            );

            $typeName = $node->name->value;

            $knownFieldNames[$typeName] ??= [];
            $fieldNames = &$knownFieldNames[$typeName];

            foreach ($node->fields as $fieldDef) {
                $fieldName = $fieldDef->name->value;

                $existingType = $schema !== null
                    ? $schema->getType($typeName)
                    : null;
                if (self::hasField($existingType, $fieldName)) {
                    $context->reportError(
                        new Error(
                            "Field \"{$typeName}.{$fieldName}\" already exists in the schema. It cannot also be defined in this type extension.",
                            $fieldDef->name,
                        ),
                    );
                } elseif (isset($fieldNames[$fieldName])) {
                    $context->reportError(
                        new Error(
                            "Field \"{$typeName}.{$fieldName}\" can only be defined once.",
                            [$fieldNames[$fieldName], $fieldDef->name],
                        ),
                    );
                } else {
                    $fieldNames[$fieldName] = $fieldDef->name;
                }
            }

            return Visitor::skipNode();
        };

        return [
            NodeKind::INPUT_OBJECT_TYPE_DEFINITION => $checkFieldUniqueness,
            NodeKind::INPUT_OBJECT_TYPE_EXTENSION => $checkFieldUniqueness,
            NodeKind::INTERFACE_TYPE_DEFINITION => $checkFieldUniqueness,
            NodeKind::INTERFACE_TYPE_EXTENSION => $checkFieldUniqueness,
            NodeKind::OBJECT_TYPE_DEFINITION => $checkFieldUniqueness,
            NodeKind::OBJECT_TYPE_EXTENSION => $checkFieldUniqueness,
        ];
    }

    /** @throws InvariantViolation */
    private static function hasField(?NamedType $type, string $fieldName): bool
    {
        if ($type instanceof ObjectType || $type instanceof InterfaceType || $type instanceof InputObjectType) {
            return $type->hasField($fieldName);
        }

        return false;
    }
}
