<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Validator\Rules;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\ArgumentNode;
use YOOtheme\GraphQL\Language\AST\NameNode;
use YOOtheme\GraphQL\Language\AST\NodeKind;
use YOOtheme\GraphQL\Language\Visitor;
use YOOtheme\GraphQL\Language\VisitorOperation;
use YOOtheme\GraphQL\Validator\QueryValidationContext;
use YOOtheme\GraphQL\Validator\SDLValidationContext;
use YOOtheme\GraphQL\Validator\ValidationContext;

/**
 * @phpstan-import-type VisitorArray from Visitor
 */
class UniqueArgumentNames extends ValidationRule
{
    /** @var array<string, NameNode> */
    protected array $knownArgNames;

    public function getSDLVisitor(SDLValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    public function getVisitor(QueryValidationContext $context): array
    {
        return $this->getASTVisitor($context);
    }

    /** @phpstan-return VisitorArray */
    public function getASTVisitor(ValidationContext $context): array
    {
        $this->knownArgNames = [];

        return [
            NodeKind::FIELD => function (): void {
                $this->knownArgNames = [];
            },
            NodeKind::DIRECTIVE => function (): void {
                $this->knownArgNames = [];
            },
            NodeKind::ARGUMENT => function (ArgumentNode $node) use ($context): VisitorOperation {
                $argName = $node->name->value;
                if (isset($this->knownArgNames[$argName])) {
                    $context->reportError(new Error(
                        static::duplicateArgMessage($argName),
                        [$this->knownArgNames[$argName], $node->name]
                    ));
                } else {
                    $this->knownArgNames[$argName] = $node->name;
                }

                return Visitor::skipNode();
            },
        ];
    }

    public static function duplicateArgMessage(string $argName): string
    {
        return "There can be only one argument named \"{$argName}\".";
    }
}
