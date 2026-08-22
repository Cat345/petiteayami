<?php

namespace YOOtheme\Builder\Wordpress\Source\Listener;

use Closure;
use WP_Post;
use WP_Query;
use WP_Term;
use YOOtheme\Config;
use YOOtheme\Http\Request;

class MatchTemplate
{
    public Config $config;
    public Request $request;

    public function __construct(Config $config, Request $request)
    {
        $this->config = $config;
        $this->request = $request;
    }

    /**
     * @param WP_Query $query
     *
     * @return ?array{type: string, query: array<string, mixed>}
     */
    public function handle($query): ?array
    {
        $locale = $this->getLocale($this->config->get('locale.code'));

        $object = $query->get_queried_object();
        $pages = ((int) get_query_var('paged')) > 1 ? 'except_first' : 'first';

        if ($query->is_home()) {
            return [
                'type' => 'archive-post',
                'query' => ['pages' => $pages, 'locale' => $locale],
            ];
        }

        if ($object && ($query->is_tax() || $query->is_category() || $query->is_tag())) {
            return [
                'type' => "taxonomy-{$object->taxonomy}",
                'query' => [
                    'terms' => static::getTaxonomyTermsFn($object),
                    'terms_filter' => static::getTaxonomyTermsFilterFn(),
                    'pages' => $pages,
                    'locale' => $locale,
                ],
            ];
        }

        if ($object && $query->is_post_type_archive()) {
            $postType = $query->get('post_type');

            if (is_array($postType)) {
                $postType = reset($postType);
            }

            return [
                'type' => "archive-{$postType}",
                'query' => ['pages' => $pages, 'locale' => $locale],
            ];
        }

        if ($query->is_author()) {
            return [
                'type' => 'author-archive',
                'query' => ['pages' => $pages, 'locale' => $locale],
            ];
        }

        if ($query->is_date()) {
            return [
                'type' => 'date-archive',
                'query' => [
                    'archive' => $query->is_time()
                        ? 'time'
                        : (is_day()
                            ? 'day'
                            : (is_month()
                                ? 'month'
                                : 'year')),
                    'pages' => $pages,
                    'locale' => $locale,
                ],
            ];
        }

        if ($query->is_search()) {
            return [
                'type' => $this->request->getParam('live-search') ? '_search' : 'search',
                'query' => ['pages' => $pages, 'locale' => $locale],
            ];
        }

        if ($query->is_404()) {
            return [
                'type' => 'error-404',
                'query' => ['locale' => $locale],
            ];
        }

        // For WooCommerce's shop page `is_page() => true` if assigned as homepage
        if ($object && $query->is_singular()) {
            return [
                'type' => "single-{$object->post_type}",
                'query' => !post_password_required($object)
                    ? [
                        'terms' => $this->getSingleTermsFn($object),
                        'locale' => $locale,
                    ]
                    : fn() => false,
            ];
        }

        return null;
    }

    /**
     * @return list<string>|string
     */
    protected function getLocale(string $locale)
    {
        if (str_contains($locale, '_')) {
            // Fallback to language code, if e.g. WPML changed the locale
            $locale = [$locale, strstr($locale, '_', true)];
        }

        return $locale;
    }

    protected function getSingleTermsFn(WP_Post $post): Closure
    {
        $postTaxonomies = get_object_taxonomies($post);
        $postTermIds = array_column(wp_get_object_terms($post->ID, $postTaxonomies), 'term_id');

        return function ($tmplTermIds, $query) use ($postTermIds, $postTaxonomies): bool {
            $includeChildren = array_any(
                $postTaxonomies,
                fn($taxonomy) => $query["{$taxonomy}_include_children"] ?? false,
            );

            // Bail early if no taxonomy is set to include children
            if (!$includeChildren && !array_intersect($postTermIds, $tmplTermIds)) {
                return false;
            }

            $terms = get_terms(['include' => $tmplTermIds, 'hide_empty' => false]);
            $taxonomies = array_unique(array_column($terms, 'taxonomy'));
            foreach ($terms as $term) {
                $match = in_array($term->term_id, $postTermIds);
                $includeChildren = $query["{$term->taxonomy}_include_children"] ?? false;

                if ($match && $includeChildren === 'only') {
                    continue;
                }

                if (
                    $match ||
                    ($includeChildren &&
                        array_any(
                            $postTermIds,
                            fn($id) => term_is_ancestor_of($term->term_id, $id, $term->taxonomy),
                        ))
                ) {
                    unset($taxonomies[array_search($term->taxonomy, $taxonomies)]);

                    if (empty($taxonomies)) {
                        return true;
                    }
                }
            }
            return empty($taxonomies);
        };
    }

    protected function getTaxonomyTermsFn(WP_Term $term): Closure
    {
        return function ($termIds, $query) use ($term): bool {
            $includeChildren = $query['include_children'] ?? false;

            $match = in_array($term->term_id, $termIds);

            if (!$includeChildren || ($match && $includeChildren === 'include')) {
                return $match;
            }

            if ($match && $includeChildren === 'only') {
                return false;
            }

            return array_any(
                $termIds,
                fn($id) => term_is_ancestor_of($id, $term->term_id, $term->taxonomy),
            );
        };
    }

    protected function getTaxonomyTermsFilterFn(): Closure
    {
        return function ($terms): bool {
            global $wp_query;

            if (empty($wp_query->tax_query->queried_terms)) {
                return false;
            }

            foreach ($terms as $id) {
                $term = get_term($id);

                if (!$term) {
                    continue;
                }

                $queried = $wp_query->tax_query->queried_terms[$term->taxonomy] ?? null;
                foreach ($queried['terms'] ?? [] as $t) {
                    $t = get_term_by($queried['field'], $t, $term->taxonomy);

                    if ($t && $t->term_id === $term->term_id) {
                        return true;
                    }
                }
            }
            return false;
        };
    }
}
