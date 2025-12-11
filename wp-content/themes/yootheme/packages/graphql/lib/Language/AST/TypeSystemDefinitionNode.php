<?php declare(strict_types=1);

namespace YOOtheme\GraphQL\Language\AST;

/**
 * export type TypeSystemDefinitionNode =
 * | SchemaDefinitionNode
 * | TypeDefinitionNode
 * | DirectiveDefinitionNode.
 */
interface TypeSystemDefinitionNode extends DefinitionNode {}
