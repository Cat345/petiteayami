<?php

namespace YOOtheme\View;

use YOOtheme\View;

class HtmlHelper implements HtmlHelperInterface
{
    /**
     * @var callable[][]
     */
    public $transforms = [];

    /**
     * Constructor.
     *
     * @param View $view
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
    public function el($name, array $attrs = [], $contents = false)
    {
        return new HtmlElement($name, $attrs, $contents, [$this, 'applyTransform']);
    }

    /**
     * @inheritdoc
     */
    public function link($title, $url = null, array $attrs = [])
    {
        return "<a{$this->attrs(['href' => $url], $attrs)}>{$title}</a>";
    }

    /**
     * @inheritdoc
     */
    public function image($url, array $attrs = [])
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
    public function form($tags, array $attrs = [])
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
    public function attrs(array $attrs)
    {
        $params = [];

        if (count($args = func_get_args()) > 1) {
            $attrs = call_user_func_array('array_merge_recursive', $args);
        }

        if (isset($attrs[':params'])) {
            $params = $attrs[':params'];
            unset($attrs[':params']);
        }

        return HtmlElement::attrs($attrs, $params);
    }

    /**
     * Adds a component.
     *
     * @param string   $name
     * @param callable $component
     */
    public function addComponent($name, callable $component)
    {
        $this->addTransform($name, $component);
    }

    /**
     * Adds a transform.
     *
     * @param string   $name
     * @param callable $transform
     */
    public function addTransform($name, callable $transform)
    {
        $this->transforms[$name][] = $transform;
    }

    /**
     * Applies transform callbacks.
     *
     * @param HtmlElement $element
     * @param array       $params
     *
     * @return string|void
     */
    public function applyTransform(HtmlElement $element, array $params = [])
    {
        if (empty($this->transforms[$element->name])) {
            return;
        }

        foreach ($this->transforms[$element->name] as $transform) {
            if ($result = call_user_func($transform, $element, $params)) {
                return $result;
            }
        }
    }
}
