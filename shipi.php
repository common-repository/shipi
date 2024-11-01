<?php
/**
 * Plugin Name: Shipi
 * Description: 15+ Shipping carriers in one package.
 * Version: 1.0.1
 * Author: Shipi
 * Author URI: https://myshipi.com/
 * Developer: Shipi
 * Developer URI: https://myshipi.com/
 * Text Domain: shipi
 * Domain Path: /i18n/languages/
 * License: GPLv3 or later License
 * URI: http://www.gnu.org/licenses/gpl-3.0.html
 *
 * @package WooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// set HPOS feature compatible by plugin
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);

// check is woocommerce is installed already.
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    if( !class_exists('shipi_Parent') ){
        Class shipi_Parent {
            private $hpos_enabled = false;
            private $new_prod_editor_enabled = false;          
            public function __construct() {
                if (get_option("woocommerce_custom_orders_table_enabled") === "yes") {
                    $this->hpos_enabled = true;
                }
                if (get_option("woocommerce_feature_product_block_editor_enabled") === "yes") {
                    $this->new_prod_editor_enabled = true;
                }
                // shipping Class defined here
                add_action( 'woocommerce_shipping_init', array($this,'init') );
                
                include_once(dirname(__FILE__) ."/includes/rest-api.php");

                add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
				add_action( 'add_meta_boxes', array($this, 'label_meta_box' ));
				add_action( 'admin_menu', array($this, 'menu_page' ));

                if ($this->hpos_enabled) {
					add_filter( 'manage_woocommerce_page_wc-orders_columns', array($this, 'wc_new_order_column') );
					add_action( 'manage_woocommerce_page_wc-orders_custom_column', array( $this, 'wc_new_order_download'), 10, 2 );
				} else {
					add_filter( 'manage_edit-shop_order_columns', array($this, 'wc_new_order_column') );
					add_action( 'manage_shop_order_posts_custom_column', array( $this, 'wc_new_order_download'), 10, 2 );
				}
                add_action( 'woocommerce_thankyou', array( $this, 'wc_checkout_order_processed' ) );
				add_action('woocommerce_order_details_after_order_table', array( $this, 'track' ) );
                add_action( 'admin_enqueue_scripts', array($this, 'admin_css_js') );
                add_action( 'admin_footer', array($this, "admin_footor"));
                add_action( 'wp_ajax_get_order_details_shipi', array($this, "get_order_details_shipi") );
                add_action( 'wp_ajax_get_tracking_shipi', array($this, "get_tracking_shipi") );
                add_action('woocommerce_admin_order_data_after_shipping_address', array($this, 'order_details_screen_data'));
                add_action( 'add_meta_boxes', array($this, 'create_meta_box'), 10, 1);

            }
            function create_meta_box(){
                $meta_scrn = $this->hpos_enabled ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
				add_meta_box( 'shipi_meta_area', __('Shipping','shipi'), array($this, 'shipi_load_data'), $meta_scrn, 'advanced', 'high' );
				
            }
            function shipi_load_data($post){
                if(!$this->hpos_enabled && $post->post_type !='shop_order' ){
		    		return;
		    	}
                $order = (!$this->hpos_enabled) ? wc_get_order( $post->ID ) : $post;
		    	$order_id = $order->get_id();
                $pushed = get_post_meta($order_id, "shipi_pushed", true);
                $response = ["status" => "success"];
                if(!$pushed){
                    $response = $this->get_order_details_shipi_core($order_id);
                }
                
                if(isset($response["status"]) && $response["status"] == "success"){
                    $key = get_option("shipi_integration_key", false);
                    ?>
                <iframe id="shipicontentFrame" frameborder="0" scrolling="no" src="https://app.myshipi.com/carriers/create-label-ui.php?platform=woocommerce&border=0&direct=load&key=<?php echo esc_html($key); ?>&load=<?php echo esc_html(base64_encode(esc_url(site_url()))); ?>&order=<?php echo esc_attr($order_id); ?>" style="width: 100%; height: auto;"></iframe>
                <?php
                }
              

            }
            function init(){
                include_once(dirname(__FILE__) ."/includes/shipping-class.php");
            }
            function plugin_action_links($links){
                $plugin_links = array(
					'<a href="' . admin_url( 'admin.php?page=shipi-configuration' ) . '" style="color:green;">' . __( 'Configure', 'shipi' ) . '</a>',
					'<a href="https://app.myshipi.com/support" target="_blank" >' . __('Support', 'shipi') . '</a>'
					);
                return array_merge( $plugin_links, $links );
            }
            public function label_meta_box() {
				$meta_scrn = $this->hpos_enabled ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		    }
            function menu_page(){
                add_menu_page(__( 'Shipping', 'shipi' ), 'Shipping', 'manage_options', 'shipi-configuration', array($this,'shipi_settings'), plugin_dir_url(__FILE__) . '/assets/img/shipi-20px.png', 6);
                add_submenu_page(
                    'shipi-configuration',
                    __('Settings', 'shipi'),
                    'Settings',
                    'manage_options',
                    'shipi-settings',
                    array($this, 'shipi_settings_callback')
                );
            }
            
            function shipi_settings(){
                $key = get_option("shipi_integration_key", false);
                if(!$key){
                    $submenu_slug = 'shipi-settings'; // The slug of the submenu to redirect to
                    $redirect_url = admin_url('admin.php?page=' . $submenu_slug);
                    wp_redirect($redirect_url);
                    exit;
                }else{
                    echo "<iframe style='width: 100%;height: 100vh;' src='https://app.myshipi.com/embed/label.php?shop=".esc_url(site_url())."&key=".esc_html($key)."&show=ship'></iframe>";
                }
            }
            function shipi_settings_callback(){
                $error = '';
                if(isset($_POST["shipi_connection_submit"])){
                    if (!isset($_POST['shipi_connection_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['shipi_connection_nonce'])), 'shipi_connection_action')) {
                        // Nonce verification failed
                        wp_die(esc_html__('Nonce verification failed', 'shipi'));
                    }
                    $shipi_connection_username = sanitize_email(isset($_POST['shipi_connection_username']) ? $_POST['shipi_connection_username'] : '');
                    $shipi_connection_password = sanitize_text_field(isset($_POST['shipi_connection_password']) ? $_POST['shipi_connection_password'] : '');
                    if($shipi_connection_username && $shipi_connection_password){
                        $random_nonce = wp_generate_password(16, false);
		                set_transient('shipi_nonce_temp', $random_nonce, HOUR_IN_SECONDS);
                        $link_request = wp_json_encode(array(
                            'site_url' => esc_url(site_url()),
                            'site_name' => get_bloginfo('name'),
                            'email_address' => $shipi_connection_username,
                            'password' => $shipi_connection_password,
                            'nonce' => $random_nonce,
                            'pulgin' => 'Shipi All in One',
                            'platfrom' => 'woocommerce',
                            "update_path" => "/wp-json/shipi/connect"
                        ));
                
                        $link_site_url = "https://app.myshipi.com/api/link-site.php";
                        $link_site_response = wp_remote_post(
                            $link_site_url,
                            array(
                                'method'      => 'POST',
                                'timeout'     => 45,
                                'redirection' => 5,
                                'httpversion' => '1.0',
                                'blocking'    => true,
                                'headers'     => array('Content-Type' => 'application/json; charset=utf-8'),
                                'body'        => $link_request,
                                'sslverify' => 0
                            )
                        );
                        $link_site_response = (is_array($link_site_response) && isset($link_site_response['body'])) ? json_decode($link_site_response['body'], true) : array();
                        if ($link_site_response) {
                            if ($link_site_response['status'] != 'error') {
                                update_option('shipi_integration_key', sanitize_text_field($link_site_response['integration_key']));
                            } else {
                                $error = '<p style="color:red;">' . $link_site_response['message'] . '</p>';
                                $success = '';
                            }
                        } else {
                            $error = '<p style="color:red;">Failed to connect with Shipi</p>';
                            $success = '';
                        }
                        
                    }else{
                        echo "Something Went Wrong";
                    }
                }
                // get_option("shipi_integration_key");
                $key = get_option("shipi_integration_key", false);
                if(!$key){
                    ?>
                    <form class="shipi-login" method="post">
                        <h2><img src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'assets/img/shipi_100px.png'); ?>"></h2>
                        <p><?php esc_html_e("Please Enter the below details.", "shipi"); ?></p>
                        <input type="text" name="shipi_connection_username" placeholder="User Name" />
                        <input type="password" name="shipi_connection_password" placeholder="Password" />
                        <p><?php esc_html_e("If you already have an account with Shipi, it will connect. Otherwise, it will create a new user and connect the site.", "shipi"); ?></p>
                        <?php echo esc_html($error); ?>
                        
                        <!-- Add the nonce field here -->
                        <?php wp_nonce_field('shipi_connection_action', 'shipi_connection_nonce'); ?>
                        
                        <input type="submit" name="shipi_connection_submit" value="Connect or Create Account" />
                        <div class="shipi-links">
                            <a href="https://calendar.app.google/aVfnftudzdtZwDVT9"><?php esc_html_e("Book Meeting with Us", "shipi"); ?></a>
                            <a href="https://app.myshipi.com/support/"><?php esc_html_e("Create Support Ticket", "shipi"); ?></a>
                        </div>
                    </form>

                                        <?php
                }else{
                    echo "<iframe id='load_iframe_content' src='https://app.myshipi.com/carriers.php?direct=load&load=". esc_html(base64_encode(esc_url(site_url()))) ."&key=". esc_html($key) ."' style='width:100%;height:100vh;'></iframe>";
                }
                
            }
            function wc_new_order_column( $columns ) {
				$columns['shipi_shipping'] = 'Shipping';
				return $columns;
			}
            function wc_new_order_download( $column, $post ) {
				
				if ( 'shipi_shipping' === $column ) {
					$order    = ($this->hpos_enabled) ? $post : wc_get_order( $post );
					$this->get_shipment_html($order);
                    
                }
            }
            function order_details_screen_data($order) {
                $this->get_shipment_html($order, true);
            }
            function get_shipment_html($order, $show_txt = false){
                $order_id = $order->get_id();
                $carrier = "";
                if ($this->hpos_enabled) {
                    $button_txt = $order->get_meta("_shipi_tracking_no");
                    $carrier = $order->get_meta("_shipi_carrier");
                }else{
                    $button_txt = get_post_meta($order_id, '_shipi_tracking_no', true);
                    $carrier = get_post_meta($order_id, '_shipi_carrier', true);
                }

                $pdf_button_style = "display:none;";
                $button_class = "button button-secondary";
                // Set default text if tracking number is not available
                if (empty($button_txt)) {
                    $button_txt = "Create Shipment";
                    
                }else{
                    $pdf_button_style = "";
                }
            
                // Display the button with the tracking number or default text
                echo '<p class="form-field form-field-wide wc-customer-user">';
                echo ($show_txt) ? "<b>Tracking <br/></b>" : "";
                echo '<a href="https://app.myshipi.com/shipping_labels/_label_'. esc_html($button_txt) .'.pdf" class="'. esc_attr($order_id) .'_shipi_pdf_btn button button-secondary" target="_blank" style="cursor: pointer;margin-right:5px;'. esc_html($pdf_button_style) .'" ><span class="dashicons dashicons-pdf"></span></a>';
                echo '<a data-order-id="' . esc_attr($order_id) . '" style="cursor: pointer;" class="'. esc_html($button_class) .' shipi_label_button '. esc_attr($order_id) .'_shipi_label_button">' . esc_html($button_txt) . '</a>';
                echo  "<img src='https://app.myshipi.com/assets/img/brand/". esc_html($carrier) .".jpg' class='". esc_attr($order_id) ."_shipi_brand_img' style='width:50px;margin-left:5px;vertical-align: middle;". esc_html($pdf_button_style) ."' >";
                echo '</p>';
            }
            function wc_checkout_order_processed($order_id){
                if ($this->hpos_enabled) {
                    if ('shop_order' !== Automattic\WooCommerce\Utilities\OrderUtil::get_order_type($order_id)) {
                        return;
                    }
                } else {
                   $post = get_post($order_id);
                   
                   if($post->post_type !='shop_order' ){
                       return;
                   }
               }

               $order = wc_get_order( $order_id );

            }
            function track($order){
                $order_id = $order->get_id();
            }
            function admin_css_js() {
                // Enqueue your CSS file
                wp_enqueue_style( 'shipi-admin_css', plugin_dir_url( __FILE__ ) . 'assets/css/admin.css', array(), '1.0.0', 'all' );
                $ajax_params = array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'security' => wp_create_nonce( 'shipi_label_ajax_nonce' ),
                );
                wp_register_script( 'shipi-admin_js', plugin_dir_url( __FILE__ ) . 'assets/js/admin.js', array( 'jquery' ), '1.0.1', true );
                wp_localize_script( 'shipi-admin_js', 'ajax_params', $ajax_params );
                wp_enqueue_script( 'shipi-admin_js' );
            }
            function admin_footor(){
                $key = get_option("shipi_integration_key", false);
                if($key){
                ?>
                <div id="shipi_label_popup" class="shipi-popup"> 
                    <div class="shipi-popup-content">
                    <center><div id="shipi-loading"></div></center>
                    <input type="hidden" id="get_base_shipi_url" value="https://app.myshipi.com/carriers/create-label-ui.php?platform=woocommerce&direct=load&key=<?php echo esc_html($key); ?>&load=<?php echo esc_html(base64_encode(esc_url(site_url()))); ?>">
                    <iframe id='shipi_load_order' src='' style='width:100%;height:780px;display:none;'></iframe>    
                    <button id="shipi_closePopup"> X </button>
                    </div>
                </div>
                <?php
                }
            }
            function get_tracking_shipi(){
                check_ajax_referer( 'shipi_label_ajax_nonce', 'security' );
                $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
                $order = wc_get_order($order_id);
                $carrier = "";
                if ($this->hpos_enabled) {
                    $tracking = $order->get_meta("_shipi_tracking_no");
                    $carrier = $order->get_meta("_shipi_carrier");
                    
                }else{
                    $tracking = get_post_meta($order_id, '_shipi_tracking_no', true);
                    $carrier = get_post_meta($order_id, '_shipi_carrier', true);
                }
                if($tracking != ""){
                    wp_send_json_success( array( "tracking_no" => $tracking, "carrier" => $carrier) );
                }

                wp_send_json_error( array("msg" => 'No Data Found.'));
            }
            function get_order_details_shipi(){
                check_ajax_referer( 'shipi_label_ajax_nonce', 'security' );
                $order_id = isset( $_POST['order_id'] ) ? intval( $_POST['order_id'] ) : 0;
                $response = $this->get_order_details_shipi_core($order_id);
                if(isset($response["status"]) && $response["status"] == "success"){
                    wp_send_json_success($response["msg"]);
                }else{
                    wp_send_json_error($response["msg"]);
                }
            }

            function get_order_details_shipi_core($order_id){
                

                $order = wc_get_order( $order_id );
                if ( ! $order ) {
                    return array("status" => "error", "msg" => 'Invalid order ID.');
                }
                $key = get_option("shipi_integration_key", false);
                if(!$key){
                    return array("status" => "error", "msg" => 'Configure the Plugin to use this option.');

                }
                // Send Order To shipi then Open the Popup to create
				$currency = get_option('woocommerce_currency');

				$pack_products = $this->get_pack_products($order->get_items());
                $shipping_methods = $order->get_shipping_methods();
                $selected_service_name = $order->get_shipping_method();
                $selected_service = "";
                foreach ($shipping_methods as $shipping_method) {
                    if($shipping_method->get_method_id() == "shipi_carrier"){
                        $meta_data = $shipping_method->get_meta_data();
                        foreach($meta_data as $meta){
                            $selected_data = $meta->get_data();
                            $selected_service = $selected_data["value"];
                        }
                    }
                }
                
                $reciver_address = [];
                if($order->get_shipping_first_name() != ""){
                    $reciver_address =  array(
                        "r_name" => $order->get_shipping_first_name(),
                        "r_email" => $order->get_billing_email(),
                        "r_phone" => $order->get_billing_phone(),
                        "r_company" => $order->get_shipping_company(),
                        "r_address_1" => $order->get_shipping_address_1(),
                        "r_address_2" => $order->get_shipping_address_2(),
                        "r_city" => $order->get_shipping_city(),
                        "r_state" => $order->get_shipping_state(),
                        "r_zip" => $order->get_shipping_postcode(),
                        "r_country" => $order->get_shipping_country(),
                        "r_meta" => apply_filters("shipi_add_reciver_info_order", array(), $order_id)
                    );
                }else{
                    $reciver_address =  array(
                        "r_name" => $order->get_billing_first_name(),
                        "r_email" => $order->get_billing_email(),
                        "r_phone" => $order->get_billing_phone(),
                        "r_company" => $order->get_billing_company(),
                        "r_address_1" => $order->get_billing_address_1(),
                        "r_address_2" => $order->get_billing_address_2(),
                        "r_city" => $order->get_billing_city(),
                        "r_state" => $order->get_billing_state(),
                        "r_zip" => $order->get_billing_postcode(),
                        "r_country" => $order->get_billing_country(),
                        "r_meta" => apply_filters("shipi_add_reciver_info_order", array(), $order_id)
                    );
                }
                $payload = array(
                    "platform" => "woocommerce",
                    "products" => $pack_products,
                    "receiver_address" => $reciver_address,
                    "shop_id" => esc_html($key),
                    "sitekey" => esc_html(base64_encode(esc_url(site_url()))),
                    "settings" => array("service_code" => $selected_service, "service_name" => $selected_service_name, "currency" => $currency),
                    "order_id"  => $order->id,
                );

                $entry_response = wp_remote_post(
					// 'https://app.myshipi.com/label_api/entry.php',
					'https://app.myshipi.com/label_api/entry.php',
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
				$entry_response = (is_array($entry_response) && isset($entry_response['body'])) ? json_decode($entry_response['body'], true) : array();
				if(!empty($entry_response) && isset($entry_response["success"])){
                    if($entry_response["success"] == "true"){
                        update_post_meta($order_id, "shipi_pushed", "pushed");
                        return array("status" => "success", "msg" => $entry_response["msg"]);

                    }else{
                        return array("status" => "error", "msg" => $entry_response["msg"]);

                    }
                }else{
                    return array("status" => "error", "msg" => 'Generating Packages failed.');
                }
            }
            public function get_pack_products($items){
                $pack_products = array();
                foreach ( $items as $item ) {
                    $curr_prod_id = $item->get_product_id();
                    $get_product = $item->get_product();
                    
                    $product = array();
                    $product['product_name'] = str_replace(":","-",$get_product->get_name());
                    $product['product_quantity'] = $item['quantity'];
                    $product['price'] = number_format((float) round((float)($get_product->get_price()),2) , 2, '.', '');
                    $product['width'] = round((float)$get_product->get_width(),2);
                    $product['height'] = round((float)$get_product->get_name(),2);
                    $product['depth'] = round((float)$get_product->get_height(),2);
                    $product['weight'] = round((float)$get_product->get_weight(),2);
                    $product['product_id'] = $curr_prod_id;
                    $pack_products[] = $product;
                }
                return $pack_products;
            }
        }
        
    }
    new shipi_Parent();
}