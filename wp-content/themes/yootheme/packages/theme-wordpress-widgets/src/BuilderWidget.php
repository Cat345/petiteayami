<?php

use YOOtheme\Builder;
use YOOtheme\Str;
use YOOtheme\View\HtmlElement;
use function YOOtheme\app;

class BuilderWidget extends WP_Widget
{
    /**
     * @inheritdoc
     */
    public function __construct()
    {
        parent::__construct('', 'Builder', [
            'description' => __('A Layout Builder for your site.', 'yootheme'),
            'settings' => ['title' => '', 'content' => '', 'element' => ''],
        ]);
    }

    /**
     * @inheritdoc
     */
    public function widget($args, $instance)
    {
        $output = [$args['before_widget']];
        $settings = array_merge($this->widget_options['settings'], $instance);

        if ($settings['title']) {
            array_push($output, $args['before_title'], $settings['title'], $args['after_title']);
        }

        $settings['content'] = app(Builder::class)->render($settings['content'], [
            'prefix' => $args['widget_id'],
        ]);

        if ($settings['content'] && in_array($args['id'] ?? '', ['top', 'bottom'])) {
            $settings['content'] = HtmlElement::tag(
                $settings['element'] ?: 'div',
                ['id' => $args['widget_id'], 'class' => 'builder'],
                $settings['content'],
            );
        }

        array_push($output, $settings['content'], $args['after_widget']);

        echo implode('', $output);
    }

    /**
     * @inheritdoc
     */
    public function form($instance)
    {
        $settings = array_merge($this->widget_options['settings'], $instance);
        $content = $settings['content']
            ? json_encode(app(Builder::class)->load($settings['content']))
            : '';

        $elements = ['div', 'address', 'article', 'aside', 'footer', 'header', 'nav', 'section'];
        ?>
        <p>
            <label for="<?= $this->get_field_id('title') ?>"><?= __('Title:', 'yootheme') ?></label>
            <input id="<?= $this->get_field_id(
                'title',
            ) ?>" class="input-title widefat" type="text" name="<?= $this->get_field_name('title') ?>" value="<?= esc_attr($settings['title']) ?>">
        </p>
        <p>
            <label for="<?= $this->get_field_id(
                'element',
            ) ?>"><?= __('HTML Element', 'yootheme') ?>:</label>
            <select id="<?= $this->get_field_id(
                'element',
            ) ?>" name="<?= $this->get_field_name('element') ?>" class="widefat">
                <?php foreach ($elements as $element): ?>
                    <?php
                    $title = Str::titleCase($element);
                    $value = $element == 'div' ? '' : $element;
                    $selected = $settings['element'] == $value ? 'selected' : '';
                    ?>
                    <option value="<?= $value ?>"<?= $selected ?>><?= $title ?></option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <?= __('Browse to widgets in the customizer to open the page builder.', 'yootheme') ?>
            <input class="input-content" type="hidden" name="<?= $this->get_field_name(
                'content',
            ) ?>" value="<?= esc_attr($content) ?>" >
        </p>
        <?php return '';
    }

    /**
     * @inheritdoc
     */
    public function update($new_instance, $old_instance)
    {
        if (isset($new_instance['content'])) {
            $new_instance['content'] = json_encode(
                app(Builder::class)
                    ->withParams(['context' => 'save'])
                    ->load($new_instance['content']),
            );
        }

        return parent::update($new_instance, $old_instance);
    }
}
