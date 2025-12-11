<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\AST\OperationDefinitionNode;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\QueryValidationContext;

class SingleFieldSubscription extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::OPERATION_DEFINITION => static function (OperationDefinitionNode $node) use ($context): VisitorOperation {
                if ($node->operation === 'subscription') {
                    $selections = $node->selectionSet->selections;

                    if (count($selections) > 1) {
                        $offendingSelections = $selections->splice(1, count($selections));

                        $context->reportError(new Error(
                            static::multipleFieldsInOperation($node->name->value ?? null),
                            $offendingSelections
                        ));
                    }
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function multipleFieldsInOperation(?string $operationName): string
    {
        if ($operationName === null) {
            return 'Anonymous Subscription must select only one top level field.';
        }

        return "Subscription \"{$operationName}\" must select only one top level field.";
    }
}
