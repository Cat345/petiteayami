<?php

namespace YOOtheme;

use SplStack;
use YOOtheme\Theme\ViewHelperInterface;
use YOOtheme\View\HtmlElementInterface;
use YOOtheme\View\HtmlHelperInterface;

/**
 * @method string builder($node, $params = [])
 * @method string|false section(string $name, $default = false)
 * @mixin ViewHelperInterface
 * @mixin HtmlHelperInterface
 * @mixin HtmlElementInterface
 * @implements \ArrayAccess<string, object>
 */
class View implements \ArrayAccess
{
    /**
     * @var SplStack<callable>
     */
    protected SplStack $loader;

    /**
     * @var list<string>
     */
    protected array $template = [];

    /**
     * @var list<array<string, mixed>>
     */
    protected array $parameters = [];

    /**
     * @var array<string, list<callable>>
     */
    protected array $filters = [];

    /**
     * @var array<string, mixed>
     */
    protected array $globals = [];

    /**
     * @var array<string, object>
     */
    protected array $helpers = [];

    /**
     * @var array<string, callable>
     */
    protected array $functions = [];

    /**
     * @var array<string, mixed>
     */
    private array $evalParameters;

    /**
     * Constructor.
     */
    public function __construct(?callable $loader = null)
    {
        $this->loader = new SplStack();
        $this->loader->push([$this, 'evaluate']);

        if ($loader) {
            $this->addLoader($loader);
        }

        $this->addFunction('e', [$this, 'escape']);
    }

    /**
     * Renders a template (shortcut).
     *
     * @param array<string, mixed> $parameters
     */
    public function __invoke(string $name, array $parameters = []): string
    {
        return $this->render($name, $parameters);
    }

    /**
     * Handles dynamic calls to the class.
     *
     * @param array<array<string, mixed>>  $args
     *
     * @return mixed
     */
    public function __call(string $name, array $args)
    {
        if (!isset($this->functions[($key = strtolower($name))])) {
            trigger_error(
                sprintf('Call to undefined method %s::%s()', get_class($this), $name),
                E_USER_ERROR,
            );
        }

        return $this->functions[$key](...$args);
    }

    /**
     * Gets the global parameters.
     *
     * @return array<string, mixed>
     */
    public function getGlobals(): array
    {
        return $this->globals;
    }

    /**
     * Adds a global parameter.
     *
     * @param mixed  $value
     *
     * @return $this
     */
    public function addGlobal(string $name, $value): self
    {
        $this->globals[$name] = $value;

        return $this;
    }

    /**
     * Adds a helper.
     *
     * @param string|callable $helper
     *
     * @return $this
     */
    public function addHelper($helper): self
    {
        if (is_callable($helper)) {
            $helper($this);
        } elseif (class_exists($helper)) {
            new $helper($this);
        }

        return $this;
    }

    /**
     * Adds a custom function.
     *
     * @return $this
     */
    public function addFunction(string $name, callable $callback): self
    {
        $this->functions[strtolower($name)] = $callback;

        return $this;
    }

    /**
     * Adds a loader callback.
     *
     * @return $this
     */
    public function addLoader(callable $loader, ?string $filter = null): self
    {
        if (is_null($filter)) {
            $next = $this->loader->top();

            $this->loader->push(
                fn(string $name, array $parameters = []) => $loader($name, $parameters, $next),
            );
        } else {
            $this->filters[$filter][] = $loader;
        }

        return $this;
    }

    /**
     * Applies multiple functions.
     *
     * @param mixed  $value
     *
     * @return mixed
     */
    public function apply($value, string $functions)
    {
        $functions = explode('|', strtolower($functions));

        return array_reduce(
            $functions,
            fn($value, $function) => ([$this, $function])($value),
            $value,
        );
    }

    /**
     * Converts special characters to HTML entities.
     *
     * @param mixed $value
     */
    public function escape($value, string $functions = ''): string
    {
        $value = strval($value);

        if ($functions) {
            $value = $this->apply($value, $functions);
        }

        return htmlspecialchars($value, ENT_COMPAT, 'UTF-8');
    }

    /**
     * Renders a template.
     *
     * @param array<string, mixed> $parameters
     */
    public function render(string $name, array $parameters = []): string
    {
        $next = $this->loader->top();

        foreach ($this->filters as $filter => $loaders) {
            if (!Str::is($filter, $name)) {
                continue;
            }

            foreach ($loaders as $loader) {
                $next = fn($name, array $parameters = []) => $loader($name, $parameters, $next);
            }
        }

        return $next(
            $name,
            array_replace(end($this->parameters) ?: $this->globals, $parameters, [
                '_root' => empty($this->template),
            ]),
        );
    }

    /**
     * Renders current template.
     *
     * @param array<string, mixed> $parameters
     */
    public function self(array $parameters = []): string
    {
        return $this->render(end($this->template), $parameters);
    }

    /**
     * Evaluates a template.
     *
     * @param array<string, mixed>  $parameters
     */
    public function evaluate(string $template, array $parameters = []): string
    {
        $this->template[] = $template;
        $this->parameters[] = $this->evalParameters = $parameters;

        unset($template, $parameters);
        extract($this->evalParameters, EXTR_SKIP);
        unset($this->evalParameters);

        $__file = end($this->template);
        $__dir = dirname($__file);

        if (is_file($__file)) {
            ob_start();
            require $__file;

            $result = ob_get_clean();
        }

        array_pop($this->template);
        array_pop($this->parameters);

        return (string) ($result ?? '');
    }

    /**
     * Checks if a helper is registered.
     *
     * @param string $name
     */
    public function offsetExists($name): bool
    {
        return isset($this->helpers[$name]);
    }

    /**
     * Gets a helper.
     *
     * @param string $name
     *
     * @return object
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        if (!$this->offsetExists($name)) {
            throw new \InvalidArgumentException(sprintf('Undefined helper "%s"', $name));
        }

        return $this->helpers[$name];
    }

    /**
     * Sets a helper.
     *
     * @param string $name
     * @param object $helper
     */
    public function offsetSet($name, $helper): void
    {
        $this->helpers[$name] = $helper;
    }

    /**
     * Removes a helper.
     *
     * @param string $name
     */
    public function offsetUnset($name): void
    {
        throw new \LogicException(sprintf('You can\'t remove a helper "%s"', $name));
    }
}
