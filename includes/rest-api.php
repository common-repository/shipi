<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if( !class_exists('shipi_RESTAPI') ){
    class shipi_RESTAPI {
        public function __construct() {
            // function to authticate with site
            add_action('rest_api_init', array($this, 'rest_api' ));
        }

        function rest_api($data) {
            register_rest_route('shipi/v1', '/update-shipment/', array(
                'methods' => 'POST',
                'callback' => array($this, "shipi_v1_update_shipment"),
                'permission_callback' => array($this, 'check_permissions'),
            ));
        }

        function check_permissions($request) {
            $key = $request->get_param('integration_key');
            $shipi_key = get_option("shipi_integration_key", false);

            // Check if the integration key matches
            if ($key !== $shipi_key) {
                return new WP_Error('invalid_key', 'Invalid integration key', array('status' => 403));
            }

            // Check if the request is coming from app.myshipi.com
            $referrer = isset($_SERVER['HTTP_REFERER']) ? sanitize_text_field($_SERVER['HTTP_REFERER']) : '';
            if (strpos($referrer, 'app.myshipi.com') === false) {
                return new WP_Error('invalid_referrer', 'Invalid referrer', array('status' => 403));
            }

            return true;
        }

        function shipi_v1_update_shipment($data) {
            $order_id = $data->get_param('order_id');
            $tracking_number = $data->get_param('tracking_number');
            $carrier = $data->get_param('carrier');
            
            $order_id = sanitize_text_field($order_id);
            $tracking_number = sanitize_text_field($tracking_number);
            $response = array();

            // Validate order ID and tracking number
            if (empty($order_id) || empty($tracking_number)) {
                return new WP_REST_Response(array('message' => 'Missing order_id or tracking_number'), 400);
            }

            // Update order with tracking number (you would need to implement the logic for updating the order)
            $order = wc_get_order($order_id);
            if ($order) {
                // Assuming you store tracking numbers as order meta
                $order->update_meta_data('tracking_no', $tracking_number);
                $order->update_meta_data('_shipi_tracking_no', $tracking_number);
                $order->update_meta_data('_shipi_carrier', $carrier);
                $order->save();

                $response = array('message' => 'Order updated successfully');
                return new WP_REST_Response($response, 200);
            } else {
                return new WP_REST_Response(array('message' => 'Invalid order_id'), 400);
            }
        }
    }
}

new shipi_RESTAPI();
