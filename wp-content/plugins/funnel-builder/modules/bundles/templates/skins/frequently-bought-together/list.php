<?php
/** Frequently Bought Together · List — authored skin template: one option card per product, summary below. */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

ob_start();
?>
<div class="wffn-bundles wffn-bundles-fbt wffn-bundles-fbt--list" data-wffn-bundles-type="frequently_bought_together" data-wffn-bundles-layout="list" data-wffn-bundles-attr="data-wffn-bundles-bundle:bundleId, data-wffn-bundles-product:productId, data-wffn-bundles-min:minCompanions">
	<div class="wffn-bundles-heading" data-wffn-bundles-if="headingShow" data-wffn-bundles-html="heading"></div>
	<div class="wffn-bundles-subheading" data-wffn-bundles-if="subheadingShow" data-wffn-bundles-text="subheading"></div>

	<div class="wffn-bundles-fbt-items">
		<label class="wffn-bundles-fbt-item" data-wffn-bundles-repeat="rows" data-wffn-bundles-class="rowClass" data-wffn-bundles-attr="data-wffn-bundles-key:key">
			<input type="checkbox" class="wffn-bundles-fbt-check" data-wffn-bundles-attr="aria-label:name" data-wffn-bundles-bool-attr="checked:checked, disabled:isHost" />
			<span class="wffn-bundles-fbt-thumb wffn-bundles-fbt-thumb--row"><img data-wffn-bundles-if="imageShow" data-wffn-bundles-attr="src:image" alt="" /></span>
			<span class="wffn-bundles-fbt-main">
				<span class="wffn-bundles-fbt-head">
					<span class="wffn-bundles-fbt-name"><span class="wffn-bundles-fbt-this" data-wffn-bundles-if="isHost" data-wffn-bundles-text="thisLabel"></span> <span data-wffn-bundles-text="name"></span></span>
					<span class="wffn-bundles-fbt-prices">
						<span class="wffn-bundles-fbt-price-original" data-wffn-bundles-if="showStrike" data-wffn-bundles-text="priceOriginal"></span>
						<span class="wffn-bundles-fbt-price-final" data-wffn-bundles-text="priceFinal"></span>
					</span>
				</span>
				<span class="wffn-bundles-variant" data-wffn-bundles-if="variantShow" data-wffn-bundles-attr="data-wffn-bundles-variations:variantMatrix, data-wffn-bundles-vinfo:variantInfo, data-wffn-bundles-vsummary:variantSummary"><span class="wffn-bundles-variant-head" aria-hidden="true" data-wffn-bundles-if="variantHead" data-wffn-bundles-text="variantHead"></span>
					<select class="wffn-bundles-variant-axis" data-wffn-bundles-repeat="variantAxes">
						<option data-wffn-bundles-repeat="options" data-wffn-bundles-attr="value:value" data-wffn-bundles-bool-attr="selected:selected" data-wffn-bundles-text="label"></option>
					</select>
				</span>
				<span class="wffn-bundles-fbt-tag" data-wffn-bundles-if="inCart" data-wffn-bundles-text="inCartLabel"></span>
			</span>
		</label>
	</div>

	<div class="wffn-bundles-fbt-summary">
		<div class="wffn-bundles-fbt-sum-price">
			<span class="wffn-bundles-fbt-total-label" data-wffn-bundles-text="totalLabel"></span>
			<span class="wffn-bundles-fbt-price-original" data-wffn-bundles-if="totalShowStrike" data-wffn-bundles-text="totalOriginal"></span>
			<span class="wffn-bundles-fbt-price-final wffn-bundles-fbt-total" data-wffn-bundles-text="totalFinal"></span>
		</div>
		<div class="wffn-bundles-fbt-sum-note">
			<span class="wffn-bundles-fbt-save" data-wffn-bundles-if="saveShow" data-wffn-bundles-text="saveText"></span>
			<span class="wffn-bundles-fbt-rule" data-wffn-bundles-if="ruleShow" data-wffn-bundles-text="ruleText"></span>
		</div>
		<button type="button" class="button alt wp-element-button wffn-bundles-fbt-add" data-wffn-bundles-if="buttonShow" data-wffn-bundles-own-add data-wffn-bundles-text="buttonText"></button>
		<span class="wffn-bundles-fbt-hint" role="status" hidden></span>
	</div>
</div>
<?php
$wffn_bundles_tpl = rtrim( ob_get_clean(), "\n" );

return $wffn_bundles_tpl;
