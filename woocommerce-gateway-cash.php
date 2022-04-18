<?php
/**
* Plugin Name: WooCommerce Cash Gateway
* Plugin URI: https://github.com/CalmSleep/woocommerce-gateway-cash
* Description: Clones the "Cheque" gateway to create another manual / cash payment method; can be used for testing as well.
* Author: Guido Gallo
* Author URI: https://github.com/CalmSleep/woocommerce-gateway-cash
* Version: 1.0.2
* Text Domain: wc-gateway-cash
* Domain Path: /i18n/languages/
*
* Copyright: (c) 2022-2023 Sleep Calm SA (it@calmessimple.com.ar) and WooCommerce
*
* License: GNU General Public License v3.0
* License URI: http://www.gnu.org/licenses/gpl-3.0.html
*
* @package   WC-Gateway-Cash
* @author    Guido Gallo
* @category  Admin
* @copyright Copyright: (c) 2022-2023 Sleep Calm SA (it@calmessimple.com.ar) and WooCommerce
* @license   http://www.gnu.org/licenses/gpl-3.0.html GNU General Public License v3.0
*
* This cash gateway forks the WooCommerce core "Cheque" payment gateway to create another cash payment method.
*/

defined( 'ABSPATH' ) or exit;


// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
	return;
}


/**
* Add the gateway to WC Available Gateways
* 
* @since 1.0.0
* @param array $gateways all available WC gateways
* @return array $gateways all WC gateways + cash gateway
*/
function wc_cash_add_to_gateways( $gateways ) {
	$gateways[] = 'WC_Gateway_Cash';
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_cash_add_to_gateways' );


/**
* Adds plugin page links
* 
* @since 1.0.0
* @param array $links all plugin links
* @return array $links all plugin links + our custom links (i.e., "Settings")
*/
function wc_cash_gateway_plugin_links( $links ) {
	
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=cash_gateway' ) . '">' . __( 'Configure', 'wc-gateway-cash' ) . '</a>'
	);
	
	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_cash_gateway_plugin_links' );




/**
* @snippet       Enable Payment Gateway for a Specific User Role | WooCommerce
* @how-to        Get CustomizeWoo.com FREE
* @compatible    WooCommerce 3.8
* @donate $9     https://businessbloomer.com/bloomer-armada/
*/

add_filter( 'woocommerce_available_payment_gateways', 'bbloomer_paypal_enable_manager' );

function bbloomer_paypal_enable_manager( $available_gateways ) {
	if ( isset( $available_gateways['cash_gateway'] ) ) {
		/*
		//if user can manage woocommerce can set cash as payment method
		if(! current_user_can( 'manage_woocommerce' )){
			unset( $available_gateways['cash_gateway'] );
		}
		*/
		//if order is pickup can select cash as payment method
		if(WC()->session){
			$chosen_methods = WC()->session->get( 'chosen_shipping_methods' );
			$chosen_shipping = $chosen_methods[0];
			if ($chosen_shipping != 'calmPickupShippingMethod') {
				unset( $available_gateways['cash_gateway'] );
			}
		}
	} 
	return $available_gateways;
}



/**
* Cash Payment Gateway
*
* Provides an Cash Payment Gateway; mainly for testing purposes.
* We load it later to ensure WC is loaded first since we're extending it.
*
* @class 		WC_Gateway_Cash
* @extends		WC_Payment_Gateway
* @version		1.0.0
* @package		WooCommerce/Classes/Payment
* @author 		Guido Gallo
*/
add_action( 'plugins_loaded', 'wc_cash_gateway_init', 11 );

function wc_cash_gateway_init() {
	
	class WC_Gateway_Cash extends WC_Payment_Gateway {
		
		/**
		* Constructor for the gateway.
		*/
		public function __construct() {
			
			$this->id                 = 'cash_gateway';
			$this->icon               = apply_filters('woocommerce_cash_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Cash', 'wc-gateway-cash' );
			$this->method_description = __( 'Habilita pagos en efectivo. Las ordenes que se realicen entran en "on-hold".', 'wc-gateway-cash' );
			
			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();
			
			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );
			
			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
			
			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}
		
		
		/**
		* Initialize Gateway Settings Form Fields
		*/
		public function init_form_fields() {
			
			$this->form_fields = apply_filters( 'wc_cash_form_fields', array(
				
				'enabled' => array(
					'title'   => __( 'Habilita/Deshabilita', 'wc-gateway-cash' ),
					'type'    => 'checkbox',
					'label'   => __( 'Habilita el metodo de pago Cash', 'wc-gateway-cash' ),
					'default' => 'yes'
				),
				
				'title' => array(
					'title'       => __( 'Título', 'wc-gateway-cash' ),
					'type'        => 'text',
					'description' => __( 'Esto es para definir el título del método de pago que los clientes ven en el checkout.', 'wc-gateway-cash' ),
					'default'     => __( 'Pago en efectivo', 'wc-gateway-cash' ),
					'desc_tip'    => true,
				),
				
				'description' => array(
					'title'       => __( 'Descripción', 'wc-gateway-cash' ),
					'type'        => 'textarea',
					'description' => __( 'Esto es para definir la descripción del método de pago que los clientes ven en el checkout.', 'wc-gateway-cash' ),
					'default'     => __( 'Pagá en efectivo en el local cuando venís a retirar el pedido.', 'wc-gateway-cash' ),
					'desc_tip'    => true,
				),
				
				'instructions' => array(
					'title'       => __( 'Instrucciones', 'wc-gateway-cash' ),
					'type'        => 'textarea',
					'description' => __( 'Instrucciones que se agregan en el mail y thank you page.', 'wc-gateway-cash' ),
					'default'     => '',
					'desc_tip'    => true,
				),
			));
		}
			
			
		/**
		* Output for the order received page.
		*/
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}
		}
		
		
		/**
		* Add content to the WC emails.
		*
		* @access public
		* @param WC_Order $order
		* @param bool $sent_to_admin
		* @param bool $plain_text
		*/
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
			
			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}
		
		
		/**
		* Process the payment and return the result
		*
		* @param int $order_id
		* @return array
		*/
		public function process_payment( $order_id ) {
			
			$order = wc_get_order( $order_id );
			
			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting cash payment', 'wc-gateway-cash' ) );
			
			// Reduce stock levels
			$order->reduce_order_stock();
			
			// Remove cart
			WC()->cart->empty_cart();
			
			// Return thankyou redirect
			return array(
				'result' 	=> 'success',
				'redirect'	=> $this->get_return_url( $order )
			);
		}
			
	} // end \WC_Gateway_Cash class
}
