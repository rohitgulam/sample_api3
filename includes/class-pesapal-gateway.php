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
            'amount' => '',  // Changed from '0' to empty string
            'currency' => 'TZS',
            'button_text' => 'Buy Now'
        ), $atts);

        // Check if amount is specified and valid
        $amount_specified = !empty($attributes['amount']) && is_numeric($attributes['amount']) && $attributes['amount'] > 0;

        ob_start();
        ?>
        <button class="pesapal-buy-button" 
                data-amount="<?php echo esc_attr($attributes['amount']); ?>"
                data-amount-specified="<?php echo $amount_specified ? 'true' : 'false'; ?>"
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

                        <div id="amount-field" class="form-group" style="display: none;">
                            <label for="amount"><?php _e('Amount', 'pesapal-payment'); ?></label>
                            <div class="amount-input-wrapper">
                                <span class="currency-symbol"></span>
                                <input type="number" name="amount" id="modal-amount" step="0.01" min="0.01" required />
                            </div>
                        </div>

                        <div id="amount-display" class="amount-display" style="display: none;">
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
            // Enable error logging
            error_log('Starting PesaPal payment processing');

            $payment_data = array(
                'first_name' => sanitize_text_field($_POST['first_name']),
                'last_name' => sanitize_text_field($_POST['last_name']),
                'email' => sanitize_email($_POST['email']),
                'phone_number' => sanitize_text_field($_POST['phone_number']),
                'amount' => floatval($_POST['amount']),
                'currency' => sanitize_text_field($_POST['currency']),
                'transaction_id' => wp_generate_uuid4()
            );

            error_log('Payment Data: ' . print_r($payment_data, true));

            // First process the payment with PesaPal
            $iframe_url = $this->helper->process_payment($payment_data);
            
            if (!$iframe_url) {
                throw new Exception(__('Failed to get payment iframe URL', 'pesapal-payment'));
            }

            // If we got the iframe URL, then record the payment
            require_once plugin_dir_path(__FILE__) . 'class-pesapal-db.php';
            $db = new Pesapal_DB();
            
            $payment_record = array(
                'first_name' => $payment_data['first_name'],
                'last_name' => $payment_data['last_name'],
                'email' => $payment_data['email'],
                'phone_number' => $payment_data['phone_number'],
                'amount' => $payment_data['amount'],
                'currency' => $payment_data['currency'],
                'transaction_id' => $payment_data['transaction_id'],
                'payment_status' => 'PENDING'
            );

            error_log('Inserting payment record');
            $payment_id = $db->insert_payment($payment_record);

            if (!$payment_id) {
                error_log('Failed to insert payment record');
                // Don't throw exception here, just log the error
                // We still want to show the iframe even if DB insert fails
            }

            error_log('Payment processing successful. IFrame URL: ' . $iframe_url);
            
            wp_send_json_success(array(
                'iframe_html' => $this->get_iframe_html($iframe_url)
            ));

        } catch (Exception $e) {
            error_log('PesaPal Payment Error: ' . $e->getMessage());
            error_log('Error trace: ' . $e->getTraceAsString());
            
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    private function get_iframe_html($iframe_url) {
        ob_start();
        ?>
        <div class="pesapal-iframe-wrapper">
            <iframe src="<?php echo esc_url($iframe_url); ?>" 
                    width="100%" 
                    height="700px" 
                    scrolling="auto" 
                    frameBorder="0">
                <p><?php _e('Browser unable to load iFrame', 'pesapal-payment'); ?></p>
            </iframe>
        </div>
        <?php
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

    // Add this method to handle the callback
    public function handle_pesapal_callback() {
        if (!isset($_GET['pesapal_callback'])) {
            return;
        }

        $order_tracking_id = isset($_GET['OrderTrackingId']) 
            ? sanitize_text_field($_GET['OrderTrackingId']) 
            : '';

        if (empty($order_tracking_id)) {
            wp_die(__('Invalid callback request', 'pesapal-payment'));
        }

        // Get transaction status
        $status = $this->helper->get_transaction_status($order_tracking_id);

        if ($status) {
            // Update payment record
            require_once plugin_dir_path(__FILE__) . 'class-pesapal-db.php';
            $db = new Pesapal_DB();
            $db->update_payment_status($order_tracking_id, $status->payment_status_description);

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
}