<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use function YOOtheme\trans;

class AttributeFieldType
{
    public static function config()
    {
        return [
            'fields' => [
                'name' => [
                    'type' => 'String',
                    'metadata' => [
                        'label' => trans('Name'),
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::name',
                    ],
                ],

                'value' => [
                    'type' => 'String',
                    'args' => [
                        'separator' => [
                            'type' => 'String',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Value'),
                        'arguments' => [
                            'separator' => [
                                'label' => trans('Separator'),
                                'description' => trans('Set the separator between tags.'),
                                'default' => ', ',
                            ],
                        ],
                    ],
                    'extensions' => [
                        'call' => __CLASS__ . '::value',
                    ],
                ],
            ],
        ];
    }

    public static function name($ref)
    {
        if (is_array($ref)) {
            return $ref['name'];
        }

        [, $attribute] = (array) $ref;
        return wc_attribute_label($attribute->get_name());
    }

    public static function value($ref, array $args)
    {
        if (is_array($ref)) {
            return $ref['value'];
        }

        [$product, $attribute] = (array) $ref;

        $args += [
            'separator' => ', ',
        ];

        $values = [];

        if ($attribute->is_taxonomy()) {
            $taxonomy = $attribute->get_taxonomy_object();
            $attributeValues = wc_get_product_terms($product->get_id(), $attribute->get_name(), [
                'fields' => 'all',
            ]);
            foreach ($attributeValues as $value) {
                $values[] = $taxonomy->attribute_public
                    ? sprintf(
                        '<a href="%s" rel="tag">%s</a>',
                        esc_url(get_term_link($value->term_id, $attribute->get_name())),
                        esc_html($value->name),
                    )
                    : esc_html($value->name);
            }
        } else {
            foreach ($attribute->get_options() as $value) {
                $values[] = esc_html($value);
            }
        }

        return apply_filters(
            'woocommerce_attribute',
            wptexturize(implode($args['separator'], $values)),
            $attribute,
            $values,
        );
    }
}
