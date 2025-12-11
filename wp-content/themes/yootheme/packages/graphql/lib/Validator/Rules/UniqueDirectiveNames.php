<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\NameNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\SDLValidationContext;

/**
 * Unique directive names.
 *
 * A GraphQL document is only valid if all defined directives have unique names.
 */
class UniqueDirectiveNames extends ValidationRule
{
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        $schema = $context->getSchema();

        /** @var array<string, NameNode> $knownDirectiveNames */
        $knownDirectiveNames = [];

        return [
            NodeKind::DIRECTIVE_DEFINITION => static function ($node) use ($context, $schema, &$knownDirectiveNames): ?VisitorOperation {
                $directiveName = $node->name->value;

                if ($schema !== null && $schema->getDirective($directiveName) !== null) {
                    $context->reportError(
                        new Error(
                            'Directive "@' . $directiveName . '" already exists in the schema. It cannot be redefined.',
                            $node->name,
                        ),
                    );

                    return null;
                }

                if (isset($knownDirectiveNames[$directiveName])) {
                    $context->reportError(
                        new Error(
                            'There can be only one directive named "@' . $directiveName . '".',
                            [
                                $knownDirectiveNames[$directiveName],
                                $node->name,
                            ]
                        ),
                    );
                } else {
                    $knownDirectiveNames[$directiveName] = $node->name;
                }

                return Visitor::skipNode();
            },
        ];
    }
}
