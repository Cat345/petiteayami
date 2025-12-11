<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Type\Definition;

use YOOtheme\GraphQL\Error\Error;
use YOOtheme\GraphQL\Language\AST\Node;
use YOOtheme\GraphQL\Language\AST\TypeDefinitionNode;
use YOOtheme\GraphQL\Language\AST\TypeExtensionNode;

/**
 * export type NamedType =
 * | ScalarType
 * | ObjectType
 * | InterfaceType
 * | UnionType
 * | EnumType
 * | InputObjectType;.
 *
 * @property string $name
 * @property string|null $description
 * @property (Node&TypeDefinitionNode)|null $astNode
 * @property array<Node&TypeExtensionNode> $extensionASTNodes
 */
interface NamedType
{
    /** @throws Error */
    public function assertValid(): void;

    /** Is this type a built-in type? */
    public function isBuiltInType(): bool;

    public function name(): string;

    public function description(): ?string;

    /** @return (Node&TypeDefinitionNode)|null */
    public function astNode(): ?Node;

    /** @return array<Node&TypeExtensionNode> */
    public function extensionASTNodes(): array;
}
