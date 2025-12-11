<?php

namespace YOOtheme\Builder\Wordpress\Woocommerce;

use WC_Query;
use WC_Widget_Layered_Nav;

/**
 * Widget layered nav class.
 */
class WidgetLayeredNav extends WC_Widget_Layered_Nav
{
    /**
     * Show dropdown layered nav.
     *
     * @param array  $terms Terms.
     * @param string $taxonomy Taxonomy.
     * @param string $query_type Query Type.
     */
    protected function layered_nav_list($terms, $taxonomy, $query_type): bool
    {
        global $wp;
        $found = false;

        if ($taxonomy === $this->get_current_taxonomy()) {
            return $found;
        }

        $term_counts = $this->get_filtered_term_product_counts(
            wp_list_pluck($terms, 'term_id'),
            $taxonomy,
            $query_type,
        );
        $_chosen_attributes = WC_Query::get_layered_nav_chosen_attributes();
        $taxonomy_filter_name = wc_attribute_taxonomy_slug($taxonomy);
        $taxonomy_label = wc_attribute_label($taxonomy);

        /* translators: %s: taxonomy name */
        $any_label = apply_filters(
            'woocommerce_layered_nav_any_label',
            sprintf(__('Any %s', 'woocommerce'), $taxonomy_label),
            $taxonomy_label,
            $taxonomy,
        );

        $current_values = $_chosen_attributes[$taxonomy]['terms'] ?? [];

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

        // List display.
        echo '<ul class="woocommerce-widget-layered-nav-list">';

        foreach ($terms as $term) {
            // If on a term page, skip that term in widget list.
            if ($term->term_id === $this->get_current_term_id()) {
                continue;
            }

            // Get count based on current view.
            $option_is_set = in_array($term->slug, $current_values, true);
            $count = $term_counts[$term->term_id] ?? 0;

            // Only show options with count > 0.
            if (0 < $count) {
                $found = true;
            } elseif (0 === $count && !$option_is_set) {
                continue;
            }

            echo '<li><label><input class="uk-checkbox uk-margin-xsmall-right list_layered_nav_' .
                esc_attr($taxonomy_filter_name) .
                '" type="checkbox" value="' .
                esc_attr(urldecode($term->slug)) .
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
                '</label></li>';
        }

        echo '</ul>';

        echo '<input type="hidden" name="filter_' .
            esc_attr($taxonomy_filter_name) .
            '" value="' .
            esc_attr(implode(',', $current_values)) .
            '">';

        echo wc_query_string_form_fields(
            null,
            ['filter_' . $taxonomy_filter_name, "query_type_{$taxonomy_filter_name}"],
            '',
            true,
        );

        echo '</form>';

        $jsTaxonomyFilterName = esc_js($taxonomy_filter_name);
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
}
