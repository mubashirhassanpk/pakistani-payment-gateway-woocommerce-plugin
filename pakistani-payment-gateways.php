<?php
/**
 * Plugin Name: Pakistani Payment Gateway
 * Plugin URI: https://www.mubashirhassan.com/pakistani-payment-gateway-plugin
 * Description: Complete payment gateway solution for Pakistani businesses. Supports 14 payment methods including Easypaisa, JazzCash, SadaPay, NayaPay, Zindigi, and major banks (HBL, UBL, Meezan, MCB, Allied, Alfalah, Faysal, Standard Chartered). Features transaction ID tracking, payment screenshot upload, sender verification, and customizable checkout fields.
 * Version: 1.0.0
 * Author: Mubashir Hassan
 * Author URI: https://www.mubashirhassan.com
 * Text Domain: pakistani-payment-gateways
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 * WC requires at least: 3.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Add settings link on plugin page
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ppg_add_plugin_action_links');
function ppg_add_plugin_action_links($links) {
    $payment_gateways_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout') . '">' . __('Payment Gateways', 'pakistani-payment-gateways') . '</a>';
    array_unshift($links, $payment_gateways_link);
    return $links;
}

/**
 * Enable file uploads in WooCommerce checkout form
 */
add_action('woocommerce_before_checkout_form', 'ppg_enable_checkout_file_upload');
function ppg_enable_checkout_file_upload() {
    // Screenshot upload removed - customers send via WhatsApp
}

/**
 * Add enctype to checkout form for file uploads
 */
add_filter('woocommerce_checkout_posted_data', 'ppg_add_enctype_to_checkout', 1);
function ppg_add_enctype_to_checkout($data) {
    // Screenshot upload removed - customers send via WhatsApp
    return $data;
}

/**
 * Ensure $_FILES is available during AJAX checkout
 */
add_action('woocommerce_checkout_process', 'ppg_process_checkout_files', 1);
function ppg_process_checkout_files() {
    // Screenshot upload removed - customers send via WhatsApp
}

// Screenshot upload JavaScript removed - customers send via WhatsApp

/**
 * Add custom payment gateways to WooCommerce
 */
add_filter('woocommerce_payment_gateways', 'ppg_add_payment_gateways');
function ppg_add_payment_gateways($gateways) {
    $gateways[] = 'WC_Gateway_Easypaisa';
    $gateways[] = 'WC_Gateway_Jazzcash';
    $gateways[] = 'WC_Gateway_Bank_Transfer_PK';
    $gateways[] = 'WC_Gateway_HBL';
    $gateways[] = 'WC_Gateway_UBL';
    $gateways[] = 'WC_Gateway_Meezan';
    $gateways[] = 'WC_Gateway_Allied';
    $gateways[] = 'WC_Gateway_MCB';
    $gateways[] = 'WC_Gateway_SadaPay';
    $gateways[] = 'WC_Gateway_NayaPay';
    $gateways[] = 'WC_Gateway_Zindigi';
    $gateways[] = 'WC_Gateway_BankAlfalah';
    $gateways[] = 'WC_Gateway_Faysal';
    $gateways[] = 'WC_Gateway_Standard';
    return $gateways;
}

/**
 * Initialize payment gateway classes
 */
add_action('plugins_loaded', 'ppg_init_payment_gateways');
function ppg_init_payment_gateways() {
    
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * Base class for Pakistani payment gateways
     */
    abstract class WC_Gateway_Pakistani_Base extends WC_Payment_Gateway {

        protected $gateway_id;
        protected $gateway_title;
        protected $gateway_description;
        protected $number_field_name;
        protected $logo_url;
        protected $is_bank_transfer = false;

        public function __construct() {
            $this->has_fields = true;
            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions');
            $this->enabled = $this->get_option('enabled');
            
            if (!$this->is_bank_transfer) {
                $gateway_number = $this->number_field_name;
                $this->$gateway_number = $this->get_option($gateway_number);
            } else {
                $this->bank_name = $this->get_option('bank_name');
                $this->account_number = $this->get_option('account_number');
                $this->iban = $this->get_option('iban');
                $this->branch_code = $this->get_option('branch_code');
                $this->swift_code = $this->get_option('swift_code');
            }
            
            $this->account_holder_name = $this->get_option('account_holder_name');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thank_you_page'));
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
        }

        public function init_form_fields() {
            $base_fields = array(
                'enabled' => array(
                    'title'   => __('Enable/Disable', 'pakistani-payment-gateways'),
                    'type'    => 'checkbox',
                    'label'   => sprintf(__('Enable %s Payment', 'pakistani-payment-gateways'), $this->gateway_title),
                    'default' => 'no',
                ),
                'title' => array(
                    'title'       => __('Title', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Payment method title shown to customers during checkout.', 'pakistani-payment-gateways'),
                    'default'     => sprintf(__('Pay with %s', 'pakistani-payment-gateways'), $this->gateway_title),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __('Description', 'pakistani-payment-gateways'),
                    'type'        => 'textarea',
                    'description' => __('Payment method description shown to customers during checkout.', 'pakistani-payment-gateways'),
                    'default'     => $this->is_bank_transfer 
                        ? sprintf(__('Pay securely via bank transfer to %s.', 'pakistani-payment-gateways'), $this->gateway_title)
                        : sprintf(__('Pay securely using %s mobile wallet.', 'pakistani-payment-gateways'), $this->gateway_title),
                    'desc_tip'    => true,
                ),
                'instructions' => array(
                    'title'       => __('Instructions', 'pakistani-payment-gateways'),
                    'type'        => 'textarea',
                    'description' => __('Instructions shown on the thank you page and in emails.', 'pakistani-payment-gateways'),
                    'default'     => $this->is_bank_transfer
                        ? __('Please transfer the payment to the bank account details provided and enter your transaction reference number.', 'pakistani-payment-gateways')
                        : sprintf(__('Please send payment to the %s number provided and enter your transaction ID.', 'pakistani-payment-gateways'), $this->gateway_title),
                    'desc_tip'    => true,
                ),
            );

            if ($this->is_bank_transfer) {
                $bank_fields = array(
                    'bank_name' => array(
                        'title'       => __('Bank Name', 'pakistani-payment-gateways'),
                        'type'        => 'text',
                        'description' => __('Your bank name.', 'pakistani-payment-gateways'),
                        'default'     => $this->gateway_title,
                        'desc_tip'    => true,
                    ),
                    'account_number' => array(
                        'title'       => __('Account Number', 'pakistani-payment-gateways'),
                        'type'        => 'text',
                        'description' => __('Your bank account number.', 'pakistani-payment-gateways'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'iban' => array(
                        'title'       => __('IBAN', 'pakistani-payment-gateways'),
                        'type'        => 'text',
                        'description' => __('Your IBAN number (International Bank Account Number).', 'pakistani-payment-gateways'),
                        'default'     => 'PK',
                        'desc_tip'    => true,
                    ),
                    'branch_code' => array(
                        'title'       => __('Branch Code', 'pakistani-payment-gateways'),
                        'type'        => 'text',
                        'description' => __('Your bank branch code (optional).', 'pakistani-payment-gateways'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'swift_code' => array(
                        'title'       => __('SWIFT Code', 'pakistani-payment-gateways'),
                        'type'        => 'text',
                        'description' => __('Your bank SWIFT/BIC code (optional, for international transfers).', 'pakistani-payment-gateways'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                    'show_swift_code' => array(
                        'title'       => __('Show SWIFT Code', 'pakistani-payment-gateways'),
                        'type'        => 'checkbox',
                        'label'       => __('Display SWIFT code to customers', 'pakistani-payment-gateways'),
                        'default'     => 'no',
                        'description' => __('Enable this to show SWIFT code on checkout and order details.', 'pakistani-payment-gateways'),
                    ),
                    'account_holder_name' => array(
                        'title'       => __('Account Holder Name', 'pakistani-payment-gateways'),
                        'type'        => 'text',
                        'description' => __('Name registered on the bank account.', 'pakistani-payment-gateways'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                );
                $account_fields = $bank_fields;
            } else {
                $wallet_fields = array(
                    $this->number_field_name => array(
                        'title'       => sprintf(__('%s Number', 'pakistani-payment-gateways'), $this->gateway_title),
                        'type'        => 'text',
                        'description' => sprintf(__('Your %s mobile wallet number.', 'pakistani-payment-gateways'), $this->gateway_title),
                        'default'     => '03XX-XXXXXXX',
                        'desc_tip'    => true,
                    ),
                    'account_holder_name' => array(
                        'title'       => __('Account Holder Name', 'pakistani-payment-gateways'),
                        'type'        => 'text',
                        'description' => __('Name registered on the mobile wallet account.', 'pakistani-payment-gateways'),
                        'default'     => '',
                        'desc_tip'    => true,
                    ),
                );
                $account_fields = $wallet_fields;
            }
            
            // Field visibility settings
            $field_settings = array(
                'field_settings_title' => array(
                    'title'       => __('Checkout Field Settings', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Control which fields are shown to customers during checkout.', 'pakistani-payment-gateways'),
                ),
                'show_transaction_id' => array(
                    'title'       => __('Show Transaction ID Field', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Display transaction ID field at checkout', 'pakistani-payment-gateways'),
                    'default'     => 'yes',
                    'description' => __('Allow customers to enter their transaction ID.', 'pakistani-payment-gateways'),
                ),
                'require_transaction_id' => array(
                    'title'       => __('Require Transaction ID', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Make transaction ID field required', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                    'description' => __('If enabled, customers must enter a transaction ID to complete the order.', 'pakistani-payment-gateways'),
                ),
                'show_sender_details' => array(
                    'title'       => __('Show Sender Details Fields', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Display sender account number and name fields', 'pakistani-payment-gateways'),
                    'default'     => 'yes',
                    'description' => __('Allow customers to enter their account/mobile number and account title.', 'pakistani-payment-gateways'),
                ),
                'require_sender_details' => array(
                    'title'       => __('Require Sender Details', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Make sender details fields required', 'pakistani-payment-gateways'),
                    'default'     => 'yes',
                    'description' => __('If enabled, customers must enter their account details.', 'pakistani-payment-gateways'),
                ),
                'show_screenshot_upload' => array(
                    'title'       => __('Show Payment Screenshot Upload', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Display payment screenshot upload field', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                    'description' => __('Note: Screenshot upload at checkout is disabled. Customers can send screenshots via WhatsApp after placing the order.', 'pakistani-payment-gateways'),
                ),
                'require_screenshot' => array(
                    'title'       => __('Require Payment Screenshot', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Make payment screenshot upload required', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                    'description' => __('Note: This setting is disabled. Customers send screenshots via WhatsApp.', 'pakistani-payment-gateways'),
                ),
                'max_screenshot_size' => array(
                    'title'       => __('Max Screenshot Size (MB)', 'pakistani-payment-gateways'),
                    'type'        => 'number',
                    'description' => __('Maximum file size for payment screenshot in megabytes.', 'pakistani-payment-gateways'),
                    'default'     => '5',
                    'desc_tip'    => true,
                    'custom_attributes' => array(
                        'min' => '1',
                        'max' => '10',
                        'step' => '1',
                    ),
                ),
            );
            
            // Email Template Settings
            $email_settings = array(
                'email_settings_title' => array(
                    'title'       => __('Email Template Settings', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Customize email templates sent to customers. Available variables: {order_number}, {customer_name}, {order_total}, {payment_method}, {transaction_id}, {sender_name}, {sender_number}, {site_name}, {order_date}, {billing_address}, {billing_city}, {billing_phone}, {billing_email}, {order_items}', 'pakistani-payment-gateways'),
                ),
                'enable_custom_email' => array(
                    'title'       => __('Enable Custom Email Templates', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Use custom email templates for this payment method', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                    'description' => __('Enable to customize email content sent to customers.', 'pakistani-payment-gateways'),
                ),
                
                // Payment Pending Email
                'pending_email_title' => array(
                    'title'       => __('Payment Pending Email (Sent After Order Placement)', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Email sent to customer immediately after placing order, before admin verification.<br><strong>Available Variables:</strong> {order_number}, {customer_name}, {order_total}, {payment_method}, {transaction_id}, {sender_name}, {sender_number}, {site_name}, {order_date}, {billing_address}, {billing_city}, {billing_phone}, {billing_email}, {order_items}', 'pakistani-payment-gateways'),
                ),
                'pending_email_subject' => array(
                    'title'       => __('Pending Email Subject', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Subject line for payment pending email. Use variables like {order_number}, {customer_name}, etc.', 'pakistani-payment-gateways'),
                    'default'     => __('Payment Submitted - Order {order_number} Awaiting Verification', 'pakistani-payment-gateways'),
                    'desc_tip'    => true,
                    'placeholder' => 'Payment Submitted - Order {order_number} Awaiting Verification',
                ),
                'pending_email_heading' => array(
                    'title'       => __('Pending Email Heading', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Main heading in the pending email. Use variables like {customer_name}, {order_number}, etc.', 'pakistani-payment-gateways'),
                    'default'     => __('Payment Submitted - Awaiting Verification', 'pakistani-payment-gateways'),
                    'desc_tip'    => true,
                    'placeholder' => 'Payment Submitted - Awaiting Verification',
                ),
                'pending_email_body' => array(
                    'title'       => __('Pending Email Body', 'pakistani-payment-gateways'),
                    'type'        => 'textarea',
                    'description' => __('Main content of the pending email. HTML is supported. <br><strong>Available Variables:</strong> {order_number}, {customer_name}, {order_total}, {payment_method}, {transaction_id}, {sender_name}, {sender_number}, {site_name}, {order_date}, {billing_address}, {billing_city}, {billing_phone}, {billing_email}, {order_items}', 'pakistani-payment-gateways'),
                    'default'     => __('Dear {customer_name},<br><br>Thank you for your order! We have received your payment submission for order #{order_number}.<br><br><strong>Order Details:</strong><br>Order Number: #{order_number}<br>Order Total: {order_total}<br>Payment Method: {payment_method}<br>Transaction ID: {transaction_id}<br>Paid From: {sender_name} ({sender_number})<br><br><strong>What happens next?</strong><br>• Our team will verify your payment details<br>• You will receive a confirmation email once verified<br>• Your order will then be processed and shipped<br>• Verification usually takes 1-24 hours<br><br><strong>Important:</strong> Your payment is currently pending verification. Please do not make duplicate payments.<br><br>If you have any questions, please contact us.<br><br>Thank you for shopping with {site_name}!', 'pakistani-payment-gateways'),
                    'desc_tip'    => false,
                    'css'         => 'min-height: 200px;',
                ),
                
                // Payment Approved Email
                'approved_email_title' => array(
                    'title'       => __('Payment Approved Email (Sent After Admin Verification)', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Email sent to customer after admin verifies and approves the payment.<br><strong>Available Variables:</strong> {order_number}, {customer_name}, {order_total}, {payment_method}, {transaction_id}, {sender_name}, {sender_number}, {site_name}, {order_date}, {billing_address}, {billing_city}, {billing_phone}, {billing_email}, {order_items}', 'pakistani-payment-gateways'),
                ),
                'approved_email_subject' => array(
                    'title'       => __('Approved Email Subject', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Subject line for payment approved email. Use variables like {order_number}, {customer_name}, etc.', 'pakistani-payment-gateways'),
                    'default'     => __('Payment Verified - Order {order_number} Confirmed', 'pakistani-payment-gateways'),
                    'desc_tip'    => true,
                    'placeholder' => 'Payment Verified - Order {order_number} Confirmed',
                ),
                'approved_email_heading' => array(
                    'title'       => __('Approved Email Heading', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Main heading in the approved email. Use variables like {customer_name}, {order_number}, etc.', 'pakistani-payment-gateways'),
                    'default'     => __('Payment Verified - Your Order is Confirmed!', 'pakistani-payment-gateways'),
                    'desc_tip'    => true,
                    'placeholder' => 'Payment Verified - Your Order is Confirmed!',
                ),
                'approved_email_body' => array(
                    'title'       => __('Approved Email Body', 'pakistani-payment-gateways'),
                    'type'        => 'textarea',
                    'description' => __('Main content of the approved email. HTML is supported. <br><strong>Available Variables:</strong> {order_number}, {customer_name}, {order_total}, {payment_method}, {transaction_id}, {sender_name}, {sender_number}, {site_name}, {order_date}, {billing_address}, {billing_city}, {billing_phone}, {billing_email}, {order_items}', 'pakistani-payment-gateways'),
                    'default'     => __('Dear {customer_name},<br><br>Great news! Your payment has been verified and confirmed.<br><br><strong>Order Details:</strong><br>Order Number: #{order_number}<br>Order Total: {order_total}<br>Payment Method: {payment_method}<br>Transaction ID: {transaction_id}<br>Order Date: {order_date}<br><br><strong>Billing Information:</strong><br>Name: {customer_name}<br>Email: {billing_email}<br>Phone: {billing_phone}<br>Address: {billing_address}, {billing_city}<br><br><strong>Items Ordered:</strong><br>{order_items}<br><br>Your order is now being processed and will be shipped soon. You will receive a shipping confirmation email with tracking details.<br><br>Thank you for your patience and for shopping with {site_name}!<br><br>If you have any questions, please don\'t hesitate to contact us.', 'pakistani-payment-gateways'),
                    'desc_tip'    => false,
                    'css'         => 'min-height: 200px;',
                ),
            );
            
            // Gateway Icon Settings
            $icon_settings = array(
                'icon_settings_title' => array(
                    'title'       => __('Gateway Icon Settings', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Upload a custom logo/icon for this payment gateway.', 'pakistani-payment-gateways'),
                ),
                'gateway_icon' => array(
                    'title'       => __('Gateway Icon URL', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Enter the URL of your gateway icon/logo image. Recommended size: 150x50px.', 'pakistani-payment-gateways'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
            );
            
            // Conditional Logic Settings
            $conditional_settings = array(
                'conditional_settings_title' => array(
                    'title'       => __('Conditional Logic Settings', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Control when this payment gateway is available based on cart conditions.', 'pakistani-payment-gateways'),
                ),
                'enable_min_amount' => array(
                    'title'       => __('Enable Minimum Amount', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Set minimum cart total for this gateway', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                ),
                'min_amount' => array(
                    'title'       => __('Minimum Amount', 'pakistani-payment-gateways'),
                    'type'        => 'number',
                    'description' => __('Minimum cart total required to use this gateway.', 'pakistani-payment-gateways'),
                    'default'     => '0',
                    'desc_tip'    => true,
                    'custom_attributes' => array('step' => '0.01'),
                ),
                'enable_max_amount' => array(
                    'title'       => __('Enable Maximum Amount', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Set maximum cart total for this gateway', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                ),
                'max_amount' => array(
                    'title'       => __('Maximum Amount', 'pakistani-payment-gateways'),
                    'type'        => 'number',
                    'description' => __('Maximum cart total allowed to use this gateway.', 'pakistani-payment-gateways'),
                    'default'     => '0',
                    'desc_tip'    => true,
                    'custom_attributes' => array('step' => '0.01'),
                ),
            );
            
            // Payment Fees Settings
            $fee_settings = array(
                'fee_settings_title' => array(
                    'title'       => __('Payment Fee Settings', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Add extra fees for using this payment gateway.', 'pakistani-payment-gateways'),
                ),
                'enable_fee' => array(
                    'title'       => __('Enable Payment Fee', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Add a fee for this payment method', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                ),
                'fee_type' => array(
                    'title'       => __('Fee Type', 'pakistani-payment-gateways'),
                    'type'        => 'select',
                    'description' => __('Choose between fixed amount or percentage.', 'pakistani-payment-gateways'),
                    'default'     => 'fixed',
                    'options'     => array(
                        'fixed'      => __('Fixed Amount', 'pakistani-payment-gateways'),
                        'percentage' => __('Percentage', 'pakistani-payment-gateways'),
                    ),
                    'desc_tip'    => true,
                ),
                'fee_amount' => array(
                    'title'       => __('Fee Amount', 'pakistani-payment-gateways'),
                    'type'        => 'number',
                    'description' => __('Enter the fee amount (e.g., 50 for fixed or 2.5 for 2.5%).', 'pakistani-payment-gateways'),
                    'default'     => '0',
                    'desc_tip'    => true,
                    'custom_attributes' => array('step' => '0.01'),
                ),
                'fee_label' => array(
                    'title'       => __('Fee Label', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Label shown for the fee in cart/checkout.', 'pakistani-payment-gateways'),
                    'default'     => __('Payment Processing Fee', 'pakistani-payment-gateways'),
                    'desc_tip'    => true,
                ),
            );
            
            // Custom Fields Settings
            $custom_fields_settings = array(
                'custom_fields_title' => array(
                    'title'       => __('Custom Payment Fields', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Add up to 3 custom fields for this payment gateway.', 'pakistani-payment-gateways'),
                ),
                'custom_field_1_label' => array(
                    'title'       => __('Custom Field 1 Label', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Label for first custom field (leave empty to disable).', 'pakistani-payment-gateways'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'custom_field_1_required' => array(
                    'title'       => __('Field 1 Required', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Make this field required', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                ),
                'custom_field_2_label' => array(
                    'title'       => __('Custom Field 2 Label', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Label for second custom field (leave empty to disable).', 'pakistani-payment-gateways'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'custom_field_2_required' => array(
                    'title'       => __('Field 2 Required', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Make this field required', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                ),
                'custom_field_3_label' => array(
                    'title'       => __('Custom Field 3 Label', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Label for third custom field (leave empty to disable).', 'pakistani-payment-gateways'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'custom_field_3_required' => array(
                    'title'       => __('Field 3 Required', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Make this field required', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                ),
            );
            
            // Automation Settings
            $automation_settings = array(
                'automation_title' => array(
                    'title'       => __('Automation Settings', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Automate order status changes and notifications.', 'pakistani-payment-gateways'),
                ),
                'auto_complete' => array(
                    'title'       => __('Auto-Complete Orders', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Automatically mark orders as completed after verification', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                    'description' => __('When enabled, orders will be marked as completed instead of processing after payment verification.', 'pakistani-payment-gateways'),
                ),
            );
            
            // WhatsApp Integration Settings
            $whatsapp_settings = array(
                'whatsapp_title' => array(
                    'title'       => __('WhatsApp Integration', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Send payment details via WhatsApp to customers and admin.', 'pakistani-payment-gateways'),
                ),
                'enable_whatsapp' => array(
                    'title'       => __('Enable WhatsApp Notifications', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Send payment details via WhatsApp', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                ),
                'whatsapp_admin_number' => array(
                    'title'       => __('Admin WhatsApp Number', 'pakistani-payment-gateways'),
                    'type'        => 'text',
                    'description' => __('Admin WhatsApp number with country code (e.g., 923001234567).', 'pakistani-payment-gateways'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'whatsapp_customer_message' => array(
                    'title'       => __('Customer WhatsApp Message', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Show WhatsApp button to customers on thank you page', 'pakistani-payment-gateways'),
                    'default'     => 'yes',
                ),
                'whatsapp_template_title' => array(
                    'title'       => __('WhatsApp Message Templates', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Customize WhatsApp message templates. Available variables: {order_number}, {amount}, {payment_method}, {transaction_id}, {sender_name}, {sender_number}, {customer_name}, {customer_email}, {customer_phone}, {customer_address}, {customer_city}, {order_items}, {admin_url}, {site_name}, {screenshot_url}, {order_date}, {billing_address}, {billing_city}, {billing_phone}, {billing_email}', 'pakistani-payment-gateways'),
                ),
                'whatsapp_admin_template' => array(
                    'title'       => __('Admin WhatsApp Template (New Payment Alert)', 'pakistani-payment-gateways'),
                    'type'        => 'textarea',
                    'description' => __('Message sent to admin when new payment is received. Use \\n for line breaks. Use variables above.', 'pakistani-payment-gateways'),
                    'default'     => "*NEW PAYMENT SUBMITTED*\n*REQUIRES VERIFICATION*\n\n━━━━━━━━━━━━━━━━━━━━\n*Order #{order_number}*\n*Amount:* {amount}\n*Method:* {payment_method}\n━━━━━━━━━━━━━━━━━━━━\n\n*PAYMENT DETAILS:*\nTransaction ID: {transaction_id}\nPaid From: *{sender_name}*\nAccount: {sender_number}\nScreenshot: {screenshot_url}\n\n*CUSTOMER INFO:*\nName: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}\nAddress: {customer_address}\nCity: {customer_city}\n\n━━━━━━━━━━━━━━━━━━━━\n*ORDER ITEMS:*\n{order_items}\n\n━━━━━━━━━━━━━━━━━━━━\n*ACTION REQUIRED:*\nVerify payment and approve order\n{admin_url}",
                    'desc_tip'    => true,
                    'css'         => 'min-height: 200px; font-family: monospace;',
                ),
                'whatsapp_customer_template' => array(
                    'title'       => __('Customer WhatsApp Template (Send to Admin)', 'pakistani-payment-gateways'),
                    'type'        => 'textarea',
                    'description' => __('Message customers can send to admin from thank you page. Use \\n for line breaks. Use variables above.', 'pakistani-payment-gateways'),
                    'default'     => "Hello, I have submitted payment for my order.\n\n*Order Details:*\nOrder Number: #{order_number}\nAmount: {amount}\nPayment Method: {payment_method}\nTransaction ID: {transaction_id}\nPaid From: {sender_name} ({sender_number})\n\n*My Details:*\nName: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}\n\nPlease verify my payment. Thank you!",
                    'desc_tip'    => true,
                    'css'         => 'min-height: 150px; font-family: monospace;',
                ),
                'whatsapp_thankyou_template' => array(
                    'title'       => __('Thank You WhatsApp Template (Admin to Customer)', 'pakistani-payment-gateways'),
                    'type'        => 'textarea',
                    'description' => __('Message admin can send to customer after verifying payment. Use \\n for line breaks. Use variables above.', 'pakistani-payment-gateways'),
                    'default'     => "*PAYMENT VERIFIED*\n\nDear {customer_name},\n\nGreat news! Your payment has been verified and confirmed.\n\n*Order Details:*\nOrder: #{order_number}\nAmount: {amount}\nMethod: {payment_method}\nTransaction: {transaction_id}\nDate: {order_date}\n\n*Items Ordered:*\n{order_items}\n\n*Delivery Address:*\n{customer_address}, {customer_city}\n\nYour order is now being processed and will be shipped soon. You will receive tracking details via email.\n\nThank you for shopping with {site_name}!\n\nIf you have any questions, feel free to reply to this message.",
                    'desc_tip'    => true,
                    'css'         => 'min-height: 200px; font-family: monospace;',
                ),
                'whatsapp_payment_request_template' => array(
                    'title'       => __('Payment Request WhatsApp Template (Admin to Customer)', 'pakistani-payment-gateways'),
                    'type'        => 'textarea',
                    'description' => __('Message admin can send to customer to request payment. Use \\n for line breaks. Use variables above.', 'pakistani-payment-gateways'),
                    'default'     => "*PAYMENT REQUEST*\n\nDear {customer_name},\n\nThis is a friendly reminder about your pending order.\n\n*Order Details:*\nOrder: #{order_number}\nAmount: {amount}\nPayment Method: {payment_method}\nOrder Date: {order_date}\n\n*Items Ordered:*\n{order_items}\n\n*Payment Instructions:*\nPlease send payment to the account details provided and share:\n1. Transaction ID\n2. Payment screenshot\n3. Account you paid from\n\nOnce payment is received, we will process your order immediately.\n\nThank you for shopping with {site_name}!",
                    'desc_tip'    => true,
                    'css'         => 'min-height: 200px; font-family: monospace;',
                ),
            );
            
            // Payment Reminder Settings
            $reminder_settings = array(
                'reminder_title' => array(
                    'title'       => __('Payment Reminder Settings', 'pakistani-payment-gateways'),
                    'type'        => 'title',
                    'description' => __('Send automatic reminders for pending payments.', 'pakistani-payment-gateways'),
                ),
                'enable_reminders' => array(
                    'title'       => __('Enable Payment Reminders', 'pakistani-payment-gateways'),
                    'type'        => 'checkbox',
                    'label'       => __('Send email reminders for pending payments', 'pakistani-payment-gateways'),
                    'default'     => 'no',
                ),
                'reminder_hours' => array(
                    'title'       => __('Reminder Delay (Hours)', 'pakistani-payment-gateways'),
                    'type'        => 'number',
                    'description' => __('Send reminder after X hours of pending payment.', 'pakistani-payment-gateways'),
                    'default'     => '24',
                    'desc_tip'    => true,
                    'custom_attributes' => array('min' => '1', 'step' => '1'),
                ),
            );
            
            $this->form_fields = array_merge(
                $base_fields, 
                $account_fields, 
                $field_settings, 
                $email_settings, 
                $icon_settings, 
                $conditional_settings, 
                $fee_settings, 
                $custom_fields_settings, 
                $automation_settings, 
                $whatsapp_settings, 
                $reminder_settings
            );
        }

        public function payment_fields() {
            if ($this->description) {
                echo '<p>' . wp_kses_post($this->description) . '</p>';
            }
            
            // Display custom gateway icon if set
            $gateway_icon = $this->get_option('gateway_icon', '');
            if (!empty($gateway_icon)) {
                echo '<img src="' . esc_url($gateway_icon) . '" alt="' . esc_attr($this->gateway_title) . '" style="max-height:50px;max-width:150px;margin-bottom:15px;display:block;" onerror="this.style.display=\'none\'" />';
            } elseif (!empty($this->logo_url)) {
                echo '<img src="' . esc_url($this->logo_url) . '" alt="' . esc_attr($this->gateway_title) . '" style="max-height:50px;max-width:150px;margin-bottom:15px;display:block;" onerror="this.style.display=\'none\'" />';
            }
            
            echo '<div style="background:#f7f7f7;padding:15px;margin-bottom:15px;border-radius:5px;">';
            
            if ($this->is_bank_transfer) {
                echo '<p style="margin:5px 0;"><strong>' . __('Bank Name:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($this->bank_name) . '</p>';
                
                if (!empty($this->account_number)) {
                    echo '<p style="margin:5px 0;"><strong>' . __('Account Number:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($this->account_number) . '</p>';
                }
                
                if (!empty($this->iban)) {
                    echo '<p style="margin:5px 0;"><strong>' . __('IBAN:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($this->iban) . '</p>';
                }
                
                if (!empty($this->branch_code)) {
                    echo '<p style="margin:5px 0;"><strong>' . __('Branch Code:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($this->branch_code) . '</p>';
                }
                
                // Show SWIFT code if enabled
                if ($this->get_option('show_swift_code', 'no') === 'yes' && !empty($this->swift_code)) {
                    echo '<p style="margin:5px 0;"><strong>' . __('SWIFT Code:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($this->swift_code) . '</p>';
                }
            } else {
                $gateway_number = $this->number_field_name;
                echo '<p style="margin:5px 0;"><strong>' . sprintf(__('%s Number:', 'pakistani-payment-gateways'), esc_html($this->gateway_title)) . '</strong> ' . esc_html($this->$gateway_number) . '</p>';
            }
            
            if (!empty($this->account_holder_name)) {
                echo '<p style="margin:5px 0;"><strong>' . __('Account Holder:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($this->account_holder_name) . '</p>';
            }
            echo '</div>';
            
            // Get field visibility settings
            $show_sender_details = $this->get_option('show_sender_details', 'yes') === 'yes';
            $require_sender_details = $this->get_option('require_sender_details', 'yes') === 'yes';
            $show_transaction_id = $this->get_option('show_transaction_id', 'yes') === 'yes';
            $require_transaction_id = $this->get_option('require_transaction_id', 'no') === 'yes';
            $show_screenshot = $this->get_option('show_screenshot_upload', 'yes') === 'yes';
            $require_screenshot = $this->get_option('require_screenshot', 'no') === 'yes';
            
            // Sender Details Fields
            if ($show_sender_details) {
                $required_mark = $require_sender_details ? ' <span class="required">*</span>' : ' <span style="color:#999;font-weight:normal;">(' . __('Optional', 'pakistani-payment-gateways') . ')</span>';
                $required_attr = $require_sender_details ? 'required' : '';
                
                echo '<p><label for="' . esc_attr($this->id) . '_sender_number">' . __('Your Account/Mobile Number', 'pakistani-payment-gateways') . $required_mark . '</label></p>';
                echo '<input type="text" id="' . esc_attr($this->id) . '_sender_number" name="' . esc_attr($this->id) . '_sender_number" class="input-text wc-payment-field" ' . $required_attr . ' placeholder="' . __('Account number you paid from', 'pakistani-payment-gateways') . '" style="width:100%;margin-bottom:10px;" />';
                
                echo '<p><label for="' . esc_attr($this->id) . '_sender_name">' . __('Your Account Title/Name', 'pakistani-payment-gateways') . $required_mark . '</label></p>';
                echo '<input type="text" id="' . esc_attr($this->id) . '_sender_name" name="' . esc_attr($this->id) . '_sender_name" class="input-text wc-payment-field" ' . $required_attr . ' placeholder="' . __('Name on your account', 'pakistani-payment-gateways') . '" style="width:100%;margin-bottom:10px;" />';
            }
            
            // Transaction ID Field
            if ($show_transaction_id) {
                $label = $this->is_bank_transfer ? __('Transaction Reference Number', 'pakistani-payment-gateways') : __('Transaction ID', 'pakistani-payment-gateways');
                $placeholder = $this->is_bank_transfer ? __('Enter transaction reference', 'pakistani-payment-gateways') : __('Enter your transaction ID', 'pakistani-payment-gateways');
                $required_mark = $require_transaction_id ? ' <span class="required">*</span>' : ' <span style="color:#999;font-weight:normal;">(' . __('Optional', 'pakistani-payment-gateways') . ')</span>';
                $required_attr = $require_transaction_id ? 'required' : '';
                
                echo '<p><label for="' . esc_attr($this->id) . '_txn_id">' . esc_html($label) . $required_mark . '</label></p>';
                echo '<input type="text" id="' . esc_attr($this->id) . '_txn_id" name="' . esc_attr($this->id) . '_txn_id" class="input-text wc-payment-field" ' . $required_attr . ' placeholder="' . esc_attr($placeholder) . '" style="width:100%;margin-bottom:10px;" />';
            }
            
            // Screenshot Upload Field - DISABLED (Use WhatsApp instead)
            // Customers can send screenshots via WhatsApp after placing the order
            /*
            if ($show_screenshot) {
                $required_mark = $require_screenshot ? ' <span class="required">*</span>' : ' <span style="color:#999;font-weight:normal;">(' . __('Optional', 'pakistani-payment-gateways') . ')</span>';
                $required_attr = $require_screenshot ? 'required' : '';
                $max_size = $this->get_option('max_screenshot_size', '5');
                
                echo '<p><label for="' . esc_attr($this->id) . '_screenshot">' . __('Payment Screenshot', 'pakistani-payment-gateways') . $required_mark . '</label></p>';
                echo '<input type="file" id="' . esc_attr($this->id) . '_screenshot" name="' . esc_attr($this->id) . '_screenshot" accept="image/*" class="input-file wc-payment-field" ' . $required_attr . ' style="width:100%;margin-bottom:5px;" />';
                echo '<small style="color:#666;">' . sprintf(__('Upload a screenshot of your payment confirmation (JPG, PNG, max %sMB). You can also send it via WhatsApp after placing the order.', 'pakistani-payment-gateways'), $max_size) . '</small>';
            }
            */
            
            // Show WhatsApp notice
            if ($this->get_option('enable_whatsapp', 'no') === 'yes') {
                echo '<div style="background:#e7f7ef;border:1px solid #25D366;border-radius:4px;padding:12px;margin:10px 0;">';
                echo '<p style="margin:0;color:#0a5d2c;font-size:13px;"><strong>' . __('Payment Screenshot:', 'pakistani-payment-gateways') . '</strong> ';
                echo __('You can send your payment screenshot via WhatsApp after placing the order.', 'pakistani-payment-gateways') . '</p>';
                echo '</div>';
            }
            
            // Custom Fields
            for ($i = 1; $i <= 3; $i++) {
                $field_label = $this->get_option('custom_field_' . $i . '_label', '');
                if (!empty($field_label)) {
                    $field_required = $this->get_option('custom_field_' . $i . '_required', 'no') === 'yes';
                    $required_mark = $field_required ? ' <span class="required">*</span>' : ' <span style="color:#999;font-weight:normal;">(' . __('Optional', 'pakistani-payment-gateways') . ')</span>';
                    $required_attr = $field_required ? 'required' : '';
                    
                    echo '<p><label for="' . esc_attr($this->id) . '_custom_field_' . $i . '">' . esc_html($field_label) . $required_mark . '</label></p>';
                    echo '<input type="text" id="' . esc_attr($this->id) . '_custom_field_' . $i . '" name="' . esc_attr($this->id) . '_custom_field_' . $i . '" class="input-text wc-payment-field" ' . $required_attr . ' style="width:100%;margin-bottom:10px;" />';
                }
            }
        }

        public function validate_fields() {
            $txn_field = $this->id . '_txn_id';
            $sender_number_field = $this->id . '_sender_number';
            $sender_name_field = $this->id . '_sender_name';
            
            // Get field settings
            $show_sender_details = $this->get_option('show_sender_details', 'yes') === 'yes';
            $require_sender_details = $this->get_option('require_sender_details', 'yes') === 'yes';
            $show_transaction_id = $this->get_option('show_transaction_id', 'yes') === 'yes';
            $require_transaction_id = $this->get_option('require_transaction_id', 'no') === 'yes';
            $show_screenshot = $this->get_option('show_screenshot_upload', 'yes') === 'yes';
            $require_screenshot = $this->get_option('require_screenshot', 'no') === 'yes';
            $max_size = intval($this->get_option('max_screenshot_size', '5'));
            
            // Validate sender details if shown and required
            if ($show_sender_details && $require_sender_details) {
                if (empty($_POST[$sender_number_field])) {
                    wc_add_notice(__('Please enter your account/mobile number that you paid from.', 'pakistani-payment-gateways'), 'error');
                    return false;
                }
                
                if (empty($_POST[$sender_name_field])) {
                    wc_add_notice(__('Please enter your account title/name.', 'pakistani-payment-gateways'), 'error');
                    return false;
                }
            }
            
            // Validate transaction ID if shown and required
            if ($show_transaction_id && $require_transaction_id) {
                if (empty($_POST[$txn_field])) {
                    wc_add_notice(sprintf(__('Please enter your %s Transaction ID.', 'pakistani-payment-gateways'), $this->gateway_title), 'error');
                    return false;
                }
            }
            
            // Validate transaction ID format if provided
            if ($show_transaction_id && !empty($_POST[$txn_field])) {
                $txn_id = sanitize_text_field($_POST[$txn_field]);
                if (strlen($txn_id) < 5) {
                    wc_add_notice(__('Transaction ID seems too short. Please verify and enter the correct ID or leave it empty.', 'pakistani-payment-gateways'), 'error');
                    return false;
                }
                
                // Check for duplicate transaction ID
                if (ppg_is_duplicate_transaction($txn_id, $this->id)) {
                    wc_add_notice(__('This transaction ID has already been used for another order. Please verify your transaction ID or contact support if you believe this is an error.', 'pakistani-payment-gateways'), 'error');
                    return false;
                }
            }
            
            // Screenshot validation removed - customers send via WhatsApp
            
            // Validate custom fields
            for ($i = 1; $i <= 3; $i++) {
                $field_label = $this->get_option('custom_field_' . $i . '_label', '');
                if (!empty($field_label)) {
                    $field_required = $this->get_option('custom_field_' . $i . '_required', 'no') === 'yes';
                    $field_name = $this->id . '_custom_field_' . $i;
                    
                    if ($field_required && empty($_POST[$field_name])) {
                        wc_add_notice(sprintf(__('Please enter %s.', 'pakistani-payment-gateways'), $field_label), 'error');
                        return false;
                    }
                }
            }
            
            return true;
        }

        public function is_available() {
            $is_available = parent::is_available();
            
            if (!$is_available) {
                return false;
            }
            
            // Check minimum amount
            if ($this->get_option('enable_min_amount', 'no') === 'yes') {
                $min_amount = floatval($this->get_option('min_amount', 0));
                if ($min_amount > 0 && WC()->cart && WC()->cart->get_total('') < $min_amount) {
                    return false;
                }
            }
            
            // Check maximum amount
            if ($this->get_option('enable_max_amount', 'no') === 'yes') {
                $max_amount = floatval($this->get_option('max_amount', 0));
                if ($max_amount > 0 && WC()->cart && WC()->cart->get_total('') > $max_amount) {
                    return false;
                }
            }
            
            return true;
        }

        public function get_icon() {
            $gateway_icon = $this->get_option('gateway_icon', '');
            if (!empty($gateway_icon)) {
                $icon_html = '<img src="' . esc_url($gateway_icon) . '" alt="' . esc_attr($this->get_title()) . '" style="max-height:24px;max-width:80px;" />';
                return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
            }
            return parent::get_icon();
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            
            if (!$order) {
                wc_add_notice(__('Order not found.', 'pakistani-payment-gateways'), 'error');
                return array('result' => 'fail');
            }

            $txn_field = $this->id . '_txn_id';
            $sender_number_field = $this->id . '_sender_number';
            $sender_name_field = $this->id . '_sender_name';
            
            $txn_id = isset($_POST[$txn_field]) ? sanitize_text_field($_POST[$txn_field]) : '';
            $sender_number = sanitize_text_field($_POST[$sender_number_field]);
            $sender_name = sanitize_text_field($_POST[$sender_name_field]);
            
            // Store transaction details
            if (!empty($txn_id)) {
                $order->update_meta_data('_' . $this->id . '_transaction_id', $txn_id);
            }
            $order->update_meta_data('_' . $this->id . '_sender_number', $sender_number);
            $order->update_meta_data('_' . $this->id . '_sender_name', $sender_name);
            $order->update_meta_data('_' . $this->id . '_payment_method', $this->gateway_title);
            
            // Store custom fields
            for ($i = 1; $i <= 3; $i++) {
                $field_label = $this->get_option('custom_field_' . $i . '_label', '');
                if (!empty($field_label)) {
                    $field_name = $this->id . '_custom_field_' . $i;
                    if (isset($_POST[$field_name])) {
                        $field_value = sanitize_text_field($_POST[$field_name]);
                        $order->update_meta_data('_' . $field_name, $field_value);
                        $order->update_meta_data('_' . $field_name . '_label', $field_label);
                    }
                }
            }
            
            // Screenshot upload removed - customers send via WhatsApp instead
            $screenshot_url = '';
            
            $order->save();

            // Update order status to pending payment (not on-hold yet, waiting for admin verification)
            $note_parts = array();
            if (!empty($txn_id)) {
                $note_parts[] = sprintf(__('Transaction ID: %s', 'pakistani-payment-gateways'), $txn_id);
            }
            $note_parts[] = sprintf(__('From: %s (%s)', 'pakistani-payment-gateways'), $sender_name, $sender_number);
            
            $order_note = sprintf(__('Payment submitted via %s. Awaiting admin verification. %s', 'pakistani-payment-gateways'), $this->gateway_title, implode(', ', $note_parts));
            
            $order->update_status(
                apply_filters('woocommerce_' . $this->id . '_process_payment_order_status', 'on-hold', $order),
                $order_note
            );

            // Send WhatsApp notification to admin IMMEDIATELY with screenshot
            $this->send_whatsapp_to_admin_immediately($order_id, $txn_id, $sender_name, $sender_number, $screenshot_url);

            // Reduce stock levels
            wc_reduce_stock_levels($order_id);

            // Remove cart
            WC()->cart->empty_cart();

            // Return success
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        /**
         * Send WhatsApp notification to admin immediately after order placement
         */
        private function send_whatsapp_to_admin_immediately($order_id, $txn_id, $sender_name, $sender_number, $screenshot_url) {
            $order = wc_get_order($order_id);
            if (!$order) {
                return;
            }
            
            // Check if WhatsApp is enabled
            if ($this->get_option('enable_whatsapp', 'no') !== 'yes') {
                return;
            }
            
            $admin_number = $this->get_option('whatsapp_admin_number', '');
            if (empty($admin_number)) {
                return;
            }
            
            // Get custom template or use default
            $template = $this->get_option('whatsapp_admin_template', '');
            if (empty($template)) {
                // Default template
                $template = "*NEW PAYMENT SUBMITTED*\n*REQUIRES VERIFICATION*\n\n━━━━━━━━━━━━━━━━━━━━\n*Order #{order_number}*\n*Amount:* {amount}\n*Method:* {payment_method}\n━━━━━━━━━━━━━━━━━━━━\n\n*PAYMENT DETAILS:*\nTransaction ID: {transaction_id}\nPaid From: *{sender_name}*\nAccount: {sender_number}\nScreenshot: {screenshot_url}\n\n*CUSTOMER INFO:*\nName: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}\nAddress: {customer_address}\nCity: {customer_city}\n\n━━━━━━━━━━━━━━━━━━━━\n*ORDER ITEMS:*\n{order_items}\n\n━━━━━━━━━━━━━━━━━━━━\n*ACTION REQUIRED:*\nVerify payment and approve order\n{admin_url}";
            }
            
            // Build order items list
            $order_items_text = '';
            foreach ($order->get_items() as $item) {
                $order_items_text .= '• ' . $item->get_name() . ' (x' . $item->get_quantity() . ')' . "\n";
            }
            $order_items_text = rtrim($order_items_text, "\n");
            
            // Build customer address
            $customer_address = $order->get_billing_address_1();
            if ($order->get_billing_address_2()) {
                $customer_address .= ', ' . $order->get_billing_address_2();
            }
            
            // Template variables
            $variables = array(
                '{order_number}' => $order->get_order_number(),
                '{amount}' => strip_tags($order->get_formatted_order_total()),
                '{payment_method}' => $this->gateway_title,
                '{transaction_id}' => $txn_id ? $txn_id : 'N/A',
                '{sender_name}' => $sender_name,
                '{sender_number}' => $sender_number,
                '{screenshot_url}' => $screenshot_url ? $screenshot_url : 'Not uploaded',
                '{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                '{customer_email}' => $order->get_billing_email(),
                '{customer_phone}' => $order->get_billing_phone(),
                '{customer_address}' => $customer_address,
                '{customer_city}' => $order->get_billing_city(),
                '{order_items}' => $order_items_text,
                '{admin_url}' => admin_url('post.php?post=' . $order_id . '&action=edit'),
                '{site_name}' => get_bloginfo('name'),
            );
            
            // Replace variables in template
            $message = str_replace(array_keys($variables), array_values($variables), $template);
            
            // Convert newlines to URL encoding
            $message = str_replace("\n", '%0A', $message);
            
            // Store WhatsApp URL
            $whatsapp_url = 'https://wa.me/' . $admin_number . '?text=' . $message;
            $order->update_meta_data('_ppg_whatsapp_admin_url', $whatsapp_url);
            $order->update_meta_data('_ppg_whatsapp_sent', current_time('mysql'));
            $order->save();
            
            // Add order note with WhatsApp link
            $note = sprintf(
                __('WhatsApp notification prepared. %sClick here to send to admin%s (Number: %s)', 'pakistani-payment-gateways'),
                '<a href="' . esc_url($whatsapp_url) . '" target="_blank" style="color:#25D366;font-weight:bold;">',
                '</a>',
                $admin_number
            );
            $order->add_order_note($note);
        }

        public function thank_you_page($order_id) {
            if ($this->instructions) {
                echo '<div class="woocommerce-order-overview woocommerce-thankyou-order-details">';
                echo '<h2>' . __('Payment Instructions', 'pakistani-payment-gateways') . '</h2>';
                echo '<p>' . wp_kses_post(nl2br($this->instructions)) . '</p>';
                
                $order = wc_get_order($order_id);
                $txn_id = $order->get_meta('_' . $this->id . '_transaction_id');
                $sender_number = $order->get_meta('_' . $this->id . '_sender_number');
                $sender_name = $order->get_meta('_' . $this->id . '_sender_name');
                $screenshot = $order->get_meta('_' . $this->id . '_screenshot');
                
                if ($sender_number) {
                    echo '<p><strong>' . __('Paid From Account:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($sender_number) . '</p>';
                }
                if ($sender_name) {
                    echo '<p><strong>' . __('Account Title:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($sender_name) . '</p>';
                }
                if ($txn_id) {
                    echo '<p><strong>' . __('Transaction ID:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($txn_id) . '</p>';
                }
                if ($screenshot) {
                    echo '<p><strong>' . __('Payment Screenshot:', 'pakistani-payment-gateways') . '</strong> ' . __('Uploaded', 'pakistani-payment-gateways') . '</p>';
                }
                echo '</div>';
            }
        }

        public function email_instructions($order, $sent_to_admin, $plain_text = false) {
            if (!$sent_to_admin && $this->id === $order->get_payment_method() && $order->has_status('on-hold')) {
                if ($this->instructions) {
                    echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
                }
            }
        }
    }

    /**
     * Easypaisa Payment Gateway
     */
    class WC_Gateway_Easypaisa extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'easypaisa';
            $this->gateway_id = 'easypaisa';
            $this->gateway_title = 'Easypaisa';
            $this->gateway_description = 'Easypaisa manual payment using transaction ID';
            $this->method_title = __('Easypaisa', 'pakistani-payment-gateways');
            $this->method_description = __('Accept manual Easypaisa payments with transaction ID verification.', 'pakistani-payment-gateways');
            $this->number_field_name = 'easypaisa_number';
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * JazzCash Payment Gateway
     */
    class WC_Gateway_Jazzcash extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'jazzcash';
            $this->gateway_id = 'jazzcash';
            $this->gateway_title = 'JazzCash';
            $this->gateway_description = 'JazzCash manual payment using transaction ID';
            $this->method_title = __('JazzCash', 'pakistani-payment-gateways');
            $this->method_description = __('Accept manual JazzCash payments with transaction ID verification.', 'pakistani-payment-gateways');
            $this->number_field_name = 'jazzcash_number';
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * Bank Transfer Payment Gateway (Generic)
     */
    class WC_Gateway_Bank_Transfer_PK extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'bank_transfer_pk';
            $this->gateway_id = 'bank_transfer_pk';
            $this->gateway_title = 'Bank Transfer';
            $this->gateway_description = 'Direct bank transfer payment';
            $this->method_title = __('Bank Transfer (Pakistan)', 'pakistani-payment-gateways');
            $this->method_description = __('Accept direct bank transfer payments from any Pakistani bank.', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * HBL (Habib Bank Limited) Payment Gateway
     */
    class WC_Gateway_HBL extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'hbl';
            $this->gateway_id = 'hbl';
            $this->gateway_title = 'HBL';
            $this->gateway_description = 'Habib Bank Limited payment';
            $this->method_title = __('HBL (Habib Bank Limited)', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via HBL bank transfer.', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * UBL (United Bank Limited) Payment Gateway
     */
    class WC_Gateway_UBL extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'ubl';
            $this->gateway_id = 'ubl';
            $this->gateway_title = 'UBL';
            $this->gateway_description = 'United Bank Limited payment';
            $this->method_title = __('UBL (United Bank Limited)', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via UBL bank transfer.', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * Meezan Bank Payment Gateway
     */
    class WC_Gateway_Meezan extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'meezan';
            $this->gateway_id = 'meezan';
            $this->gateway_title = 'Meezan Bank';
            $this->gateway_description = 'Meezan Bank Islamic banking payment';
            $this->method_title = __('Meezan Bank', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via Meezan Bank transfer (Islamic Banking).', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * Allied Bank Payment Gateway
     */
    class WC_Gateway_Allied extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'allied';
            $this->gateway_id = 'allied';
            $this->gateway_title = 'Allied Bank';
            $this->gateway_description = 'Allied Bank Limited payment';
            $this->method_title = __('Allied Bank Limited', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via Allied Bank transfer.', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * MCB (Muslim Commercial Bank) Payment Gateway
     */
    class WC_Gateway_MCB extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'mcb';
            $this->gateway_id = 'mcb';
            $this->gateway_title = 'MCB';
            $this->gateway_description = 'Muslim Commercial Bank payment';
            $this->method_title = __('MCB (Muslim Commercial Bank)', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via MCB bank transfer.', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * SadaPay Payment Gateway
     */
    class WC_Gateway_SadaPay extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'sadapay';
            $this->gateway_id = 'sadapay';
            $this->gateway_title = 'SadaPay';
            $this->gateway_description = 'SadaPay digital wallet payment';
            $this->method_title = __('SadaPay', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via SadaPay digital wallet.', 'pakistani-payment-gateways');
            $this->number_field_name = 'sadapay_number';
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * NayaPay Payment Gateway
     */
    class WC_Gateway_NayaPay extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'nayapay';
            $this->gateway_id = 'nayapay';
            $this->gateway_title = 'NayaPay';
            $this->gateway_description = 'NayaPay digital wallet payment';
            $this->method_title = __('NayaPay', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via NayaPay digital wallet.', 'pakistani-payment-gateways');
            $this->number_field_name = 'nayapay_number';
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * Zindigi by JS Bank Payment Gateway
     */
    class WC_Gateway_Zindigi extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'zindigi';
            $this->gateway_id = 'zindigi';
            $this->gateway_title = 'Zindigi by JS Bank';
            $this->gateway_description = 'Zindigi digital banking payment';
            $this->method_title = __('Zindigi by JS Bank', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via Zindigi digital banking app.', 'pakistani-payment-gateways');
            $this->number_field_name = 'zindigi_number';
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * Bank Alfalah Payment Gateway
     */
    class WC_Gateway_BankAlfalah extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'bankalfalah';
            $this->gateway_id = 'bankalfalah';
            $this->gateway_title = 'Bank Alfalah';
            $this->gateway_description = 'Bank Alfalah payment';
            $this->method_title = __('Bank Alfalah', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via Bank Alfalah transfer.', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * Faysal Bank Payment Gateway
     */
    class WC_Gateway_Faysal extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'faysal';
            $this->gateway_id = 'faysal';
            $this->gateway_title = 'Faysal Bank';
            $this->gateway_description = 'Faysal Bank payment';
            $this->method_title = __('Faysal Bank', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via Faysal Bank transfer.', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }

    /**
     * Standard Chartered Bank Payment Gateway
     */
    class WC_Gateway_Standard extends WC_Gateway_Pakistani_Base {

        public function __construct() {
            $this->id = 'standard';
            $this->gateway_id = 'standard';
            $this->gateway_title = 'Standard Chartered';
            $this->gateway_description = 'Standard Chartered Bank payment';
            $this->method_title = __('Standard Chartered Bank', 'pakistani-payment-gateways');
            $this->method_description = __('Accept payments via Standard Chartered Bank transfer.', 'pakistani-payment-gateways');
            $this->is_bank_transfer = true;
            $this->logo_url = '';

            parent::__construct();
        }
    }
}

/**
 * Add custom column to orders list
 */
add_filter('manage_edit-shop_order_columns', 'ppg_add_order_transaction_column', 20);
function ppg_add_order_transaction_column($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        if ($key === 'order_status') {
            $new_columns['transaction_id'] = __('Transaction ID', 'pakistani-payment-gateways');
        }
    }
    
    return $new_columns;
}

add_action('manage_shop_order_posts_custom_column', 'ppg_display_order_transaction_column', 20, 2);
function ppg_display_order_transaction_column($column, $post_id) {
    if ($column === 'transaction_id') {
        $order = wc_get_order($post_id);
        $payment_method = $order->get_payment_method();
        
        $supported_methods = array(
            'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
            'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
            'faysal', 'standard'
        );
        
        if (in_array($payment_method, $supported_methods)) {
            $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
            if ($txn_id) {
                echo '<span style="color:#2271b1;font-weight:600;">' . esc_html($txn_id) . '</span>';
            } else {
                echo '—';
            }
        } else {
            echo '—';
        }
    }
}

/**
 * Display transaction ID in order details
 */
add_action('woocommerce_admin_order_data_after_billing_address', 'ppg_display_transaction_id_in_admin');
function ppg_display_transaction_id_in_admin($order) {
    $payment_method = $order->get_payment_method();
    
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    if (in_array($payment_method, $supported_methods)) {
        $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
        $sender_number = $order->get_meta('_' . $payment_method . '_sender_number');
        $sender_name = $order->get_meta('_' . $payment_method . '_sender_name');
        $screenshot = $order->get_meta('_' . $payment_method . '_screenshot');
        $payment_title = $order->get_meta('_' . $payment_method . '_payment_method');
        
        if ($sender_number || $sender_name || $txn_id || $screenshot) {
            echo '<div class="order_data_column" style="clear:both; padding-top:13px;">';
            echo '<h3>' . sprintf(__('%s Payment Details', 'pakistani-payment-gateways'), esc_html($payment_title)) . '</h3>';
            
            if ($sender_number) {
                echo '<p><strong>' . __('Paid From Account:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($sender_number) . '</p>';
            }
            
            if ($sender_name) {
                echo '<p><strong>' . __('Account Title:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($sender_name) . '</p>';
            }
            
            if ($txn_id) {
                echo '<p><strong>' . __('Transaction ID:', 'pakistani-payment-gateways') . '</strong> ' . esc_html($txn_id) . '</p>';
            }
            
            // Display custom fields
            for ($i = 1; $i <= 3; $i++) {
                $field_value = $order->get_meta('_' . $payment_method . '_custom_field_' . $i);
                $field_label = $order->get_meta('_' . $payment_method . '_custom_field_' . $i . '_label');
                
                if (!empty($field_value) && !empty($field_label)) {
                    echo '<p><strong>' . esc_html($field_label) . ':</strong> ' . esc_html($field_value) . '</p>';
                }
            }
            
            echo '</div>';
        }
    }
}

/**
 * Get gateway enabled status (matches WooCommerce's method)
 */
function ppg_is_gateway_enabled($gateway_id) {
    $settings = get_option('woocommerce_' . $gateway_id . '_settings', false);
    
    // If no settings exist, gateway is disabled
    if (false === $settings || !is_array($settings)) {
        return false;
    }
    
    // Check if enabled key exists and equals 'yes'
    return isset($settings['enabled']) && 'yes' === $settings['enabled'];
}


/**
 * Add payment gateway fees to cart
 */
add_action('woocommerce_cart_calculate_fees', 'ppg_add_payment_gateway_fee');
function ppg_add_payment_gateway_fee() {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    $chosen_gateway = WC()->session->get('chosen_payment_method');
    if (!$chosen_gateway) {
        return;
    }
    
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    if (!in_array($chosen_gateway, $supported_methods)) {
        return;
    }
    
    $settings = get_option('woocommerce_' . $chosen_gateway . '_settings', array());
    
    if (!isset($settings['enable_fee']) || $settings['enable_fee'] !== 'yes') {
        return;
    }
    
    $fee_type = isset($settings['fee_type']) ? $settings['fee_type'] : 'fixed';
    $fee_amount = isset($settings['fee_amount']) ? floatval($settings['fee_amount']) : 0;
    $fee_label = isset($settings['fee_label']) ? $settings['fee_label'] : __('Payment Processing Fee', 'pakistani-payment-gateways');
    
    if ($fee_amount <= 0) {
        return;
    }
    
    $cart_total = WC()->cart->get_subtotal();
    
    if ($fee_type === 'percentage') {
        $fee = ($cart_total * $fee_amount) / 100;
    } else {
        $fee = $fee_amount;
    }
    
    WC()->cart->add_fee($fee_label, $fee, true);
}

/**
 * Send WhatsApp notification to admin
 */
function ppg_send_whatsapp_notification($order_id, $gateway_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }

    $settings = get_option('woocommerce_' . $gateway_id . '_settings', array());

    if (!isset($settings['enable_whatsapp']) || $settings['enable_whatsapp'] !== 'yes') {
        return;
    }

    $admin_number = isset($settings['whatsapp_admin_number']) ? $settings['whatsapp_admin_number'] : '';
    if (empty($admin_number)) {
        return;
    }

    $payment_method = $order->get_payment_method();
    $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
    $sender_number = $order->get_meta('_' . $payment_method . '_sender_number');
    $sender_name = $order->get_meta('_' . $payment_method . '_sender_name');
    $payment_title = $order->get_meta('_' . $payment_method . '_payment_method');

    // Build WhatsApp message
    $message = '*New Payment Received*%0A%0A';
    $message .= '*Order:* #' . $order->get_order_number() . '%0A';
    $message .= '*Amount:* ' . strip_tags($order->get_formatted_order_total()) . '%0A';
    $message .= '*Payment Method:* ' . $payment_title . '%0A';
    if ($txn_id) {
        $message .= '*Transaction ID:* ' . $txn_id . '%0A';
    }
    $message .= '*Paid From:* ' . $sender_name . ' (' . $sender_number . ')%0A%0A';

    $message .= '*Customer Details:*%0A';
    $message .= 'Name: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '%0A';
    $message .= 'Email: ' . $order->get_billing_email() . '%0A';
    $message .= 'Phone: ' . $order->get_billing_phone() . '%0A';

    if ($order->get_billing_address_1()) {
        $message .= 'Address: ' . $order->get_billing_address_1();
        if ($order->get_billing_address_2()) {
            $message .= ', ' . $order->get_billing_address_2();
        }
        $message .= '%0A';
        $message .= 'City: ' . $order->get_billing_city() . '%0A';
    }

    $message .= '%0A*View Order:* ' . admin_url('post.php?post=' . $order_id . '&action=edit');

    // Store WhatsApp link in order meta for admin use
    $whatsapp_url = 'https://wa.me/' . $admin_number . '?text=' . $message;
    $order->update_meta_data('_ppg_whatsapp_admin_url', $whatsapp_url);
    $order->save();

    // Add admin note with WhatsApp link
    $note = sprintf(
        __('WhatsApp notification ready. %sClick here to send to admin%s', 'pakistani-payment-gateways'),
        '<a href="' . esc_url($whatsapp_url) . '" target="_blank" style="color:#25D366;font-weight:bold;">',
        '</a>'
    );
    $order->add_order_note($note);
}


/**
 * Add custom admin styles for WhatsApp button
 */
add_action('admin_head', 'ppg_admin_custom_styles');
function ppg_admin_custom_styles() {
    ?>
    <style>
        /* WhatsApp Notifications Box */
        .ppg-whatsapp-box {
            background: #fff;
            border: 1px solid #c3c4c7;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .ppg-whatsapp-header {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            padding: 15px 20px;
            border-bottom: 1px solid #128C7E;
        }
        
        .ppg-whatsapp-header h3 {
            color: #fff;
            margin: 0;
            font-size: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* WhatsApp Buttons */
        .button.ppg-whatsapp-btn {
            background: #25D366 !important;
            border-color: #25D366 !important;
            color: #fff !important;
            text-shadow: none !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
            transition: all 0.3s ease !important;
        }
        
        .button.ppg-whatsapp-btn:hover {
            background: #20BA5A !important;
            border-color: #20BA5A !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15) !important;
        }
        
        .button.ppg-whatsapp-btn:active {
            transform: translateY(0);
        }
        
        .button.ppg-thankyou-btn {
            background: #10b981 !important;
            border-color: #059669 !important;
            color: #fff !important;
            text-shadow: none !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
            transition: all 0.3s ease !important;
        }
        
        .button.ppg-thankyou-btn:hover {
            background: #059669 !important;
            border-color: #047857 !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 5px rgba(0,0,0,0.15) !important;
        }
        
        /* Screenshot hover effect */
        .ppg-screenshot-link {
            display: inline-block;
            border: 2px solid #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .ppg-screenshot-link:hover {
            border-color: #25D366;
            box-shadow: 0 2px 8px rgba(37, 211, 102, 0.2);
            transform: scale(1.02);
        }
        
        /* Notice boxes */
        .ppg-notice {
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
            display: flex;
            align-items: start;
            gap: 10px;
        }
        
        .ppg-notice-warning {
            background: #fffbeb;
            border: 1px solid #fde68a;
        }
        
        .ppg-notice-success {
            background: #f0f9ff;
            border: 1px solid #bfdbfe;
        }
        
        .ppg-notice-error {
            background: #fef2f2;
            border: 1px solid #fecaca;
        }
        
        /* Responsive */
        @media (max-width: 782px) {
            .ppg-whatsapp-header h3 {
                font-size: 14px;
            }
            
            .button.ppg-whatsapp-btn,
            .button.ppg-thankyou-btn {
                width: 100%;
                text-align: center;
                justify-content: center;
            }
        }
    </style>
    <?php
}

/**
 * Add WhatsApp notification button in admin order details
 */
add_action('woocommerce_admin_order_data_after_order_details', 'ppg_add_admin_whatsapp_button');
function ppg_add_admin_whatsapp_button($order) {
    $payment_method = $order->get_payment_method();
    
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    if (!in_array($payment_method, $supported_methods)) {
        return;
    }
    
    $settings = get_option('woocommerce_' . $payment_method . '_settings', array());
    
    if (!isset($settings['enable_whatsapp']) || $settings['enable_whatsapp'] !== 'yes') {
        return;
    }
    
    $admin_number = isset($settings['whatsapp_admin_number']) ? $settings['whatsapp_admin_number'] : '';
    if (empty($admin_number)) {
        return;
    }
    
    $whatsapp_url = $order->get_meta('_ppg_whatsapp_admin_url');
    
    if (empty($whatsapp_url)) {
        // Generate WhatsApp URL if not exists
        $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
        $sender_number = $order->get_meta('_' . $payment_method . '_sender_number');
        $sender_name = $order->get_meta('_' . $payment_method . '_sender_name');
        $payment_title = $order->get_meta('_' . $payment_method . '_payment_method');
        $screenshot = $order->get_meta('_' . $payment_method . '_screenshot');
        
        $message = '*Payment Details*%0A%0A';
        $message .= '*Order:* #' . $order->get_order_number() . '%0A';
        $message .= '*Amount:* ' . strip_tags($order->get_formatted_order_total()) . '%0A';
        $message .= '*Payment Method:* ' . $payment_title . '%0A';
        if ($txn_id) {
            $message .= '*Transaction ID:* ' . $txn_id . '%0A';
        }
        $message .= '*Paid From:* ' . $sender_name . ' (' . $sender_number . ')%0A';
        
        if ($screenshot) {
            $message .= '*Screenshot:* ' . $screenshot . '%0A';
        }
        $message .= '%0A';
        
        $message .= '*Customer:*%0A';
        $message .= 'Name: ' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '%0A';
        $message .= 'Email: ' . $order->get_billing_email() . '%0A';
        $message .= 'Phone: ' . $order->get_billing_phone() . '%0A';
        
        if ($order->get_billing_address_1()) {
            $message .= 'Address: ' . $order->get_billing_address_1();
            if ($order->get_billing_address_2()) {
                $message .= ', ' . $order->get_billing_address_2();
            }
            $message .= '%0A';
            $message .= 'City: ' . $order->get_billing_city() . '%0A';
        }
        
        $whatsapp_url = 'https://wa.me/' . $admin_number . '?text=' . $message;
    }
    
    // Generate Thank You WhatsApp message to customer
    $customer_phone = $order->get_billing_phone();
    // Clean phone number (remove spaces, dashes, etc.)
    $customer_phone_clean = preg_replace('/[^0-9]/', '', $customer_phone);
    
    // Get thank you template
    $thankyou_template = isset($settings['whatsapp_thankyou_template']) ? $settings['whatsapp_thankyou_template'] : '';
    
    if (empty($thankyou_template)) {
        // Default thank you template
        $thankyou_template = "*PAYMENT VERIFIED*\n\nDear {customer_name},\n\nGreat news! Your payment has been verified and confirmed.\n\n*Order Details:*\nOrder: #{order_number}\nAmount: {amount}\nMethod: {payment_method}\nTransaction: {transaction_id}\nDate: {order_date}\n\n*Items Ordered:*\n{order_items}\n\n*Delivery Address:*\n{customer_address}, {customer_city}\n\nYour order is now being processed and will be shipped soon. You will receive tracking details via email.\n\nThank you for shopping with {site_name}!\n\nIf you have any questions, feel free to reply to this message.";
    }
    
    // Build order items list
    $order_items_text = '';
    foreach ($order->get_items() as $item) {
        $order_items_text .= '• ' . $item->get_name() . ' (x' . $item->get_quantity() . ')' . "\n";
    }
    $order_items_text = rtrim($order_items_text, "\n");
    
    // Build customer address
    $customer_address = $order->get_billing_address_1();
    if ($order->get_billing_address_2()) {
        $customer_address .= ', ' . $order->get_billing_address_2();
    }
    
    $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
    $sender_name = $order->get_meta('_' . $payment_method . '_sender_name');
    $sender_number = $order->get_meta('_' . $payment_method . '_sender_number');
    $payment_title = $order->get_meta('_' . $payment_method . '_payment_method');
    $screenshot = $order->get_meta('_' . $payment_method . '_screenshot');
    
    // Template variables for thank you message
    $variables = array(
        '{order_number}' => $order->get_order_number(),
        '{amount}' => strip_tags($order->get_formatted_order_total()),
        '{payment_method}' => $payment_title,
        '{transaction_id}' => $txn_id ? $txn_id : 'N/A',
        '{sender_name}' => $sender_name,
        '{sender_number}' => $sender_number,
        '{screenshot_url}' => $screenshot ? $screenshot : 'Not uploaded',
        '{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        '{customer_email}' => $order->get_billing_email(),
        '{customer_phone}' => $order->get_billing_phone(),
        '{customer_address}' => $customer_address,
        '{customer_city}' => $order->get_billing_city(),
        '{order_items}' => $order_items_text,
        '{admin_url}' => admin_url('post.php?post=' . $order->get_id() . '&action=edit'),
        '{site_name}' => get_bloginfo('name'),
        '{order_date}' => $order->get_date_created()->date_i18n(wc_date_format()),
        '{billing_address}' => $customer_address,
        '{billing_city}' => $order->get_billing_city(),
        '{billing_phone}' => $order->get_billing_phone(),
        '{billing_email}' => $order->get_billing_email(),
    );
    
    // Replace variables in thank you template
    $thankyou_message = str_replace(array_keys($variables), array_values($variables), $thankyou_template);
    
    // Convert newlines to URL encoding
    $thankyou_message = str_replace("\n", '%0A', $thankyou_message);
    
    $thankyou_whatsapp_url = 'https://wa.me/' . $customer_phone_clean . '?text=' . $thankyou_message;
    
    // Get payment request template from settings
    $payment_request_template = isset($settings['whatsapp_payment_request_template']) ? $settings['whatsapp_payment_request_template'] : '';
    
    if (empty($payment_request_template)) {
        // Default payment request template
        $payment_request_template = "*PAYMENT REQUEST*\n\nDear {customer_name},\n\nThis is a friendly reminder about your pending order.\n\n*Order Details:*\nOrder: #{order_number}\nAmount: {amount}\nPayment Method: {payment_method}\nOrder Date: {order_date}\n\n*Items Ordered:*\n{order_items}\n\n*Payment Instructions:*\nPlease send payment to the account details provided and share:\n1. Transaction ID\n2. Payment screenshot\n3. Account you paid from\n\nOnce payment is received, we will process your order immediately.\n\nThank you for shopping with {site_name}!";
    }
    
    // Replace variables in payment request template
    $payment_request_message = str_replace(array_keys($variables), array_values($variables), $payment_request_template);
    
    // Convert newlines to URL encoding
    $payment_request_message = str_replace("\n", '%0A', $payment_request_message);
    
    $payment_request_whatsapp_url = 'https://wa.me/' . $customer_phone_clean . '?text=' . $payment_request_message;
    
    $screenshot = $order->get_meta('_' . $payment_method . '_screenshot');
    
    // Check if order is verified (processing or completed status)
    $is_verified = in_array($order->get_status(), array('processing', 'completed'));
    
    // Check if order is pending payment
    $is_pending = in_array($order->get_status(), array('pending', 'on-hold'));
    
    ?>
    <div class="order_data_column" style="width: 100%; clear: both; margin-top: 20px;">
        <div style="background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 20px;">
            
            <?php if ($is_pending && !empty($customer_phone_clean)): ?>
            <!-- Payment Request Button (for pending orders) -->
            <div style="margin-bottom: 15px;">
                <a href="<?php echo esc_url($payment_request_whatsapp_url); ?>" target="_blank" style="
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    max-width: 300px;
                    margin: 0 auto;
                    padding: 14px 20px;
                    background: #25D366;
                    color: #fff;
                    text-decoration: none;
                    border: none;
                    font-size: 14px;
                    font-weight: 500;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#20BA5A';" onmouseout="this.style.background='#25D366';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="margin-right: 10px; flex-shrink: 0;">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" fill="currentColor"/>
                    </svg>
                    <?php _e('Request Payment', 'pakistani-payment-gateways'); ?>
                </a>
            </div>
            <?php endif; ?>
            
            <?php if ($is_verified && !empty($customer_phone_clean)): ?>
            <!-- Customer WhatsApp Button -->
            <div style="margin-bottom: 15px;">
                <a href="<?php echo esc_url($thankyou_whatsapp_url); ?>" target="_blank" style="
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    max-width: 300px;
                    margin: 0 auto;
                    padding: 14px 20px;
                    background: #25D366;
                    color: #fff;
                    text-decoration: none;
                    border: none;
                    font-size: 14px;
                    font-weight: 500;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#20BA5A';" onmouseout="this.style.background='#25D366';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="margin-right: 10px; flex-shrink: 0;">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" fill="currentColor"/>
                    </svg>
                    <?php printf(__('Customer: %s', 'pakistani-payment-gateways'), esc_html($order->get_billing_phone())); ?>
                </a>
            </div>
            
            <!-- Admin WhatsApp Button -->
            <div>
                <a href="<?php echo esc_url($whatsapp_url); ?>" target="_blank" style="
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    max-width: 300px;
                    margin: 0 auto;
                    padding: 14px 20px;
                    background: #25D366;
                    color: #fff;
                    text-decoration: none;
                    border: none;
                    font-size: 14px;
                    font-weight: 500;
                    transition: background 0.2s;
                " onmouseover="this.style.background='#20BA5A';" onmouseout="this.style.background='#25D366';">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" style="margin-right: 10px; flex-shrink: 0;">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z" fill="currentColor"/>
                    </svg>
                    <?php printf(__('Admin: %s', 'pakistani-payment-gateways'), esc_html($admin_number)); ?>
                </a>
            </div>
            <?php else: ?>
            <!-- Not Verified Notice -->
            <div style="background: #fff3cd; border: 1px solid #ffc107; padding: 12px; text-align: center; max-width: 300px; margin: 0 auto;">
                <p style="color: #856404; font-size: 12px; margin: 0;">
                    <?php _e('Verify payment first', 'pakistani-payment-gateways'); ?>
                </p>
            </div>
            <?php endif; ?>
            
        </div>
    </div>
    <?php
}

/**
 * Check if transaction ID is duplicate
 */
function ppg_is_duplicate_transaction($txn_id, $gateway_id) {
    global $wpdb;
    
    $meta_key = '_' . $gateway_id . '_transaction_id';
    
    $existing = $wpdb->get_var($wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} 
        WHERE meta_key = %s AND meta_value = %s 
        LIMIT 1",
        $meta_key,
        $txn_id
    ));
    
    return !empty($existing);
}

/**
 * Send admin notification email for new payment
 */
function ppg_send_admin_notification($order_id, $gateway_title, $txn_id, $sender_name, $sender_number) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $admin_email = get_option('admin_email');
    $subject = sprintf(__('[%s] New Payment Received - Order #%s', 'pakistani-payment-gateways'), get_bloginfo('name'), $order->get_order_number());
    
    $message = sprintf(__('A new payment has been received via %s', 'pakistani-payment-gateways'), $gateway_title) . "\n\n";
    $message .= __('Order Details:', 'pakistani-payment-gateways') . "\n";
    $message .= sprintf(__('Order Number: #%s', 'pakistani-payment-gateways'), $order->get_order_number()) . "\n";
    $message .= sprintf(__('Order Total: %s', 'pakistani-payment-gateways'), $order->get_formatted_order_total()) . "\n";
    $message .= sprintf(__('Customer: %s', 'pakistani-payment-gateways'), $order->get_billing_first_name() . ' ' . $order->get_billing_last_name()) . "\n\n";
    
    $message .= __('Payment Details:', 'pakistani-payment-gateways') . "\n";
    $message .= sprintf(__('Payment Method: %s', 'pakistani-payment-gateways'), $gateway_title) . "\n";
    if ($txn_id) {
        $message .= sprintf(__('Transaction ID: %s', 'pakistani-payment-gateways'), $txn_id) . "\n";
    }
    $message .= sprintf(__('Paid From: %s (%s)', 'pakistani-payment-gateways'), $sender_name, $sender_number) . "\n\n";
    
    $message .= sprintf(__('View Order: %s', 'pakistani-payment-gateways'), admin_url('post.php?post=' . $order_id . '&action=edit')) . "\n";
    
    wp_mail($admin_email, $subject, $message);
}

/**
 * Enhanced thank you page with payment confirmation
 */
add_action('woocommerce_thankyou', 'ppg_enhanced_thank_you_page', 5);
function ppg_enhanced_thank_you_page($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $payment_method = $order->get_payment_method();
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    if (!in_array($payment_method, $supported_methods)) {
        return;
    }
    
    $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
    $sender_number = $order->get_meta('_' . $payment_method . '_sender_number');
    $sender_name = $order->get_meta('_' . $payment_method . '_sender_name');
    $screenshot = $order->get_meta('_' . $payment_method . '_screenshot');
    $payment_title = $order->get_meta('_' . $payment_method . '_payment_method');
    
    ?>
    <div class="ppg-thank-you-box" style="background: #fff3cd; border: 2px solid #ffc107; border-radius: 8px; padding: 25px; margin: 20px 0;">
        <h2 style="color: #856404; margin-top: 0;">
            <span style="font-size: 24px;">⏳</span> <?php _e('Payment Submitted - Awaiting Verification', 'pakistani-payment-gateways'); ?>
        </h2>
        
        <p style="font-size: 16px; margin: 15px 0;">
            <?php _e('Thank you for submitting your payment details. Your order is currently pending admin verification.', 'pakistani-payment-gateways'); ?>
        </p>
        
        <div style="background: white; padding: 20px; border-radius: 5px; margin: 20px 0;">
            <h3 style="margin-top: 0; color: #333;"><?php _e('Payment Summary', 'pakistani-payment-gateways'); ?></h3>
            
            <table style="width: 100%; border-collapse: collapse;">
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px 0; font-weight: 600;"><?php _e('Order Number:', 'pakistani-payment-gateways'); ?></td>
                    <td style="padding: 10px 0;">#<?php echo $order->get_order_number(); ?></td>
                </tr>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px 0; font-weight: 600;"><?php _e('Payment Method:', 'pakistani-payment-gateways'); ?></td>
                    <td style="padding: 10px 0;"><?php echo esc_html($payment_title); ?></td>
                </tr>
                <?php if ($sender_number): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px 0; font-weight: 600;"><?php _e('Paid From:', 'pakistani-payment-gateways'); ?></td>
                    <td style="padding: 10px 0;"><?php echo esc_html($sender_number); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($sender_name): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px 0; font-weight: 600;"><?php _e('Account Title:', 'pakistani-payment-gateways'); ?></td>
                    <td style="padding: 10px 0;"><?php echo esc_html($sender_name); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($txn_id): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 10px 0; font-weight: 600;"><?php _e('Transaction ID:', 'pakistani-payment-gateways'); ?></td>
                    <td style="padding: 10px 0; font-family: monospace; color: #2271b1;"><?php echo esc_html($txn_id); ?></td>
                </tr>
                <?php endif; ?>
                <?php if ($screenshot): ?>
                <tr>
                    <td style="padding: 10px 0; font-weight: 600;"><?php _e('Screenshot:', 'pakistani-payment-gateways'); ?></td>
                    <td style="padding: 10px 0; color: #00a32a;">✓ <?php _e('Uploaded', 'pakistani-payment-gateways'); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div style="background: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid #dc3545; margin-bottom: 20px;">
            <h4 style="margin-top: 0; color: #721c24;"><?php _e('Important Notice', 'pakistani-payment-gateways'); ?></h4>
            <p style="margin: 10px 0; color: #721c24;">
                <?php _e('Your payment is NOT yet verified. Admin will manually verify your payment details and approve your order.', 'pakistani-payment-gateways'); ?>
            </p>
        </div>
        
        <div style="background: #d1ecf1; padding: 15px; border-radius: 5px; border-left: 4px solid #0c5460;">
            <h4 style="margin-top: 0; color: #0c5460;"><?php _e('What happens next?', 'pakistani-payment-gateways'); ?></h4>
            <ol style="margin: 10px 0; padding-left: 20px; color: #0c5460;">
                <li><?php _e('Admin will receive your payment details via WhatsApp', 'pakistani-payment-gateways'); ?></li>
                <li><?php _e('Admin will verify your transaction ID and payment screenshot', 'pakistani-payment-gateways'); ?></li>
                <li><?php _e('Once verified, you will receive a confirmation email', 'pakistani-payment-gateways'); ?></li>
                <li><?php _e('Your order will then be processed and shipped', 'pakistani-payment-gateways'); ?></li>
                <li><?php _e('Verification usually takes 1-24 hours', 'pakistani-payment-gateways'); ?></li>
            </ol>
        </div>
        
        <?php
        // Get WhatsApp URL from order meta or generate from customer template
        $whatsapp_url = $order->get_meta('_ppg_whatsapp_admin_url');
        
        // Check if customer WhatsApp is enabled
        $settings = get_option('woocommerce_' . $payment_method . '_settings', array());
        $show_customer_whatsapp = isset($settings['whatsapp_customer_message']) && $settings['whatsapp_customer_message'] === 'yes';
        $admin_number = isset($settings['whatsapp_admin_number']) ? $settings['whatsapp_admin_number'] : '';
        
        if ($show_customer_whatsapp && !empty($admin_number)):
            // Get customer template
            $customer_template = isset($settings['whatsapp_customer_template']) ? $settings['whatsapp_customer_template'] : '';
            
            if (empty($customer_template)) {
                // Default customer template
                $customer_template = "Hello, I have submitted payment for my order.\n\n*Order Details:*\nOrder Number: #{order_number}\nAmount: {amount}\nPayment Method: {payment_method}\nTransaction ID: {transaction_id}\nPaid From: {sender_name} ({sender_number})\n\n*My Details:*\nName: {customer_name}\nEmail: {customer_email}\nPhone: {customer_phone}\n\nPlease verify my payment. Thank you!";
            }
            
            // Build order items list
            $order_items_text = '';
            foreach ($order->get_items() as $item) {
                $order_items_text .= '• ' . $item->get_name() . ' (x' . $item->get_quantity() . ')' . "\n";
            }
            $order_items_text = rtrim($order_items_text, "\n");
            
            // Build customer address
            $customer_address = $order->get_billing_address_1();
            if ($order->get_billing_address_2()) {
                $customer_address .= ', ' . $order->get_billing_address_2();
            }
            
            // Template variables
            $variables = array(
                '{order_number}' => $order->get_order_number(),
                '{amount}' => strip_tags($order->get_formatted_order_total()),
                '{payment_method}' => $payment_title,
                '{transaction_id}' => $txn_id ? $txn_id : 'N/A',
                '{sender_name}' => $sender_name,
                '{sender_number}' => $sender_number,
                '{screenshot_url}' => $screenshot ? $screenshot : 'Not uploaded',
                '{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                '{customer_email}' => $order->get_billing_email(),
                '{customer_phone}' => $order->get_billing_phone(),
                '{customer_address}' => $customer_address,
                '{customer_city}' => $order->get_billing_city(),
                '{order_items}' => $order_items_text,
                '{admin_url}' => admin_url('post.php?post=' . $order_id . '&action=edit'),
                '{site_name}' => get_bloginfo('name'),
            );
            
            // Replace variables in template
            $customer_message = str_replace(array_keys($variables), array_values($variables), $customer_template);
            
            // Convert newlines to URL encoding
            $customer_message = str_replace("\n", '%0A', $customer_message);
            
            $customer_whatsapp_url = 'https://wa.me/' . $admin_number . '?text=' . $customer_message;
        ?>
        <div style="background: #25D366; color: white; padding: 20px; border-radius: 8px; margin: 20px 0; text-align: center;">
            <h3 style="color: white; margin-top: 0;">
                <?php _e('Send Payment Details to Admin', 'pakistani-payment-gateways'); ?>
            </h3>
            <p style="color: white; margin: 15px 0;">
                <?php _e('Click the button below to send your payment details directly to admin via WhatsApp for faster verification!', 'pakistani-payment-gateways'); ?>
            </p>
            <a href="<?php echo esc_url($customer_whatsapp_url); ?>" target="_blank" class="button" style="background: white; color: #25D366; border: none; padding: 15px 40px; font-size: 18px; font-weight: bold; text-decoration: none; display: inline-block; border-radius: 5px; margin: 10px 0;">
                <?php _e('Send WhatsApp Message Now', 'pakistani-payment-gateways'); ?>
            </a>
            <p style="color: white; margin: 10px 0; font-size: 14px;">
                <?php _e('This will open WhatsApp with your payment details pre-filled', 'pakistani-payment-gateways'); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <p style="text-align: center; margin: 20px 0 0 0;">
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="button" style="margin-right: 10px;">
                <?php _e('Continue Shopping', 'pakistani-payment-gateways'); ?>
            </a>
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="button">
                <?php _e('View My Orders', 'pakistani-payment-gateways'); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Send custom email template to customer (Payment Pending)
 */
function ppg_send_custom_email($order_id, $gateway_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    // Get gateway settings
    $settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
    
    // Check if custom email is enabled
    if (!isset($settings['enable_custom_email']) || $settings['enable_custom_email'] !== 'yes') {
        return;
    }
    
    // Get pending email template settings
    $subject = isset($settings['pending_email_subject']) ? $settings['pending_email_subject'] : __('Payment Submitted - Order {order_number} Awaiting Verification', 'pakistani-payment-gateways');
    $heading = isset($settings['pending_email_heading']) ? $settings['pending_email_heading'] : __('Payment Submitted - Awaiting Verification', 'pakistani-payment-gateways');
    $body = isset($settings['pending_email_body']) ? $settings['pending_email_body'] : '';
    
    // Get order data
    $payment_method = $order->get_payment_method();
    $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
    $sender_name = $order->get_meta('_' . $payment_method . '_sender_name');
    $sender_number = $order->get_meta('_' . $payment_method . '_sender_number');
    $payment_title = $order->get_meta('_' . $payment_method . '_payment_method');
    
    // Build order items list
    $order_items_html = '<ul>';
    foreach ($order->get_items() as $item) {
        $order_items_html .= '<li>' . $item->get_name() . ' (x' . $item->get_quantity() . ') - ' . wc_price($item->get_total()) . '</li>';
    }
    $order_items_html .= '</ul>';
    
    // Template variables
    $variables = array(
        '{order_number}' => $order->get_order_number(),
        '{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        '{order_total}' => $order->get_formatted_order_total(),
        '{payment_method}' => $payment_title,
        '{transaction_id}' => $txn_id ? $txn_id : __('N/A', 'pakistani-payment-gateways'),
        '{sender_name}' => $sender_name,
        '{sender_number}' => $sender_number,
        '{site_name}' => get_bloginfo('name'),
        '{order_date}' => $order->get_date_created()->date_i18n(wc_date_format()),
        '{billing_address}' => $order->get_billing_address_1() . ($order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : ''),
        '{billing_city}' => $order->get_billing_city(),
        '{billing_phone}' => $order->get_billing_phone(),
        '{billing_email}' => $order->get_billing_email(),
        '{order_items}' => $order_items_html,
    );
    
    // Replace variables in subject, heading, and body
    $subject = str_replace(array_keys($variables), array_values($variables), $subject);
    $heading = str_replace(array_keys($variables), array_values($variables), $heading);
    $body = str_replace(array_keys($variables), array_values($variables), $body);
    
    // Build HTML email
    $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
    $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;">';
    $message .= '<div style="background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
    $message .= '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; border-left: 4px solid #ffc107;">';
    $message .= '<h2 style="color: #856404; margin: 0;">' . esc_html($heading) . '</h2>';
    $message .= '</div>';
    $message .= '<div style="margin: 20px 0;">' . wpautop($body) . '</div>';
    $message .= '<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">';
    $message .= '<p style="font-size: 12px; color: #999; text-align: center;">This is an automated email from ' . get_bloginfo('name') . '</p>';
    $message .= '</div></div></body></html>';
    
    // Send email
    $customer_email = $order->get_billing_email();
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    wp_mail($customer_email, $subject, $message, $headers);
}

/**
 * Send payment verified email to customer (Payment Approved)
 */
function ppg_send_payment_verified_email($order_id, $gateway_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    // Get gateway settings
    $settings = get_option('woocommerce_' . $gateway_id . '_settings', array());
    
    // Check if custom email is enabled
    if (!isset($settings['enable_custom_email']) || $settings['enable_custom_email'] !== 'yes') {
        return;
    }
    
    // Get approved email template settings
    $subject = isset($settings['approved_email_subject']) ? $settings['approved_email_subject'] : __('Payment Verified - Order {order_number} Confirmed', 'pakistani-payment-gateways');
    $heading = isset($settings['approved_email_heading']) ? $settings['approved_email_heading'] : __('Payment Verified - Your Order is Confirmed!', 'pakistani-payment-gateways');
    $body = isset($settings['approved_email_body']) ? $settings['approved_email_body'] : '';
    
    // Get order data
    $payment_method = $order->get_payment_method();
    $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
    $sender_name = $order->get_meta('_' . $payment_method . '_sender_name');
    $sender_number = $order->get_meta('_' . $payment_method . '_sender_number');
    $payment_title = $order->get_meta('_' . $payment_method . '_payment_method');
    
    // Build order items list
    $order_items_html = '<ul>';
    foreach ($order->get_items() as $item) {
        $order_items_html .= '<li>' . $item->get_name() . ' (x' . $item->get_quantity() . ') - ' . wc_price($item->get_total()) . '</li>';
    }
    $order_items_html .= '</ul>';
    
    // Template variables
    $variables = array(
        '{order_number}' => $order->get_order_number(),
        '{customer_name}' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        '{order_total}' => $order->get_formatted_order_total(),
        '{payment_method}' => $payment_title,
        '{transaction_id}' => $txn_id ? $txn_id : __('N/A', 'pakistani-payment-gateways'),
        '{sender_name}' => $sender_name,
        '{sender_number}' => $sender_number,
        '{site_name}' => get_bloginfo('name'),
        '{order_date}' => $order->get_date_created()->date_i18n(wc_date_format()),
        '{billing_address}' => $order->get_billing_address_1() . ($order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : ''),
        '{billing_city}' => $order->get_billing_city(),
        '{billing_phone}' => $order->get_billing_phone(),
        '{billing_email}' => $order->get_billing_email(),
        '{order_items}' => $order_items_html,
    );
    
    // Replace variables
    $subject = str_replace(array_keys($variables), array_values($variables), $subject);
    $heading = str_replace(array_keys($variables), array_values($variables), $heading);
    $body = str_replace(array_keys($variables), array_values($variables), $body);
    
    // Build HTML email
    $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
    $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;">';
    $message .= '<div style="background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
    $message .= '<div style="background: #d4edda; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center; border-left: 4px solid #28a745;">';
    $message .= '<span style="font-size: 48px; color: #155724;">✓</span>';
    $message .= '<h2 style="color: #155724; margin: 10px 0 0 0;">' . esc_html($heading) . '</h2>';
    $message .= '</div>';
    $message .= '<div style="margin: 20px 0;">' . wpautop($body) . '</div>';
    $message .= '<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">';
    $message .= '<p style="font-size: 12px; color: #999; text-align: center;">This is an automated email from ' . get_bloginfo('name') . '</p>';
    $message .= '</div></div></body></html>';
    
    // Send email
    $customer_email = $order->get_billing_email();
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    wp_mail($customer_email, $subject, $message, $headers);
}

/**
 * Auto-complete orders after payment verification
 */
add_action('woocommerce_order_status_on-hold_to_processing', 'ppg_auto_complete_order', 20, 1);
function ppg_auto_complete_order($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $payment_method = $order->get_payment_method();
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    if (!in_array($payment_method, $supported_methods)) {
        return;
    }
    
    $settings = get_option('woocommerce_' . $payment_method . '_settings', array());
    
    if (isset($settings['auto_complete']) && $settings['auto_complete'] === 'yes') {
        $order->update_status('completed', __('Order auto-completed after payment verification.', 'pakistani-payment-gateways'));
    }
}

/**
 * Schedule payment reminder check
 */
add_action('wp', 'ppg_schedule_payment_reminders');
function ppg_schedule_payment_reminders() {
    if (!wp_next_scheduled('ppg_check_pending_payments')) {
        wp_schedule_event(time(), 'hourly', 'ppg_check_pending_payments');
    }
}

/**
 * Check and send payment reminders
 */
add_action('ppg_check_pending_payments', 'ppg_send_payment_reminders');
function ppg_send_payment_reminders() {
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    // Get all on-hold orders
    $args = array(
        'status' => 'on-hold',
        'limit' => -1,
        'payment_method' => $supported_methods,
    );
    $orders = wc_get_orders($args);
    
    foreach ($orders as $order) {
        $payment_method = $order->get_payment_method();
        $settings = get_option('woocommerce_' . $payment_method . '_settings', array());
        
        // Check if reminders are enabled
        if (!isset($settings['enable_reminders']) || $settings['enable_reminders'] !== 'yes') {
            continue;
        }
        
        // Check if reminder already sent
        if ($order->get_meta('_ppg_reminder_sent')) {
            continue;
        }
        
        $reminder_hours = isset($settings['reminder_hours']) ? intval($settings['reminder_hours']) : 24;
        $order_date = $order->get_date_created();
        $hours_passed = (time() - $order_date->getTimestamp()) / 3600;
        
        if ($hours_passed >= $reminder_hours) {
            ppg_send_reminder_email($order->get_id(), $payment_method);
            $order->update_meta_data('_ppg_reminder_sent', 'yes');
            $order->save();
        }
    }
}

/**
 * Send payment reminder email
 */
function ppg_send_reminder_email($order_id, $gateway_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $payment_title = $order->get_meta('_' . $gateway_id . '_payment_method');
    
    $subject = sprintf(__('[%s] Payment Reminder - Order #%s', 'pakistani-payment-gateways'), get_bloginfo('name'), $order->get_order_number());
    
    $message = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">';
    $message .= '<div style="max-width: 600px; margin: 0 auto; padding: 20px; background: #f9f9f9;">';
    $message .= '<div style="background: white; padding: 30px; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">';
    $message .= '<div style="background: #fff3cd; padding: 15px; border-radius: 5px; margin-bottom: 20px; text-align: center;">';
    $message .= '<span style="font-size: 48px;">⏰</span>';
    $message .= '</div>';
    $message .= '<h2 style="color: #856404; margin-top: 0; text-align: center;">' . __('Payment Reminder', 'pakistani-payment-gateways') . '</h2>';
    $message .= '<p>' . sprintf(__('Dear %s,', 'pakistani-payment-gateways'), $order->get_billing_first_name()) . '</p>';
    $message .= '<p>' . sprintf(__('This is a friendly reminder that your order #%s is awaiting payment verification.', 'pakistani-payment-gateways'), $order->get_order_number()) . '</p>';
    $message .= '<div style="background: #f7f7f7; padding: 15px; border-radius: 5px; margin: 20px 0;">';
    $message .= '<p style="margin: 5px 0;"><strong>' . __('Order Number:', 'pakistani-payment-gateways') . '</strong> #' . $order->get_order_number() . '</p>';
    $message .= '<p style="margin: 5px 0;"><strong>' . __('Order Total:', 'pakistani-payment-gateways') . '</strong> ' . $order->get_formatted_order_total() . '</p>';
    $message .= '<p style="margin: 5px 0;"><strong>' . __('Payment Method:', 'pakistani-payment-gateways') . '</strong> ' . $payment_title . '</p>';
    $message .= '</div>';
    $message .= '<p>' . __('If you have already made the payment, please ignore this reminder. Your payment will be verified shortly.', 'pakistani-payment-gateways') . '</p>';
    $message .= '<p>' . __('If you have not completed the payment yet, please do so at your earliest convenience.', 'pakistani-payment-gateways') . '</p>';
    $message .= '<p style="text-align: center; margin: 30px 0;">';
    $message .= '<a href="' . esc_url($order->get_view_order_url()) . '" style="background: #2271b1; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;">' . __('View Order', 'pakistani-payment-gateways') . '</a>';
    $message .= '</p>';
    $message .= '<hr style="border: none; border-top: 1px solid #eee; margin: 30px 0;">';
    $message .= '<p style="font-size: 12px; color: #999; text-align: center;">' . __('This is an automated reminder from', 'pakistani-payment-gateways') . ' ' . get_bloginfo('name') . '</p>';
    $message .= '</div></div></body></html>';
    
    $customer_email = $order->get_billing_email();
    $headers = array('Content-Type: text/html; charset=UTF-8');
    
    wp_mail($customer_email, $subject, $message, $headers);
}

/**
 * Track failed/abandoned payments
 */
add_action('woocommerce_order_status_cancelled', 'ppg_track_failed_payment', 10, 1);
add_action('woocommerce_order_status_failed', 'ppg_track_failed_payment', 10, 1);
function ppg_track_failed_payment($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $payment_method = $order->get_payment_method();
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    if (!in_array($payment_method, $supported_methods)) {
        return;
    }
    
    // Store failed payment data
    $failed_payments = get_option('ppg_failed_payments', array());
    
    $failed_payments[] = array(
        'order_id' => $order_id,
        'order_number' => $order->get_order_number(),
        'payment_method' => $payment_method,
        'amount' => $order->get_total(),
        'customer_email' => $order->get_billing_email(),
        'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
        'date' => current_time('mysql'),
        'status' => $order->get_status(),
    );
    
    // Keep only last 100 failed payments
    if (count($failed_payments) > 100) {
        $failed_payments = array_slice($failed_payments, -100);
    }
    
    update_option('ppg_failed_payments', $failed_payments);
}

/**
 * Add failed payments report page
 */
add_action('admin_menu', 'ppg_add_failed_payments_page');
function ppg_add_failed_payments_page() {
    add_submenu_page(
        'woocommerce',
        __('Failed Payments', 'pakistani-payment-gateways'),
        __('PK Failed Payments', 'pakistani-payment-gateways'),
        'manage_woocommerce',
        'ppg-failed-payments',
        'ppg_render_failed_payments_page'
    );
}

function ppg_render_failed_payments_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'pakistani-payment-gateways'));
    }
    
    $failed_payments = get_option('ppg_failed_payments', array());
    $failed_payments = array_reverse($failed_payments); // Show newest first
    
    $supported_methods = array(
        'easypaisa' => 'Easypaisa',
        'jazzcash' => 'JazzCash',
        'sadapay' => 'SadaPay',
        'nayapay' => 'NayaPay',
        'zindigi' => 'Zindigi',
        'bank_transfer_pk' => 'Bank Transfer',
        'hbl' => 'HBL',
        'ubl' => 'UBL',
        'meezan' => 'Meezan Bank',
        'allied' => 'Allied Bank',
        'mcb' => 'MCB',
        'bankalfalah' => 'Bank Alfalah',
        'faysal' => 'Faysal Bank',
        'standard' => 'Standard Chartered',
    );
    
    // Calculate statistics by payment method
    $stats_by_method = array();
    $total_failed_amount = 0;
    
    foreach ($failed_payments as $payment) {
        $method = $payment['payment_method'];
        if (!isset($stats_by_method[$method])) {
            $stats_by_method[$method] = array(
                'count' => 0,
                'amount' => 0,
            );
        }
        $stats_by_method[$method]['count']++;
        $stats_by_method[$method]['amount'] += $payment['amount'];
        $total_failed_amount += $payment['amount'];
    }
    
    ?>
    <div class="wrap">
        <h1><?php _e('Failed & Abandoned Payments', 'pakistani-payment-gateways'); ?></h1>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
            <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #d63638;"><?php echo count($failed_payments); ?></div>
                <div style="color: #666;"><?php _e('Total Failed Payments', 'pakistani-payment-gateways'); ?></div>
            </div>
            <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #d63638;"><?php echo wc_price($total_failed_amount); ?></div>
                <div style="color: #666;"><?php _e('Total Lost Revenue', 'pakistani-payment-gateways'); ?></div>
            </div>
            <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #d63638;">
                    <?php echo count($failed_payments) > 0 ? wc_price($total_failed_amount / count($failed_payments)) : wc_price(0); ?>
                </div>
                <div style="color: #666;"><?php _e('Average Failed Amount', 'pakistani-payment-gateways'); ?></div>
            </div>
        </div>
        
        <?php if (!empty($stats_by_method)): ?>
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
            <h2><?php _e('Failed Payments by Method', 'pakistani-payment-gateways'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Payment Method', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Failed Count', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Lost Revenue', 'pakistani-payment-gateways'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats_by_method as $method_id => $data): ?>
                    <tr>
                        <td><strong><?php echo isset($supported_methods[$method_id]) ? esc_html($supported_methods[$method_id]) : esc_html($method_id); ?></strong></td>
                        <td><?php echo $data['count']; ?></td>
                        <td><?php echo wc_price($data['amount']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
            <h2><?php _e('Recent Failed Payments', 'pakistani-payment-gateways'); ?></h2>
            <?php if (empty($failed_payments)): ?>
                <p><?php _e('No failed payments recorded yet.', 'pakistani-payment-gateways'); ?></p>
            <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Order', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Customer', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Payment Method', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Amount', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Status', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Date', 'pakistani-payment-gateways'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach (array_slice($failed_payments, 0, 50) as $payment): ?>
                    <tr>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $payment['order_id'] . '&action=edit'); ?>">
                                #<?php echo esc_html($payment['order_number']); ?>
                            </a>
                        </td>
                        <td>
                            <?php echo esc_html($payment['customer_name']); ?><br>
                            <small><?php echo esc_html($payment['customer_email']); ?></small>
                        </td>
                        <td><?php echo isset($supported_methods[$payment['payment_method']]) ? esc_html($supported_methods[$payment['payment_method']]) : esc_html($payment['payment_method']); ?></td>
                        <td><?php echo wc_price($payment['amount']); ?></td>
                        <td><span class="order-status status-<?php echo esc_attr($payment['status']); ?>"><?php echo esc_html($payment['status']); ?></span></td>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($payment['date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

/**
 * Send payment verified email when order status changes to processing or completed
 */
add_action('woocommerce_order_status_on-hold_to_processing', 'ppg_notify_payment_verified', 10, 1);
add_action('woocommerce_order_status_on-hold_to_completed', 'ppg_notify_payment_verified', 10, 1);
function ppg_notify_payment_verified($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $payment_method = $order->get_payment_method();
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    if (!in_array($payment_method, $supported_methods)) {
        return;
    }
    
    // Check if we already sent verification email
    if ($order->get_meta('_ppg_verified_email_sent')) {
        return;
    }
    
    // Send verification email
    ppg_send_payment_verified_email($order_id, $payment_method);
    
    // Mark as sent
    $order->update_meta_data('_ppg_verified_email_sent', 'yes');
    $order->save();
}

/**
 * Send admin email notification on new payment
 */
add_action('woocommerce_order_status_on-hold', 'ppg_notify_admin_new_payment', 10, 1);
function ppg_notify_admin_new_payment($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $payment_method = $order->get_payment_method();
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    if (!in_array($payment_method, $supported_methods)) {
        return;
    }
    
    // Check if we already sent notification
    if ($order->get_meta('_ppg_admin_notified')) {
        return;
    }
    
    $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
    $sender_number = $order->get_meta('_' . $payment_method . '_sender_number');
    $sender_name = $order->get_meta('_' . $payment_method . '_sender_name');
    $payment_title = $order->get_meta('_' . $payment_method . '_payment_method');
    
    ppg_send_admin_notification($order_id, $payment_title, $txn_id, $sender_name, $sender_number);
    
    // Note: WhatsApp notification is already prepared in process_payment method
    // Admin can click the button in order details or customer can send via thank you page
    
    // Send custom email to customer if enabled
    ppg_send_custom_email($order_id, $payment_method);
    
    // Mark as notified
    $order->update_meta_data('_ppg_admin_notified', 'yes');
    $order->save();
}

/**
 * Add Payment Status Dashboard Widget
 */
add_action('wp_dashboard_setup', 'ppg_add_dashboard_widget');
function ppg_add_dashboard_widget() {
    wp_add_dashboard_widget(
        'ppg_payment_status_widget',
        __('Pakistani Payment Gateways - Payment Status', 'pakistani-payment-gateways'),
        'ppg_render_dashboard_widget'
    );
}

function ppg_render_dashboard_widget() {
    $supported_methods = array(
        'easypaisa', 'jazzcash', 'bank_transfer_pk', 'hbl', 'ubl', 'meezan', 
        'allied', 'mcb', 'sadapay', 'nayapay', 'zindigi', 'bankalfalah', 
        'faysal', 'standard'
    );
    
    // Get pending payments (on-hold status)
    $pending_args = array(
        'status' => 'on-hold',
        'limit' => -1,
        'payment_method' => $supported_methods,
    );
    $pending_orders = wc_get_orders($pending_args);
    
    // Get today's payments
    $today_args = array(
        'date_created' => '>' . strtotime('today midnight'),
        'limit' => -1,
        'payment_method' => $supported_methods,
    );
    $today_orders = wc_get_orders($today_args);
    
    // Get this week's payments
    $week_args = array(
        'date_created' => '>' . strtotime('monday this week'),
        'limit' => -1,
        'payment_method' => $supported_methods,
    );
    $week_orders = wc_get_orders($week_args);
    
    // Get failed payments
    $failed_payments = get_option('ppg_failed_payments', array());
    $recent_failed = array_filter($failed_payments, function($payment) {
        return strtotime($payment['date']) > strtotime('-7 days');
    });
    
    // Calculate totals
    $pending_count = count($pending_orders);
    $today_count = count($today_orders);
    $week_count = count($week_orders);
    $today_total = 0;
    $week_total = 0;
    
    foreach ($today_orders as $order) {
        $today_total += $order->get_total();
    }
    
    foreach ($week_orders as $order) {
        $week_total += $order->get_total();
    }
    
    ?>
    <div class="ppg-dashboard-widget">
        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px;">
            <div style="background: #fff3cd; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #856404;"><?php echo $pending_count; ?></div>
                <div style="color: #856404; font-size: 13px;"><?php _e('Pending', 'pakistani-payment-gateways'); ?></div>
            </div>
            <div style="background: #d4edda; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #155724;"><?php echo $today_count; ?></div>
                <div style="color: #155724; font-size: 13px;"><?php _e('Today', 'pakistani-payment-gateways'); ?></div>
            </div>
            <div style="background: #f8d7da; padding: 15px; border-radius: 5px; text-align: center;">
                <div style="font-size: 32px; font-weight: bold; color: #721c24;"><?php echo count($recent_failed); ?></div>
                <div style="color: #721c24; font-size: 13px;"><?php _e('Failed (7d)', 'pakistani-payment-gateways'); ?></div>
            </div>
        </div>
        
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
            <div style="background: #e7f5ff; padding: 15px; border-radius: 5px;">
                <div style="font-size: 13px; color: #0c5ba0; margin-bottom: 5px;"><?php _e('Today\'s Revenue', 'pakistani-payment-gateways'); ?></div>
                <div style="font-size: 20px; font-weight: bold; color: #0c5ba0;"><?php echo wc_price($today_total); ?></div>
            </div>
            <div style="background: #e7f5ff; padding: 15px; border-radius: 5px;">
                <div style="font-size: 13px; color: #0c5ba0; margin-bottom: 5px;"><?php _e('This Week', 'pakistani-payment-gateways'); ?></div>
                <div style="font-size: 20px; font-weight: bold; color: #0c5ba0;"><?php echo wc_price($week_total); ?></div>
            </div>
        </div>
        
        <?php if ($pending_count > 0): ?>
        <div style="margin-top: 15px;">
            <h4 style="margin: 0 0 10px 0;"><?php _e('Recent Pending Payments', 'pakistani-payment-gateways'); ?></h4>
            <table style="width: 100%; font-size: 12px;">
                <?php 
                $recent_pending = array_slice($pending_orders, 0, 5);
                foreach ($recent_pending as $order): 
                    $payment_method = $order->get_payment_method();
                    $txn_id = $order->get_meta('_' . $payment_method . '_transaction_id');
                ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 8px 0;">
                        <a href="<?php echo esc_url(admin_url('post.php?post=' . $order->get_id() . '&action=edit')); ?>">
                            #<?php echo $order->get_order_number(); ?>
                        </a>
                    </td>
                    <td style="padding: 8px 0;"><?php echo $order->get_formatted_order_total(); ?></td>
                    <td style="padding: 8px 0; font-size: 11px; color: #666;">
                        <?php echo $txn_id ? esc_html($txn_id) : '-'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endif; ?>
        
        <p style="text-align: center; margin-top: 15px;">
            <a href="<?php echo admin_url('edit.php?post_type=shop_order&post_status=wc-on-hold'); ?>" class="button button-primary">
                <?php _e('View All Pending', 'pakistani-payment-gateways'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=ppg-reports'); ?>" class="button">
                <?php _e('View Reports', 'pakistani-payment-gateways'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=ppg-failed-payments'); ?>" class="button">
                <?php _e('Failed Payments', 'pakistani-payment-gateways'); ?>
            </a>
        </p>
    </div>
    <?php
}

/**
 * Add Payment Reports Page
 */
add_action('admin_menu', 'ppg_add_reports_page');
function ppg_add_reports_page() {
    add_submenu_page(
        'woocommerce',
        __('Payment Reports', 'pakistani-payment-gateways'),
        __('PK Payment Reports', 'pakistani-payment-gateways'),
        'manage_woocommerce',
        'ppg-reports',
        'ppg_render_reports_page'
    );
}

function ppg_render_reports_page() {
    if (!current_user_can('manage_woocommerce')) {
        wp_die(__('You do not have sufficient permissions to access this page.', 'pakistani-payment-gateways'));
    }
    
    $supported_methods = array(
        'easypaisa' => 'Easypaisa',
        'jazzcash' => 'JazzCash',
        'sadapay' => 'SadaPay',
        'nayapay' => 'NayaPay',
        'zindigi' => 'Zindigi',
        'bank_transfer_pk' => 'Bank Transfer',
        'hbl' => 'HBL',
        'ubl' => 'UBL',
        'meezan' => 'Meezan Bank',
        'allied' => 'Allied Bank',
        'mcb' => 'MCB',
        'bankalfalah' => 'Bank Alfalah',
        'faysal' => 'Faysal Bank',
        'standard' => 'Standard Chartered',
    );
    
    // Get date range
    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : date('Y-m-01');
    $date_to = isset($_GET['date_to']) ? sanitize_text_field($_GET['date_to']) : date('Y-m-d');
    
    // Get orders for date range
    $args = array(
        'date_created' => $date_from . '...' . $date_to,
        'limit' => -1,
        'payment_method' => array_keys($supported_methods),
    );
    $orders = wc_get_orders($args);
    
    // Calculate statistics
    $stats = array();
    $total_revenue = 0;
    $total_orders = 0;
    
    foreach ($supported_methods as $method_id => $method_name) {
        $stats[$method_id] = array(
            'name' => $method_name,
            'count' => 0,
            'revenue' => 0,
        );
    }
    
    foreach ($orders as $order) {
        $method = $order->get_payment_method();
        if (isset($stats[$method])) {
            $stats[$method]['count']++;
            $stats[$method]['revenue'] += $order->get_total();
            $total_revenue += $order->get_total();
            $total_orders++;
        }
    }
    
    // Sort by revenue
    uasort($stats, function($a, $b) {
        return $b['revenue'] - $a['revenue'];
    });
    
    ?>
    <div class="wrap">
        <h1><?php _e('Pakistani Payment Gateways - Reports', 'pakistani-payment-gateways'); ?></h1>
        
        <div class="ppg-reports-filters" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
            <form method="get" action="">
                <input type="hidden" name="page" value="ppg-reports" />
                <div style="display: flex; gap: 15px; align-items: end;">
                    <div>
                        <label><?php _e('From:', 'pakistani-payment-gateways'); ?></label><br>
                        <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" />
                    </div>
                    <div>
                        <label><?php _e('To:', 'pakistani-payment-gateways'); ?></label><br>
                        <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" />
                    </div>
                    <div>
                        <button type="submit" class="button button-primary"><?php _e('Filter', 'pakistani-payment-gateways'); ?></button>
                        <a href="<?php echo admin_url('admin.php?page=ppg-reports'); ?>" class="button"><?php _e('Reset', 'pakistani-payment-gateways'); ?></a>
                    </div>
                </div>
            </form>
        </div>
        
        <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
            <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #2271b1;"><?php echo $total_orders; ?></div>
                <div style="color: #666;"><?php _e('Total Orders', 'pakistani-payment-gateways'); ?></div>
            </div>
            <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #00a32a;"><?php echo wc_price($total_revenue); ?></div>
                <div style="color: #666;"><?php _e('Total Revenue', 'pakistani-payment-gateways'); ?></div>
            </div>
            <div style="background: white; padding: 20px; border: 1px solid #ccc; border-radius: 5px; text-align: center;">
                <div style="font-size: 36px; font-weight: bold; color: #d63638;">
                    <?php echo $total_orders > 0 ? wc_price($total_revenue / $total_orders) : wc_price(0); ?>
                </div>
                <div style="color: #666;"><?php _e('Average Order Value', 'pakistani-payment-gateways'); ?></div>
            </div>
        </div>
        
        <div style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccc; border-radius: 5px;">
            <h2><?php _e('Payment Method Performance', 'pakistani-payment-gateways'); ?></h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Payment Method', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Orders', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Revenue', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Percentage', 'pakistani-payment-gateways'); ?></th>
                        <th><?php _e('Avg Order', 'pakistani-payment-gateways'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($stats as $method_id => $data): 
                        if ($data['count'] == 0) continue;
                        $percentage = $total_revenue > 0 ? ($data['revenue'] / $total_revenue) * 100 : 0;
                        $avg_order = $data['count'] > 0 ? $data['revenue'] / $data['count'] : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo esc_html($data['name']); ?></strong></td>
                        <td><?php echo $data['count']; ?></td>
                        <td><?php echo wc_price($data['revenue']); ?></td>
                        <td>
                            <div style="background: #eee; height: 20px; border-radius: 3px; overflow: hidden;">
                                <div style="background: #2271b1; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                            </div>
                            <?php echo number_format($percentage, 1); ?>%
                        </td>
                        <td><?php echo wc_price($avg_order); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
