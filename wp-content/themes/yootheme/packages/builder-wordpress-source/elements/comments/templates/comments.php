<?php

namespace YOOtheme;

$view = app(View::class);
$globals = $view->getGlobals();
$props = $globals['comments.props'];

// Title
$title = $view->el($props['title_element'], [

    'class' => [
        'el-title',
        'uk-[text-{@title_style: meta|lead}]{title_style}',
        'uk-heading-{title_decoration}',
        'uk-font-{title_font_family}',
        'uk-text-{title_color} {@!title_color: background}',
    ],

]);

$titleInner = $props['title_color'] == 'background' || $props['title_decoration'] == 'line' ? $view->el('span', [

    'class' => [
        'uk-text-background' => $props['title_color'] == 'background',
    ],

]) : null;

$titleText = sprintf(_n(__('Comment'), __('Comments (%s)', 'yootheme'), get_comments_number()), number_format_i18n(get_comments_number()))

?>

<?php if (have_comments()) : ?>

    <?= $title($props, $titleInner ? $titleInner([], $titleText) : $titleText) ?>

    <ul class="uk-comment-list uk-margin-medium-top">
        <?php wp_list_comments([
            'style' => 'ul',
            'short_ping' => true,
            'callback' => function ($comment, $args, $depth) use ($view) {
                echo $view->render(__DIR__ . '/comment', compact('comment', 'args', 'depth'));
            },
            'avatar_size' => 60,
        ]) ?>
    </ul>

    <?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : ?>
    <nav class="uk-margin">
        <ul class="uk-pagination uk-margin-remove-bottom uk-flex-between">
            <li><?php previous_comments_link('<span uk-pagination-previous></span> ' . __('Older Comments')) ?></li>
            <li><?php next_comments_link(__('Newer Comments') . ' <span uk-pagination-next></span>') ?></li>
        </ul>
    </nav>
    <?php endif ?>

<?php endif ?>

<?php if (!comments_open() && get_comments_number() && post_type_supports(get_post_type(), 'comments')) : ?>
<p class="uk-margin-medium uk-text-danger"><?= __('Comments are closed.') ?></p>
<?php endif ?>

<?php get_template_part('packages/builder-wordpress-source/elements/comments/templates/commentform') ?>
