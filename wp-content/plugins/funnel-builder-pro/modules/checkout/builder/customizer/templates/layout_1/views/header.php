<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="UTF-8">
    <title><?php echo $page_meta_title ? $page_meta_title : get_bloginfo( 'name' ); ?></title>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
	<?php wp_head(); ?>
	<?php
	do_action( 'wfacp_header_print_in_head' );
	?>
    <style>
        fieldset {
            border: 1px solid #000;
            padding: 5px;
            margin-top: 10px;
        }

        fieldset legend {
            display: inline;
        }

        .wfacp_page.two_step {
            display: none;
        }

        button.button.button-primary {
            width: 120px;
            padding: 10px;
            margin-top: 14px;
            background: #3665A6;
            color: #Fff;
            font-weight: 600;
            margin-bottom: 15px;
        }

        label.wfacp-input-animated {
            display: none;
        }
    </style>
</head>
<body class="<?php echo $this->get_class_from_body(); ?>">