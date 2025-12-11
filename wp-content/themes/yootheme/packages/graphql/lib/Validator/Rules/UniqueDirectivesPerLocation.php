<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Error\InvariantViolation;
use YOOtheme\GraphQL\Language\AST\DirectiveDefinitionNode;
use YOOtheme\GraphQL\Language\AST\Node;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Type\Definition\Directive;
use YOOtheme\GraphQL\Validator\QueryValidationContext;
use YOOtheme\GraphQL\Validator\SDLValidationContext;
use YOOtheme\GraphQL\Validator\ValidationContext;

/**
 * Unique directive names per location.
 *
 * A GraphQL document is only valid if all non-repeatable directives at
 * a given location are uniquely named.
 *
 * @phpstan-import-type VisitorArray from Visitor
 */
class UniqueDirectivesPerLocation extends ValidationRule
{
    /** @throws InvariantViolation */
    public function getVisitor(QueryValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /** @throws InvariantViolation */
    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /**
     * @throws InvariantViolation
     *
     * @phpstan-return VisitorArray
     */
    public function getASTVisitor(ValidationContext $context): array
    {
        /** @var array<string, true> $uniqueDirectiveMap */
        $uniqueDirectiveMap = [];

        $schema = $context->getSchema();
        $definedDirectives = $schema !== null
            ? $schema->getDirectives()
            : Directive::getInternalDirectives();
        foreach ($definedDirectives as $directive) {
            if (! $directive->isRepeatable) {
                $uniqueDirectiveMap[$directive->name] = true;
            }
        }

        $astDefinitions = $context->getDocument()->definitions;
        foreach ($astDefinitions as $definition) {
            if ($definition instanceof DirectiveDefinitionNode
                && ! $definition->repeatable
            ) {
                $uniqueDirectiveMap[$definition->name->value] = true;
            }
        }

        return [
            'enter' => static function (Node $node) use ($uniqueDirectiveMap, $context): void {
                if (! property_exists($node, 'directives')) {
                    return;
                }

                $knownDirectives = [];

                foreach ($node->directives as $directive) {
                    $directiveName = $directive->name->value;

                    if (isset($uniqueDirectiveMap[$directiveName])) {
                        if (isset($knownDirectives[$directiveName])) {
                            $context->reportError(new Error(
                                static::duplicateDirectiveMessage($directiveName),
                                [$knownDirectives[$directiveName], $directive]
                            ));
                        } else {
                            $knownDirectives[$directiveName] = $directive;
                        }
                    }
                }
            },
        ];
    }

    public static function duplicateDirectiveMessage(string $directiveName): string
    {
        return "The directive \"{$directiveName}\" can only be used once at this location.";
    }
}
