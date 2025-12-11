<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\FragmentDefinitionNode;
use YOOtheme\GraphQL\Language\AST\NameNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\QueryValidationContext;

class UniqueFragmentNames extends ValidationRule
{
    /** @var array<string, NameNode> */
    protected array $knownFragmentNames;

    public function getVisitor(QueryValidationContext $context): array
    {
        $this->knownFragmentNames = [];

        return [
            NodeKind::OPERATION_DEFINITION => static fn (): VisitorOperation => Visitor::skipNode(),
            NodeKind::FRAGMENT_DEFINITION => function (FragmentDefinitionNode $node) use ($context): VisitorOperation {
                $fragmentName = $node->name->value;
                if (! isset($this->knownFragmentNames[$fragmentName])) {
                    $this->knownFragmentNames[$fragmentName] = $node->name;
                } else {
                    $context->reportError(new Error(
                        static::duplicateFragmentNameMessage($fragmentName),
                        [$this->knownFragmentNames[$fragmentName], $node->name]
                    ));
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateFragmentNameMessage(string $fragName): string
    {
        return "There can be only one fragment named \"{$fragName}\".";
    }
}
