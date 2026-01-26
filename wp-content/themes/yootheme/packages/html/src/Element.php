<?php

namespace YOOtheme\Html;

use Exception;

class Element implements HtmlElement
{
    /**
     * @readonly
     */
    public string $tag;

    protected Attributes $attrs;

    /**
     * @var list<HtmlElement|string|float|int|null>
     */
    protected array $children = [];

    final public function __construct(string $tag)
    {
        $this->tag = $tag;
        $this->attrs = new Attributes();
    }

    public function __clone()
    {
        $this->attrs = clone $this->attrs;
    }

    public function __toString(): string
    {
        return $this->render();
    }

    /**
     * @return static
     */
    public static function create(string $tag): self
    {
        return new static($tag);
    }

    /**
     * @inheritdoc
     */
    public function attr(string $name, string ...$names)
    {
        return !$names
            ? $this->attrs->get($name)
            : array_map([$this->attrs, 'get'], [$name, ...$names]);
    }

    /**
     * @inheritdoc
     */
    public function attrs(string ...$names): array
    {
        $attributes = $this->attrs->toArray();

        return $names ? array_intersect_key($attributes, array_flip($names)) : $attributes;
    }

    /**
     * @inheritdoc
     */
    public function withAttr(string $name, $value = null)
    {
        $element = clone $this;
        $element->attrs->set($name, $value);

        return $element;
    }

    /**
     * @inheritdoc
     */
    public function withoutAttr(string $name, string ...$names): self
    {
        $element = clone $this;

        foreach ([$name, ...$names] as $name) {
            $element->attrs->remove($name);
        }

        return $element;
    }

    /**
     * @inheritdoc
     */
    public function withAttrs($attributes)
    {
        $element = clone $this;
        $element->attrs->merge($attributes);

        return $element;
    }

    /**
     * @inheritdoc
     */
    public function data(string $name)
    {
        return $this->attr("data-{$name}");
    }

    /**
     * @inheritdoc
     */
    public function withData(string $name, ?string $value = null): self
    {
        return $this->withAttr("data-{$name}", $value);
    }

    public function class(): ?string
    {
        return $this->attr('class');
    }

    /**
     * @inheritdoc
     */
    public function withClass($class): self
    {
        $element = clone $this;
        $element->attrs->addClass($class);

        return $element;
    }

    public function style(): ?string
    {
        return $this->attr('style');
    }

    /**
     * @inheritdoc
     */
    public function withStyle($style): self
    {
        $element = clone $this;
        $element->attrs->addStyle($style);

        return $element;
    }

    /**
     * @inheritdoc
     */
    public function withText(?string $text): self
    {
        return $this->withHtml(Html::esc($text ?? ''));
    }

    /**
     * @inheritdoc
     */
    public function withHtml(?string $html): self
    {
        if ($this->isVoid()) {
            throw new Exception("Cannot set HTML content for void elements: {$this->tag}");
        }

        return $this->withChildren($html);
    }

    /**
     * @inheritdoc
     */
    public function append(...$children)
    {
        return $this->withChildren(array_merge($this->children, $children));
    }

    /**
     * @inheritdoc
     */
    public function prepend(...$children)
    {
        return $this->withChildren(array_merge($children, $this->children));
    }

    /**
     * @inheritdoc
     */
    public function children(): array
    {
        return $this->children;
    }

    /**
     * @inheritdoc
     */
    public function withChildren($children)
    {
        $element = clone $this;
        $element->children = $this->parseChildren($children);

        return $element;
    }

    public function open(): string
    {
        $html = $this->attrs->isEmpty()
            ? "<{$this->tag}>"
            : "<{$this->tag} {$this->attrs->render()}>";

        foreach ($this->children as $child) {
            $html .= $child;
        }

        return $html;
    }

    public function close(): string
    {
        return $this->isVoid() ? '' : "</{$this->tag}>";
    }

    public function render(): string
    {
        return $this->open() . $this->close();
    }

    public function isVoid(): bool
    {
        return in_array($this->tag, [
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

    /**
     * @param mixed $children
     *
     * @return list<HtmlElement|string|float|int|null>
     *
     * @throws Exception
     */
    protected function parseChildren($children): array
    {
        if ($children instanceof HtmlElement) {
            $children = [$children];
        }

        foreach ($children as $child) {
            if (
                $child instanceof HtmlElement ||
                is_null($child) ||
                is_string($child) ||
                is_numeric($child)
            ) {
                continue;
            }

            throw new Exception('Invalid child element');
        }

        return $children;
    }
}
