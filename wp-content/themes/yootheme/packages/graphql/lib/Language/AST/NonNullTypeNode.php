<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Language\AST;

class NonNullTypeNode extends Node implements TypeNode
{
    public string $kind = NodeKind::NON_NULL_TYPE;

    /** @var NamedTypeNode|ListTypeNode */
    public TypeNode $type;
}
