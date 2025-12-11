<?php

namespace YOOtheme\Theme\Widgets;

use function YOOtheme\app;
use YOOtheme\Theme\Wordpress\Breadcrumbs;
use YOOtheme\View;

class BreadcrumbsWidget extends \WP_Widget
{
    public function __construct()
    {
        parent::__construct('breadcrumbs', 'Breadcrumbs', [
            'description' => __('Display your sites breadcrumb navigation.', 'yootheme'),
        ]);
    }

    public function widget($args, $instance)
    {
        $view = app(View::class);

        $output = [$args['before_widget']];

        if ($instance['title']) {
            $output[] = $args['before_title'] . $instance['title'] . $args['after_title'];
        }

        $output[] = $view->render('~theme/templates/breadcrumbs', [
            'items' => Breadcrumbs::getItems($instance),
        ]);
        $output[] = $args['after_widget'];

        echo implode("\n", $output);
    }

    public function form($instance)
    {
        $instance = wp_parse_args((array) $instance, [
            'title' => '',
            'show_current' => true,
            'show_home' => true,
            'home_text' => '',
        ]); ?>
        <p>
            <label for="<?= $this->get_field_id('title') ?>"><?= __('Title:', 'yootheme') ?></label>
            <input type="text" name="<?= $this->get_field_name(
                'title',
            ) ?>" value="<?= esc_attr($instance['title']) ?>" class="widefat" id="<?= $this->get_field_id('title') ?>">
        </p>
        <p>
            <label for="<?= $this->get_field_id(
                'home_text',
            ) ?>"><?= __('Home Text:', 'yootheme') ?></label>
            <input type="text" name="<?= $this->get_field_name(
                'home_text',
            ) ?>" value="<?= esc_attr($instance['home_text']) ?>" class="widefat" id="<?= $this->get_field_id('home_text') ?>">
        </p>
        <p>
            <input type="hidden" name="<?= $this->get_field_name('show_home') ?>" value="">
            <input type="checkbox" name="<?= $this->get_field_name(
                'show_home',
            ) ?>" class="widefat" id="<?= $this->get_field_id('show_home') ?>" value="1"<?= $instance['show_home'] ? ' checked' : '' ?>>
            <label for="<?= $this->get_field_id(
                'show_home',
            ) ?>"><?= __('Show home link', 'yootheme') ?></label>
        </p>
        <p>
            <input type="hidden" name="<?= $this->get_field_name('show_current') ?>" value="">
            <input type="checkbox" name="<?= $this->get_field_name(
                'show_current',
            ) ?>" class="widefat" id="<?= $this->get_field_id('show_current') ?>" value="1"<?= $instance['show_current'] ? ' checked' : '' ?>>
            <label for="<?= $this->get_field_id(
                'show_current',
            ) ?>"><?= __('Show current page', 'yootheme') ?></label>
        </p>
        <?php return '';
    }
}
