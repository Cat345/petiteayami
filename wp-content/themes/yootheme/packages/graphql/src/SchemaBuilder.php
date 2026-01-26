<?php

namespace YOOtheme\GraphQL;

use Generator;
use YOOtheme\GraphQL\Error\InvariantViolation;
use YOOtheme\GraphQL\Executor\Executor;
use YOOtheme\GraphQL\Executor\Values;
use YOOtheme\GraphQL\Language\Parser;
use YOOtheme\GraphQL\Type\Definition\Directive;
use YOOtheme\GraphQL\Type\Definition\InputObjectType;
use YOOtheme\GraphQL\Type\Definition\ListOfType;
use YOOtheme\GraphQL\Type\Definition\NamedType;
use YOOtheme\GraphQL\Type\Definition\ObjectType;
use YOOtheme\GraphQL\Type\Definition\ResolveInfo;
use YOOtheme\GraphQL\Type\Definition\Type;
use YOOtheme\GraphQL\Type\Schema;
use YOOtheme\GraphQL\Utils\ASTHelper;
use YOOtheme\GraphQL\Utils\BuildSchema;
use YOOtheme\GraphQL\Utils\Middleware;

class SchemaBuilder
{
    /**
     * @var callable[][]
     */
    protected array $hooks = [];

    /**
     * @var array<string, array<string, mixed>|callable>
     */
    protected array $configs = [];

    /**
     * @var array<string, Type&NamedType>
     */
    protected array $types = [];

    /**
     * @var array<string, Type&NamedType>
     */
    protected array $loadedTypes = [];

    /**
     * @var array<string, Directive>
     */
    protected array $directives = [];

    /**
     * Constructor.
     *
     * @param array<object> $plugins
     */
    public function __construct(array $plugins = [])
    {
        $this->hooks = [
            'onLoad' => [],
            'onLoadType' => [],
            'onLoadField' => [],
        ];

        foreach ($plugins as $plugin) {
            $this->loadPlugin($plugin);
        }

        foreach ($this->hooks['onLoad'] as $hook) {
            $hook($this);
        }
    }

    /**
     * @param string $file
     * @param string $cache
     *
     * @return Schema
     */
    public function loadSchema($file, $cache = null)
    {
        $isCached = is_file($cache) && filectime($cache) > filectime($file);

        $document = $isCached
            ? ASTHelper::fromArray(require $cache)
            : Parser::parse(file_get_contents($file), ['noLocation' => true]);

        $result = BuildSchema::build(
            $document,
            fn(array $config) => ['resolveField' => [$this, 'resolveField']] + $config,
            ['assumeValid' => $isCached, 'assumeValidSDL' => $isCached],
        );

        if (!$isCached && $cache) {
            file_put_contents(
                $cache,
                "<?php\n\nreturn {$this->exportValue(ASTHelper::toArray($document))};",
            );
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $config
     */
    public function buildSchema(array $config = []): Schema
    {
        $config = array_replace_recursive(
            [
                'query' => 'Query',
                'mutation' => 'Mutation',
                'subscription' => 'Subscription',
                'directives' => $this->directives,
                'typeLoader' => [$this, 'getType'],
            ],
            $config,
        );

        if (is_string($config['query'])) {
            $config['query'] = $this->getType($config['query']);
        }

        if (is_string($config['mutation'])) {
            $config['mutation'] = $this->getType($config['mutation']);
        }

        if (is_string($config['subscription'])) {
            $config['subscription'] = $this->getType($config['subscription']);
        }

        return new Schema($config);
    }

    /**
     * @param array<string, mixed> $config
     *
     * @return string
     */
    public function printSchema(array $config = [])
    {
        return SchemaPrinter::doPrint($this->buildSchema($config));
    }

    /**
     * @param string $name
     *
     * @return Directive
     */
    public function getDirective($name)
    {
        return $this->directives[$name] ?? null;
    }

    /**
     * @param Directive $directive
     */
    public function setDirective(Directive $directive): void
    {
        $this->directives[$directive->name] = $directive;
    }

    public function hasType(string $name): bool
    {
        return isset($this->types[$name]);
    }

    /**
     * @return ?(Type&NamedType)
     */
    public function getType(string $name): ?Type
    {
        if (empty($this->loadedTypes)) {
            $this->loadedTypes = Type::getStandardTypes();
        }

        if (isset($this->loadedTypes[$name])) {
            return $this->loadedTypes[$name];
        }

        if (isset($this->types[$name])) {
            return $this->loadType($this->loadedTypes[$name] = $this->types[$name]);
        }

        return null;
    }

    /**
     * @param NamedType&Type $type
     */
    public function setType($type): void
    {
        $this->types[$type->name] = $type;
    }

    /**
     * @param array<string, mixed>|callable $config
     */
    public function queryType($config = []): void
    {
        $this->objectType('Query', $config);
    }

    /**
     * @param array<string, mixed>|callable $config
     */
    public function inputType(string $name, $config = []): void
    {
        $type =
            $this->types[$name] ??
            new InputObjectType([
                'name' => $name,
                'fields' => [],
            ]);

        if ($config) {
            $this->configs[$name][] = $config;
        }

        if (!$type instanceof InputObjectType) {
            throw new InvariantViolation("Type '{$name}' must be an InputObjectType.");
        }

        $this->types[$name] = $type;
    }

    /**
     * @param array<string, mixed>|callable $config
     */
    public function objectType(string $name, $config = []): void
    {
        $type =
            $this->types[$name] ??
            new ObjectType([
                'name' => $name,
                'fields' => [],
                'resolveField' => [$this, 'resolveField'],
            ]);

        if ($config) {
            $this->configs[$name][] = $config;
        }

        if (!$type instanceof ObjectType) {
            throw new InvariantViolation("Type '{$name}' must be an ObjectType.");
        }

        $this->types[$name] = $type;
    }

    /**
     * @template T of Type
     * @param T $type
     * @param array<string, mixed>|callable $config
     * @return T
     */
    public function extendType(Type $type, $config = [])
    {
        if (is_callable($config)) {
            $config = $config($type, $this);
        }

        if (is_array($config) && property_exists($type, 'config')) {
            $type->config = array_replace_recursive($type->config, $config);
        }

        return $type;
    }

    /**
     * @template T of Type&NamedType
     * @param T $type
     * @return T
     */
    public function loadType(Type $type)
    {
        foreach ($this->configs[$type->name] ?? [] as $config) {
            $this->extendType($type, $config);
        }

        foreach ($this->hooks['onLoadType'] as $hook) {
            $hook($type, $this);
        }

        if (isset($type->config['description']) && property_exists($type, 'description')) {
            $type->description = $type->config['description'];
        }

        if (isset($type->config['resolveField']) && $type instanceof ObjectType) {
            $type->resolveFieldFn = $type->config['resolveField'];
        }

        if (isset($type->config['fields'])) {
            $type->config['fields'] = $this->loadFields($type, $type->config['fields']);
        }

        return $type;
    }

    /**
     * @param mixed       $value
     * @param mixed       $args
     * @param mixed       $context
     * @param ResolveInfo $info
     *
     * @return mixed
     */
    public function resolveField($value, $args, $context, ResolveInfo $info)
    {
        $resolver = new Middleware([Executor::class, 'defaultFieldResolver']);

        foreach ($this->resolveDirectives($info) as ['name' => $name, 'args' => $arguments]) {
            if (is_callable($directiveDef = $this->getDirective($name))) {
                if (is_callable($directive = $directiveDef($arguments, $resolver))) {
                    $resolver->push($directive);
                }
            }
        }

        return $resolver($value, $args, $context, $info);
    }

    /**
     * @return Generator<array{'name': string, 'args': array<string, mixed>}>
     */
    public function resolveDirectives(ResolveInfo $info): Generator
    {
        // type directives
        yield from $info->parentType->config['directives'] ?? []; // @phpstan-ignore-line

        // field directives
        yield from $info->parentType->getField($info->fieldName)->config['directives'] ?? []; // @phpstan-ignore-line

        // query field directives
        foreach ($info->fieldNodes as $node) {
            if ($info->fieldName !== $node->name->value) {
                continue;
            }

            foreach ($node->directives as $directiveNode) {
                $name = $directiveNode->name->value;
                $args = Values::getArgumentValues(
                    $this->getDirective($name),
                    $directiveNode,
                    $info->variableValues,
                );

                yield ['name' => $name, 'args' => $args];
            }

            return;
        }
    }

    /**
     * @param Type&NamedType $type
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    protected function loadField($type, array $field): array
    {
        $field += ['type' => null];

        if (is_string($field['type'])) {
            $field['type'] = $this->getType($field['type']);
        } elseif (is_array($field['type'])) {
            $field['type'] = $this->loadModifiers($field['type']);
        }

        if (!$field['type']) {
            throw new InvariantViolation(
                "Field '{$field['name']}' on '{$type->name}' does not have a Type.",
            );
        }

        return $field;
    }

    /**
     * @param Type&NamedType $type
     * @param array<string, array<string, mixed>> $fields
     *
     * @return Generator<string, callable>
     */
    protected function loadFields(Type $type, array $fields): Generator
    {
        foreach ($fields as $name => $field) {
            yield $name => function () use ($type, $name, $field) {
                $field = $this->loadField(
                    $type,
                    $field + [
                        'name' => lcfirst($name),
                        'args' => [],
                    ],
                );

                foreach ($field['args'] as $key => $arg) {
                    $field['args'][$key] = $this->loadField($type, $arg);
                }

                foreach ($this->hooks['onLoadField'] as $hook) {
                    $field = $hook($type, $field, $this);
                }

                return $field;
            };
        }
    }

    /**
     * @param array<string, mixed> $type
     *
     * @return Type|ListOfType
     */
    protected function loadModifiers(array $type)
    {
        if (isset($type['nonNull'])) {
            if (is_string($type['nonNull'])) {
                $nonNull = $this->getType($type['nonNull']);
            } elseif (is_array($type['nonNull'])) {
                $nonNull = $this->loadModifiers($type['nonNull']);
            }

            $type = Type::nonNull($nonNull ?? Type::string());
        } elseif (isset($type['listOf'])) {
            if (is_string($type['listOf'])) {
                $listOf = $this->getType($type['listOf']);
            } elseif (is_array($type['listOf'])) {
                $listOf = $this->loadModifiers($type['listOf']);
            }

            $type = Type::listOf($listOf ?? Type::string());
        }

        return $type;
    }

    /**
     * @param mixed $plugin
     */
    protected function loadPlugin($plugin): void
    {
        foreach ($this->hooks as $method => &$hooks) {
            $hook = [$plugin, $method];

            if (is_callable($hook)) {
                $hooks[] = $hook;
            }
        }
    }

    /**
     * Export a parsable string representation of a value.
     *
     * @param mixed $value
     * @param int   $indent
     *
     * @return string
     */
    protected function exportValue($value, $indent = 0)
    {
        if (is_array($value)) {
            $array = [];
            $assoc = !array_is_list($value);
            $indention = str_repeat('  ', $indent);
            $indentlast = $assoc ? "\n" . $indention : '';

            foreach ($value as $key => $val) {
                $array[] =
                    ($assoc ? "\n  " . $indention . var_export($key, true) . ' => ' : '') .
                    $this->exportValue($val, $indent + 1);
            }

            return '[' . join(', ', $array) . $indentlast . ']';
        }

        return var_export($value, true);
    }
}
