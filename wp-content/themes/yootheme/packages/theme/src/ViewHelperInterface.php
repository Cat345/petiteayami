<?php

namespace YOOtheme\Theme;

interface ViewHelperInterface
{
    /**
     * @param string $link
     *
     * @return string
     */
    public function social($link);

    /**
     * @param string $link
     * @param array $params
     * @param bool $defaults
     *
     * @return false|string
     */
    public function iframeVideo($link, $params = [], $defaults = true);

    /**
     * @param string $link
     *
     * @return bool
     */
    public function isYouTubeShorts($link);

    /**
     * @return int
     */
    public function uid();

    /**
     * @param string $link
     *
     * @return string|false
     */
    public function isVideo($link);

    /**
     * @param string|array $url
     * @param array $attrs
     *
     * @return string
     */
    public function image($url, array $attrs = []);

    /**
     * @param string $url
     * @param array $params
     *
     * @return array
     */
    public function bgImage($url, array $params = []);

    /**
     * @param string $link
     *
     * @return string|false
     */
    public function isImage($link);

    /**
     * @param string $url
     *
     * @return bool
     */
    public function isAbsolute($url): bool;

    /**
     * @param array    $params
     * @param string   $prefix
     * @param string[] $props
     *
     * @return mixed
     */
    public function parallaxOptions(
        $params,
        $prefix = '',
        $props = ['x', 'y', 'scale', 'rotate', 'opacity', 'blur', 'background']
    );

    /**
     * @param string $str
     * @param string $allowable_tags
     *
     * @return string
     */
    public function striptags(
        $str,
        $allowable_tags = '<div><h1><h2><h3><h4><h5><h6><p><ul><ol><li><img><svg><br><hr><span><strong><em><i><b><s><mark><sup><del>'
    ): string;

    /**
     * @param string $margin
     */
    public function margin($margin): ?string;
}
