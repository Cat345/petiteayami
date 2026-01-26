<?php

namespace YOOtheme\View;

interface HtmlHelperInterface
{
    /**
     * Creates an element.
     *
     * @param array<string, mixed> $attrs
     * @param string|string[]|false $contents
     *
     * @return HtmlElement
     */
    public function el(string $name, array $attrs = [], $contents = false);

    /**
     * Renders a link tag.
     *
     * @param array<string, mixed> $attrs
     */
    public function link(string $title, ?string $url = null, array $attrs = []): string;

    /**
     * Renders an image tag.
     *
     * @param array<string|array<string>>|string $url
     * @param array<string, mixed> $attrs
     */
    public function image($url, array $attrs = []): string;

    /**
     * Renders a form tag.
     *
     * @param array<string, mixed> $tags
     * @param array<string, mixed> $attrs
     */
    public function form(array $tags, array $attrs = []): string;

    /**
     * Renders tag attributes.
     *
     * @param array<string, mixed> $attrs
     */
    public function attrs(array $attrs): string;
}
