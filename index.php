<?php
/*
 * Plugin Name: Pickup from Store Payment Gateway
 * Plugin URI: https://github.com/yourusername/pickup-from-store
 * Description: Custom offline payment gateway for WooCommerce that allows customers to pay when picking up from store
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * Version: 1.0.0
 * Requires WP: 5.8
 * Requires PHP: 7.4
 */

// Ensure this file is only accessed through WordPress
if (!defined('ABSPATH')) {
    exit;
}

// Load the gateway class early
add_action('plugins_loaded', 'init_pickup_from_store_gateway', 0);
function init_pickup_from_store_gateway() {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'pickup_from_store_woocommerce_missing_notice');
        return;
    }
    
    // Check if WC_Payment_Gateway class exists (WooCommerce is fully loaded)
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }
    
    // Load the gateway class
    $gateway_file = plugin_dir_path(__FILE__) . 'includes/class-wc-pickup-from-store-gateway.php';
    if (file_exists($gateway_file)) {
        require_once $gateway_file;
        
        // Register the gateway after class is loaded
        add_filter('woocommerce_payment_gateways', 'register_pickup_from_store_gateway');
    }
}

function register_pickup_from_store_gateway($gateways) {
    // Only add if class exists
    if (class_exists('WC_Pickup_From_Store_Gateway')) {
        $gateways[] = 'WC_Pickup_From_Store_Gateway';
    }
    return $gateways;
}

function pickup_from_store_woocommerce_missing_notice() {
    echo '<div class="error"><p><strong>Pickup from Store Payment Gateway</strong> requires WooCommerce to be installed and active.</p></div>';
}

