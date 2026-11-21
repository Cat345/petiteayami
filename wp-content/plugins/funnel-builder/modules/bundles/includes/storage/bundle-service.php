<?php
/**
 * The checked write path for bundles.
 *
 * @package WFFN_Bundles
 */
namespace WFFN\Bundles\Includes\Storage;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Creates, updates, activates and deletes bundles behind the same checks the
 * editor relies on: type, target, value rules and the add-on activation seam.
 *
 * Bundle_Store writes whatever it is handed. Everything that changes a
 * bundle on a caller's behalf, the admin REST routes and the abilities alike,
 * goes through here, so no caller can store what the editor would refuse.
 *
 * A refusal is a WP_Error whose data carries the envelope `status` and, for a
 * rule violation, the first issue's `path` and every `issues` entry.
 */
class Bundle_Service {

	/**
	 * Create a bundle.
	 *
	 * @param array $payload Bundle payload in the stored shape.
	 * @return array|\WP_Error The new list item.
	 */
	public static function create( array $payload ) {
		$payload = self::new_bundle( $payload );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}

		$err = self::rules_error( $payload );
		if ( null === $err ) {
			$err = self::target_error( $payload );
		}
		// A bundle created active meets the same activation gates as one switched on later.
		if ( null === $err && 1 === (int) ( $payload['status'] ?? 0 ) ) {
			$err = self::completeness_error( $payload, 1 );
			if ( null === $err ) {
				$err = self::schedule_error( $payload, 1 );
			}
		}
		if ( null !== $err ) {
			return $err;
		}

		$created = Bundle_Store::create( $payload );
		if ( null === $created ) {
			return self::error( 'wffn_bundles_create_failed', __( 'The bundle could not be created.', 'funnel-builder' ), 500 );
		}
		return $created;
	}

	/**
	 * What a new bundle opens with in the editor, stored nowhere until its first save.
	 *
	 * @param array $payload Bundle payload, as a create would be posted.
	 * @return array|\WP_Error The editor payload.
	 */
	public static function starter( array $payload ) {
		$payload = self::new_bundle( $payload );
		if ( is_wp_error( $payload ) ) {
			return $payload;
		}
		if ( empty( $payload['name'] ) && isset( $payload['discountType'] ) ) {
			$payload['name'] = Bundle_Store::type_label( $payload['discountType'] );
		}
		return Bundle_Store::starter( $payload );
	}

	/**
	 * Check and complete a payload for a bundle that does not exist yet.
	 *
	 * @param array $payload Bundle payload in the stored shape.
	 * @return array|\WP_Error
	 */
	private static function new_bundle( array $payload ) {
		// The list "create" dialog posts { name, discount_type }.
		if ( isset( $payload['discount_type'] ) && ! isset( $payload['discountType'] ) ) {
			$payload['discountType'] = $payload['discount_type'];
		}
		if ( isset( $payload['discountType'] ) ) {
			$payload['discountType'] = (int) $payload['discountType'];
			if ( ! in_array( $payload['discountType'], Bundle_Store::accepted_types(), true ) ) {
				return self::error( 'wffn_bundles_unknown_type', __( 'Invalid bundle type.', 'funnel-builder' ), 400 );
			}
		}
		if ( isset( $payload['name'] ) && is_string( $payload['name'] ) && mb_strlen( $payload['name'] ) > 255 ) {
			return self::error( 'wffn_bundles_invalid_bundle', __( 'Bundle name may not exceed 255 characters.', 'funnel-builder' ), 400 );
		}

		$payload = Quick_Starts::apply( $payload );
		if ( is_wp_error( $payload ) ) {
			return self::error( 'wffn_bundles_invalid_bundle', $payload->get_error_message(), 400 );
		}

		/**
		 * Filters a bundle about to be created, before any rule checks run.
		 *
		 * Return a WP_Error to refuse the create with its message.
		 *
		 * @since 1.0.0
		 *
		 * @param array $payload Incoming bundle payload.
		 */
		$payload = apply_filters( 'wffn_bundles_bundle_create_payload', $payload );
		if ( is_wp_error( $payload ) ) {
			return self::error( 'wffn_bundles_invalid_bundle', $payload->get_error_message(), 400 );
		}

		return Bundle_Store::fill_row_ids( $payload );
	}

	/**
	 * Apply a partial patch to a bundle.
	 *
	 * Status may ride along: saving an active bundle stays allowed even when
	 * the activation seam would now refuse it, so a stale setting cannot block an
	 * unrelated edit.
	 *
	 * @param int|string $id    Bundle id.
	 * @param array      $patch Partial payload in the stored shape.
	 * @return array|\WP_Error The hydrated bundle.
	 */
	public static function update( $id, array $patch ) {
		// The type decides which config keys survive pruning, so reject an unknown one here.
		if ( isset( $patch['discountType'] ) && ! in_array( (int) $patch['discountType'], Bundle_Store::accepted_types(), true ) ) {
			return self::error( 'wffn_bundles_unknown_type', __( 'Invalid bundle type.', 'funnel-builder' ), 400 );
		}

		$current = Bundle_Store::get_config( $id );
		if ( null === $current ) {
			return self::not_found();
		}
		// The stored type, not just a patched one: a partial save carries no type
		// and would otherwise write back a bundle nothing here can render.
		if ( ! in_array( (int) ( $current['discountType'] ?? 0 ), Bundle_Store::accepted_types(), true ) ) {
			return self::error( 'wffn_bundles_unknown_type', __( 'This bundle type is not available on this site.', 'funnel-builder' ), 403 );
		}
		// A patch naming one config key keeps the rest; a top-level merge would drop them.
		if ( is_array( $patch['discountConfig'] ?? null ) && is_array( $current['discountConfig'] ?? null ) ) {
			$patch['discountConfig'] = array_merge( $current['discountConfig'], $patch['discountConfig'] );
		}
		$patch = Bundle_Store::fill_row_ids( $patch );
		// VR-004: active needs a placement; drafts stay permissive. Checked on the
		// merged record because a partial patch may carry status, selection, or neither.
		$merged = array_merge( $current, $patch );
		$err    = self::target_error( $merged );
		if ( null === $err ) {
			$err = self::rules_error( $merged );
		}
		// Only a move to active is gated: saving an already-active bundle stays allowed.
		if ( null === $err && isset( $patch['status'] ) && 1 === (int) $patch['status'] && 1 !== (int) ( $current['status'] ?? 0 ) ) {
			$err = self::completeness_error( $merged, 1 );
			if ( null === $err ) {
				$err = self::schedule_error( $merged, 1 );
			}
		}
		if ( null === $err && 1 === (int) ( $merged['status'] ?? 0 ) && 1 === (int) ( $current['status'] ?? 0 ) ) {
			$err = self::reach_error( $current, $merged );
		}
		if ( null !== $err ) {
			return $err;
		}

		$payload = Bundle_Store::update( $id, $patch );
		return null === $payload ? self::not_found() : $payload;
	}

	/**
	 * Activate or deactivate one bundle.
	 *
	 * @param int|string $id     Bundle id.
	 * @param int        $status 1 for active, 0 for draft.
	 * @return int|\WP_Error The status now stored.
	 */
	public static function set_status( $id, $status ) {
		// Activating is the other door into "live with no placement"; same gate.
		$current = Bundle_Store::get_config( $id );
		if ( null !== $current ) {
			$err = self::activation_error( $current, $status );
			if ( null !== $err ) {
				return $err;
			}
		}

		$stored = Bundle_Store::set_status( $id, $status );
		return null === $stored ? self::not_found() : $stored;
	}

	/**
	 * Set the status of several bundles, each behind the single-bundle gates.
	 *
	 * A bundle that fails them is skipped rather than failing the batch.
	 *
	 * @param array $ids    Bundle ids.
	 * @param int   $status 1 for active, 0 for draft.
	 * @return array{updated:string[],skipped:array<int,array{id:string,reason:string}>,unchanged:string[],missing:string[]}
	 */
	public static function bulk_set_status( array $ids, $status ) {
		$status = (int) $status;
		$result = array(
			'updated'   => array(),
			'skipped'   => array(),
			'unchanged' => array(),
			'missing'   => array(),
		);

		foreach ( $ids as $id ) {
			$id      = (string) $id;
			$current = Bundle_Store::get_config( $id );
			if ( null === $current ) {
				$result['missing'][] = $id;
				continue;
			}
			if ( (int) ( $current['status'] ?? 0 ) === $status ) {
				$result['unchanged'][] = $id;
				continue;
			}
			$err = self::activation_error( $current, $status );
			if ( null !== $err ) {
				$result['skipped'][] = array(
					'id'     => $id,
					'reason' => $err->get_error_message(),
				);
				continue;
			}
			Bundle_Store::set_status( $id, $status );
			$result['updated'][] = $id;
		}

		return $result;
	}

	/**
	 * Delete one bundle.
	 *
	 * @param int|string $id Bundle id.
	 * @return true|\WP_Error
	 */
	public static function delete( $id ) {
		if ( null === Bundle_Store::get_config( $id ) ) {
			return self::not_found();
		}
		Bundle_Store::delete( $id );
		return true;
	}

	/**
	 * Every problem a save would hit, without saving.
	 *
	 * @param array      $record The bundle as it would be stored.
	 * @param int|null   $status Status the caller is about to apply, if any.
	 * @param array|null $stored The stored bundle, when $record patches one.
	 * @return array<int,array{path:string,message:string}>
	 */
	public static function issues( array $record, $status = null, $stored = null ) {
		$issues = array();
		$type   = (int) ( $record['discountType'] ?? 0 );
		if ( ! in_array( $type, Bundle_Store::accepted_types(), true ) ) {
			$issues[] = array(
				'path'    => 'discountType',
				'message' => __( 'Invalid bundle type.', 'funnel-builder' ),
			);
		}

		$issues = array_merge( $issues, Bundle_Rules::validate( $record ) );

		if ( null !== $status ) {
			$record['status'] = (int) $status;
		}
		$err = self::target_error( $record );
		// Only a status change meets the activation seam; see update().
		if ( null === $err && null !== $status && 1 !== (int) ( $stored['status'] ?? 0 ) ) {
			$err = self::completeness_error( $record, $status );
			if ( null === $err ) {
				$err = self::schedule_error( $record, $status );
			}
		}
		if ( null !== $err ) {
			$issues[] = array(
				'path'    => 'status',
				'message' => $err->get_error_message(),
			);
		}

		return $issues;
	}

	/**
	 * The type, target and activation-seam gates a status change runs.
	 *
	 * A stored bundle of a type this site does not serve is kept as it is, as
	 * update() keeps it, so neither switching it on nor off goes through.
	 *
	 * @param array $current    The bundle as stored.
	 * @param int   $new_status Status being applied.
	 * @return \WP_Error|null
	 */
	private static function activation_error( array $current, $new_status ) {
		if ( ! in_array( (int) ( $current['discountType'] ?? 0 ), Bundle_Store::accepted_types(), true ) ) {
			return self::error( 'wffn_bundles_unknown_type', __( 'This bundle type is not available on this site.', 'funnel-builder' ), 403 );
		}
		$err = self::target_error( array_merge( $current, array( 'status' => $new_status ) ) );
		// Switching on is refused for a bundle whose values the editor would refuse (an import, say).
		if ( null === $err && 1 === (int) $new_status ) {
			$err = self::rules_error( $current );
		}
		if ( null === $err ) {
			$err = self::completeness_error( $current, $new_status );
		}
		return null !== $err ? $err : self::schedule_error( $current, $new_status );
	}

	/**
	 * Reject an activation whose bundle type is not fully configured.
	 *
	 * Null when the bundle is not being activated, or is complete. The editor runs the same
	 * rules, so the list toggle, bulk actions and the API cannot activate what it refuses.
	 *
	 * @param array<string,mixed> $record     The bundle as it would be stored.
	 * @param int                 $new_status Status being applied.
	 * @return \WP_Error|null
	 */
	private static function completeness_error( array $record, $new_status ) {
		if ( 1 !== (int) $new_status ) {
			return null;
		}
		$issues = Bundle_Rules::activation_issues( $record );
		if ( empty( $issues ) ) {
			return null;
		}
		return self::error(
			'wffn_bundles_activation_refused',
			Bundle_Rules::first_message( $issues ),
			400,
			array(
				'path'   => $issues[0]['path'] ?? null,
				'issues' => array_values( $issues ),
			)
		);
	}

	/**
	 * Reject a save of a live bundle that newly sets "Minimum items added for discount" beyond the companions a page can show.
	 *
	 * A bundle already over the limit stays editable, so a stale setting cannot block an unrelated edit.
	 *
	 * @param array<string,mixed> $current The bundle as stored.
	 * @param array<string,mixed> $merged  The bundle as it would be stored.
	 * @return \WP_Error|null
	 */
	private static function reach_error( array $current, array $merged ) {
		$unreachable = function ( array $record ) {
			foreach ( Bundle_Rules::activation_issues( $record ) as $issue ) {
				if ( 'fbtMinCompanions' === ( $issue['path'] ?? '' ) ) {
					return $issue;
				}
			}
			return null;
		};
		$issue = $unreachable( $merged );
		if ( null === $issue || null !== $unreachable( $current ) ) {
			return null;
		}
		return self::error(
			'wffn_bundles_invalid_bundle',
			(string) $issue['message'],
			400,
			array(
				'path'   => $issue['path'],
				'issues' => array( $issue ),
			)
		);
	}

	/**
	 * Reject a payload whose values are out of range (Bundle_Rules).
	 *
	 * @param array<string,mixed> $record The bundle as it would be stored.
	 * @return \WP_Error|null
	 */
	private static function rules_error( array $record ) {
		$issues = Bundle_Rules::validate( $record );
		if ( empty( $issues ) ) {
			return null;
		}
		return self::error(
			'wffn_bundles_invalid_bundle',
			Bundle_Rules::first_message( $issues ),
			400,
			array(
				'path'   => $issues[0]['path'] ?? null,
				'issues' => array_values( $issues ),
			)
		);
	}

	/**
	 * Reject an active bundle that targets nothing.
	 *
	 * @param array<string,mixed> $record The bundle as it would be stored.
	 * @return \WP_Error|null
	 */
	private static function target_error( array $record ) {
		if ( 1 !== (int) ( $record['status'] ?? 0 ) || Bundle_Store::has_target( $record ) ) {
			return null;
		}
		return self::error(
			'wffn_bundles_activation_refused',
			__( 'Select at least one product or category before activating this bundle.', 'funnel-builder' ),
			400
		);
	}

	/**
	 * Reject an activation an add-on says cannot run.
	 *
	 * @param array<string,mixed> $record     The bundle as stored.
	 * @param int                 $new_status Status being applied.
	 * @return \WP_Error|null
	 */
	private static function schedule_error( array $record, $new_status ) {
		if ( 1 !== (int) $new_status ) {
			return null;
		}
		/**
		 * Filters why a bundle cannot be activated, or '' when it can.
		 *
		 * @since 1.0.0
		 *
		 * @param string $message  Reason shown to the merchant; empty allows it.
		 * @param array  $bundle The bundle as stored.
		 */
		$message = (string) apply_filters( 'wffn_bundles_bundle_activation_error', '', $record );
		return '' === $message ? null : self::error( 'wffn_bundles_activation_refused', $message, 400 );
	}

	/**
	 * The refusal for an id that matches no bundle.
	 *
	 * @return \WP_Error
	 */
	private static function not_found() {
		return self::error( 'wffn_bundles_bundle_not_found', __( 'Bundle not found.', 'funnel-builder' ), 404 );
	}

	/**
	 * A refusal carrying its envelope status.
	 *
	 * @param string $code    Untranslated error code.
	 * @param string $message Translated message.
	 * @param int    $status  Envelope status.
	 * @param array  $data    Extra data: `path`, `issues`.
	 * @return \WP_Error
	 */
	private static function error( $code, $message, $status, array $data = array() ) {
		return new \WP_Error( $code, $message, array_merge( array( 'status' => (int) $status ), $data ) );
	}
}
