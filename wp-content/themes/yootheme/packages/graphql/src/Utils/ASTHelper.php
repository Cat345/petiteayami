<?php

namespace YOOtheme\GraphQL\Utils;

use YOOtheme\GraphQL\Language\AST\ArgumentNode;
use YOOtheme\GraphQL\Language\AST\DirectiveNode;
use YOOtheme\GraphQL\Language\AST\FieldDefinitionNode;
use YOOtheme\GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use YOOtheme\GraphQL\Language\AST\InputValueDefinitionNode;
use YOOtheme\GraphQL\Language\AST\ObjectTypeDefinitionNode;
use YOOtheme\GraphQL\Type\Definition\FieldDefinition;
use YOOtheme\GraphQL\Type\Definition\InputObjectField;
use YOOtheme\GraphQL\Type\Definition\InputObjectType;
use YOOtheme\GraphQL\Type\Definition\ObjectType;

class ASTHelper extends AST
{
    public static function objectType(ObjectType $type): ObjectTypeDefinitionNode
    {
        $node = [
            'kind' => 'ObjectTypeDefinition',
            'name' => [
                'kind' => 'Name',
                'value' => $type->name,
            ],
            'fields' => [],
            'interfaces' => [],
            'directives' => [],
        ];

        static::addDirectives($node, $type->config);

        foreach ($type->getFields() as $field) {
            $field->astNode = static::field($field);
        }

        /** @var ObjectTypeDefinitionNode $result */
        $result = static::fromArray($node);
        return $result;
    }

    public static function inputType(InputObjectType $type): InputObjectTypeDefinitionNode
    {
        $node = [
            'kind' => 'InputObjectTypeDefinition',
            'name' => [
                'kind' => 'Name',
                'value' => $type->name,
            ],
            'fields' => [],
            'directives' => [],
        ];

        static::addDirectives($node, $type->config);

        foreach ($type->getFields() as $field) {
            $field->astNode = static::inputField($field);
        }

        /** @var InputObjectTypeDefinitionNode $result */
        $result = static::fromArray($node);
        return $result;
    }

    public static function field(FieldDefinition $field): FieldDefinitionNode
    {
        $node = [
            'kind' => 'FieldDefinition',
            'name' => [
                'kind' => 'Name',
                'value' => $field->name,
            ],
            'arguments' => [],
            'directives' => [],
        ];

        static::addDirectives($node, $field->config);

        /** @var FieldDefinitionNode $result */
        $result = static::fromArray($node);
        return $result;
    }

    public static function inputField(InputObjectField $field): InputValueDefinitionNode
    {
        $node = [
            'kind' => 'InputValueDefinition',
            'name' => [
                'kind' => 'Name',
                'value' => $field->name,
            ],
            'directives' => [],
        ];

        static::addDirectives($node, $field->config);

        /** @var InputValueDefinitionNode $result */
        $result = static::fromArray($node);
        return $result;
    }

    /**
     * @param array<string, mixed> $node
     * @param array<string, mixed> $config
     */
    protected static function addDirectives(array &$node, array $config): void
    {
        foreach ($config['directives'] ?? [] as $directives) {
            $node['directives'][] = static::directive($directives);
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    public static function directive(array $config): DirectiveNode
    {
        $directive = [
            'kind' => 'Directive',
            'name' => [
                'kind' => 'Name',
                'value' => $config['name'],
            ],
        ];

        foreach ($config['args'] ?? [] as $name => $value) {
            $directive['arguments'][] = static::argument($name, $value);
        }

        /** @var DirectiveNode $result */
        $result = static::fromArray($directive);
        return $result;
    }

    /**
     * @param mixed $value
     */
    public static function argument(string $name, $value): ArgumentNode
    {
        $argument = [
            'kind' => 'Argument',
            'name' => [
                'kind' => 'Name',
                'value' => $name,
            ],
            'value' => [
                'kind' => 'StringValue',
                'value' => $value,
            ],
        ];

        /** @var ArgumentNode $result */
        $result = static::fromArray($argument);
        return $result;
    }
}
