<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\FieldNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\QueryValidationContext;

class ProvidedRequiredArguments extends ValidationRule
{
    /** @throws \Exception */
    public function getVisitor(QueryValidationContext $context): array
    {
        $providedRequiredArgumentsOnDirectives = new ProvidedRequiredArgumentsOnDirectives();

        return $providedRequiredArgumentsOnDirectives->getVisitor($context) + [
            NodeKind::FIELD => [
                'leave' => static function (FieldNode $fieldNode) use ($context): ?VisitorOperation {
                    $fieldDef = $context->getFieldDef();

                    if ($fieldDef === null) {
                        return Visitor::skipNode();
                    }

                    $argNodes = $fieldNode->arguments;

                    $argNodeMap = [];
                    foreach ($argNodes as $argNode) {
                        $argNodeMap[$argNode->name->value] = $argNode;
                    }

                    foreach ($fieldDef->args as $argDef) {
                        $argNode = $argNodeMap[$argDef->name] ?? null;
                        if ($argNode === null && $argDef->isRequired()) {
                            $context->reportError(new Error(
                                static::missingFieldArgMessage($fieldNode->name->value, $argDef->name, $argDef->getType()->toString()),
                                [$fieldNode]
                            ));
                        }
                    }

                    return null;
                },
            ],
        ];
    }

    public static function missingFieldArgMessage(string $fieldName, string $argName, string $type): string
    {
        return "Field \"{$fieldName}\" argument \"{$argName}\" of type \"{$type}\" is required but not provided.";
    }
}
