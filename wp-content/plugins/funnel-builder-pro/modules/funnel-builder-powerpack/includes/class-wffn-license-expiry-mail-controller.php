<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

if ( ! class_exists( 'WFFN_License_Expiry_Mail_controller' ) ) {
	class WFFN_License_Expiry_Mail_controller {


		/**
		 * Retrieves the email sections for the notification email.
		 *
		 * @return array The array of email sections.
		 */
		public function get_email_sections() {
			$License = WooFunnels_licenses::get_instance();
			$License->get_plugins_list();
			$date_range            = WFFN_Core()->admin->get_license_expiry();
			$formatted_date        = date_i18n( 'F j, Y', strtotime( $date_range ) );
			$upgrade_link          = 'https://funnelkit.com/exclusive-offer/';
			$highlight_subtitle    = __( 'Please renew your license to continue using pro features without interruption', 'Funnelkit' );
			$highlight_button_text = __( 'Renew Your License Now', 'Funnelkit' );
			$highlight_button_url  = add_query_arg( [
				'utm_source'   => 'WordPress',
				'utm_campaign' => 'Pro+Plugin',
				'utm_medium'   => 'Expiry+Email+Notification'
			], $upgrade_link );
			$wffn_pro_admin        = new WFFN_Pro_Admin();
			$texts                 = $wffn_pro_admin->add_license_related_code( null );
			$email_sections        = [
				[
					'type' => 'email_header',
				],
				[
					'type' => 'highlight',
					'data' => [
						'date'        => $formatted_date,
						'title'       => __( 'Funnelkit License Expired', 'Funnelkit' ),
						'subtitle'    => $highlight_subtitle,
						'button_text' => $highlight_button_text,
						'button_url'  => $highlight_button_url,
					],
				],
				[
					'type' => 'body',
					'data' => [
						'totals'     => $texts,
						'button_url' => $highlight_button_url,
						'support'    => 'https://funnelkit.com/contact/',
					],
				],
				[
					'type' => 'email_footer',
					'data' => [
						'business_name' => 'funnelkit.com',
					],
				],
			];

			return apply_filters( 'bwfan_weekly_notification_email_section', $email_sections );
		}

		/**
		 * Returns the HTML content for the email.
		 *
		 * @return string The HTML content of the email.
		 */
		public function get_content_html() {
			$email_sections = $this->get_email_sections();
			ob_start();

			foreach ( $email_sections as $section ) {
				if ( empty( $section['type'] ) ) {
					continue;
				}

				switch ( $section['type'] ) {
					case 'email_header':
						echo $this->get_template_html( '/expiry-email-template/email-header.php' );
						break;
					case 'highlight':
						echo $this->get_template_html( '/expiry-email-template/admin-email-report-highlight.php', $section['data'] );
						break;
					case 'body':
						echo $this->get_template_html( '/expiry-email-template/email-body.php', $section['data'] );
						break;
					case 'email_footer':
						echo $this->get_template_html( '/expiry-email-template/email-footer.php', $section['data'] );
						break;
					default:
						do_action( 'bwfan_email_section_' . $section['type'], $section['data'] ?? [] );
						break;
				}
			}

			return ob_get_clean();
		}


		/**
		 * Retrieves the HTML content of a template.
		 *
		 * This method includes the specified template file and allows passing arguments to it.
		 *
		 * @param string $template The name of the template file to include.
		 * @param array $args Optional. An array of arguments to pass to the template file. Default is an empty array.
		 *
		 * @return string
		 */
		public function get_template_html( $template, $args = array() ) {
			if ( ! empty( $args ) && is_array( $args ) ) {
				extract( $args ); // @codingStandardsIgnoreLine
			}

			ob_start();
			include __DIR__ . '/' . $template;

			return ob_get_clean();// phpcs:ignore WordPress.Security.NonceVerification.Missing
		}


	}
}