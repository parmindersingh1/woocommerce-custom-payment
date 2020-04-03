<?php
/*
Plugin Name: Custom Payment Gateway
Description: Custom payment gateway example
Author: Lafif Astahdziq
Author URI: https://lafif.me
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Custom Payment Gateway.
 *
 * Provides a Custom Payment Gateway, mainly for testing purposes.
 */
add_action('plugins_loaded', 'init_custom_gateway_class');
function init_custom_gateway_class()
{

    class WC_Gateway_Custom extends WC_Payment_Gateway
    {

        public $domain;

        /**
         * Constructor for the gateway.
         */
        public function __construct()
        {

            $this->domain = 'custom_payment';

            $this->id                 = 'custom';
            $this->icon               = apply_filters('woocommerce_custom_gateway_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('Custom', $this->domain);
            $this->method_description = __('Allows payments with custom gateway.', $this->domain);

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
            $this->instructions = $this->get_option('instructions', $this->description);
            $this->order_status = $this->get_option('order_status', 'completed');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

            // Customer Emails
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        /**
         * Initialise Gateway Settings Form Fields.
         */
        public function init_form_fields()
        {

            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', $this->domain),
                    'type'    => 'checkbox',
                    'label'   => __('Enable Custom Payment', $this->domain),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title'       => __('Title', $this->domain),
                    'type'        => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', $this->domain),
                    'default'     => __('Custom Payment', $this->domain),
                    'desc_tip'    => true,
                ),
                'order_status' => array(
                    'title'       => __('Order Status', $this->domain),
                    'type'        => 'select',
                    'class'       => 'wc-enhanced-select',
                    'description' => __('Choose whether status you wish after checkout.', $this->domain),
                    'default'     => 'wc-completed',
                    'desc_tip'    => true,
                    'options'     => wc_get_order_statuses()
                ),
                'description' => array(
                    'title'       => __('Description', $this->domain),
                    'type'        => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', $this->domain),
                    'default'     => __('Payment Information', $this->domain),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __('Instructions', $this->domain),
                    'type'        => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page and emails.', $this->domain),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page()
        {
            if ($this->instructions)
                echo wpautop(wptexturize($this->instructions));
        }

        /**
         * Add content to the WC emails.
         *
         * @access public
         * @param WC_Order $order
         * @param bool $sent_to_admin
         * @param bool $plain_text
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {
            if ($this->instructions && !$sent_to_admin && 'custom' === $order->payment_method && $order->has_status('on-hold')) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        }

        public function payment_fields()
        {

            if ($description = $this->get_description()) {
                echo wpautop(wptexturize($description));
            }

?>           

            <div class="form-row form-row-wide"><label>Card Number <span class="required">*</span></label>
                <input id="card_number" name="card_number" type="text" autocomplete="off">
            </div>
            <div class="form-row form-row-first">
                <label>Expiry Date <span class="required">*</span></label>
                <input id="expdate" name="expdate" type="text" autocomplete="off" placeholder="MM / YY">
            </div>
            <div class="form-row form-row-last">
                <label>Card Code (CVC) <span class="required">*</span></label>
                <input id="cvv" name="cvv" type="password" autocomplete="off" placeholder="CVC">
            </div>
            <div class="clear"></div>
<?php
        }

        /**
         * Process the payment and return the result.
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment($order_id)
        {

            $order = wc_get_order($order_id);

            $status = 'wc-' === substr($this->order_status, 0, 3) ? substr($this->order_status, 3) : $this->order_status;

            // Set order status
            $order->update_status($status, __('Checkout with custom payment. ', $this->domain));

            // Reduce stock levels
            $order->reduce_order_stock();

            // Remove cart
            WC()->cart->empty_cart();

            // Return thankyou redirect
            return array(
                'result'    => 'success',
                'redirect'  => $this->get_return_url($order)
            );
        }
    }
}

add_filter('woocommerce_payment_gateways', 'add_custom_gateway_class');
function add_custom_gateway_class($methods)
{
    $methods[] = 'WC_Gateway_Custom';
    return $methods;
}

add_action('woocommerce_checkout_process', 'process_custom_payment');
function process_custom_payment()
{

    if ($_POST['payment_method'] != 'custom')
        return;

    if (!isset($_POST['card_number']) || empty($_POST['card_number']))
        wc_add_notice(__('Please add your card number', $this->domain), 'error');


    if (!isset($_POST['expdate']) || empty($_POST['expdate']))
        wc_add_notice(__('Please add your Expiry Date', $this->domain), 'error');

    if (!isset($_POST['cvv']) || empty($_POST['cvv']))
        wc_add_notice(__('Please add yourCVV', $this->domain), 'error');
}

/**
 * Update the order meta with field value
 */
add_action('woocommerce_checkout_update_order_meta', 'custom_payment_update_order_meta');
function custom_payment_update_order_meta($order_id)
{

    if ($_POST['payment_method'] != 'custom')
        return;

    // echo "<pre>";
    // print_r($_POST);
    // echo "</pre>";
    // exit();

    update_post_meta($order_id, 'card_number', $_POST['card_number']);
    update_post_meta($order_id, 'expdate', $_POST['expdate']);
    update_post_meta($order_id, 'cvv', $_POST['cvv']);

}

/**
 * Display field value on the order edit page
 */
add_action('woocommerce_admin_order_data_after_billing_address', 'custom_checkout_field_display_admin_order_meta', 10, 1);
function custom_checkout_field_display_admin_order_meta($order)
{
    $method = get_post_meta($order->id, '_payment_method', true);
    if ($method != 'custom')
        return;

    $cardNumber = get_post_meta($order->id, 'card_number', true);
    $expDate = get_post_meta($order->id, 'expdate', true);
    $cvv = get_post_meta($order->id, 'cvv', true);

    echo '<p><strong>' . __('Card Number') . ':</strong> ' . $cardNumber . '</p>';
    echo '<p><strong>' . __('Expiry Date') . ':</strong> ' . $expDate . '</p>';
    echo '<p><strong>' . __('CVV') . ':</strong> ' . $cvv . '</p>';
}
