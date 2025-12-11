<?php
/**
 * Template Name: Default UpStroke Offer Template
 *
 * @package UpStroke
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="no-js">
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="profile" href="http://gmpg.org/xfn/11">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<div class="wfocu-container">
    <div class="wfocu-primary-content">
		<?php
		while ( have_posts() ) :
			the_post();


			the_content();

		endwhile;
		?>
    </div>
</div>
<?php wp_footer(); ?>

</body>

</html>

<?php
