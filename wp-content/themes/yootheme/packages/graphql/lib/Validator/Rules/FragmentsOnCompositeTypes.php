<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\FragmentDefinitionNode;
use YOOtheme\GraphQL\Language\AST\InlineFragmentNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\Printer;
use YOOtheme\GraphQL\Type\Definition\Type;
use YOOtheme\GraphQL\Utils\AST;
use YOOtheme\GraphQL\Validator\QueryValidationContext;

class FragmentsOnCompositeTypes extends ValidationRule
{
    public function getVisitor(QueryValidationContext $context): array
    {
        return [
            NodeKind::INLINE_FRAGMENT => static function (InlineFragmentNode $node) use ($context): void {
                if ($node->typeCondition === null) {
                    return;
                }

                $type = AST::typeFromAST([$context->getSchema(), 'getType'], $node->typeCondition);
                if ($type === null || Type::isCompositeType($type)) {
                    return;
                }

                $context->reportError(new Error(
                    static::inlineFragmentOnNonCompositeErrorMessage($type->toString()),
                    [$node->typeCondition]
                ));
            },
            NodeKind::FRAGMENT_DEFINITION => static function (FragmentDefinitionNode $node) use ($context): void {
                $type = AST::typeFromAST([$context->getSchema(), 'getType'], $node->typeCondition);

                if ($type === null || Type::isCompositeType($type)) {
                    return;
                }

                $context->reportError(new Error(
                    static::fragmentOnNonCompositeErrorMessage(
                        $node->name->value,
                        Printer::doPrint($node->typeCondition)
                    ),
                    [$node->typeCondition]
                ));
            },
        ];
    }

    public static function inlineFragmentOnNonCompositeErrorMessage(string $type): string
    {
        return "Fragment cannot condition on non composite type \"{$type}\".";
    }

    public static function fragmentOnNonCompositeErrorMessage(string $fragName, string $type): string
    {
        return "Fragment \"{$fragName}\" cannot condition on non composite type \"{$type}\".";
    }
}
