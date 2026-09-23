<?php

declare( strict_types=1 );

namespace WPDesk\FS\RatingPetition;

/** Persistent state of the installation's rating request. */
final class PetitionState {
	public const OPTION        = 'flexible_shipping_rating_petition';
	public const COUNT_OPTION  = 'flexible-shipping-method-update-count';
	public const LEGACY_OPTION = 'flexible-shipping_popup_petition_displayed';

	private array $state;

	public function __construct( array $state = [] ) {
		$this->state = array_merge(
			[
				'completed'                     => false,
				'muted'                         => false,
				'next_prompt_at'                => 0,
				'last_snoozed_at'               => 0,
				'hour_streak'                   => 0,
				'snooze_counts'                 => [
					15 => 0,
					30 => 0,
					60 => 0,
				],
				'snooze_minutes_total'          => 0,
				'selected_rating'               => 0,
				'last_snooze_to_rating_seconds' => null,
			],
			$state
		);
	}

	public function is_due( int $save_count, bool $legacy_completed, int $now ): bool {
		return $save_count >= 10 && ! $legacy_completed && ! $this->state['completed'] && ! $this->state['muted'] && $now >= $this->state['next_prompt_at'];
	}

	public function snooze( int $minutes, int $now ): void {
		$this->state['hour_streak']     = $minutes === 60 ? $this->state['hour_streak'] + 1 : 0;
		$this->state['muted']           = $this->state['hour_streak'] >= 3;
		$this->state['next_prompt_at']  = $now + $minutes * 60;
		$this->state['last_snoozed_at'] = $now;
		++$this->state['snooze_counts'][ $minutes ];
		$this->state['snooze_minutes_total'] += $minutes;
	}

	public function complete( int $rating, int $now ): void {
		$this->state['completed']       = true;
		$this->state['selected_rating'] = $rating;
		if ( $this->state['last_snoozed_at'] > 0 ) {
			$this->state['last_snooze_to_rating_seconds'] = max( 0, $now - $this->state['last_snoozed_at'] );
		}
	}

	public function to_array(): array {
		return $this->state;
	}

	public function tracking_data(): array {
		return array_intersect_key( $this->state, array_flip( [ 'completed', 'muted', 'snooze_counts', 'snooze_minutes_total', 'selected_rating', 'last_snooze_to_rating_seconds' ] ) );
	}
}
