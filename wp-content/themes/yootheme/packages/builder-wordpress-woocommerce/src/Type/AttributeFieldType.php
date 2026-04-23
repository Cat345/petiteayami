<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Type;

use WC_Product;
use WC_Product_Attribute;
use YOOtheme\Builder\Source;
use function YOOtheme\trans;

/**
 * @phpstan-import-type ObjectConfig from Source
 */
class AttributeFieldType
{
    /**
     * @return ObjectConfig
     */
    public static function config(): array
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
                            'defaultValue' => ', ',
                        ],
                    ],
                    'metadata' => [
                        'label' => trans('Value'),
                        'arguments' => [
                            'separator' => [
                                'label' => trans('Separator'),
                                'description' => trans('Set the separator between tags.'),
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

    /**
     * @param array{name: string, value: string}|object $ref
     * @return string
     */
    public static function name($ref)
    {
        if (is_array($ref)) {
            return $ref['name'];
        }

        /** @var WC_Product_Attribute $attribute */
        [, $attribute] = (array) $ref;
        return wc_attribute_label($attribute->get_name());
    }

    /**
     * @param array{name: string, value: string}|object $ref
     * @param array<string, mixed> $args
     * @return string
     */
    public static function value($ref, array $args)
    {
        if (is_array($ref)) {
            return $ref['value'];
        }

        /**
         * @var WC_Product $product
         * @var WC_Product_Attribute $attribute
         */
        [$product, $attribute] = (array) $ref;

        $values = [];

        if ($attribute->is_taxonomy()) {
            /** @var object{attribute_public: bool} $taxonomy */
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
