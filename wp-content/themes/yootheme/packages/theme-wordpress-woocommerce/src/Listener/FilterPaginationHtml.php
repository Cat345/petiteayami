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
     *
     * @param array<string, mixed> $args
     *
     * @return array<string, mixed>
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
     *
     * @param array<string, mixed> $args
     */
    public function links(string $r, array $args): string
    {
        if ($args['type'] === 'yootheme_woocommerce') {
            return $this->view->render(Path::join(__DIR__, '../../templates/pagination'), [
                'args' => $args,
                'links' => explode("\n", $r),
            ]);
        }

        return $r;
    }
}
