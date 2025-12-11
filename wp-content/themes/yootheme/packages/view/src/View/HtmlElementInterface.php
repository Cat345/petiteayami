<?php

namespace YOOtheme\View;

interface HtmlElementInterface
{
    /**
     * Renders element tag.
     *
     * @param string $name
     * @param array $attrs
     * @param false|string|string[] $contents
     * @param array $params
     *
     * @return string
     */
    public static function tag($name, $attrs = null, $contents = null, array $params = []);

    /**
     * Evaluate expression attribute.
     *
     * @param array $expressions
     * @param array $params
     *
     * @return string|null
     */
    public static function expr($expressions, array $params = []);
}
