<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Language\AST;

class ListValueNode extends Node implements ValueNode
{
    public string $kind = NodeKind::LST;

    /** @var NodeList<ValueNode&Node> */
    public NodeList $values;
}
