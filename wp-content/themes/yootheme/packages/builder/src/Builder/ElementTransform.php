<?php

namespace YOOtheme\Builder;

use YOOtheme\Builder;
use YOOtheme\View;

class ElementTransform
{
    protected View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * Transform callback.
     *
     * @param array<string, mixed> $params
     */
    public function __invoke(object $node, array $params): void
    {
        $type = $params['type'];

        if (empty($params['parent'])) {
            return;
        }

        $node->attrs += [
            'id' => !empty($node->props['id']) ? $node->props['id'] : null,
            'class' => !empty($node->props['class']) ? [$node->props['class']] : [],
        ];

        $this->customAttributes($node);

        if (!($type->element || $type->container)) {
            return;
        }

        $this->parallax($node);
        $this->position($node, $params);
        $this->blend($node, $params);
        $this->margin($node, $params);
        $this->maxWidth($node);
        $this->textAlign($node);
        $this->customCSS($node, $params);

        if ($type->element) {
            $this->animation($node, $params);
            $this->containerPadding($node, $params);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function animation(object $node, array $params): void
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

    public function parallax(object $node): void
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
     * @param array<string, mixed>  $params
     */
    public function position(object $node, array $params): void
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

        $node->attrs['style'] = (array) ($node->attrs['style'] ?? []);
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
     * @param array<string, mixed> $params
     */
    public function blend(object $node, array $params): void
    {
        if (empty($node->props['blend'])) {
            return;
        }

        if (!empty($params['parent']->props['position_sticky'])) {
            $node->attrs['class'][] = 'uk-blend-overlay';
        } else {
            $node->attrs['class'][] = 'uk-blend-difference';
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public function margin(object $node, array $params): void
    {
        if (($node->props['position'] ?? '') === 'absolute') {
            return;
        }

        // Same
        if (($node->props['margin_top'] ?? '') === ($node->props['margin_bottom'] ?? '')) {
            $node->attrs['class'][] = 'uk-margin {@margin_top: default}';

            if (($node->props['margin_top'] ?? '') == 'auto' && $params['i'] === 0) {
                $node->attrs['class'][] = 'uk-margin-auto-bottom';
            } else {
                $node->attrs['class'][] =
                    'uk-margin-{!margin_top: |default}[-vertical {@margin_top: remove|auto}]';
            }

            // Different
        } else {
            if (($node->props['margin_top'] ?? '') && $params['i'] !== 0) {
                $node->attrs['class'][] = 'uk-margin-top {@margin_top: default}';
                $node->attrs['class'][] = 'uk-margin-{!margin_top: |default}-top';
            }

            if (
                ($node->props['margin_bottom'] ?? '') &&
                $node !== end($params['parent']->children)
            ) {
                $node->attrs['class'][] = 'uk-margin-bottom {@margin_bottom: default}';
                $node->attrs['class'][] = 'uk-margin-{!margin_bottom: |default}-bottom';
            }
        }
    }

    public function maxWidth(object $node): void
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

    public function textAlign(object $node): void
    {
        if (empty($node->props['text_align'])) {
            return;
        }

        if (
            !empty($node->props['height_expand']) &&
            in_array($node->type, ['image', 'video']) &&
            empty($node->props['maxwidth'])
        ) {
            // Left
            $node->attrs['class'][] =
                'uk-margin-auto-right{@!text_align}{@text_align_fallback}[@{text_align_breakpoint}]';
            $node->attrs['class'][] =
                'uk-margin-remove-left{@!text_align}{@text_align_fallback}@{text_align_breakpoint}';

            // Right
            $node->attrs['class'][] =
                'uk-margin-auto-left{@text_align: right}[@{text_align_breakpoint}]';
            $node->attrs['class'][] =
                'uk-margin-remove-right{@text_align: right}{@text_align_fallback: center}@{text_align_breakpoint}';

            // Center
            $node->attrs['class'][] =
                'uk-margin-auto{@text_align: center}[@{text_align_breakpoint}]';

            // Fallback
            $node->attrs['class'][] =
                'uk-margin-auto-left{@text_align_fallback: right} {@text_align_breakpoint}';
            $node->attrs['class'][] =
                'uk-margin-auto{@text_align_fallback: center} {@text_align_breakpoint}';
        } else {
            $node->attrs['class'][] =
                $node->props['text_align'] === 'justify'
                    ? 'uk-text-{text_align}'
                    : 'uk-text-{text_align}[@{text_align_breakpoint} [uk-text-{text_align_fallback}]]';
        }
    }

    public function customAttributes(object $node): void
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
     * @param array<string, mixed>  $params
     */
    public function customCSS(object $node, array $params): void
    {
        if (empty($node->props['css'])) {
            return;
        }

        if (empty($node->attrs['id'])) {
            $node->attrs['id'] = $this->getUniqueId($params['prefix'] ?? '');
        }

        $css = static::prefixCSS($node->props['css'], '#' . addcslashes($node->attrs['id'], '#'));
        $css = preg_replace('/[\r\n\h]+/u', ' ', $css);
        $css = preg_replace('/\s*([{}])\s*/', '$1', $css);

        $root = end($params['path']);
        $root->props['css'] = trim(($root->props['css'] ?? '') . $css);
    }

    /**
     * @param array<string, mixed>  $params
     */
    public function containerPadding(object $node, array $params): void
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

        $first =
            $parent === $orderFirstColumn ||
            (!$orderFirstColumn && array_first($row->children) === $parent);
        $last =
            $parent === $orderLastColumn ||
            (!$orderLastColumn && array_last($row->children) === $parent);

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
     */
    protected static function prefixCSS(string $css, string $prefix = ''): string
    {
        // The atomic group `(?\>...)` is used to match nested CSS rules
        $pattern = '/([@#:.\w[\]][\\\\@#:,<>~="\'+\-^$.()\w\s[\]*]*)({(?>[^{}]+|(?R))*})/';

        if (preg_match_all($pattern, $css, $matches, PREG_SET_ORDER)) {
            $keys = [];

            foreach ($matches as $match) {
                [$match, $selector, $content] = $match;

                if (in_array($key = sha1($match), $keys)) {
                    continue;
                }

                $selectors = preg_split('/,(?![^(]*\))/', $selector);
                foreach ($selectors as &$sel) {
                    $sel = preg_replace(
                        '/(\([^)]*?)\.el-(?:element|section|row|column)(?=[^)]*?\))/',
                        "$1{$prefix}",
                        $sel,
                        -1,
                        $count,
                    );

                    if (!$count) {
                        $sel = ltrim(
                            preg_replace('/\.el-(element|section|row|column)/', $prefix, $sel),
                        );
                        if (!str_contains($sel, $prefix)) {
                            $sel = "{$prefix} {$sel}";
                        }
                    }

                    $sel = trim($sel);
                }
                $selector = implode(',', $selectors);

                $css = str_replace($match, $selector . static::prefixCSS($content, $prefix), $css);
                $keys[] = $key;
            }
        }

        return $css;
    }

    protected function getUniqueId(string $prefix): string
    {
        static $prefixes = [];

        $prefixes[$prefix] ??= 0;

        return "{$prefix}#" . $prefixes[$prefix]++;
    }
}
