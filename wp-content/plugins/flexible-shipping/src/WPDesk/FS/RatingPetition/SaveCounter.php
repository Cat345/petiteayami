<?php

declare( strict_types=1 );

namespace WPDesk\FS\RatingPetition;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/** Counts successful method saves towards rating eligibility. */
final class SaveCounter implements Hookable {
	public function hooks(): void {
		add_action( 'flexible_shipping_method_updated', [ $this, 'count_save' ], 10, 2 );
	}

	/** @internal */
	public function count_save( int $instance_id, bool $processed = true ): void {
		if ( ! $processed ) {
			return;
		}
		$count = (int) get_option( PetitionState::COUNT_OPTION, 0 );
		if ( $count < 10 ) {
			update_option( PetitionState::COUNT_OPTION, $count + 1, false );
		}
	}
}
