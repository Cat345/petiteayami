<?php

namespace YOOtheme\View;

interface HtmlElementInterface
{
    /**
     * Renders element tag.
     *
     * @param ?array<string, mixed> $attrs
     * @param string|string[]|false $contents
     * @param array<string, mixed> $params
     */
    public static function tag(
        string $name,
        ?array $attrs = null,
        $contents = null,
        array $params = []
    ): string;

    /**
     * Evaluate expression attribute.
     *
     * @param array<mixed>|string $expressions
     * @param array<string, mixed> $params
     */
    public static function expr($expressions, array $params = []): ?string;
}
