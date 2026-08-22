<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}


$iconbgcolor   = $sy_options['s-icon-bgcolor'];
$iconcolor     = $sy_options['s-icon-color'];
$iconsize      = $sy_options['s-icon-size'];
$iconwidth     = $sy_options['s-icon-width'];
$fieldmargin   = $sy_options['s-field-bmargin'];
$inputbgcolor  = $sy_options['s-input-bgcolor'];
$inputtxtcolor = $sy_options['s-input-txtcolor'];
$focusbgcolor  = $sy_options['s-input-focusbgcolor'];
$focustxtcolor = $sy_options['s-input-focustxtcolor'];
$inputheight   = $sy_options['s-input-height'];
$reqiredSymbol = $sy_options['s-show-reqicon'];
$iconAlign     = $sy_options['s-icon-align'];

$inputBorderCSS        = $ff_helper->get_border_css_value( $sy_options['s-input-border'] );
$inputBorderFocus      = $ff_helper->get_border_css_value( $sy_options['s-input-border-focus'] );
$inputIconColorFocus   = $sy_options['s-icon-color-focus'];
$inputFsize            = $sy_options['s-input-fsize'];
$labelFsize            = $sy_options['s-label-fsize'];
$inputIconBGColorFocus = $sy_options['s-icon-bgcolor-focus'] ?: 'transparent';

$inputIconPadding = (int) $iconwidth + ( $iconbgcolor ? 10 : 0 );
$showIcons        = $sy_options['s-show-icons'] === 'yes';

?>

.xoo-aff-input-group .xoo-aff-input-icon {
    position: absolute;
    z-index: 4;
    top: 0;
    bottom: 0;

    background-color: <?php echo esc_html( $iconbgcolor ); ?>;
    color: <?php echo esc_html( $iconcolor ); ?>;

    max-width: <?php echo (int) $iconwidth; ?>px;
    min-width: <?php echo (int) $iconwidth; ?>px;
    font-size: <?php echo (int) $iconsize; ?>px;

    <?php if ( $iconbgcolor ) : ?>
        <?php 
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $inputBorderCSS;
        ?>
    <?php else : ?>
        border: 0;
        border-radius: 0;
    <?php endif; ?>

    <?php echo esc_html( $iconAlign ); ?>: 0;
}

<?php if ( $showIcons && $iconAlign === 'right' && ! is_rtl() ) : ?>
.xoo-aff-pw-toggle {
    right: <?php echo (int) $iconwidth + 5; ?>px;
}
<?php endif; ?>

<?php if ( $showIcons && $iconAlign === 'left' && is_rtl() ) : ?>
body.rtl .xoo-aff-pw-toggle {
    left: <?php echo (int) $iconwidth + 5; ?>px;
}
<?php endif; ?>

.xoo-aff-isfocused.xoo-aff-group .xoo-aff-input-icon {
    color: <?php echo esc_html( $inputIconColorFocus ); ?>;
    background-color: <?php echo esc_html( $inputIconBGColorFocus ); ?>;
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $inputBorderFocus;
    ?>
}

.xoo-aff-input-group .xoo-aff-input-icon,
.xoo-aff-isfocused.xoo-aff-group .xoo-aff-input-icon {

    <?php if ( $iconAlign === 'left' ) : ?>
        border-right: 0;
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    <?php else : ?>
        border-left: 0;
        border-top-left-radius: 0;
        border-bottom-left-radius: 0;
    <?php endif; ?>
}

.xoo-aff-group {
    margin-bottom: <?php echo (int) $fieldmargin; ?>px;
}

.xoo-aff-group label {
    font-size: <?php echo (int) $labelFsize; ?>px;
}

.xoo-aff-group input[type="text"].xoo-aff-field,
.xoo-aff-group input[type="password"].xoo-aff-field,
.xoo-aff-group input[type="email"].xoo-aff-field,
.xoo-aff-group input[type="number"].xoo-aff-field,
.xoo-aff-group input[type="tel"].xoo-aff-field,
.xoo-aff-group input[type="file"].xoo-aff-field,
.xoo-aff-group select.xoo-aff-field,
.xoo-aff-group select.xoo-aff-field  + .select2{
    background-color: <?php echo esc_html( $inputbgcolor ); ?>;
    color: <?php echo esc_html( $inputtxtcolor ); ?>;

    height: <?php echo (int) $inputheight; ?>px;
    line-height: <?php echo (int) $inputheight; ?>px;

    padding: 12px;
    font-size: <?php echo (int) $inputFsize; ?>px;

    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $inputBorderCSS;
    ?>
}

.xoo-aff-input-group input[type="text"].xoo-aff-field,
.xoo-aff-input-group input[type="password"].xoo-aff-field,
.xoo-aff-input-group input[type="email"].xoo-aff-field,
.xoo-aff-input-group input[type="number"].xoo-aff-field,
.xoo-aff-input-group input[type="tel"].xoo-aff-field,
.xoo-aff-input-group input[type="file"].xoo-aff-field,
.xoo-aff-input-group select.xoo-aff-field,
.xoo-aff-input-group select.xoo-aff-field + .select2 {
    padding-left: <?php echo ( $iconAlign === 'left' && $showIcons ) ? (float) $inputIconPadding : 12; ?>px;
    padding-right: <?php echo ( $iconAlign === 'right' && $showIcons ) ? (float) $inputIconPadding : 12; ?>px;
}

.xoo-aff-group input[type="file"].xoo-aff-field {
    line-height: calc(<?php echo (int) $inputheight; ?>px - 24px);
}

.xoo-aff-group .xoo-aff-field::placeholder,
.xoo-aff-group .select2-selection__rendered,
.xoo-aff-group .select2-container--default .select2-selection--single .select2-selection__rendered,
.xoo-aff-group input.xoo-aff-field::file-selector-button {
    color: <?php echo esc_html( $inputtxtcolor ); ?>;
}

.xoo-aff-group input[type="text"].xoo-aff-field:focus,
.xoo-aff-group input[type="password"].xoo-aff-field:focus,
.xoo-aff-group input[type="email"].xoo-aff-field:focus,
.xoo-aff-group input[type="number"].xoo-aff-field:focus,
.xoo-aff-group input[type="tel"].xoo-aff-field:focus,
.xoo-aff-group input[type="file"].xoo-aff-field:focus,
.xoo-aff-group select.xoo-aff-field:focus,
.xoo-aff-group select.xoo-aff-field:focus  + .select2 {
    background-color: <?php echo esc_html( $focusbgcolor ); ?>;
    color: <?php echo esc_html( $focustxtcolor ); ?>;
    <?php
    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    echo $inputBorderFocus;
    ?>
}

.xoo-aff-field[placeholder]:focus::-webkit-input-placeholder {
    color: <?php echo esc_html( $focustxtcolor ); ?> !important;
}

<?php if ( ! $showIcons ) : ?>
	.xoo-aff-input-group .xoo-aff-input-icon,
	.xoo-aff-pw-toggle {
	    display: none !important;
	}
<?php endif; ?>

<?php if ( $reqiredSymbol === 'yes' ) : ?>
	.xoo-aff-cont-required::after {
	    content: '*';
	    position: absolute;
	    top: 2px;
	    right: 5px;
	    z-index: 10;
	    font-weight: 600;
	    opacity: 0.5;
	}

	body.rtl .xoo-aff-cont-required::after {
	    left: 5px;
	    right: auto;
	}
<?php endif; ?>