<?php
/**
 * License key notice view.
 *
 * @since 4.7.7
 *
 * @var array  $notice        Notice data (type, class, heading, paragraphs, actions).
 * @var string $dismiss_nonce Dismiss AJAX nonce.
 * @var string $dismiss_action Dismiss AJAX action.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="notice <?php echo esc_attr( $notice['class'] ); ?> acfwf-license-key-notice acfwf-license-key-notice--<?php echo esc_attr( str_replace( '_', '-', $notice['type'] ) ); ?>" data-notice-type="<?php echo esc_attr( $notice['type'] ); ?>" data-nonce="<?php echo esc_attr( $dismiss_nonce ); ?>" data-action="<?php echo esc_attr( $dismiss_action ); ?>">
    <style>
        .acfwf-license-key-notice {
            position: relative;
            padding: 12px 40px 12px 16px;
        }
        .acfwf-license-key-notice h3 {
            margin: 0 0 8px;
            font-size: 14px;
        }
        .acfwf-license-key-notice p {
            margin: 0 0 8px;
        }
        .acfwf-license-key-notice .acfwf-license-key-notice-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            margin: 12px 0 0;
        }
        .woocommerce-layout__notice-list-hide ~ .acfwf-license-key-notice {
            margin-left: 20px;
            margin-right: 20px;
        }
    </style>

    <h3><?php echo esc_html( $notice['heading'] ); ?></h3>

    <?php foreach ( $notice['paragraphs'] as $paragraph ) : ?>
        <p><?php echo esc_html( $paragraph ); ?></p>
    <?php endforeach; ?>

    <p class="acfwf-license-key-notice-actions">
        <?php foreach ( $notice['actions'] as $notice_action ) : ?>
            <a
                class="acfwf-license-key-notice-action acfwf-license-key-notice-action--<?php echo esc_attr( $notice_action['key'] ); ?> <?php echo $notice_action['is_primary'] ? 'button button-primary' : ''; ?>"
                href="<?php echo esc_url( $notice_action['link'] ); ?>"
                <?php echo $notice_action['is_external'] ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
            ><?php echo esc_html( $notice_action['text'] ); ?></a>
        <?php endforeach; ?>
        <button type="button" class="button-link acfwf-license-key-notice-dismiss"><?php esc_html_e( 'Dismiss', 'advanced-coupons-for-woocommerce-free' ); ?></button>
    </p>

    <button type="button" class="notice-dismiss acfwf-license-key-notice-close">
        <span class="screen-reader-text"><?php esc_html_e( 'Dismiss this notice.', 'advanced-coupons-for-woocommerce-free' ); ?></span>
    </button>

    <script>
    jQuery( function ( $ ) {
        $( '.acfwf-license-key-notice' ).on( 'click', '.acfwf-license-key-notice-dismiss, .acfwf-license-key-notice-close', function ( e ) {
            var $notice = $( e.delegateTarget );

            e.preventDefault();

            $.post( ajaxurl, {
                action: $notice.data( 'action' ),
                notice_type: $notice.data( 'notice-type' ),
                nonce: $notice.data( 'nonce' )
            } );

            $notice.fadeOut( 300, function () {
                $notice.remove();
            } );
        } );
    } );
    </script>
</div>
