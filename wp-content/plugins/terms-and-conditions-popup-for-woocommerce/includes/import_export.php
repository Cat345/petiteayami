<?php
class BeRocket_terms_cond_popup_import_export {
    public function __construct() {
        add_filter('berocket_conditions_import_export_schema', array($this, 'condition_schema'), 10, 2);
        add_filter('berocket_conditions_export_type_shipping_zone', array($this, 'export_shipping_zone'), 10, 2);
        add_filter('berocket_conditions_import_type_shipping_zone', array($this, 'import_shipping_zone'), 10, 2);
    }

    public function condition_schema($schema, $context) {
        $schema['product_in_cart'] = array('inherit' => 'product');
        return $schema;
    }

    public function export_shipping_zone($condition, $context) {
        if( isset($condition['zone_id']) && is_scalar($condition['zone_id']) ) {
            foreach($this->get_shipping_zones() as $zone) {
                if( isset($zone['id'], $zone['zone_name']) && (string) $zone['id'] === (string) $condition['zone_id'] ) {
                    $condition['zone_id'] = array(
                        'name' => (string) $zone['zone_name'],
                        'source_id' => (string) $zone['id'],
                    );
                    break;
                }
            }
        }
        return $condition;
    }

    public function import_shipping_zone($condition, $context) {
        if( ! isset($condition['zone_id']['name']) ) {
            return $condition;
        }
        $zone_name = (string) $condition['zone_id']['name'];
        $condition['zone_id'] = '';
        foreach($this->get_shipping_zones() as $zone) {
            if( isset($zone['id'], $zone['zone_name']) && (string) $zone['zone_name'] === $zone_name ) {
                $condition['zone_id'] = $zone['id'];
                break;
            }
        }
        return $condition;
    }

    private function get_shipping_zones() {
        return class_exists('WC_Shipping_Zones') ? WC_Shipping_Zones::get_zones() : array();
    }
}
new BeRocket_terms_cond_popup_import_export();
