<?php

namespace YOOtheme\Theme;

interface ViewHelperInterface
{
    public function social(?string $link): string;

    /**
     * @param array<string, mixed> $params
     *
     * @return false|string
     */
    public function iframeVideo(?string $link, array $params = [], bool $defaults = true);

    public function isYouTubeShorts(?string $link): bool;

    public function uid(): int;

    /**
     * @return string|false
     */
    public function isVideo(?string $link);

    /**
     * @param string|array<mixed> $url
     * @param array<string, mixed> $attrs
     */
    public function image($url, array $attrs = []): string;

    /**
     * @param array<string, mixed> $params
     *
     * @return array<string, mixed>
     */
    public function bgImage(?string $url, array $params = []): array;

    /**
     * @return string|false
     */
    public function isImage(?string $link);

    /**
     * @deprecated
     */
    public function isAbsolute(?string $url): bool;

    /**
     * @param array<string, mixed> $params
     * @param string   $prefix
     * @param list<string> $props
     */
    public function parallaxOptions(
        array $params,
        string $prefix = '',
        array $props = ['x', 'y', 'scale', 'rotate', 'opacity', 'blur', 'background']
    ): string;

    public function striptags(
        ?string $str,
        string $allowable_tags = '<div><h1><h2><h3><h4><h5><h6><p><ul><ol><li><img><svg><br><hr><span><strong><em><i><b><s><mark><sup><del>'
    ): string;

    public function margin(?string $margin): ?string;
}
