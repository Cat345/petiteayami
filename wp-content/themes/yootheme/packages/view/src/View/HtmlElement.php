<?php

namespace YOOtheme\View;

use YOOtheme\Arr;

class HtmlElement implements HtmlElementInterface
{
    public string $name;

    /**
     * @var array<string, mixed>
     */
    public array $attrs;

    /**
     * @var mixed
     */
    public $contents;

    /**
     * @var ?callable
     */
    protected $transform;

    /**
     * Constructor.
     *
     * @param array<string, mixed> $attrs
     * @param string|string[]|false $contents
     */
    public function __construct(
        string $name,
        array $attrs = [],
        $contents = '',
        ?callable $transform = null
    ) {
        $this->name = $name;
        $this->attrs = $attrs;
        $this->contents = $contents;
        $this->transform = $transform;
    }

    /**
     * Renders element shortcut.
     *
     * @see self::render()
     */
    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * Render element shortcut.
     *
     * @param array<string, mixed> $params
     * @param string|array<string, mixed>|null $attrs
     * @param string|string[]|false|null $contents
     *
     * @see self::render()
     */
    public function __invoke(
        array $params = [],
        $attrs = null,
        $contents = null,
        ?string $name = null
    ): string {
        return $this->render($params, $attrs, $contents, $name);
    }

    /**
     * Renders the element tag.
     *
     * @param array<string, mixed> $params
     * @param string|array<string, mixed>|null $attrs
     * @param string|string[]|false|null $contents
     */
    public function render(
        array $params = [],
        $attrs = null,
        $contents = null,
        ?string $name = null
    ): string {
        $element = isset($attrs) ? $this->copy($attrs, $contents, $name) : $this;

        if (($transform = $this->transform) && ($result = $transform($element, $params))) {
            return $result;
        }

        return self::tag($element->name, $element->attrs, $element->contents, $params);
    }

    /**
     * Renders element closing tag.
     */
    public function end(): string
    {
        return self::isSelfClosing($this->name) ? '' : "</{$this->name}>";
    }

    /**
     * Adds an attribute.
     *
     * @param string|array<string, mixed> $name
     * @param mixed $value
     *
     * @return $this
     */
    public function attr($name, $value = null): self
    {
        $attrs = is_array($name) ? $name : [$name => $value];

        $this->attrs = Arr::merge($this->attrs, $attrs);

        return $this;
    }

    /**
     * Copy instance.
     *
     * @param string|array<string, mixed>|null $attrs
     * @param string|string[]|false|null $contents
     *
     * @return static
     */
    public function copy($attrs = null, $contents = null, ?string $name = null)
    {
        $clone = clone $this;

        if (is_array($attrs)) {
            $clone->attr($attrs);
        } elseif (isset($attrs)) {
            $contents = $attrs;
        }

        if (isset($name)) {
            $clone->name = $name;
        }

        if (isset($contents)) {
            $clone->contents = $contents;
        }

        return $clone;
    }

    /**
     * @inheritdoc
     */
    public static function tag(
        string $name,
        ?array $attrs = null,
        $contents = null,
        array $params = []
    ): string {
        $tag = $contents === false || self::isSelfClosing($name);

        if (is_array($attrs)) {
            $attrs = self::attrs($attrs, $params);
        }

        if (is_array($contents)) {
            $contents = join($contents);
        }

        return $tag ? "<{$name}{$attrs}>" : "<{$name}{$attrs}>{$contents}</{$name}>";
    }

    /**
     * Renders tag attributes.
     *
     * @param array<string, mixed> $attrs
     * @param array<string, mixed> $params
     */
    public static function attrs(array $attrs, array $params = []): string
    {
        $output = [];

        foreach ($attrs as $key => $value) {
            if (is_array($value)) {
                $value = self::expr($value, $params);
            }

            if (empty($value) && !is_numeric($value)) {
                continue;
            }

            if (is_numeric($key)) {
                $output[] = $value;
            } elseif ($value === true) {
                $output[] = $key;
            } elseif ($value !== '') {
                $output[] = sprintf(
                    '%s="%s"',
                    $key,
                    htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false),
                );
            }
        }

        return $output ? ' ' . implode(' ', $output) : '';
    }

    /**
     * @inheritdoc
     */
    public static function expr($expressions, array $params = []): ?string
    {
        $output = [];

        if (func_num_args() > 2) {
            $params = array_replace(...array_slice(func_get_args(), 1));
        }

        foreach ((array) $expressions as $expression => $condition) {
            if (!$condition) {
                continue;
            }

            if (is_int($expression)) {
                $expression = $condition;
            }

            if (
                $expression = self::evaluateExpression(
                    $expression,
                    array_replace($params, (array) $condition),
                )
            ) {
                $output[] = $expression;
            }
        }

        return $output ? join(' ', $output) : null;
    }

    /**
     * Checks if tag name is self-closing.
     */
    public static function isSelfClosing(string $name): bool
    {
        static $tags;

        if (is_null($tags)) {
            $tags = array_flip([
                'area',
                'base',
                'br',
                'col',
                'embed',
                'hr',
                'img',
                'input',
                'keygen',
                'link',
                'menuitem',
                'meta',
                'param',
                'source',
                'track',
                'wbr',
            ]);
        }

        return isset($tags[strtolower($name)]);
    }

    /**
     * Parse expression string.
     *
     * @return array{string,list<list<string>>,list<string>}
     */
    protected static function parseExpression(string $expression): array
    {
        static $expressions;

        if (isset($expressions[$expression])) {
            return $expressions[$expression];
        }

        $optionals = [];

        // match all optionals
        $output = preg_replace_callback(
            '/\[((?>[^\[\]]+|(?R))*)]/',
            function ($matches) use (&$optionals) {
                return '%' . array_push($optionals, $matches[1]) . '$s';
            },
            $expression,
        );

        // match all parameters
        preg_match_all(
            '/\{\s*(@?)(!?)(\w+)\s*(?::\s*([^{}]*(?:\{(?-1)\}[^{}]*)*))?}/',
            $output,
            $parameters,
            PREG_SET_ORDER,
        );

        return $expressions[$expression] = [$output, $parameters, $optionals];
    }

    /**
     * Evaluate expression string.
     *
     * @param array<string, mixed> $params
     */
    protected static function evaluateExpression(string $expression, array $params = []): string
    {
        if (!str_contains($expression, '{')) {
            return trim($expression);
        }

        [$output, $parameters, $optionals] = self::parseExpression($expression);

        foreach ($parameters as $match) {
            [$parameter, $empty, $negate, $name] = $match;

            $regex = isset($match[4]) ? "/^({$match[4]})$/" : '';
            $value = $params[$name] ?? '';
            $result = $regex
                ? preg_match($regex, $value)
                : $value || (is_string($value) && $value !== '');

            if ($result xor $negate) {
                $output = str_replace($parameter, $empty ? '' : $value, $output);
            } else {
                return '';
            }
        }

        if ($optionals) {
            $args = [$output];

            foreach ($optionals as $match) {
                $args[] = self::evaluateExpression($match, $params);
            }

            $output = sprintf(...$args);
        }

        return trim($output);
    }
}
