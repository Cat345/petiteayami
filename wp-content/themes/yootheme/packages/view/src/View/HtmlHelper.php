<?php

namespace YOOtheme\View;

use YOOtheme\View;

class HtmlHelper implements HtmlHelperInterface
{
    /**
     * @var callable[][]
     */
    public array $transforms = [];

    /**
     * Constructor.
     */
    public function __construct(View $view)
    {
        $view['html'] = $this;
        $view->addFunction('el', [$this, 'el']);
        $view->addFunction('link', [$this, 'link']);
        $view->addFunction('image', [$this, 'image']);
        $view->addFunction('form', [$this, 'form']);
        $view->addFunction('attrs', [$this, 'attrs']);
        $view->addFunction('expr', [HtmlElement::class, 'expr']);
        $view->addFunction('tag', [HtmlElement::class, 'tag']);
    }

    /**
     * @inheritdoc
     */
    public function el(string $name, array $attrs = [], $contents = false)
    {
        return new HtmlElement(
            $name,
            $attrs,
            $contents,
            isset($this->transforms[$name]) ? [$this, 'applyTransform'] : null,
        );
    }

    /**
     * @inheritdoc
     */
    public function link(string $title, ?string $url = null, array $attrs = []): string
    {
        return "<a{$this->attrs(['href' => $url], $attrs)}>{$title}</a>";
    }

    /**
     * @inheritdoc
     */
    public function image($url, array $attrs = []): string
    {
        $url = (array) $url;
        $path = array_shift($url);
        $params = $url
            ? '#' .
                http_build_query(
                    array_map(fn($value) => is_array($value) ? implode(',', $value) : $value, $url),
                    '',
                    '&',
                )
            : '';

        if (empty($attrs['alt'])) {
            $attrs['alt'] = true;
        }

        return "<img{$this->attrs(['src' => $path . $params], $attrs)}>";
    }

    /**
     * @inheritdoc
     */
    public function form(array $tags, array $attrs = []): string
    {
        return HtmlElement::tag(
            'form',
            $attrs,
            array_map(
                fn($tag) => HtmlElement::tag($tag['tag'], array_diff_key($tag, ['tag' => null])),
                $tags,
            ),
        );
    }

    /**
     * @inheritdoc
     */
    public function attrs(array $attrs): string
    {
        $params = [];

        if (count($args = func_get_args()) > 1) {
            $attrs = array_merge_recursive(...$args);
        }

        if (isset($attrs[':params'])) {
            $params = $attrs[':params'];
            unset($attrs[':params']);
        }

        return HtmlElement::attrs($attrs, $params);
    }

    /**
     * Adds a component.
     */
    public function addComponent(string $name, callable $component): void
    {
        $this->addTransform($name, $component);
    }

    /**
     * Adds a transform.
     */
    public function addTransform(string $name, callable $transform): void
    {
        $this->transforms[$name][] = $transform;
    }

    /**
     * Applies transform callbacks.
     *
     * @param HtmlElement $element
     * @param array<string, mixed> $params
     */
    public function applyTransform(HtmlElement $element, array $params = []): ?string
    {
        if (empty($this->transforms[$element->name])) {
            return null;
        }

        foreach ($this->transforms[$element->name] as $transform) {
            if ($result = $transform($element, $params)) {
                return $result;
            }
        }

        return null;
    }
}
