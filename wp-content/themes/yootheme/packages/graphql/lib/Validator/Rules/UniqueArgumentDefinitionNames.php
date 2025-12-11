<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\DirectiveDefinitionNode;
use YOOtheme\GraphQL\Language\AST\InputValueDefinitionNode;
use YOOtheme\GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use YOOtheme\GraphQL\Language\AST\InterfaceTypeExtensionNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\AST\NodeList;
use YOOtheme\GraphQL\Language\AST\ObjectTypeDefinitionNode;
use YOOtheme\GraphQL\Language\AST\ObjectTypeExtensionNode;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\SDLValidationContext;

/**
 * Unique argument definition names.
 *
 * A GraphQL Object or Interface type is only valid if all its fields have uniquely named arguments.
 * A GraphQL Directive is only valid if all its arguments are uniquely named.
 */
class UniqueArgumentDefinitionNames extends ValidationRule
{
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        $checkArgUniquenessPerField = static function ($node) use ($context): VisitorOperation {
            assert(
                $node instanceof InterfaceTypeDefinitionNode
                || $node instanceof InterfaceTypeExtensionNode
                || $node instanceof ObjectTypeDefinitionNode
                || $node instanceof ObjectTypeExtensionNode
            );

            foreach ($node->fields as $fieldDef) {
                self::checkArgUniqueness("{$node->name->value}.{$fieldDef->name->value}", $fieldDef->arguments, $context);
            }

            return Visitor::skipNode();
        };

        return [
            NodeKind::DIRECTIVE_DEFINITION => static fn (DirectiveDefinitionNode $node): VisitorOperation => self::checkArgUniqueness("@{$node->name->value}", $node->arguments, $context),
            NodeKind::INTERFACE_TYPE_DEFINITION => $checkArgUniquenessPerField,
            NodeKind::INTERFACE_TYPE_EXTENSION => $checkArgUniquenessPerField,
            NodeKind::OBJECT_TYPE_DEFINITION => $checkArgUniquenessPerField,
            NodeKind::OBJECT_TYPE_EXTENSION => $checkArgUniquenessPerField,
        ];
    }

    /** @param NodeList<InputValueDefinitionNode> $arguments */
    private static function checkArgUniqueness(string $parentName, NodeList $arguments, SDLValidationContext $context): VisitorOperation
    {
        $seenArgs = [];
        foreach ($arguments as $argument) {
            $seenArgs[$argument->name->value][] = $argument;
        }

        foreach ($seenArgs as $argName => $argNodes) {
            if (count($argNodes) > 1) {
                $context->reportError(
                    new Error(
                        "Argument \"{$parentName}({$argName}:)\" can only be defined once.",
                        $argNodes,
                    ),
                );
            }
        }

        return Visitor::skipNode();
    }
}
