<?php

namespace YOOtheme\Html;

use Exception;
use Stringable;

/**
 * @property-read string $tag
 */
interface HtmlElement extends Stringable
{
    /**
     * @return mixed
     */
    public function attr(string $name, string ...$names);

    /**
     * @return array<string, mixed>
     */
    public function attrs(string ...$names): array;

    /**
     * @param mixed $value
     * @return static
     */
    public function withAttr(string $name, $value = null);

    /**
     * @return static
     */
    public function withoutAttr(string $name, string ...$names): self;

    /**
     * @param iterable<mixed> $attributes
     *
     * @return static
     */
    public function withAttrs($attributes);

    /**
     * @return mixed
     */
    public function data(string $name);

    /**
     * @return static
     */
    public function withData(string $name, ?string $value = null): self;

    public function class(): ?string;

    /**
     * @param string|iterable<mixed> $class
     *
     * @return static
     */
    public function withClass($class): self;

    public function style(): ?string;

    /**
     * @param array<string, string> $style
     *
     * @return static
     */
    public function withStyle($style): self;

    /**
     * @return static
     */
    public function withText(?string $text): self;

    /**
     * @return static
     *
     * @throws Exception
     */
    public function withHtml(?string $html): self;

    /**
     * @param HtmlElement|iterable<mixed>|string|float|int|null $children
     *
     * @return static
     */
    public function append(...$children);

    /**
     * @param HtmlElement|iterable<mixed>|string|float|int|null $children
     *
     * @return static
     */
    public function prepend(...$children);

    /**
     * @return list<HtmlElement|string|float|int|null>
     */
    public function children(): array;

    /**
     * @param HtmlElement|iterable<mixed>|string|float|int|null $children
     *
     * @return static
     */
    public function withChildren($children);

    public function isVoid(): bool;

    public function open(): string;

    public function close(): string;

    public function render(): string;
}
