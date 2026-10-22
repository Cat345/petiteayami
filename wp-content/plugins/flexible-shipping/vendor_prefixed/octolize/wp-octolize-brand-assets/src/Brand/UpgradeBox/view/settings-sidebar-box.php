<?php

namespace FSVendor;

/**
 * @var array $offer Localized upgrade offer.
 * @var array $placement Optional shipping method sidebar placement.
 */
$positioned = !empty($placement);
$icon_paths = ['box' => '<path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/>', 'percent' => '<circle cx="6" cy="6" r="2.5"/><circle cx="18" cy="18" r="2.5"/><path d="M18 6L6 18"/>', 'calendar' => '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/>', 'pin' => '<path d="M12 21s7-6.5 7-11a7 7 0 10-14 0c0 4.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/>'];
?>
<aside class="oct-upgrade-box<?php 
echo $positioned ? ' oct-upgrade-box--positioned' : '';
?>" aria-label="<?php 
echo \esc_attr($offer['eyebrow']);
?>"<?php 
if ($positioned) {
    ?> data-min-width="<?php 
    echo \esc_attr($placement['min_width'] ?? 1000);
    ?>" data-position-right="<?php 
    echo \esc_attr($placement['position_right'] ?? 20);
    ?>" data-align-top-to-element="<?php 
    echo \esc_attr($placement['align_top_to_element'] ?? '#mainform h2:first');
    ?>"<?php 
}
?>>
	<div class="oct-upgrade-box__body">
		<div class="oct-upgrade-box__logo" aria-hidden="true"></div>
		<p class="oct-upgrade-box__eyebrow"><?php 
echo \esc_html($offer['eyebrow']);
?></p>
		<h3 class="oct-upgrade-box__headline"><?php 
echo \esc_html($offer['headline']);
?></h3>
		<p class="oct-upgrade-box__description"><?php 
echo \esc_html($offer['description']);
?></p>

		<div class="oct-upgrade-box__comparison">
			<?php 
foreach (['current', 'upgrade'] as $variant) {
    ?>
				<div class="oct-upgrade-box__comparison-row oct-upgrade-box__comparison-row--<?php 
    echo \esc_attr($variant);
    ?>">
					<span><?php 
    echo \esc_html($offer[$variant]['label']);
    ?></span>
					<span class="oct-upgrade-box__badge"><?php 
    echo \esc_html($offer[$variant]['badge']);
    ?></span>
				</div>
			<?php 
}
?>
		</div>

		<ul class="oct-upgrade-box__benefits">
			<?php 
foreach ($offer['benefits'] as $benefit) {
    ?>
				<li>
					<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><?php 
    echo $icon_paths[$benefit['icon']];
    ?></svg>
					<span><?php 
    echo \esc_html($benefit['text']);
    ?></span>
				</li>
			<?php 
}
?>
		</ul>

		<p class="oct-upgrade-box__social-proof">
			<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.4 7.2H22l-6 4.4L18.4 21 12 16.6 5.6 21 8 13.6l-6-4.4h7.6z"/></svg>
			<span><?php 
echo \esc_html($offer['social_proof']);
?></span>
		</p>
	</div>
	<div class="oct-upgrade-box__footer">
		<p class="oct-upgrade-box__guarantee">
			<svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l8 4v6c0 5-3.5 8.5-8 10-4.5-1.5-8-5-8-10V6l8-4z"/><path d="M9 12l2 2 4-4"/></svg>
			<span><?php 
echo \esc_html($offer['guarantee']);
?></span>
		</p>
		<a class="oct-upgrade-box__cta" href="<?php 
echo \esc_url($offer['cta_url']);
?>" target="_blank" rel="noopener noreferrer">
			<?php 
echo \esc_html($offer['cta_label']);
?> <span aria-hidden="true">→</span>
		</a>
	</div>
</aside>
<?php 
if ($positioned) {
    ?>
<script>
(function(box) {
	jQuery(function($) {
		const $box = $(box);
		const minWidth = Number($box.attr('data-min-width'));
		const positionRight = Number($box.attr('data-position-right'));
		const alignTopToElement = $box.attr('data-align-top-to-element');
		const container = box.parentElement;

		function placeBox() {
			const $heading = $(alignTopToElement);
			const showBox = window.innerWidth > minWidth && $heading.length > 0;
			if ($heading.length) {
				$box.css('top', $heading.position().top + 20);
				$box.css(document.dir === 'rtl' ? 'left' : 'right', positionRight);
			}
			container.style.setProperty('--oct-upgrade-box-sidebar-space', `${parseFloat($box.css('width')) + positionRight + 20}px`);
			container.classList.toggle('oct-upgrade-box-container', showBox);
			$box.toggle(showBox);
		}

		setTimeout(placeBox, 1000);
		$(window).on('resize', placeBox);
	});
})(document.currentScript.previousElementSibling);
</script>
<?php 
}
