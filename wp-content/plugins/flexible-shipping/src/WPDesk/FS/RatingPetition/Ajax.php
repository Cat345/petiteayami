<?php

declare( strict_types=1 );

namespace WPDesk\FS\RatingPetition;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;

/** Validates and persists explicit rating and postponement actions. */
final class Ajax implements Hookable {
	public const ACTION = 'fs_rating_petition';

	public function hooks(): void {
		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
	}

	/** @internal */
	public function handle(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( $this->post_string( 'nonce' ), self::ACTION ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to perform this action. Please reload the page.', 'flexible-shipping' ) ], 403 );
			return;
		}
		$state = new PetitionState( (array) get_option( PetitionState::OPTION, [] ) );
		$now   = time();
		if ( ! $state->is_due( (int) get_option( PetitionState::COUNT_OPTION, 0 ), (int) get_option( PetitionState::LEGACY_OPTION, 0 ) === 1, $now ) ) {
			wp_send_json_error( [ 'message' => __( 'This rating request is no longer active. Please reload the page.', 'flexible-shipping' ) ], 409 );
			return;
		}
		$operation = $this->post_string( 'operation' );
		if ( $operation === 'snooze' && in_array( $this->post_string( 'minutes' ), [ '15', '30', '60' ], true ) ) {
			$state->snooze( (int) $this->post_string( 'minutes' ), $now );
		} elseif ( $operation === 'rate' && preg_match( '/^[1-5]$/D', $this->post_string( 'rating' ) ) ) {
			$rating = (int) $this->post_string( 'rating' );
			if ( $rating <= 3 && ! $this->send_feedback( $rating ) ) {
				return;
			}
			$state->complete( $rating, $now );
		} else {
			wp_send_json_error( [ 'message' => __( 'Invalid rating or postponement value.', 'flexible-shipping' ) ], 400 );
			return;
		}
		update_option( PetitionState::OPTION, $state->to_array(), false );
		$result = array_intersect_key( $state->to_array(), array_flip( [ 'completed', 'muted' ] ) );
		if ( isset( $rating ) && $rating >= 4 ) {
			$result['url'] = 'https://wordpress.org/support/plugin/flexible-shipping/reviews/?rate=' . $rating . '#new-post';
		}
		wp_send_json_success( $result );
	}

	private function send_feedback( int $rating ): bool {
		$feedback = trim( sanitize_textarea_field( $this->post_string( 'feedback' ) ) );
		$length   = function_exists( 'mb_strlen' ) ? mb_strlen( $feedback, 'UTF-8' ) : preg_match_all( '/./us', $feedback );
		if ( $length < 10 ) {
			wp_send_json_error( [ 'message' => __( 'Please enter at least 10 characters of feedback.', 'flexible-shipping' ) ], 400 );
			return false;
		}
		$user    = wp_get_current_user();
		$email   = $user ? sanitize_email( $user->user_email ) : '';
		$headers = $email !== '' && is_email( $email ) ? [ 'Reply-To: ' . $email ] : [];
		// translators: %s: plugin slug.
		$sent = wp_mail( 'help@octolize.com', sprintf( __( 'Feedback for %s', 'flexible-shipping' ), 'flexible-shipping' ), "Rating: {$rating}\n\nMessage:\n{$feedback}\n", $headers );
		if ( ! $sent ) {
			wp_send_json_error( [ 'message' => __( 'Could not send email. Please try again later.', 'flexible-shipping' ) ], 500 );
		}
		return $sent;
	}

	private function post_string( string $key ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified in handle(); callers strictly validate numbers or sanitize feedback.
		return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
	}
}
