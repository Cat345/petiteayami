<?php
/**
 * `.wffn-bundles-fb-member-head` puts a member's name and price on one line so the
 * picker below can have the summary column's full width. The price used to be a
 * sibling of that column, so the two competed for one track: the price won (its
 * content barely wraps) and left the pair of <select>s ~100px between them on a
 * 350px row — or the row wrapped and dropped the price onto a line of its own,
 * 59-91px below the name it prices.
 */
/** Fixed Bundle · Stacked — authored skin template (matches React FixedBundleCard). */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

ob_start();
?>
<div class="wffn-bundles wffn-bundles-fb wffn-bundles-fb--stacked" data-wffn-bundles-type="fixed_bundles" data-wffn-bundles-layout="stacked" data-wffn-bundles-class="oosClass">
	<div class="wffn-bundles-heading" data-wffn-bundles-if="headingShow" data-wffn-bundles-html="heading"></div>
	<div class="wffn-bundles-subheading" data-wffn-bundles-if="subheadingShow" data-wffn-bundles-text="subheading"></div>
	<div class="wffn-bundles-fb-oos" data-wffn-bundles-if="oosShow" role="status" data-wffn-bundles-text="oosText"></div>

	<div class="wffn-bundles-fb-flat" data-wffn-bundles-if="flatMode">
		<span class="wffn-bundles-fb-badge" data-wffn-bundles-if="flatBadgeShow" data-wffn-bundles-text="badge"></span>
		<div class="wffn-bundles-fb-flat-head">
			<span class="wffn-bundles-fb-thumb wffn-bundles-fb-thumb--lg" data-wffn-bundles-if="leadImageShow"><img data-wffn-bundles-attr="src:leadImage" alt="" /></span>
			<span class="wffn-bundles-fb-flat-head-text">
				<span class="wffn-bundles-fb-name" data-wffn-bundles-text="bundleTitle"></span>
				<span class="wffn-bundles-fb-sub" data-wffn-bundles-if="bundleSubtitleShow" data-wffn-bundles-text="bundleSubtitle"></span>
			</span>
			<span class="wffn-bundles-fb-prices" data-wffn-bundles-if="hasItems">
				<span class="wffn-bundles-fb-price-original" data-wffn-bundles-if="totalShowStrike" data-wffn-bundles-text="totalOriginal"></span>
				<span class="wffn-bundles-fb-price-final" data-wffn-bundles-text="totalFinal"></span>
			</span>
		</div>
		<span class="wffn-bundles-fb-empty" data-wffn-bundles-if="itemsEmpty" data-wffn-bundles-text="emptyText"></span>
		<div class="wffn-bundles-fb-flat-row" data-wffn-bundles-repeat="items" data-wffn-bundles-class="oosItemClass">
			<span class="wffn-bundles-fb-thumb wffn-bundles-fb-thumb--lg" data-wffn-bundles-if="imageShow">
				<img data-wffn-bundles-attr="src:image" alt="" />
			</span>
			<span class="wffn-bundles-fb-flat-info">
				<span class="wffn-bundles-fb-name"><span class="wffn-bundles-fb-qty" data-wffn-bundles-if="qtyShow" data-wffn-bundles-text="qtyLabel"></span><span data-wffn-bundles-text="name"></span></span>
				<span class="wffn-bundles-fb-oos-tag" data-wffn-bundles-if="oosLabelShow" data-wffn-bundles-text="oosLabel"></span>
				<span class="wffn-bundles-variant" data-wffn-bundles-if="variantShow" data-wffn-bundles-attr="data-wffn-bundles-variations:variantMatrix, data-wffn-bundles-vinfo:variantInfo, data-wffn-bundles-vsummary:variantSummary"><span class="wffn-bundles-variant-head" aria-hidden="true" data-wffn-bundles-if="variantHead" data-wffn-bundles-text="variantHead"></span><select class="wffn-bundles-variant-axis" data-wffn-bundles-repeat="variantAxes"><option data-wffn-bundles-repeat="options" data-wffn-bundles-attr="value:value" data-wffn-bundles-bool-attr="selected:selected" data-wffn-bundles-text="label"></option></select></span>
			</span>
			<span class="wffn-bundles-fb-prices">
				<span class="wffn-bundles-fb-price-original" data-wffn-bundles-if="showStrike" data-wffn-bundles-text="priceOriginal"></span>
				<span class="wffn-bundles-fb-price-final" data-wffn-bundles-text="priceFinal"></span>
			</span>
		</div>
		<!-- wffn-bundles:bundle-extras -->
	</div>

	<div class="wffn-bundles-fb-stack" data-wffn-bundles-if="showSingle">
		<label class="wffn-bundles-fb-single" data-wffn-bundles-if="hasChoice" data-wffn-bundles-class="singleSelectedClass">
			<span class="wffn-bundles-radio" aria-hidden="true"></span>
			<input type="radio" name="wffn-bundles-fb-choice" class="wffn-bundles-fb-input" value="single" data-wffn-bundles-bool-attr="checked:singleSelected" />
			<span class="wffn-bundles-fb-thumb wffn-bundles-fb-thumb--lg" data-wffn-bundles-if="singleImageShow"><img data-wffn-bundles-attr="src:singleImage" alt="" /></span>
			<span class="wffn-bundles-fb-single-meta">
				<span class="wffn-bundles-fb-name" data-wffn-bundles-text="singleTitle"></span>
				<span class="wffn-bundles-fb-sub" data-wffn-bundles-if="singleSubtitleShow" data-wffn-bundles-text="singleSubtitle"></span>
				<span class="wffn-bundles-variant" data-wffn-bundles-if="variantShow" data-wffn-bundles-attr="data-wffn-bundles-variations:variantMatrix, data-wffn-bundles-vinfo:variantInfo, data-wffn-bundles-vsummary:variantSummary"><span class="wffn-bundles-variant-head" aria-hidden="true" data-wffn-bundles-if="variantHead" data-wffn-bundles-text="variantHead"></span>
					<select class="wffn-bundles-variant-axis" data-wffn-bundles-repeat="variantAxes">
						<option data-wffn-bundles-repeat="options" data-wffn-bundles-attr="value:value" data-wffn-bundles-bool-attr="selected:selected" data-wffn-bundles-text="label"></option>
					</select>
				</span>
			</span>
			<span class="wffn-bundles-fb-price-final" data-wffn-bundles-text="singlePrice"></span>
		</label>

		<label class="wffn-bundles-fb-card" data-wffn-bundles-class="bundleSelectedClass">
			<span class="wffn-bundles-fb-badge" data-wffn-bundles-if="badgeShow" data-wffn-bundles-text="badge"></span>
			<span class="wffn-bundles-fb-card-row">
				<span class="wffn-bundles-fb-card-body">
					<span class="wffn-bundles-fb-lead">
						<span class="wffn-bundles-radio" data-wffn-bundles-if="hasChoice" aria-hidden="true"></span>
						<input type="radio" name="wffn-bundles-fb-choice" class="wffn-bundles-fb-input" value="bundle" data-wffn-bundles-if="hasChoice" data-wffn-bundles-bool-attr="checked:bundleSelected" />
						<span class="wffn-bundles-fb-thumb wffn-bundles-fb-thumb--lg" data-wffn-bundles-if="leadImageShow"><img data-wffn-bundles-attr="src:leadImage" alt="" /></span>
						<span class="wffn-bundles-fb-lead-top">
							<span class="wffn-bundles-fb-lead-copy">
								<span class="wffn-bundles-fb-name" data-wffn-bundles-text="bundleTitle"></span>
								<span class="wffn-bundles-fb-sub" data-wffn-bundles-if="bundleSubtitleShow" data-wffn-bundles-text="bundleSubtitle"></span>
							</span>
							<span class="wffn-bundles-fb-prices">
								<span class="wffn-bundles-fb-price-original" data-wffn-bundles-if="totalShowStrike" data-wffn-bundles-text="totalOriginal"></span>
								<span class="wffn-bundles-fb-price-final" data-wffn-bundles-text="totalFinal"></span>
							</span>
						</span>
					</span>

					<span class="wffn-bundles-fb-empty" data-wffn-bundles-if="itemsEmpty" data-wffn-bundles-text="emptyText"></span>

					<span class="wffn-bundles-fb-members" data-wffn-bundles-if="hasItems">
						<span class="wffn-bundles-fb-divider"></span>
						<span class="wffn-bundles-fb-member" data-wffn-bundles-repeat="items" data-wffn-bundles-class="oosItemClass">
							<span class="wffn-bundles-fb-thumb wffn-bundles-fb-thumb--sm" data-wffn-bundles-if="imageShow"><img data-wffn-bundles-attr="src:image" alt="" /></span>
							<span class="wffn-bundles-fb-member-main">
								<span class="wffn-bundles-fb-member-head">
									<span class="wffn-bundles-fb-name"><span class="wffn-bundles-fb-qty" data-wffn-bundles-if="qtyShow" data-wffn-bundles-text="qtyLabel"></span><span data-wffn-bundles-text="name"></span></span>
									<span class="wffn-bundles-fb-prices">
										<span class="wffn-bundles-fb-price-original" data-wffn-bundles-if="showStrike" data-wffn-bundles-text="priceOriginal"></span>
										<span class="wffn-bundles-fb-price-final" data-wffn-bundles-text="priceFinal"></span>
									</span>
								</span>
								<span class="wffn-bundles-fb-oos-tag" data-wffn-bundles-if="oosLabelShow" data-wffn-bundles-text="oosLabel"></span>
								<span class="wffn-bundles-variant" data-wffn-bundles-if="variantShow" data-wffn-bundles-attr="data-wffn-bundles-variations:variantMatrix, data-wffn-bundles-vinfo:variantInfo, data-wffn-bundles-vsummary:variantSummary"><span class="wffn-bundles-variant-head" aria-hidden="true" data-wffn-bundles-if="variantHead" data-wffn-bundles-text="variantHead"></span>
									<select class="wffn-bundles-variant-axis" data-wffn-bundles-repeat="variantAxes">
										<option data-wffn-bundles-repeat="options" data-wffn-bundles-attr="value:value" data-wffn-bundles-bool-attr="selected:selected" data-wffn-bundles-text="label"></option>
									</select>
								</span>
							</span>
						</span>
					</span>

					<!-- wffn-bundles:card-extras -->
				</span>
			</span>
		</label>
	</div>
</div>
<?php
$wffn_bundles_tpl = rtrim( ob_get_clean(), "\n" );

return $wffn_bundles_tpl;
