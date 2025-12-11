<?php

namespace YOOtheme\Theme\Wordpress\WooCommerce\Listener;

use YOOtheme\Path;
use YOOtheme\View;

class FilterPaginationHtml
{
    public View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /**
     * Filters the pagination args.
     */
    public static function args(array $args): array
    {
        if ($args['type'] === 'list') {
            return [
                'type' => 'yootheme_woocommerce',
                'mid_size' => 3,
                'end_size' => 1,
                'next_text' => '<span uk-pagination-next></span>',
                'prev_text' => '<span uk-pagination-previous></span>',
            ] + $args;
        }

        return $args;
    }

    /**
     * Renders the pagination template.
     *
     * @link https://developer.wordpress.org/reference/hooks/paginate_links_output/
     */
    public function links(string $r, array $args): string
    {
        if ($args['type'] === 'yootheme_woocommerce') {
            return $this->view->render(Path::get('../../templates/pagination', __DIR__), [
                'args' => $args,
                'links' => explode("\n", $r),
            ]);
        }

        return $r;
    }
}
