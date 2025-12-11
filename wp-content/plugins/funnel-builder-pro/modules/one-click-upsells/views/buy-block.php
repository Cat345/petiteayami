<?php
$style = WFOCU_Common::get_option( 'wfocu_buy_block_buy_block_style' );
switch ( $style ) {
	case 'style1':
	case 'style2':
		WFOCU_Core()->template_loader->get_template_part( 'buy-block/' . $style, $data );
		break;
}
