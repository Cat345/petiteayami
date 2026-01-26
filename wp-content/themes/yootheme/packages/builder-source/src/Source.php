<?php

namespace YOOtheme\Builder;

use YOOtheme\Event;
use YOOtheme\GraphQL\Executor\ExecutionResult;
use YOOtheme\GraphQL\GraphQL;
use YOOtheme\GraphQL\SchemaBuilder;
use YOOtheme\GraphQL\Type\Schema;
use YOOtheme\GraphQL\Utils\AST;
use YOOtheme\GraphQL\Utils\Introspection;

/**
 * @phpstan-type FieldConfig array{
 *  name?: string,
 *  type: string|array{listOf: string},
 *  args?: array<string, array{type: string|array{listOf: string}}>,
 *  metadata?: array<string, mixed>,
 *  extensions?: array<string, mixed>,
 * }
 *
 * @phpstan-type ObjectConfig array{
 *  fields: array<string, FieldConfig>,
 *  metadata?: array<string, mixed>,
 * }
 */
class Source extends SchemaBuilder
{
    protected ?Schema $schema = null;

    /**
     * Gets the schema.
     */
    public function getSchema(): Schema
    {
        return $this->schema ??= $this->buildSchema();
    }

    /**
     * Sets the schema.
     */
    public function setSchema(Schema $schema): Schema
    {
        return $this->schema = $schema;
    }

    /**
     * Executes a query on schema.
     *
     * @param mixed $source
     * @param mixed $value
     * @param mixed $context
     * @param ?array<mixed> $variables
     * @param ?string $operation
     * @param callable $fieldResolver
     * @param array<mixed> $validationRules
     */
    public function query(
        $source,
        $value = null,
        $context = null,
        $variables = null,
        $operation = null,
        $fieldResolver = null,
        $validationRules = null
    ): ExecutionResult {
        if (is_array($source)) {
            $source = AST::fromArray($source);
        }

        return GraphQL::executeQuery(
            $this->getSchema(),
            $source,
            $value,
            $context,
            $variables,
            $operation,
            $fieldResolver,
            $validationRules,
        );
    }

    /**
     * Executes an introspection on schema.
     *
     * @param array<string, mixed> $options
     */
    public function queryIntrospection(array $options = []): ExecutionResult
    {
        $metadata = [
            'type' => $this->getType('Object'),
            'resolve' => fn($type) => Event::emit(
                'source.type.metadata|filter',
                $type->config['metadata'] ?? null,
                $type,
            ),
        ];

        $options += [
            '__Type' => compact('metadata'),
            '__Field' => compact('metadata'),
            '__Directive' => compact('metadata'),
            '__InputValue' => compact('metadata'),
        ];

        return GraphQL::executeQuery(
            $this->getSchema(),
            Introspection::getIntrospectionQuery($options),
        );
    }
}
