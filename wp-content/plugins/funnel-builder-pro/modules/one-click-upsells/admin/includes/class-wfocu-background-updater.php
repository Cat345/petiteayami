<?php
/**
 * Background Updater
 *
 * @version 1.7.4
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WC_Background_Process', false ) && is_file( dirname( __FILE__ ) . '/abstracts/class-wc-background-process.php' ) ) {
	include_once dirname( __FILE__ ) . '/abstracts/class-wc-background-process.php';
}

if ( ! class_exists( 'WC_Background_Process', false ) ) {
	return;
}
if ( ! class_exists( 'WFOCU_Background_Updater' ) ) {
	/**
	 * WFOCU_Background_Updater Class.
	 * Based on WC_Background_Updater concept
	 */
	class WFOCU_Background_Updater extends WC_Background_Process {

		/**
		 * Initiate new background process.
		 */
		public function __construct() {
			// Uses unique prefix per blog so each blog has separate queue.
			$this->prefix = 'wp_' . get_current_blog_id();
			$this->action = 'wfocu_updater';

			parent::__construct();
		}


		/**
		 * Handle cron healthcheck
		 *
		 * Restart the background process if not already running
		 * and data exists in the queue.
		 */
		public function handle_cron_healthcheck() {
			if ( $this->is_process_running() ) {
				// Background process already running.
				return;
			}

			if ( $this->is_queue_empty() ) {
				// No data to process.
				$this->clear_scheduled_event();

				return;
			}

			$this->handle();
		}

		/**
		 * Schedule fallback event.
		 */
		protected function schedule_event() {
			if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
				wp_schedule_event( time() + 10, $this->cron_interval_identifier, $this->cron_hook_identifier );
			}
		}

		/**
		 * Is the updater running?
		 *
		 * @return boolean
		 */
		public function is_updating() {
			return false === $this->is_queue_empty();
		}

		/**
		 * Task
		 *
		 * Override this method to perform any actions required on each
		 * queue item. Return the modified item for further processing
		 * in the next pass through. Or, return false to remove the
		 * item from the queue.
		 *
		 * @param string $callback Update callback function.
		 *
		 * @return string|bool
		 */
		protected function task( $callback ) {

			$result = false;
			if ( is_callable( $callback ) ) {
				WFOCU_Core()->log->log( sprintf( 'Running %s callback', $callback ) );
				$result = (bool) call_user_func( $callback );

				if ( $result ) {
					WFOCU_Core()->log->log( sprintf( '%s callback needs to run again', $callback ) );
				} else {
					WFOCU_Core()->log->log( sprintf( 'Finished running %s callback', $callback ) );
				}
			} else {
				WFOCU_Core()->log->log( sprintf( 'Could not find %s callback', $callback ) );
			}

			return $result ? $callback : false;
		}

		/**
		 * Complete
		 *
		 * Override if applicable, but ensure that the below actions are
		 * performed, or, call parent::complete().
		 */
		protected function complete() {

			WFOCU_Core()->log->log( 'Data update complete' );

			parent::complete();
		}
	}
}