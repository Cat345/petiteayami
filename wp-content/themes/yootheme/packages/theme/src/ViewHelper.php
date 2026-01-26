<?php

namespace YOOtheme\Theme;

use YOOtheme\Config;
use YOOtheme\Event;
use YOOtheme\Html\Html;
use YOOtheme\Url;
use YOOtheme\View;
use YOOtheme\View\HtmlElement;

class ViewHelper implements ViewHelperInterface
{
    // https://developer.mozilla.org/en-US/docs/Web/Media/Formats/Image_types
    public const REGEX_IMAGE = '#\.(avif|gif|a?png|jpe?g|svg|webp)(?=$|\#)#i';

    public const REGEX_VIDEO = '#\.(mp4|m4v|ogv|webm)(?=$|\#)#i';

    public const REGEX_VIMEO = '#(?:player\.)?vimeo\.com(?:/video)?/(?P<id>\d+)#i';

    public const REGEX_YOUTUBE = '#(?:youtube(?P<nocookie>-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?|shorts)/|.*[?&]v=)|youtu\.be/)(?P<id>[\w-]{11})#i';

    public const REGEX_YOUTUBE_SHORTS = '#youtube\.com/shorts/#i';

    protected Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * Register helper.
     */
    public function register(View $view): void
    {
        // Functions
        $view->addFunction('social', [$this, 'social']);
        $view->addFunction('uid', [$this, 'uid']);
        $view->addFunction('iframeVideo', [$this, 'iframeVideo']);
        $view->addFunction('isYouTubeShorts', [$this, 'isYouTubeShorts']);
        $view->addFunction('isVideo', [$this, 'isVideo']);
        $view->addFunction('isImage', [$this, 'isImage']);
        $view->addFunction('image', [$this, 'image']);
        $view->addFunction('bgImage', [$this, 'bgImage']);
        $view->addFunction('parallaxOptions', [$this, 'parallaxOptions']);
        $view->addFunction('striptags', [$this, 'striptags']);
        $view->addFunction('margin', [$this, 'margin']);

        // Components
        $view['html']->addComponent('image', [$this, 'comImage']);
        $view['html']->addComponent('iframe', [$this, 'comIframe']);
    }

    public function social(?string $link): string
    {
        $link = strval($link);

        if (str_starts_with($link, 'mailto:')) {
            return 'mail';
        }

        if (str_starts_with($link, 'tel:')) {
            return 'receiver';
        }

        if (preg_match('#(maps.app.goo.gl|google\.(.+?)/maps)/#i', $link)) {
            return 'location';
        }

        $host = strtr(parse_url($link, PHP_URL_HOST) ?? '', [
            'wa.me' => 'whatsapp.',
            't.me' => 'telegram.',
            'bsky.app' => 'bluesky.',
        ]);

        $icon = array_slice(explode('.', $host), -2, 1)[0] ?? '';
        return in_array($icon, $this->config->get('theme.social_icons', [])) ? $icon : 'social';
    }

    /**
     * @inheritdoc
     */
    public function iframeVideo(?string $link, array $params = [], bool $defaults = true)
    {
        $link = strval($link);

        $query = parse_url($link, PHP_URL_QUERY);

        if ($query) {
            parse_str($query, $_params);
            $params = array_merge($_params, $params);
        }

        if (preg_match(static::REGEX_VIMEO, $link, $matches)) {
            if (empty($params['controls'])) {
                $params['keyboard'] = 0;
            }

            return Url::to(
                "https://player.vimeo.com/video/{$matches['id']}",
                $params +
                    ($defaults
                        ? [
                            'background' => 1,
                            'keyboard' => 0,
                            'muted' => 1,
                        ]
                        : []),
            );
        }

        if (preg_match(static::REGEX_YOUTUBE, $link, $matches)) {
            if (!empty($params['loop'])) {
                $params['playlist'] = $matches['id'];
            }

            if (empty($params['controls'])) {
                $params['disablekb'] = 1;
            }

            return Url::to(
                "https://www.youtube{$matches['nocookie']}.com/embed/{$matches['id']}",
                $params +
                    ($defaults
                        ? [
                            'playsinline' => 1,
                            'rel' => 0,
                            'iv_load_policy' => 3,
                            'controls' => 0,
                            'loop' => 1,
                            'mute' => 1,
                            'playlist' => $matches['id'],
                        ]
                        : []),
            );
        }

        return false;
    }

    public function isYouTubeShorts(?string $link): bool
    {
        return $link && preg_match(static::REGEX_YOUTUBE_SHORTS, $link);
    }

    public function uid(): int
    {
        static $uid = 0;

        return $uid++;
    }

    /**
     * @inheritdoc
     */
    public function isVideo(?string $link)
    {
        return $link && preg_match(static::REGEX_VIDEO, $link, $matches) ? $matches[1] : false;
    }

    /**
     * @inheritdoc
     */
    public function image($url, array $attrs = []): string
    {
        $url = (array) $url;
        $src = array_shift($url);

        $element = Event::emit(
            'html.image|middleware',
            fn($element) => $element,
            Html::tag('Image', array_merge(['src' => $src], $attrs, $url)),
            [],
        );

        if ($element->attr('uk-svg')) {
            return $element->withAttr('uk-svg', '');
        }

        return $element->withoutAttr('uk-svg');
    }

    /**
     * @inheritdoc
     */
    public function bgImage(?string $url, array $params = []): array
    {
        $bgImage = Event::emit(
            'html.bgImage|middleware',
            fn($element) => $element,
            Html::tag(
                'BgImage',
                array_merge(
                    ['src' => strval($url), 'thumbnail' => $params['width'] || $params['height']],
                    $params,
                ),
            ),
            [],
        );

        $bgImage = $bgImage->withAttrs([
            'class' => HtmlElement::expr(
                [
                    'uk-background-norepeat',
                    'uk-background-{size}',
                    'uk-background-{position}',
                    'uk-background-image@{visibility}',
                    'uk-background-blend-{blend_mode}',
                    'uk-background-fixed{@effect: fixed}',
                ],
                $params,
            ),
            'style' => $params['background'] ? ['background-color' => $params['background']] : [],
            'uk-parallax' =>
                ($params['effect'] ?? '') == 'parallax' &&
                ($options = $this->parallaxOptions($params, '', ['bgx', 'bgy']))
                    ? $options
                    : null,
        ]);

        $attrs = $bgImage->attrs();

        $attrs['class'] = [$attrs['class']];
        $attrs['style'] = isset($attrs['style']) ? [$attrs['style']] : [];

        return $attrs;
    }

    /**
     * @param array<string, mixed> $params
     */
    public function comImage(HtmlElement $element, array $params = []): string
    {
        if (empty($element->attrs['alt'])) {
            $element->attrs['alt'] = true;
        }

        foreach ($element->attrs as $prop => $attrs) {
            if (is_array($attrs) && $prop != 'thumbnail') {
                $element->attrs[$prop] = HtmlElement::expr($attrs, $params);
            }
        }

        $image = Event::emit(
            'html.image|middleware',
            fn($element) => $element,
            Html::tag('Image', $element->attrs),
            [],
        );

        return $image->render();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function comIframe(HtmlElement $element, array $params = []): void
    {
        if (empty($element->attrs['referrerpolicy'])) {
            $element->attr('referrerpolicy', 'strict-origin-when-cross-origin');
        }
    }

    /**
     * @inheritdoc
     */
    public function isImage(?string $link)
    {
        return $link && preg_match(static::REGEX_IMAGE, $link, $matches) ? $matches[1] : false;
    }

    /**
     * @inerhitdoc
     */
    public function isAbsolute(?string $url): bool
    {
        return $url && preg_match('/^(\/|#|[a-z0-9-.]+:)/', $url);
    }

    /**
     * @inheritdoc
     */
    public function parallaxOptions(
        array $params,
        string $prefix = '',
        array $props = ['x', 'y', 'scale', 'rotate', 'opacity', 'blur', 'background']
    ): string {
        $prefix = "{$prefix}parallax_";

        $filter = fn($value) => implode(
            ',',
            array_filter(explode(',', $value), fn($value) => '' !== trim($value)),
        );

        $options = [];
        foreach ($props as $prop) {
            $value = $filter($params["{$prefix}{$prop}"] ?? '');
            if ('' !== $value) {
                if ($prop === 'background') {
                    $prop .= '-color';
                }
                $options[] = "{$prop}: {$value}";
            }
        }

        if (!$options) {
            return '';
        }

        $options[] = sprintf(
            'easing: %s',
            is_numeric($params["{$prefix}easing"] ?? '') ? $params["{$prefix}easing"] : 0,
        );
        $options[] = !empty($params["{$prefix}breakpoint"])
            ? "media: @{$params["{$prefix}breakpoint"]}"
            : '';
        foreach (['target', 'start', 'end'] as $prop) {
            if (!empty($params[$prefix . $prop])) {
                $options[] = "{$prop}: {$params[$prefix . $prop]}";
            }
        }
        return implode('; ', array_filter($options));
    }

    public function striptags(
        ?string $str,
        string $allowable_tags = '<div><h1><h2><h3><h4><h5><h6><p><ul><ol><li><img><svg><br><hr><span><strong><em><i><b><s><mark><sup><del>'
    ): string {
        return strip_tags((string) $str, $allowable_tags);
    }

    public function margin(?string $margin): ?string
    {
        switch ($margin) {
            case '':
                return null;
            case 'default':
                return 'uk-margin-top';
            default:
                return "uk-margin-{$margin}-top";
        }
    }
}
