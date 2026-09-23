<?php

declare( strict_types=1 );

namespace WPDesk\FS\RatingPetition;

use FSVendor\WPDesk\PluginBuilder\Plugin\Hookable;
use FSVendor\WPDesk\RepositoryRating\DisplayStrategy\DisplayDecision;

/** Displays the rating request on eligible shipping settings screens. */
final class Popup implements Hookable {

	private DisplayDecision $display_decision;

	private string $plugin_url;

	private string $version;

	public function __construct( DisplayDecision $display_decision, string $plugin_url, string $version ) {
		$this->display_decision = $display_decision;
		$this->plugin_url       = trailingslashit( $plugin_url );
		$this->version          = $version;
	}

	public function hooks(): void {
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_footer', [ $this, 'render' ] );
	}

	private function should_display(): bool {
		$state = new PetitionState( (array) get_option( PetitionState::OPTION, [] ) );
		return current_user_can( 'manage_woocommerce' ) && $this->display_decision->should_display() && $state->is_due( (int) get_option( PetitionState::COUNT_OPTION, 0 ), (int) get_option( PetitionState::LEGACY_OPTION, 0 ) === 1, time() );
	}

	/** @internal */
	public function enqueue(): void {
		if ( ! $this->should_display() ) {
			return;
		}
		wp_enqueue_style( 'fs-rating-petition', $this->plugin_url . 'assets/css/rating-petition.css', [], $this->version );
		wp_enqueue_script( 'fs-rating-petition', $this->plugin_url . 'assets/js/rating-petition.js', [], $this->version, true );
		wp_localize_script(
			'fs-rating-petition',
			'fsRatingPetition',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( Ajax::ACTION ),
			]
		);
	}

	/** @internal */
	public function render(): void {
		if ( $this->should_display() ) {
			$logo_url = $this->plugin_url . 'assets/images/octolize-logo-green.svg';
			include __DIR__ . '/views/rating-petition.php';
		}
	}
}
