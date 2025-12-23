<?php
/*
 * Plugin Name: Pickup from Store Payment Gateway
 * Plugin URI: https://github.com/mobipine/mp_woocommerce_pickup_from_store_plugin
 * Description: Custom offline payment gateway for WooCommerce that allows customers to pay when picking up from store
 * Author: Mobipine Limited
 * Author URI: https://mobipine.com
 * Version: 1.0.0
 * GitHub Plugin URI: mobipine/mp_woocommerce_pickup_from_store_plugin
 * GitHub Branch: main
 * Requires WP: 5.8
 * Requires PHP: 7.4
 */

// Ensure this file is only accessed through WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Check if the license key is valid before registering gateways and other functionalities
function pickup_from_store_init() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'pickup_from_store_woocommerce_missing_notice');
        return;
    }
    
    // Load the gateway class file
    $gateway_file = plugin_dir_path(__FILE__) . 'includes/class-wc-pickup-from-store-gateway.php';
    if (file_exists($gateway_file)) {
        require_once $gateway_file;
    }
    
    // Register the gateways
    add_filter('woocommerce_payment_gateways', 'register_pickup_from_store_gateway');
    function register_pickup_from_store_gateway($gateways) {
        $gateways[] = 'WC_Pickup_From_Store_Gateway';
        return $gateways;
    }

    add_action('woocommerce_blocks_loaded', 'pickup_from_store_gateway_block_support');
    function pickup_from_store_gateway_block_support() {
        require_once __DIR__ . '/includes/class-wc-pickup-from-store-gateway-blocks-support.php';
        add_action(
            'woocommerce_blocks_payment_method_type_registration',
            function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                $payment_method_registry->register(new WC_Pickup_From_Store_Gateway_Blocks_Support);
            }
        );
    }

    add_action('before_woocommerce_init', 'pickup_from_store_cart_checkout_blocks_compatibility');
    function pickup_from_store_cart_checkout_blocks_compatibility() {
        if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                'cart_checkout_blocks',
                __FILE__,
                true // true (compatible) - Pickup from Store gateway supports WooCommerce Blocks
            );
        }
    }
}
add_action('plugins_loaded', 'pickup_from_store_init');

function pickup_from_store_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>Pickup from Store Payment Gateway</strong> requires WooCommerce to be installed and active.</p></div>';
}

