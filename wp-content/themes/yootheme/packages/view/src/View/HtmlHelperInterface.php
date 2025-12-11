<?php

namespace YOOtheme\View;

interface HtmlHelperInterface
{
    /**
     * Creates an element.
     *
     * @param string $name
     * @param array $attrs
     * @param mixed $contents
     *
     * @return HtmlElement
     */
    public function el($name, array $attrs = [], $contents = false);

    /**
     * Renders a link tag.
     *
     * @param string $title
     * @param string $url
     * @param array $attrs
     *
     * @return string
     */
    public function link($title, $url = null, array $attrs = []);

    /**
     * Renders an image tag.
     *
     * @param array|string $url
     * @param array $attrs
     *
     * @return string
     */
    public function image($url, array $attrs = []);

    /**
     * Renders a form tag.
     *
     * @param array $tags
     * @param array $attrs
     *
     * @return string
     */
    public function form($tags, array $attrs = []);

    /**
     * Renders tag attributes.
     *
     * @param array $attrs
     *
     * @return string
     */
    public function attrs(array $attrs);
}
