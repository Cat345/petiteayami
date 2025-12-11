<?php

namespace YOOtheme\Builder;

use YOOtheme\Builder;
use YOOtheme\View;

class ElementTransform
{
    /**
     * @var View
     */
    protected $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * Transform callback.
     *
     * @param object $node
     * @param array  $params
     */
    public function __invoke($node, array $params)
    {
        $type = $params['type'];

        if (empty($params['parent']) || !($type->element || $type->container)) {
            return;
        }

        $node->attrs += [
            'id' => !empty($node->props['id']) ? $node->props['id'] : null,
            'class' => !empty($node->props['class']) ? [$node->props['class']] : [],
        ];

        $this->parallax($node);
        $this->position($node, $params);
        $this->blend($node, $params);
        $this->margin($node);
        $this->maxWidth($node);
        $this->textAlign($node);
        $this->customAttributes($node);
        $this->customCSS($node, $params);

        if ($type->element) {
            $this->animation($node, $params);
            $this->containerPadding($node, $params);
        }
    }

    /**
     * @param object $node
     * @param array  $params
     */
    public function animation($node, array $params)
    {
        /** @var Builder $builder */
        $builder = $params['builder'];
        $path = $params['path'];

        if (!empty($node->props['image_svg_inline']) && !empty($node->props['image_svg_animate'])) {
            $node->props['image_svg_inline'] = [
                'stroke-animation: true; attributes: uk-scrollspy-class:uk-animation-stroke',
            ];
        }

        if ($builder->parent($path, 'section', 'animation')) {
            $attr = 'uk-scrollspy-class';

            $value = $node->props['animation'] ?? null;
            $value = in_array($value, ['none', 'parallax'])
                ? false
                : (!empty($value)
                    ? ['uk-animation-{0}' => $value]
                    : true);
        } else {
            if (!empty($node->props['image_svg_inline'])) {
                $attr = 'uk-scrollspy';
                $value = ['target: [uk-scrollspy-class];'];
            }

            // Reset animation if there is no section animation,
            // so no look up is needed in Grid and Gallery to animate filter navigation
            if (($node->props['animation'] ?? null) !== 'parallax') {
                $node->props['animation'] = 'none';
            }
        }

        if (!empty($value) && !empty($attr)) {
            foreach (!empty($node->props['item_animation']) ? $node->children : [$node] as $child) {
                $child->attrs[$attr] = $value;
            }
        }
    }

    /**
     * @param object $node
     */
    public function parallax($node)
    {
        if (empty($node->props['animation']) || $node->props['animation'] !== 'parallax') {
            return;
        }

        $node->attrs['class'][] = 'uk-position-z-index uk-position-relative {@parallax_zindex}';
        $node->attrs['class'][] = 'uk-transform-origin-{parallax_transform_origin}';

        if ($options = $this->view->parallaxOptions($node->props)) {
            $node->attrs['uk-parallax'] = $options;
        }
    }

    /**
     * @param object $node
     * @param array  $params
     */
    public function position($node, array $params)
    {
        if (empty($node->props['position'])) {
            return;
        }

        foreach (['left', 'right', 'top', 'bottom'] as $pos) {
            if (
                !empty($node->props["position_{$pos}"]) &&
                is_numeric($node->props["position_{$pos}"])
            ) {
                $node->props["position_{$pos}"] .= 'px';
            }
        }

        $node->attrs['class'][] = 'uk-position-{position} [uk-width-1-1 {@position: absolute}]';
        $node->attrs['style'][] = 'left: {position_left}; {@!position_right}';
        $node->attrs['style'][] = 'right: {position_right}; {@!position_left}';
        $node->attrs['style'][] = 'top: {position_top}; {@!position_bottom}';
        $node->attrs['style'][] = 'bottom: {position_bottom}; {@!position_top}';
        $node->attrs['style'][] = 'z-index: {position_z_index};';

        if ($node->props['position'] == 'absolute') {
            $params['parent']->props['element_absolute'] = true;
        }
    }

    /**
     * @param object $node
     */
    public function blend($node, array $params)
    {
        if (empty($node->props['blend'])) {
            return;
        }

        if ($params['parent']->props['position_sticky']) {
            $node->attrs['class'][] = 'uk-blend-overlay';
        } else {
            $node->attrs['class'][] = 'uk-blend-difference';
        }
    }

    /**
     * @param object $node
     */
    public function margin($node)
    {
        if (($node->props['position'] ?? '') === 'absolute') {
            return;
        }

        if ($node->type !== 'row') {
            $node->attrs['class'][] = 'uk-margin {@margin: default}';
            $node->attrs['class'][] = 'uk-margin-{!margin: |default}';

            if (empty($node->props['margin']) || $node->props['margin'] !== 'remove-vertical') {
                $node->attrs['class'][] = 'uk-margin-remove-top {@margin_remove_top}';
                $node->attrs['class'][] = 'uk-margin-remove-bottom {@margin_remove_bottom}';
            }
        }
    }

    /**
     * @param object $node
     */
    public function maxWidth($node)
    {
        if (empty($node->props['maxwidth'])) {
            return;
        }

        $node->attrs['class'][] = 'uk-width-{maxwidth}[@{maxwidth_breakpoint}]';

        if (empty($node->props['position']) || $node->props['position'] !== 'absolute') {
            // Left
            $node->attrs['class'][] =
                'uk-margin-auto-right{@!block_align}{@block_align_fallback}[@{block_align_breakpoint}]';
            $node->attrs['class'][] =
                'uk-margin-remove-left{@!block_align}{@block_align_fallback}@{block_align_breakpoint}';

            // Right
            $node->attrs['class'][] =
                'uk-margin-auto-left{@block_align: right}[@{block_align_breakpoint}]';
            $node->attrs['class'][] =
                'uk-margin-remove-right{@block_align: right}{@block_align_fallback: center}@{block_align_breakpoint}';

            // Center
            $node->attrs['class'][] =
                'uk-margin-auto{@block_align: center}[@{block_align_breakpoint}]';

            // Fallback
            $node->attrs['class'][] =
                'uk-margin-auto-left{@block_align_fallback: right} {@block_align_breakpoint}';
            $node->attrs['class'][] =
                'uk-margin-auto{@block_align_fallback: center} {@block_align_breakpoint}';
        }
    }

    /**
     * @param object $node
     */
    public function textAlign($node)
    {
        if (empty($node->props['text_align'])) {
            return;
        }

        $node->attrs['class'][] =
            $node->props['text_align'] === 'justify'
                ? 'uk-text-{text_align}'
                : 'uk-text-{text_align}[@{text_align_breakpoint} [uk-text-{text_align_fallback}]]';
    }

    /**
     * @param object $node
     */
    public function customAttributes($node)
    {
        if (empty($node->props['attributes'])) {
            return;
        }

        foreach (explode("\n", $node->props['attributes']) as $attribute) {
            [$name, $value] = array_pad(explode('=', $attribute, 2), 2, '');

            $name = trim($name);

            if ($name && !in_array($name, ['id', 'class'])) {
                $node->attrs[$name] = $value
                    ? preg_replace('/^([\'"])(.*)(\1)/', '$2', $value)
                    : true;
            }
        }
    }

    /**
     * @param object $node
     * @param array  $params
     */
    public function customCSS($node, array $params)
    {
        if (empty($node->props['css'])) {
            return;
        }

        if (empty($node->attrs['id'])) {
            $node->attrs['id'] = $this->getUniqueId($params['prefix']);
        }

        $css = static::prefixCSS($node->props['css'], '#' . addcslashes($node->attrs['id'], '#'));
        $css = preg_replace('/[\r\n\h]+/u', ' ', $css);
        $css = preg_replace('/\s*([{}])\s*/', '$1', $css);

        $root = end($params['path']);
        $root->props['css'] = trim(($root->props['css'] ?? '') . $css);
    }

    /**
     * @param object $node
     * @param array  $params
     */
    public function containerPadding($node, array $params)
    {
        if (
            empty($node->props['container_padding_remove']) ||
            ($node->props['position'] ?? '') === 'absolute'
        ) {
            return;
        }

        /** @var Builder $builder */
        $builder = $params['builder'];
        $path = $params['path'];
        $parent = $params['parent'];

        // Container Padding Remove
        $row = $builder->parent($path, 'row');

        $orderFirstColumn = array_find(
            $row->children,
            fn($column) => !empty($column->props['order_first'] ?? null),
        );

        $orderLastColumn = array_find(
            array_reverse($row->children),
            fn($column) => empty($column->props['order_first'] ?? null),
        );

        $index = array_search($parent, $row->children);
        $first = $parent === $orderFirstColumn || (!$orderFirstColumn && $index === 0);
        $last =
            $parent === $orderLastColumn ||
            (!$orderLastColumn && $index + 1 === count($row->children));

        foreach (['row', 'section'] as $type) {
            if (
                !in_array($builder->parent($path, $type, 'width'), [
                    'default',
                    'small',
                    'large',
                    'xlarge',
                ]) ||
                !($dir = $builder->parent($path, $type, 'width_expand'))
            ) {
                continue;
            }

            $node->attrs['class']['uk-container-item-padding-remove-left'] =
                $first && $dir === 'left';
            $node->attrs['class']['uk-container-item-padding-remove-right'] =
                $last && $dir === 'right';

            break;
        }
    }

    /**
     * Prefix CSS classes.
     *
     * @param string $css
     * @param string $prefix
     *
     * @return string
     */
    protected static function prefixCSS($css, $prefix = '')
    {
        $pattern = '/([@#:.\w[\]][\\\\@#:,>~="\'+\-^$.()\w\s[\]*]*)({(?:[^{}]+|(?R))*})/';

        if (preg_match_all($pattern, $css, $matches, PREG_SET_ORDER)) {
            $keys = [];

            foreach ($matches as $match) {
                [$match, $selector, $content] = $match;

                if (in_array($key = sha1($match), $keys)) {
                    continue;
                }

                if ($selector[0] != '@') {
                    $selector = preg_replace('/(^|,)\s*/', "$0{$prefix} ", $selector);
                    $selector = preg_replace(
                        '/\s\.el-(element|section|row|column)/',
                        '',
                        $selector,
                    );
                }

                $css = str_replace($match, $selector . static::prefixCSS($content, $prefix), $css);
                $keys[] = $key;
            }
        }

        return $css;
    }

    protected function getUniqueId($prefix)
    {
        static $prefixes = [];

        if (!isset($prefixes[$prefix])) {
            $prefixes[$prefix] = 0;
        }

        return "{$prefix}#" . $prefixes[$prefix]++;
    }
}
