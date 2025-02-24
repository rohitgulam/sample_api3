<?php
class Pesapal_Gateway {
    private $helper;

    public function __construct() {
        require_once plugin_dir_path(__FILE__) . 'class-pesapal-helper.php';
        $this->helper = new Pesapal_Helper();
    }

    public function init() {
        // Update shortcode to accept attributes
        add_shortcode('pesapal_payment_form', array($this, 'render_payment_button'));
        
        // Add necessary actions and filters
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('wp_ajax_process_pesapal_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_nopriv_process_pesapal_payment', array($this, 'ajax_process_payment'));
        add_action('wp_footer', array($this, 'add_payment_modal'));
        
        // Add credential verification handler
        add_action('wp_ajax_verify_pesapal_credentials', array($this, 'ajax_verify_credentials'));
    }

    public function enqueue_scripts() {
        wp_enqueue_style('pesapal-style', 
            plugins_url('/assets/css/style.css', dirname(__FILE__)));
            
        wp_enqueue_script('pesapal-script', 
            plugins_url('/assets/js/script.js', dirname(__FILE__)), 
            array('jquery'), 
            '1.0.0', 
            true);

        wp_localize_script('pesapal-script', 'pesapal_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pesapal_payment')
        ));
    }

    public function render_payment_button($atts) {
        // Default attributes
        $attributes = shortcode_atts(array(
            'amount' => '0',
            'currency' => 'TZS',
            'button_text' => 'Buy Now'
        ), $atts);

        // Validate amount
        if (!is_numeric($attributes['amount']) || $attributes['amount'] <= 0) {
            return '<p class="error">Invalid amount specified</p>';
        }

        ob_start();
        ?>
        <button class="pesapal-buy-button" 
                data-amount="<?php echo esc_attr($attributes['amount']); ?>"
                data-currency="<?php echo esc_attr($attributes['currency']); ?>">
            <?php echo esc_html($attributes['button_text']); ?>
        </button>
        <?php
        return ob_get_clean();
    }

    public function add_payment_modal() {
        ?>
        <div id="pesapal-modal" class="pesapal-modal">
            <div class="pesapal-modal-content">
                <span class="pesapal-close">&times;</span>
                <div id="pesapal-modal-body">
                    <form id="pesapal-payment-form" class="pesapal-form">
                        <input type="hidden" name="amount" id="modal-amount" value="">
                        <input type="hidden" name="currency" id="modal-currency" value="">
                        
                        <div class="form-group">
                            <label for="first_name"><?php _e('First Name', 'pesapal-payment'); ?></label>
                            <input type="text" name="first_name" required />
                        </div>

                        <div class="form-group">
                            <label for="last_name"><?php _e('Last Name', 'pesapal-payment'); ?></label>
                            <input type="text" name="last_name" required />
                        </div>

                        <div class="form-group">
                            <label for="email"><?php _e('Email Address', 'pesapal-payment'); ?></label>
                            <input type="email" name="email" required />
                        </div>

                        <div class="form-group">
                            <label for="phone_number"><?php _e('Phone Number', 'pesapal-payment'); ?></label>
                            <input type="text" name="phone_number" required />
                        </div>

                        <div class="amount-display">
                            <strong>Amount to Pay: </strong>
                            <span id="display-amount"></span>
                        </div>

                        <input type="submit" value="<?php _e('Make Payment', 'pesapal-payment'); ?>" class="button" />
                    </form>
                </div>
            </div>
        </div>
        <?php
    }

    public function ajax_process_payment() {
        check_ajax_referer('pesapal_payment', 'nonce');

        try {
            $payment_data = array(
                'first_name' => sanitize_text_field($_POST['first_name']),
                'last_name' => sanitize_text_field($_POST['last_name']),
                'email' => sanitize_email($_POST['email']),
                'phone_number' => sanitize_text_field($_POST['phone_number']),
                'amount' => floatval($_POST['amount'])
            );

            // Log payment attempt
            error_log('PesaPal Payment Attempt: ' . print_r($payment_data, true));

            $iframe_url = $this->helper->process_payment($payment_data);
            
            if ($iframe_url) {
                error_log('PesaPal Payment Success - IFrame URL: ' . $iframe_url);
                wp_send_json_success(array(
                    'iframe_html' => $this->get_iframe_html($iframe_url)
                ));
            } else {
                error_log('PesaPal Payment Failed - No IFrame URL returned');
                wp_send_json_error(array(
                    'message' => __('Payment processing failed: No iframe URL returned', 'pesapal-payment')
                ));
            }
        } catch (Exception $e) {
            error_log('PesaPal Payment Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => __('Payment processing failed: ', 'pesapal-payment') . $e->getMessage()
            ));
        }
    }

    private function get_iframe_html($iframe_url) {
        ob_start();
        include plugin_dir_path(dirname(__FILE__)) . 'templates/payment-iframe.php';
        return ob_get_clean();
    }

    public function ajax_verify_credentials() {
        check_ajax_referer('verify_pesapal_credentials', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized access', 'pesapal-payment')));
        }

        $helper = new Pesapal_Helper();
        $token = $helper->get_access_token();

        if ($token) {
            wp_send_json_success(array(
                'message' => __('Credentials verified successfully', 'pesapal-payment')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to verify credentials. Please check your Consumer Key, Consumer Secret, and API Mode.', 'pesapal-payment')
            ));
        }
    }
}