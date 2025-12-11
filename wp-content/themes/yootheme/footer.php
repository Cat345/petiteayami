<?php
/**
 * The template for displaying the footer.
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 */

namespace YOOtheme;

[$config, $view] = app(Config::class, View::class);

?>
        <?php if (!$config('app.isBuilder')) : ?>

                        <?php if (is_active_sidebar('sidebar')) : ?>
                        </div>

                        <?= $view('~theme/templates/sidebar') ?>

                    </div>
                     <?php endif ?>

                </div>
                <?php endif ?>

            </main>

            <?php dynamic_sidebar("bottom:section") ?>

            <?php if ($config('~theme.footer.content')) : ?>
            <footer>
                <?= $view->builder($config('~theme.footer.content'), 'footer') ?>
            </footer>
            <?php endif ?>

        </div>

        <?php if ($config('~theme.site.layout') == 'boxed') : ?>
        </div>
        <?php endif ?>

        <?php wp_footer() ?>
    </body>
</html>
