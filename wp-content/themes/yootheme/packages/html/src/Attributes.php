<?php

namespace YOOtheme\Html;

use Stringable;

class Attributes implements Stringable
{
    /**
     * @var array<int, string>
     */
    protected $class = [];

    /**
     * @var array<string, string>
     */
    protected $style = [];

    /**
     * @var array<string, string>
     */
    protected $attributes = [];

    public function __toString(): string
    {
        return $this->render();
    }

    public function has(string $name): bool
    {
        return !is_null($this->get($name));
    }

    /**
     * @return mixed
     */
    public function get(string $name)
    {
        if ($name === 'class') {
            return $this->class ? implode(' ', $this->class) : null;
        }

        if ($name === 'style') {
            $styles = [];

            foreach ($this->style as $key => $value) {
                $styles[] = "{$key}: {$value}";
            }

            return $this->style ? implode('; ', $styles) . ';' : null;
        }

        return $this->attributes[$name] ?? null;
    }

    /**
     * @param mixed $value
     *
     * @return $this
     */
    public function set(string $name, $value = null): self
    {
        if ($name === 'class') {
            $this->addClass($value);
        } elseif ($name === 'style') {
            $this->addStyle($value);
        } else {
            $this->attributes[$name] = $value;
        }

        return $this;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function remove($name): self
    {
        if ($name === 'class') {
            $this->class = [];
        } elseif ($name === 'style') {
            $this->style = [];
        } else {
            unset($this->attributes[$name]);
        }

        return $this;
    }

    /**
     * @param iterable<mixed> $attributes
     *
     * @return $this
     */
    public function merge($attributes): self
    {
        foreach ($attributes as $name => $value) {
            if (is_int($name)) {
                $name = $value;
                $value = '';
            }

            $this->set($name, $value);
        }

        return $this;
    }

    /**
     * @param string|iterable<mixed> $class
     */
    public function addClass($class): void
    {
        $classes = [];

        if (is_string($class)) {
            $classes = explode(' ', $class);
        } elseif (is_array($class)) {
            foreach ($class as $key => $value) {
                if (is_int($key)) {
                    $classes[] = $value;
                } elseif ($value) {
                    $classes[] = $key;
                }
            }
        }

        $this->class = array_unique(array_merge($this->class, $classes));
    }

    /**
     * @param string|array<string> $style
     */
    public function addStyle($style): void
    {
        $regex = '/([a-zA-Z-]+)\s*:\s*([^;]+)(?:;|$)/';

        foreach ((array) $style as $key => $value) {
            if (!is_int($key)) {
                $this->style[$key] = $value;
            } elseif (preg_match_all($regex, $value, $matches, PREG_SET_ORDER)) {
                foreach ($matches as [, $k, $v]) {
                    $this->style[$k] = $v;
                }
            }
        }
    }

    public function isEmpty(): bool
    {
        return !$this->attributes && !$this->class && !$this->style;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $attributes = [];

        if ($this->class) {
            $attributes['class'] = $this->get('class');
        }

        if ($this->style) {
            $attributes['style'] = $this->get('style');
        }

        return array_merge($attributes, $this->attributes);
    }

    public function render(): string
    {
        $attributes = [];

        foreach ($this->toArray() as $name => $value) {
            if ($value === '' || $value === true) {
                $attributes[] = $name;
            } elseif (isset($value) && $value !== false) {
                $attributes[] =
                    $name . '="' . htmlspecialchars($value, ENT_COMPAT, 'UTF-8', false) . '"';
            }
        }

        return implode(' ', $attributes);
    }
}
