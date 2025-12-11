<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\NameNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\AST\OperationDefinitionNode;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\QueryValidationContext;

class UniqueOperationNames extends ValidationRule
{
    /** @var array<string, NameNode> */
    protected array $knownOperationNames;

    public function getVisitor(QueryValidationContext $context): array
    {
        $this->knownOperationNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => function (OperationDefinitionNode $node) use ($context): VisitorOperation {
                $operationName = $node->name;

                if ($operationName !== null) {
                    if (! isset($this->knownOperationNames[$operationName->value])) {
                        $this->knownOperationNames[$operationName->value] = $operationName;
                    } else {
                        $context->reportError(new Error(
                            static::duplicateOperationNameMessage($operationName->value),
                            [$this->knownOperationNames[$operationName->value], $operationName]
                        ));
                    }
                }

                return Visitor::skipNode();
            },
            NodeKind::FRAGMENT_DEFINITION => static fn (): VisitorOperation => Visitor::skipNode(),
        ];
    }

    public static function duplicateOperationNameMessage(string $operationName): string
    {
        return "There can be only one operation named \"{$operationName}\".";
    }
}
