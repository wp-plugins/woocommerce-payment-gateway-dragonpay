<?php
/**
 * Dragonpay WooCommerce Plugin
 * 
 * @author dennis.paler<dpaler@shockwebstudio.com>
 * @version 2.3.0
 * @example For callback : http://shoppingcarturl/?wc-api=WC_Dragonpay_Gateway
 */

/**
 * Plugin Name: WooCommerce Dragonpay
 * Description: WooCommerce Dragonpay Plugin
 * Author: dennis.paler
 * Author URI: http:/www.shockwebstudio.com/
 * Version: 1.1.0
 * License: MIT
 * For callback : http://shoppingcarturl/?wc-api=WC_Dragonpay_Gateway
 */

/**
 * If WooCommerce plugin is not available
 * 
 */
function wcdragonpay_woocommerce_fallback_notice() {
    $message = '<div class="error">';
    $message .= '<p>' . __( 'WooCommerce dragonpay Gateway depends on the last version of <a href="http://wordpress.org/extend/plugins/woocommerce/">WooCommerce</a> to work!' , 'wcdragonpay' ) . '</p>';
    $message .= '</div>';
    echo $message;
}

//Load the function
add_action( 'plugins_loaded', 'wcdragonpay_gateway_load', 0 );

/**
 * Load dragonpay gateway plugin function
 * 
 * @return mixed
 */
function wcdragonpay_gateway_load() {
    if ( !class_exists( 'WC_Payment_Gateway' ) ) {
        add_action( 'admin_notices', 'wcdragonpay_woocommerce_fallback_notice' );
        return;
    }

    //Load language
    load_plugin_textdomain( 'wcdragonpay', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    add_filter( 'woocommerce_payment_gateways', 'wcdragonpay_add_gateway' );

    /**
     * Add dragonpay gateway to ensure WooCommerce can load it
     * 
     * @param array $methods
     * @return array
     */
    function wcdragonpay_add_gateway( $methods ) {
        $methods[] = 'WC_Dragonpay_Gateway';
        return $methods;
    }

    /**
     * Define the dragonpay gateway
     * 
     */
    class WC_Dragonpay_Gateway extends WC_Payment_Gateway {

        /**
         * Construct the dragonpay gateway class
         * 
         * @global mixed $woocommerce
         */
        public function __construct() {
            global $woocommerce;

            $this->id = 'dragonpay';
            $this->icon = plugins_url( 'images/dragonpay.png', __FILE__ );
            $this->has_fields = false;
            $this->pay_url = 'http://test.dragonpay.ph/Pay.aspx?';
            $this->method_title = __( 'dragonpay', 'wcdragonpay' );

            // Load the form fields.
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            // Define user setting variables.
            $this->title = $this->settings['title'];
            $this->description = $this->settings['description'];
            $this->merchant_id = $this->settings['merchant_id'];
            $this->password = $this->settings['password'];

            // Actions.
            add_action( 'valid_dragonpay_request_returnurl', array( &$this, 'check_dragonpay_response_returnurl' ) );
            add_action( 'woocommerce_receipt_dragonpay', array( &$this, 'receipt_page' ) );
			
            //save setting configuration
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
						

            // Checking if merchant_id is not empty.
            $this->merchant_id == '' ? add_action( 'admin_notices', array( &$this, 'merchant_id_missing_message' ) ) : '';

            // Checking if verify_key is not empty.
            $this->password == '' ? add_action( 'admin_notices', array( &$this, 'password_missing_message' ) ) : '';
        }

        /**
         * Checking if this gateway is enabled and available in the user's country.
         *
         * @return bool
         */
        public function is_valid_for_use() {
            if ( !in_array( get_woocommerce_currency() , array( 'PHP' ) ) ) {
                return false;
            }
            return true;
        }

        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis.
         *
         */
        public function admin_options() {
            ?>
            <h3><?php _e( 'Dragonpay Online Payment', 'wcdragonpay' ); ?></h3>
            <p><?php _e( 'Dragonpay Online Payment works by sending the user to Dragonpay to enter their payment information.', 'wcdragonpay' ); ?></p>
            <table class="form-table">
                <?php $this->generate_settings_html(); ?>
            </table><!--/.form-table-->
            <?php
        }

        /**
         * Gateway Settings Form Fields.
         * 
         */
        public function init_form_fields() {

            $this->form_fields = array(
                'enabled' => array(
                    'title' => __( 'Enable/Disable', 'wcdragonpay' ),
                    'type' => 'checkbox',
                    'label' => __( 'Enable dragonpay', 'wcdragonpay' ),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __( 'Title', 'wcdragonpay' ),
                    'type' => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'wcdragonpay' ),
                    'default' => __( 'Dragonpay Online Payment', 'wcdragonpay' )
                ),
                'description' => array(
                    'title' => __( 'Description', 'wcdragonpay' ),
                    'type' => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'wcdragonpay' ),
                    'default' => __( 'Pay with Dragonpay Online Payment', 'wcdragonpay' )
                ),
                'merchant_id' => array(
                    'title' => __( 'Merchant ID', 'wcdragonpay' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Dragonpay Merchant ID.', 'wcdragonpay' ) . ' ' . sprintf( __( 'You can to get this information in: %sDragonpay Account%s.', 'wcdragonpay' ), '<a href="https://www.dragonpay.ph/" target="_blank">', '</a>' ),
                    'default' => ''
                ),
                'password' => array(
                    'title' => __( 'Password', 'wcdragonpay' ),
                    'type' => 'text',
                    'description' => __( 'Please enter your Dragonpay password.', 'wcdragonpay' ) . ' ' . sprintf( __( 'You can to get this information in: %sDragonpay Account%s.', 'wcdragonpay' ), '<a href="https://www.dragonpay.ph/" target="_blank">', '</a>' ),
                    'default' => ''
                )
            );
        }

        /**
         * Generate the form.
         *
         * @param mixed $order_id
         * @return string
         */
        public function generate_form( $order_id ) {
            $order = new WC_Order( $order_id );	
            $pay_url = $this->pay_url;
            $total = $order->order_total;			
            if ( sizeof( $order->get_items() ) > 0 ) 
                foreach ( $order->get_items() as $item )
                    if ( $item['qty'] )
                        $item_names[] = $item['name'] . ' x ' . $item['qty'];

            $desc = sprintf( __( 'Order %s' , 'woocommerce'), $order->get_order_number() ) . " - " . implode( ', ', $item_names );
						
            $merchantid = $this->merchant_id;
            $txnid = $order->id;
            $amount = $order->order_total;
            $ccy = get_woocommerce_currency();
            $description = $desc;
            $email = $order->billing_email;
            $password = $this->password;
            $digest_str = "$merchantid:$txnid:$amount:$ccy:$description:$email:$password";  
            $digest = sha1($digest_str);  

            $dragonpay_args = array(
                'merchantid' => $this->merchant_id,
                'txnid' => $order->id,
                'amount' => $total,
                'ccy' => get_woocommerce_currency(),
                'description' => $desc,
                'email' => $order->billing_email,
                'digest' => $digest
            );

            $dragonpay_args_array = array();

            foreach ($dragonpay_args as $key => $value) {
                $dragonpay_args_array[] = "<input type='hidden' name='".$key."' value='". $value ."' />";
            }
			
            return "<form action='".$pay_url."' method='get' id='dragonpay_payment_form' name='dragonpay_payment_form'>"
                    . implode('', $dragonpay_args_array)
                    . "<input type='submit' class='button-alt' id='submit_dragonpay_payment_form' value='" . __('Pay via dragonpay', 'woothemes') . "' /> "
                    . "<a class='button cancel' href='" . $order->get_cancel_order_url() . "'>".__('Cancel order &amp; restore cart', 'woothemes')."</a>"
                    . "<script>document.dragonpay_payment_form.submit();</script>"
                    . "</form>";
        }

        /**
         * Order error button.
         *
         * @param  object $order Order data.
         * @return string Error message and cancel button.
         */
        protected function dragonpay_order_error( $order ) {
            $html = '<p>' . __( 'An error has occurred while processing your payment, please try again. Or contact us for assistance.', 'wcdragonpay' ) . '</p>';
            $html .='<a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Click to try again', 'wcdragonpay' ) . '</a>';
            return $html;
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {
            $order = new WC_Order( $order_id );
            return array(
                'result' => 'success',
                'redirect' => add_query_arg( 'order', $order->id, add_query_arg( 'key', $order->order_key, get_permalink( woocommerce_get_page_id( 'pay' ) ) ) )
            );
        }

        /**
         * Output for the order received page.
         * 
         */
        public function receipt_page( $order ) {
            echo $this->generate_form( $order );
        }

        /**
         * Check for Dragonpay Response
         *
         * @access public
         * @return void
         */
       
		
        /**
         * This part is returnurl function for dragonpay
         * 
         * @global mixed $woocommerce
         */
        function check_dragonpay_response_returnurl() {
            global $woocommerce;
			
			//Get parameters from dragonpay response

            /*
             *  Your code here
             */

            $order = new WC_Order( $orderid );
           

            if ($status == 'S') {
                $order->add_order_note('dragonpay Payment Status: SUCCESSFUL'.'<br>Transaction ID: ' . $tranID . $referer);								
                $order->payment_complete();
                wp_redirect($order->get_checkout_order_received_url());
                exit;
            }
			else if ($status == "P") { 
                $order->add_order_note('dragonpay Payment Status: PENDING');
                $order->update_status('pending', sprintf(__('Payment %s via dragonpay.', 'woocommerce')) );
                wp_redirect($order->get_checkout_order_received_url());
                exit;
            }
            else if ($status == "F") { 
                $order->add_order_note('dragonpay Payment Status: FAILED');
                $order->update_status('failed', sprintf(__('Payment %s via dragonpay.', 'woocommerce')) );
                wp_redirect($order->get_cancel_order_url());
                exit;
            } 
            else  {
                $order->add_order_note('dragonpay Payment Status: Invalid Transaction');
                $order->update_status('on-hold', sprintf(__('Payment %s via dragonpay.', 'woocommerce')) );
                wp_redirect($order->get_cancel_order_url());
                exit;
            }	
        }
		
	
        /**
         * Adds error message when not configured the app_key.
         * 
         */
        public function merchant_id_missing_message() {
            $message = '<div class="error">';
            $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your Merchant ID in dragonpay. %sClick here to configure!%s' , 'wcdragonpay' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Dragonpay_Gateway">', '</a>' ) . '</p>';
            $message .= '</div>';
            echo $message;
        }

        /**
         * Adds error message when not configured the app_secret.
         * 
         */
        public function password_missing_message() {
            $message = '<div class="error">';
            $message .= '<p>' . sprintf( __( '<strong>Gateway Disabled</strong> You should inform your password in dragonpay. %sClick here to configure!%s' , 'wcdragonpay' ), '<a href="' . get_admin_url() . 'admin.php?page=woocommerce_settings&tab=payment_gateways&section=WC_Dragonpay_Gateway">', '</a>' ) . '</p>';
            $message .= '</div>';
            echo $message;
        }

    }
}