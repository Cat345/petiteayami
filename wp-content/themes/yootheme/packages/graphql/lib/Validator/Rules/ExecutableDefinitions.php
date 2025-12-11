<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\DocumentNode;
use YOOtheme\GraphQL\Language\AST\ExecutableDefinitionNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\AST\SchemaDefinitionNode;
use YOOtheme\GraphQL\Language\AST\SchemaExtensionNode;
use YOOtheme\GraphQL\Language\AST\TypeDefinitionNode;
use YOOtheme\GraphQL\Language\AST\TypeExtensionNode;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\QueryValidationContext;

/**
 * Executable definitions.
 *
 * A GraphQL document is only valid for execution if all definitions are either
 * operation or fragment definitions.
 */
class ExecutableDefinitions extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::DOCUMENT => static function (DocumentNode $node) use ($context): VisitorOperation {
                foreach ($node->definitions as $definition) {
                    if (! $definition instanceof ExecutableDefinitionNode) {
                        if ($definition instanceof SchemaDefinitionNode || $definition instanceof SchemaExtensionNode) {
                            $defName = 'schema';
                        } else {
                            assert(
                                $definition instanceof TypeDefinitionNode || $definition instanceof TypeExtensionNode,
                                'only other option'
                            );
                            $defName = "\"{$definition->getName()->value}\"";
                        }

                        $context->reportError(new Error(
                            static::nonExecutableDefinitionMessage($defName),
                            [$definition]
                        ));
                    }
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function nonExecutableDefinitionMessage(string $defName): string
    {
        return "The {$defName} definition is not executable.";
    }
}
