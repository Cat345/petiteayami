<?php
$offers = WFOCU_Core()->funnels->get_funnel_offers_admin();

$steps = $offers['steps'];
if ( ! empty( $steps ) ) {

	$index       = 0;
	$steps_count = count( $steps );
	foreach ( $steps as $key => $step ) {
		if ( empty( $step ) ) {
			continue;
		}

		$url = "";
		if ( isset( $step['url'] ) ) {
			$url = $step['url'];
		} else {
			$url = get_permalink( $step['id'] );
		}

		if ( $step['state'] === '1' || true === $step['state'] ) {
			$state = '1';
		} else {
			$state = '0';
		}
		?>
        <div class="wfocu_step_container" data-offer_id="<?php echo $step['id'] ?>" data-offer_title="<?php echo $step['name'] ?>" data-offer_type="<?php echo $step['type'] ?>" data-index_id="<?php echo $index; ?>" data-offer_state="<?php echo $state ?>" data-offer_url="<?php echo $url; ?>" data-offer-slug="<?php echo $step['slug']; ?>">
            <a class="wfocu_step" data-offer_id="<?php echo $step['id'] ?>" data-offer_title="<?php echo $step['name'] ?>" data-offer_type="<?php echo $step['type'] ?>" data-index_id="<?php echo $index; ?>">
				<span class="step_name_card"><span class="step_name"> <?php echo $step['name'] ?></span></span>
                <span class="wfocu_up_arrow"></span>
                <span class="wfocu_down_arrow"></span>
                <!-- <span class="wfocu_step_offer_state <?php echo $step['state'] == '1' ? '' : 'state_off' ?>" title="<?php echo $step['state'] == '1' ? 'Active' : 'Inactive' ?>"></span> -->
				<?php if ( true === WFOCU_Core()->admin->is_upstroke_page( 'offers' ) ) { ?>
                    <span class="wfocu_remove_step" onClick="wfocuBuilder.offer_settings_btn_bottom.delete_offer(this, <?php echo $step['id'] ?>)"><i class=" dashicons dashicons-no-alt"></i></span>

				<?php } ?>
            </a>
        </div>
		<?php
		$index ++;
	}
}