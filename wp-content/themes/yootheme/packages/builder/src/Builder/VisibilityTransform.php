<?php

namespace YOOtheme\Builder;

class VisibilityTransform
{
    protected static array $bounds = [['', 's', 'm', 'l', 'xl'], ['s', 'm', 'l', 'xl', '']];

    /**
     * Transform callback.
     *
     * @param object $node
     * @param array  $params
     */
    public function __invoke($node, array $params)
    {
        $type = $params['type'];
        $parent = $params['parent'];

        if (empty($parent) || !($type->element || $type->container)) {
            return;
        }

        $visibility = $this->intersect(
            $this->toRange($node->props['visibility'] ?? ''),
            $node->props['child_visibility'] ?? false,
        );

        // Column may prevent collapsing and should not be visible while stacking than
        if (!$node->children && ($node->props['prevent_collapse'] ?? false)) {
            foreach (['default', 'small', 'medium', 'large', 'xlarge'] as $i => $width) {
                $value = $node->props["width_{$width}"] ?? '';
                if ($value && $value !== '1-1') {
                    $visibility = $this->intersect(
                        $visibility,
                        $this->toRange(static::$bounds[0][$i]),
                    );
                    break;
                }
            }
        }

        $node->attrs['class']['uk-visible@{0}'] = $visibility[0];
        $node->attrs['class']['uk-hidden@{0}'] = $visibility[1];

        $parent->props['child_visibility'] = $this->merge(
            $visibility,
            $parent->props['child_visibility'] ?? false,
        );
    }

    /**
     * Convert to visibility range.
     *
     * @param string|array $visibility
     *
     * @return string[]
     */
    protected function toRange($visibility): array
    {
        if (is_array($visibility)) {
            return $visibility;
        }

        $hidden = str_starts_with($visibility, 'hidden-');
        return [$hidden ? '' : $visibility, $hidden ? substr($visibility, 7) : ''];
    }

    /**
     * Returns intersection of two visibility ranges.
     *
     * @param string[] $rangeA
     * @param string[] $rangeB
     *
     * @return string[]
     */
    protected function intersect($rangeA, $rangeB): array
    {
        if (!$rangeB) {
            return $rangeA;
        }

        [$lower, $upper] = static::$bounds;

        return [
            $lower[max(array_search($rangeA[0], $lower), array_search($rangeB[0], $lower))],
            $upper[min(array_search($rangeA[1], $upper), array_search($rangeB[1], $upper))],
        ];
    }

    /**
     * Returns union of two visibility ranges.
     *
     * @param string[] $rangeA
     * @param string[] $rangeB
     *
     * @return string[]
     */
    protected function merge($rangeA, $rangeB): array
    {
        if (!$rangeB) {
            return $rangeA;
        }

        [$lower, $upper] = static::$bounds;

        return [
            $lower[min(array_search($rangeA[0], $lower), array_search($rangeB[0], $lower))],
            $upper[max(array_search($rangeA[1], $upper), array_search($rangeB[1], $upper))],
        ];
    }
}
