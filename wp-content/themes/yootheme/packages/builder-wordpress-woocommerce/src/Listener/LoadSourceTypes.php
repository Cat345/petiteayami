<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Listener;

use YOOtheme\Builder\Source;
use YOOtheme\Builder\Wordpress\Source\Type\TaxonomyQueryType;
use YOOtheme\Builder\Wordpress\Source\Type\TaxonomyType;
use YOOtheme\Builder\Wordpress\Woocommerce\Helper;
use YOOtheme\Builder\Wordpress\Woocommerce\Type;
use YOOtheme\Str;
use function YOOtheme\trans;

class LoadSourceTypes
{
    /**
     * @param Source $source
     */
    public static function handle($source): void
    {
        $source->objectType('Product', [Type\ProductType::class, 'config']);
        $source->objectType('ProductBrand', [Type\ProductTaxonomyType::class, 'config']);
        $source->objectType('ProductCat', [Type\ProductTaxonomyType::class, 'config']);
        $source->objectType('ProductsQuery', [Type\CustomProductQueryType::class, 'config']);
        $source->objectType('AttributeField', [Type\AttributeFieldType::class, 'config']);
        $source->objectType('WoocommerceFields', [Type\FieldsType::class, 'config']);

        foreach (
            [
                fn() => $source->objectType(
                    'ProductTagsQuery',
                    fn() => static::renameLabel('customProductTag', trans('Custom Product tag')),
                ),
                fn() => $source->objectType(
                    'ProductCatsQuery',
                    fn() => static::renameLabel(
                        'customProductCat',
                        trans('Custom Product category'),
                    ),
                ),
                fn() => $source->objectType(
                    'ProductCat',
                    fn() => static::addOrdering('children', 'arguments'),
                ),
                fn() => $source->objectType(
                    'ProductCatsQuery',
                    fn() => static::addOrdering('customProductCats', 'fields'),
                ),
            ]
            as $query
        ) {
            $source->queryType($query);
        }

        foreach (Helper::getAttributeTaxonomies() as $taxonomy) {
            if ($taxonomy->public) {
                $source->queryType(TaxonomyQueryType::config($source, $taxonomy, false));
                $source->objectType(
                    Str::camelCase($taxonomy->name, true),
                    fn() => TaxonomyType::config($taxonomy),
                );
            }
        }

        $source->objectType('Site', fn() => Type\SiteType::config($source));
    }

    /**
     * @return array<string, mixed>
     */
    protected static function renameLabel(string $field, string $label): array
    {
        return [
            'fields' => [
                $field => ['metadata' => ['label' => $label]],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function addOrdering(string $field, string $key): array
    {
        return [
            'fields' => [
                $field => [
                    'metadata' => [
                        $key => [
                            '_order' => [
                                'fields' => [
                                    'order' => [
                                        'options' => [
                                            trans('Menu Order') => 'menu_order',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
