<?php
/*
 * Template Name: SlingBlocks Canvas
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta charset="<?php bloginfo( 'charset' ); ?>"/>
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<div class="slb-container">
	<?php
	do_action( 'slingblocks_before_content' );
	the_content();
	do_action( 'slingblocks_after_content' );
	wp_footer();
	?>
</div>
</body>
</html>
