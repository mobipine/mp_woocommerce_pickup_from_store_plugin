<?php

// Ensure this file is only accessed through WordPress
if (!defined('ABSPATH')) {
    exit;
}

class WC_Pickup_From_Store_Gateway extends WC_Payment_Gateway
{
    public $instructions;
    public $enable_for_methods;
    public $enable_for_virtual;

    public function __construct()
    {
        $this->id = 'pickup_from_store'; // Unique ID for your gateway.
        $this->icon = ''; // URL of the gateway icon.
        $this->has_fields = false; // No custom fields needed.
        $this->method_title = 'Pickup from Store'; // Title of the payment method shown on the admin page.
        $this->method_description = 'Allow customers to pay when picking up their order from the store.'; // Description for the payment method shown on the admin page.

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->instructions = $this->get_option('instructions');
        $enable_for_methods = $this->get_option('enable_for_methods', array());
        $this->enable_for_methods = is_array($enable_for_methods) ? $enable_for_methods : array();
        $this->enable_for_virtual = $this->get_option('enable_for_virtual', 'yes') === 'yes';

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
        add_filter('woocommerce_payment_complete_order_status', array($this, 'change_payment_complete_order_status'), 10, 3);

        // Customer Emails.
        add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled'            => array(
                'title'       => __('Enable/Disable', 'woocommerce'),
                'label'       => __('Enable Pickup from Store', 'woocommerce'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'yes',
            ),
            'title'              => array(
                'title'       => __('Title', 'woocommerce'),
                'type'        => 'text',
                'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                'default'     => __('Pickup from Store', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'description'        => array(
                'title'       => __('Description', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('Payment method description that the customer will see on your website.', 'woocommerce'),
                'default'     => __('Pay when you pick up your order from the store.', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'instructions'       => array(
                'title'       => __('Instructions', 'woocommerce'),
                'type'        => 'textarea',
                'description' => __('Instructions that will be added to the thank you page and emails.', 'woocommerce'),
                'default'     => __('Please bring your order confirmation when picking up. Payment will be collected at the store.', 'woocommerce'),
                'desc_tip'    => true,
            ),
            'enable_for_methods' => array(
                'title'             => __('Enable for shipping methods', 'woocommerce'),
                'type'              => 'multiselect',
                'class'             => 'wc-enhanced-select',
                'css'               => 'width: 400px;',
                'default'           => '',
                'description'       => __('If Pickup from Store is only available for certain shipping methods, set it up here. Leave blank to enable for all methods.', 'woocommerce'),
                'options'           => $this->load_shipping_method_options(),
                'desc_tip'          => true,
                'custom_attributes' => array(
                    'data-placeholder' => __('Select shipping methods', 'woocommerce'),
                ),
            ),
            'enable_for_virtual' => array(
                'title'   => __('Enable for virtual orders', 'woocommerce'),
                'label'   => __('Accept Pickup from Store if the order is virtual', 'woocommerce'),
                'type'    => 'checkbox',
                'default' => 'yes',
            ),
        );
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id Order ID.
     * @return array
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if (!$order) {
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        // Mark as on-hold (we're awaiting payment at pickup)
        $order->update_status('on-hold', __('Awaiting payment at store pickup.', 'woocommerce'));

        // Reduce stock levels
        wc_reduce_stock_levels($order_id);

        // Remove cart
        WC()->cart->empty_cart();

        // Return thankyou redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Output for the order received page.
     *
     * @param int $order_id Order ID.
     */
    public function thankyou_page($order_id)
    {
        if ($this->instructions) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)));
        }
    }

    /**
     * Change payment complete order status to on-hold for pickup orders.
     *
     * @param string $status Order status.
     * @param int    $order_id Order ID.
     * @param object $order Order object.
     * @return string
     */
    public function change_payment_complete_order_status($status, $order_id = 0, $order = false)
    {
        if ($order && $order->get_payment_method() === $this->id) {
            $status = 'on-hold';
        }
        return $status;
    }

    /**
     * Add content to the WC emails.
     *
     * @param WC_Order $order Order object.
     * @param bool     $sent_to_admin  Sent to admin.
     * @param bool     $plain_text Email format: plain text or HTML.
     */
    public function email_instructions($order, $sent_to_admin, $plain_text = false)
    {
        if ($this->instructions && !$sent_to_admin && $this->id === $order->get_payment_method()) {
            echo wp_kses_post(wpautop(wptexturize($this->instructions)) . PHP_EOL);
        }
    }

    /**
     * Check If The Gateway Is Available For Use.
     *
     * @return bool
     */
    public function is_available()
    {
        $is_virtual       = true;
        $shipping_methods = array();

        // Get shipping methods from the cart or order.
        if (is_wc_endpoint_url('order-pay')) {
            $order            = wc_get_order(absint(get_query_var('order-pay')));
            $shipping_methods = $order ? $order->get_shipping_methods() : array();
            $is_virtual       = !count($shipping_methods);
        } elseif (WC()->cart && WC()->cart->needs_shipping()) {
            $shipping_methods = WC()->cart->get_shipping_methods();
            $is_virtual       = false;
        }

        // If Pickup from Store is not enabled for virtual orders and the order does not need shipping, return false.
        if (!$this->enable_for_virtual && $is_virtual) {
            return false;
        }

        // Return early if:
        // - There are no shipping methods restrictions in place.
        // - The order is virtual so needs no shipping.
        // - Shipping methods are not set yet.
        if (empty($this->enable_for_methods) || $is_virtual || !$shipping_methods) {
            return parent::is_available();
        }

        // Get the selected shipping method ids. This works on both WC_Shipping_Rate and WC_Order_Item_Shipping class instances.
        $canonical_rate_ids = array_unique(
            array_values(
                array_map(
                    function ($shipping_method) {
                        return $shipping_method && is_callable(array($shipping_method, 'get_method_id')) && is_callable(array($shipping_method, 'get_instance_id')) ? $shipping_method->get_method_id() . ':' . $shipping_method->get_instance_id() : null;
                    },
                    $shipping_methods
                )
            )
        );

        if (!count($this->get_matching_rates($canonical_rate_ids))) {
            return false;
        }

        return parent::is_available();
    }

    /**
     * Checks to see whether or not the admin settings are being accessed by the current request.
     *
     * @return bool
     */
    private function is_accessing_settings()
    {
        if (is_admin()) {
            if (!is_wc_admin_settings_page()) {
                return false;
            }
            // phpcs:disable WordPress.Security.NonceVerification
            if (!isset($_REQUEST['tab']) || 'checkout' !== $_REQUEST['tab']) {
                return false;
            }
            if (!isset($_REQUEST['section']) || $this->id !== $_REQUEST['section']) {
                return false;
            }
            // phpcs:enable WordPress.Security.NonceVerification

            return true;
        }

        if (defined('REST_REQUEST') && REST_REQUEST) {
            global $wp;
            if (isset($wp->query_vars['rest_route']) && false !== strpos($wp->query_vars['rest_route'], '/payment_gateways')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Loads all of the shipping method options for the enable_for_methods field.
     *
     * @return array
     */
    private function load_shipping_method_options()
    {
        // Since this is expensive, we only want to do it if we're actually on the settings page.
        if (!$this->is_accessing_settings()) {
            return array();
        }

        $data_store = WC_Data_Store::load('shipping-zone');
        $raw_zones  = $data_store->get_zones();
        $zones      = array();

        foreach ($raw_zones as $raw_zone) {
            $zones[] = new WC_Shipping_Zone($raw_zone);
        }

        $zones[] = new WC_Shipping_Zone(0);

        $options = array();
        foreach (WC()->shipping()->load_shipping_methods() as $method) {

            $options[$method->get_method_title()] = array();

            // Translators: %1$s shipping method name.
            $options[$method->get_method_title()][$method->id] = sprintf(__('Any &quot;%1$s&quot; method', 'woocommerce'), $method->get_method_title());

            foreach ($zones as $zone) {

                $shipping_method_instances = $zone->get_shipping_methods();

                foreach ($shipping_method_instances as $shipping_method_instance_id => $shipping_method_instance) {

                    if ($shipping_method_instance->id !== $method->id) {
                        continue;
                    }

                    $option_id = $shipping_method_instance->get_rate_id();

                    // Translators: %1$s shipping method title, %2$s shipping method id.
                    $option_instance_title = sprintf(__('%1$s (#%2$s)', 'woocommerce'), $shipping_method_instance->get_title(), $shipping_method_instance_id);

                    // Translators: %1$s zone name, %2$s shipping method instance name.
                    $option_title = sprintf(__('%1$s &ndash; %2$s', 'woocommerce'), $zone->get_id() ? $zone->get_zone_name() : __('Other locations', 'woocommerce'), $option_instance_title);

                    $options[$method->get_method_title()][$option_id] = $option_title;
                }
            }
        }

        return $options;
    }

    /**
     * Indicates whether a rate exists in an array of canonically-formatted rate IDs that activates this gateway.
     *
     * @param array $rate_ids Rate ids to check.
     * @return array
     */
    private function get_matching_rates($rate_ids)
    {
        // Ensure enable_for_methods is an array
        $enable_for_methods = is_array($this->enable_for_methods) ? $this->enable_for_methods : array();
        
        // First, match entries in 'method_id:instance_id' format. Then, match entries in 'method_id' format by stripping off the instance ID from the candidates.
        return array_unique(array_merge(array_intersect($enable_for_methods, $rate_ids), array_intersect($enable_for_methods, array_unique(array_map('wc_get_string_before_colon', $rate_ids)))));
    }
}

