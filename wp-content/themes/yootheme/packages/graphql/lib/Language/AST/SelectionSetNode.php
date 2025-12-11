<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Language\AST;

class SelectionSetNode extends Node
{
    public string $kind = NodeKind::SELECTION_SET;

    /** @var NodeList<SelectionNode&Node> */
    public NodeList $selections;
}
