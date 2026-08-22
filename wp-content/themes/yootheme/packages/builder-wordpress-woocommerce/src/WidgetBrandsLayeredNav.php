<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

use WC_Widget_Brand_Nav;

/**
 * Widget layered nav class.
 */
class WidgetBrandsLayeredNav extends WC_Widget_Brand_Nav
{
    /**
     * Show dropdown layered nav.
     *
     * @param list<\WP_Term>  $terms Terms.
     * @param string $taxonomy Taxonomy.
     * @param  int    $depth Depth.
     */
    protected function layered_nav_list($terms, $taxonomy, $depth = 0): bool
    {
        global $wp;

        if ($taxonomy === $this->get_current_taxonomy()) {
            return false;
        }

        if ('' === get_option('permalink_structure')) {
            $form_action = remove_query_arg(
                ['page', 'paged'],
                add_query_arg($wp->query_string, '', home_url($wp->request)),
            );
        } else {
            $form_action = preg_replace(
                '%\/page/[0-9]+%',
                '',
                home_url(user_trailingslashit($wp->request)),
            );
        }

        echo '<form method="get" action="' .
            esc_url($form_action) .
            '" class="woocommerce-widget-layered-nav-dropdown">';

        $found = $this->render_term_list($terms, $taxonomy, $depth);

        $_chosen_attributes = $this->get_chosen_attributes();
        $current_values = !empty($_chosen_attributes) ? $_chosen_attributes : [];

        echo '<input type="hidden" name="filter_' .
            esc_attr($taxonomy) .
            '" value="' .
            esc_attr(implode(',', $current_values)) .
            '">';

        echo wc_query_string_form_fields(
            null,
            ['filter_' . $taxonomy, "query_type_{$taxonomy}"],
            '',
            true,
        );

        echo '</form>';

        $jsTaxonomyFilterName = esc_js($taxonomy);
        wc_enqueue_js(
            "
                    // Update value on change.
                    jQuery( '.list_layered_nav_{$jsTaxonomyFilterName}' ).on( 'change', function() {

                        var form = jQuery( this ).closest( 'form' );
                        var value = form.find('li input').map(function (i, el) {
                            return el.checked ? el.value : '';
                        }).toArray().filter(Boolean).join(',');

                        jQuery( ':input[name=\"filter_{$jsTaxonomyFilterName}\"]' ).val( value );

                        // Submit form on change if standard dropdown.
                        form.trigger( 'submit' );
                    });
                ",
        );

        return $found;
    }

    /**
     * Show dropdown layered nav.
     *
     * @param list<\WP_Term>  $terms Terms.
     * @param string $taxonomy Taxonomy.
     * @param  int    $depth Depth.
     */
    protected function render_term_list($terms, $taxonomy, $depth = 0): bool
    {
        $found = false;
        $term_counts = $this->get_filtered_term_product_counts(
            wp_list_pluck($terms, 'term_id'),
            $taxonomy,
            'or',
        );
        $_chosen_attributes = $this->get_chosen_attributes();

        // List display.
        echo '<ul class="' .
            ($depth === 0 ? '' : 'children ') .
            'wc-brand-list-layered-nav-' .
            esc_attr($taxonomy) .
            '">';

        foreach ($terms as $term) {
            // If on a term page, skip that term in widget list.
            if ($term->term_id === $this->get_current_term_id()) {
                continue;
            }

            // Get count based on current view.
            $current_values = !empty($_chosen_attributes) ? $_chosen_attributes : [];
            $option_is_set = in_array($term->term_id, $current_values, true);
            $count = $term_counts[$term->term_id] ?? 0;

            // Only show options with count > 0.
            if (0 < $count) {
                $found = true;
            } elseif (0 === $count && !$option_is_set) {
                continue;
            }

            $child_terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => true,
                'parent' => $term->term_id,
            ]);

            echo '<li><label><input class="uk-checkbox uk-margin-xsmall-right list_layered_nav_' .
                esc_attr($taxonomy) .
                '" type="checkbox" value="' .
                esc_attr(urldecode((string) $term->term_id)) .
                '"' .
                checked($option_is_set, true, false) .
                '> ' .
                esc_html($term->name) .
                ' ' .
                apply_filters(
                    'woocommerce_layered_nav_count',
                    '<span class="count">(' . absint($count) . ')</span>',
                    $count,
                    $term,
                ) .
                '</label>';

            if (!empty($child_terms)) {
                $this->render_term_list($child_terms, $taxonomy, $depth + 1);
            }

            echo '</li>';
        }

        echo '</ul>';

        return $found;
    }
}
