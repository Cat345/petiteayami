<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Error\InvariantViolation;
use YOOtheme\GraphQL\Language\AST\ArgumentNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Type\Definition\Argument;
use YOOtheme\GraphQL\Type\Definition\NamedType;
use YOOtheme\GraphQL\Utils\Utils;
use YOOtheme\GraphQL\Validator\QueryValidationContext;

/**
 * Known argument names.
 *
 * A GraphQL field is only valid if all supplied arguments are defined by
 * that field.
 */
class KnownArgumentNames extends ValidationRule
{
    /** @throws InvariantViolation */
    public function getVisitor(QueryValidationContext $context): array
    {
        $knownArgumentNamesOnDirectives = new KnownArgumentNamesOnDirectives();

        return $knownArgumentNamesOnDirectives->getVisitor($context) + [
            NodeKind::ARGUMENT => static function (ArgumentNode $node) use ($context): void {
                $argDef = $context->getArgument();
                if ($argDef !== null) {
                    return;
                }

                $fieldDef = $context->getFieldDef();
                if ($fieldDef === null) {
                    return;
                }

                $parentType = $context->getParentType();
                if (! $parentType instanceof NamedType) {
                    return;
                }

                $context->reportError(new Error(
                    static::unknownArgMessage(
                        $node->name->value,
                        $fieldDef->name,
                        $parentType->name,
                        Utils::suggestionList(
                            $node->name->value,
                            array_map(
                                static fn (Argument $arg): string => $arg->name,
                                $fieldDef->args
                            )
                        )
                    ),
                    [$node]
                ));
            },
        ];
    }

    /** @param array<string> $suggestedArgs */
    public static function unknownArgMessage(string $argName, string $fieldName, string $typeName, array $suggestedArgs): string
    {
        $message = "Unknown argument \"{$argName}\" on field \"{$fieldName}\" of type \"{$typeName}\".";

        if ($suggestedArgs !== []) {
            $suggestions = Utils::quotedOrList($suggestedArgs);
            $message .= " Did you mean {$suggestions}?";
        }

        return $message;
    }
}
