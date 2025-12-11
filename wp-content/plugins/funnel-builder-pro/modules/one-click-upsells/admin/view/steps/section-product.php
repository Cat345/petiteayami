<style>
    .show_border {
        border: 1px solid dodgerblue
    }

    .remove_border {
        border: 1px solid #eee
    }
</style>
<div class="wfocu-loader"><img src="<?php echo admin_url( 'images/spinner-2x.gif' ); ?>"/></div>
<div id="wfocu_offer_area">
    <div class="offer_forms" id="funnel_products">
		<?php
		include_once __DIR__ . "/parts/offer-product-table.php";
		include_once __DIR__ . "/parts/add-product-or-offer.php";
		?>
    </div>
</div>

<?php include_once __DIR__ . "/parts/offer-settings.php" ?>

<!-- Update offer -->
<?php
    include_once __DIR__ . "/model/add-offer.php";
include_once __DIR__ . "/model/update-offer.php";
include_once __DIR__ . "/model/add-products.php";
?>



