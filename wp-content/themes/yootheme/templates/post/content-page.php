<?php
/**
 * Template part for displaying pages.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy
 */

namespace YOOtheme;

global $multipage, $numpages, $page;

?>

<article id="post-<?php the_ID() ?>" <?php post_class('uk-article') ?> typeof="Article" vocab="https://schema.org/">

    <meta property="name" content="<?= esc_html(get_the_title()) ?>">
    <meta property="author" typeof="Person" content="<?= esc_html(get_the_author()) ?>">
    <meta property="dateModified" content="<?= get_the_modified_date('c') ?>">
    <meta class="uk-margin-remove-adjacent" property="datePublished" content="<?= get_the_date('c') ?>">

    <?php if ($thumbnail = get_the_post_thumbnail('', 'post-thumbnail')) : ?>
    <div class="uk-margin-large-bottom" property="image" typeof="ImageObject">
        <meta property="url" content="<?= get_the_post_thumbnail_url() ?>">
        <?= $thumbnail ?>
    </div>
    <?php endif ?>

    <?php the_title('<h1 class="uk-article-title">', '</h1>') ?>

    <div class="uk-margin-medium" property="text">

        <?php if ($multipage) : ?>
            <p class="uk-text-meta tm-page-break <?= ($page == '1') ? 'tm-page-break-first-page' : '' ?>"><?= sprintf(__('Page %s of %s', 'yootheme'), $page, $numpages) ?></p>
        <?php endif ?>

        <?php the_content('') ?>

        <?= link_pages() ?>

    </div>

</article>
