<?php
/**
 * Class RuleSpecialActionFieldsFactory
 *
 * @package WPDesk\FS\TableRate\Rule\SpecialAction
 */

namespace WPDesk\FS\TableRate\Rule\SpecialAction;

use FSVendor\WPDesk\Forms\Field;
use FSVendor\WPDesk\Forms\Field\InputTextField;
use FSVendor\WPDesk\Forms\FieldProvider;
use FSVendor\WPDesk\Forms\Renderer\JsonNormalizedRenderer;
use WPDesk\FS\TableRate\Rule\SpecialAction\SpecialAction;

/**
 * Can create special action fields.
 */
class SpecialActionFieldsFactory implements FieldProvider {

	/**
	 * @var SpecialAction[]
	 */
	private $available_special_actions;

	/**
	 * RuleSpecialActionFieldsFactory constructor.
	 *
	 * @param SpecialAction[] $available_special_actions .
	 */
	public function __construct( array $available_special_actions ) {
		$this->available_special_actions = $available_special_actions;
	}


	/**
	 * @return Field[]
	 */
	public function get_fields() {
		return apply_filters( 'flexible_shipping_rule_special_action_fields', $this->get_built_in_rule_special_actions_fields() );
	}

	/**
	 * .
	 *
	 * @return array
	 */
	public function get_normalized_cost_fields() {
		$normalized_cost_fields = [];
		$renderer               = new JsonNormalizedRenderer();

		return $renderer->render_fields( $this, [] );
	}

	/**
	 * @return Field[]
	 */
	public function get_built_in_rule_special_actions_fields() {
		return [
			( new Field\SelectField() )
				->set_name( 'special_action' )
				->set_options( $this->get_special_action_options() )
				->add_class( 'hs-beacon-search' )
				->add_class( 'special-action' )
				->add_data( 'beacon_search', __( 'special action', 'flexible-shipping' ) ),
		];
	}

	/**
	 * @return array
	 */
	private function get_special_action_options() {
		$options = [];
		foreach ( $this->available_special_actions as $special_action ) {
			$options[] = [
				'value'       => $special_action->get_special_action_id(),
				'label'       => $special_action->get_name(),
				'description' => $special_action->get_description(),
			];
		}

		return $options;
	}
}
