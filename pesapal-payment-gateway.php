<?php
/*
Plugin Name: PesaPal Payment Gateway
Plugin URI: https://yourwebsite.com/pesapal-plugin
Description: A payment gateway integration for PesaPal
Version: 1.0.0
Author: Your Name
Author URI: https://yourwebsite.com
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: pesapal-payment
*/

// Prevent direct access to this file
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PESAPAL_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PESAPAL_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PESAPAL_VERSION', '1.0.0');

// Include required files
require_once PESAPAL_PLUGIN_DIR . 'includes/class-pesapal-gateway.php';
require_once PESAPAL_PLUGIN_DIR . 'admin/class-pesapal-admin.php';

class Pesapal_Plugin {
    private $gateway;
    private $admin;

    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
    }

    public function init() {
        // Initialize gateway
        $this->gateway = new Pesapal_Gateway();
        $this->gateway->init();

        // Initialize admin if in admin area
        if (is_admin()) {
            $this->admin = new Pesapal_Admin();
        }

        // Add callback handler
        add_action('init', array($this, 'handle_pesapal_callback'));
    }

    public function handle_pesapal_callback() {
        if (!isset($_GET['pesapal_callback'])) {
            return;
        }

        $order_tracking_id = isset($_GET['OrderTrackingId']) ? sanitize_text_field($_GET['OrderTrackingId']) : '';
        
        if (empty($order_tracking_id)) {
            wp_die(__('Invalid callback request', 'pesapal-payment'));
        }

        // Get transaction status
        $helper = new Pesapal_Helper();
        $status = $helper->get_transaction_status($order_tracking_id);

        if ($status) {
            // Handle the status update
            do_action('pesapal_payment_status_update', $status, $order_tracking_id);

            // Redirect to thank you page
            $redirect_url = add_query_arg(array(
                'payment_status' => $status->payment_status_description,
                'order_id' => $order_tracking_id
            ), home_url('/thank-you/'));

            wp_redirect($redirect_url);
            exit;
        }

        wp_die(__('Error processing payment callback', 'pesapal-payment'));
    }

    public static function activate() {
        // Create necessary database tables
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}pesapal_transactions (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            order_id varchar(100) NOT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(10) NOT NULL,
            status varchar(50) NOT NULL,
            payment_method varchar(50),
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY order_id (order_id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public static function deactivate() {
        // Cleanup if needed
    }
}

// Initialize the plugin
global $pesapal_plugin;
$pesapal_plugin = new Pesapal_Plugin();

// Register activation and deactivation hooks
register_activation_hook(__FILE__, array('Pesapal_Plugin', 'activate'));
register_deactivation_hook(__FILE__, array('Pesapal_Plugin', 'deactivate'));