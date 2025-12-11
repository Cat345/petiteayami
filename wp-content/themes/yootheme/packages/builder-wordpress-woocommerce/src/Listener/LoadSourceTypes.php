<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce\Listener;

use YOOtheme\Builder\Wordpress\Source\Type\TaxonomyQueryType;
use YOOtheme\Builder\Wordpress\Source\Type\TaxonomyType;
use YOOtheme\Builder\Wordpress\Woocommerce\Helper;
use YOOtheme\Builder\Wordpress\Woocommerce\Type;
use YOOtheme\Str;
use function YOOtheme\trans;

class LoadSourceTypes
{
    public static function handle($source): void
    {
        $source->objectType('Product', Type\ProductType::config());
        $source->objectType('ProductBrand', Type\ProductBrandType::config());
        $source->objectType('ProductCat', Type\ProductCategoryType::config());
        $source->objectType('ProductsQuery', Type\CustomProductQueryType::config());
        $source->objectType('AttributeField', Type\AttributeFieldType::config());
        $source->objectType('WoocommerceFields', Type\FieldsType::config());

        foreach (
            [
                ['ProductTagsQuery', 'customProductTag', trans('Custom Product tag')],
                ['ProductCatsQuery', 'customProductCat', trans('Custom Product category')],
            ]
            as [$name, $field, $label]
        ) {
            static::renameLabel($source, $name, $field, $label);
        }

        foreach (Helper::getAttributeTaxonomies() as $taxonomy) {
            if ($taxonomy->public) {
                $source->queryType(TaxonomyQueryType::config($source, $taxonomy, false));
                $source->objectType(
                    Str::camelCase($taxonomy->name, true),
                    TaxonomyType::config($taxonomy),
                );
            }
        }

        $source->objectType('Site', Type\SiteType::config($source));
    }

    protected static function renameLabel($source, $name, $field, $label): void
    {
        $source->objectType($name, [
            'fields' => [
                $field => ['metadata' => compact('label')],
            ],
        ]);
    }
}
