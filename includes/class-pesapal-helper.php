<?php
class Pesapal_Helper {
    private $url;
    private $consumer_key;
    private $consumer_secret;
    private $api_mode;

    public function __construct() {
        $this->api_mode = 'demo';
        $this->consumer_key = "ngW+UEcnDhltUc5fxPfrCD987xMh3Lx8";
        $this->consumer_secret = "q27RChYs5UkypdcNYKzuUw460Dg=";
        $this->url = ($this->api_mode == 'live') 
            ? "https://pay.pesapal.com/v3" 
            : "https://cybqa.pesapal.com/pesapalv3";
    }

    public function process_payment($payment_data) {
        try {
            // Validate credentials
            if (empty($this->consumer_key) || empty($this->consumer_secret)) {
                error_log('PesaPal Error: Missing API credentials');
                throw new Exception(__('Payment gateway not properly configured', 'pesapal-payment'));
            }

            // Step 1: Get Access Token
            error_log('PesaPal: Requesting access token...');
            $access_token = $this->get_access_token();
            if (!$access_token) {
                error_log('PesaPal Error: Failed to get access token');
                throw new Exception(__('Failed to authenticate with payment gateway', 'pesapal-payment'));
            }
            error_log('PesaPal: Access token received successfully');

            // Step 2: Get IPN ID
            error_log('PesaPal: Requesting IPN ID...');
            $ipn_id = $this->get_notification_id($access_token);
            if (!$ipn_id) {
                error_log('PesaPal Error: Failed to get IPN ID');
                throw new Exception(__('Failed to set up payment notification', 'pesapal-payment'));
            }
            error_log('PesaPal: IPN ID received successfully: ' . $ipn_id);

            // Step 3: Submit Order Request
            error_log('PesaPal: Submitting order request...');
            $order = $this->prepare_order_data($payment_data, $ipn_id);
            $order_url = $this->submit_order_request($order, $access_token);
            
            if (!$order_url) {
                error_log('PesaPal Error: Failed to get order URL');
                throw new Exception(__('Failed to create payment order', 'pesapal-payment'));
            }
            error_log('PesaPal: Order URL received successfully: ' . $order_url);

            return $order_url;

        } catch (Exception $e) {
            error_log('PesaPal Process Payment Error: ' . $e->getMessage());
            throw $e;
        }
    }

    private function get_access_token() {
        try {
            // Log the API endpoint and credentials (remove in production)
            error_log('PesaPal Auth URL: ' . $this->url . '/api/Auth/RequestToken');
            error_log('PesaPal Consumer Key: ' . $this->consumer_key);
            error_log('PesaPal API Mode: ' . $this->api_mode);

            $headers = array(
                'accept' => 'text/plain',
                'content-type' => 'application/json'
            );

            $post_data = array(
                'consumer_key' => $this->consumer_key,
                'consumer_secret' => $this->consumer_secret
            );

            $args = array(
                'headers' => array(
                    'Accept' => 'text/plain',
                    'Content-Type' => 'application/json'
                ),
                'body' => json_encode($post_data),
                'timeout' => 30,
                'sslverify' => false // Only for development/testing
            );

            // Make the request
            $response = wp_remote_post($this->url . '/api/Auth/RequestToken', $args);

            // Log the raw response for debugging
            error_log('PesaPal Auth Response: ' . print_r($response, true));

            if (is_wp_error($response)) {
                error_log('PesaPal Auth WP Error: ' . $response->get_error_message());
                return false;
            }

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);

            if (empty($data) || !isset($data->token)) {
                error_log('PesaPal Auth Error - Invalid Response: ' . $body);
                return false;
            }

            error_log('PesaPal Auth Success - Token Received');
            return $data->token;

        } catch (Exception $e) {
            error_log('PesaPal Auth Exception: ' . $e->getMessage());
            return false;
        }
    }

    private function get_notification_id($access_token) {
        $callback_url = add_query_arg('pesapal_callback', '1', home_url('/'));
        
        $headers = array(
            'accept' => 'text/plain',
            'content-type' => 'application/json',
            'authorization' => 'Bearer ' . $access_token
        );

        $post_data = array(
            'url' => $callback_url,
            'ipn_notification_type' => 'GET'
        );

        $response = wp_remote_post($this->url . '/api/URLSetup/RegisterIPN', array(
            'headers' => $headers,
            'body' => json_encode($post_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            $this->log_error('IPN Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        return isset($body->ipn_id) ? $body->ipn_id : false;
    }

    private function submit_order_request($payment_data, $access_token) {
        $callback_url = add_query_arg('pesapal_callback', '1', home_url('/'));
        
        $headers = array(
            'accept' => 'text/plain',
            'content-type' => 'application/json',
            'authorization' => 'Bearer ' . $access_token
        );

        $response = wp_remote_post($this->url . '/api/Transactions/SubmitOrderRequest', array(
            'headers' => $headers,
            'body' => json_encode($payment_data),
            'timeout' => 30
        ));

        if (is_wp_error($response)) {
            $this->log_error('Order Submit Error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response));
        return isset($body->redirect_url) ? $body->redirect_url : false;
    }

    private function prepare_order_data($payment_data, $ipn_id) {
        $callback_url = add_query_arg('pesapal_callback', '1', home_url('/'));
        
        return array(
            'id' => wp_generate_uuid4(),
            'currency' => 'TZS', // Make this configurable in admin settings
            'amount' => number_format($payment_data['amount'], 2, '.', ''),
            'description' => 'Payment via ' . get_bloginfo('name'),
            'callback_url' => $callback_url,
            'notification_id' => $ipn_id,
            'billing_address' => array(
                'email_address' => $payment_data['email'],
                'phone_number' => $payment_data['phone_number'],
                'first_name' => $payment_data['first_name'],
                'last_name' => $payment_data['last_name'],
                'country_code' => 'TZ', // Make this configurable in admin settings
                'line_1' => '',
                'line_2' => '',
                'city' => '',
                'state' => '',
                'postal_code' => '',
                'zip_code' => ''
            ),
            'language' => 'EN',
            'terms_and_conditions_id' => ''
        );
    }

    public function get_transaction_status($order_tracking_id) {
        try {
            $access_token = $this->get_access_token();
            if (!$access_token) {
                throw new Exception(__('Failed to get access token', 'pesapal-payment'));
            }

            $headers = array(
                'accept' => 'text/plain',
                'content-type' => 'application/json',
                'authorization' => 'Bearer ' . $access_token
            );

            $response = wp_remote_get($this->url . '/api/Transactions/GetTransactionStatus?orderTrackingId=' . $order_tracking_id, array(
                'headers' => $headers,
                'timeout' => 30
            ));

            if (is_wp_error($response)) {
                throw new Exception($response->get_error_message());
            }

            return json_decode(wp_remote_retrieve_body($response));

        } catch (Exception $e) {
            $this->log_error('Transaction Status Error: ' . $e->getMessage());
            return false;
        }
    }

    private function log_error($message) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('PesaPal Plugin Error: ' . $message);
        }
        
        // Optionally store errors in WordPress database
        $errors = get_option('pesapal_error_log', array());
        $errors[] = array(
            'timestamp' => current_time('mysql'),
            'message' => $message
        );
        
        // Keep only last 100 errors
        if (count($errors) > 100) {
            array_shift($errors);
        }
        
        update_option('pesapal_error_log', $errors);
    }
} 