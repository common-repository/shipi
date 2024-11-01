<?php
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly.
}

if (!class_exists('ShipiCarrierLoad')) {
	class ShipiCarrierLoad extends WC_Shipping_Method
	{
        public $hpos_enabled = false;
		public function __construct($instance_id = 0)
		{
			$this->id                 = 'shipi_carrier';
			$this->method_title       = __('Shipi', 'shipi');  // Title shown in admin
			$this->title       = __('Shipi Shipping', 'shipi');
			$this->method_description = __('15+ Shipping carriers in one package. Visit Plugin settings for extra configurations.', 'shipi'); // 
			$this->instance_id        = absint($instance_id);
			$this->enabled            = "yes"; // This can be added as an setting but for this example its forced enabled
			$this->supports           = array(
				'shipping-zones',
				'instance-settings',
				'instance-settings-modal',
			);
			$this->init();
			if (get_option("woocommerce_custom_orders_table_enabled") === "yes") {
 		        $this->hpos_enabled = true;
 		    }
		}
        function init()
		{
			// Load the settings API
			$this->init_form_fields(); // This is part of the settings API. Override the method to add your own settings
			$this->init_settings(); // This is part of the settings API. Loads settings you previously init.

			// Save settings in admin if you have any defined
			add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
		}
        function init_form_fields() {
            $this->form_fields = array(
                'shipi_instructions' => array(
                    'title' => __('Once the carrier is enabled, Visit the plugin settings to configure it', 'shipi'),
                    'type' => 'title',
                    'description' => __('Instructions: Admin Menu => Settings => Shipi Config.', 'shipi'),
                ),
            );
        }
        public function calculate_shipping($package = array())
		{
			// if(!is_checkout() && !is_cart()){
			// 	return;
			// }
			// Calualte the shipping cost
			$key = get_option("shipi_integration_key", false);
			if(!$key){
				return;
			}
			$package = apply_filters("shipi_alter_package_info", $package);

			if($package["destination"]["country"] != "" && $package["destination"]["city"] != ""){
				// Create Payload
				$currency = get_option('woocommerce_currency');
				$pack_products = $this->get_pack_products($package["contents"]);
				$payload = array(
					"platform" => "woocommerce",
					"products" => $pack_products,
					"receiver_address" => array(
						"r_address_1" => $package["destination"]["address_1"],
						"r_address_2" => $package["destination"]["address_2"],
						"r_city" => $package["destination"]["city"],
						"r_state" => $package["destination"]["state"],
						"r_zip" => $package["destination"]["postcode"],
						"r_country" => $package["destination"]["country"],
						"r_meta" => apply_filters("shipi_add_reciver_info", array(), $package)
					),
					"shop_id" => $key,
					"sitekey" => base64_encode(esc_url(site_url())),
					"settings" => array("currency" => $currency)
				);
				
				$rates_url = "https://app.myshipi.com/rates_api/shipi_rates.php";
				$rates_response = wp_remote_post(
					$rates_url,
					array(
						'method'      => 'POST',
						'timeout'     => 60,
						'redirection' => 5,
						'httpversion' => '1.0',
						'blocking'    => true,
						'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
						'body'        => wp_json_encode($payload),
						'sslverify' => 0
					)
				);
				$rates_response = (is_array($rates_response) && isset($rates_response['body'])) ? json_decode($rates_response['body'], true) : array();
				
				if(!empty($rates_response) && !isset($rates_response["status"])){
					foreach($rates_response as $key => $value){
						if(!empty($value)){
							foreach($value as $rate){
								
								if(!empty($rate)){
									foreach($rate as $rkey => $rvalue){
										if(isset($rvalue['price']) && isset($rvalue['name'])){
											$rate = array(
												'id'       => 'shipi_carrier:' . $rkey,
												'label'    => $rvalue['name'],
												'cost'     => $rvalue['price'],
												'meta_data' => array('shipi_carrier' => $rkey)
											);						
											$this->add_rate($rate);
										}
										
									}
								}
								
							}
						}
					}
				}
			}
			
        }
		public function get_pack_products($items){
			$pack_products = array();
			foreach ( $items as $item ) {
				$curr_prod_id = $item['product_id'];
				$get_product = $item["data"]->get_data();
				$product = array();
				$product['product_name'] = str_replace(":","-",$get_product['name']);
				$product['product_quantity'] = $item['quantity'];
				$product['price'] = number_format((float) round((float)($get_product['price']),2) , 2, '.', '');
				$product['width'] = round((float)$get_product['width'],2);
				$product['height'] = round((float)$get_product['height'],2);
				$product['depth'] = round((float)$get_product['length'],2);
				$product['weight'] = round((float)$get_product['weight'],2);
				$product['product_id'] = $curr_prod_id;
				$pack_products[] = $product;
			}
			return $pack_products;
		}
    }
}
function shipi_carrier( $methods )
{
	$methods['shipi_carrier'] = 'ShipiCarrierLoad'; 
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'shipi_carrier' );