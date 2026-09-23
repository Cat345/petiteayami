<?php

declare( strict_types=1 );

namespace WPDesk\FS\RatingPetition;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/** Adds aggregate rating request metrics to the existing opt-in tracker. */
final class Tracker implements Hookable {
	public function hooks(): void {
		add_filter( 'wpdesk_tracker_data', [ $this, 'tracking_data' ], 12 );
	}

	/** @internal */
	public function tracking_data( array $data ): array {
		$state                                        = new PetitionState( (array) get_option( PetitionState::OPTION, [] ) );
		$data['flexible_shipping']['rating_petition'] = $state->tracking_data();
		return $data;
	}
}
