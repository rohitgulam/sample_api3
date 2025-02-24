<?php
class Pesapal_Admin {
    public function __construct() {
        add_action('admin_menu', array($this, 'add_settings_page'));
        add_action('admin_init', array($this, 'register_settings'));
    }

    public function add_settings_page() {
        add_options_page(
            __('PesaPal Settings', 'pesapal-payment'),
            __('PesaPal', 'pesapal-payment'),
            'manage_options',
            'pesapal-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting('pesapal_options', 'pesapal_api_mode');
        register_setting('pesapal_options', 'pesapal_consumer_key');
        register_setting('pesapal_options', 'pesapal_consumer_secret');

        add_settings_section(
            'pesapal_main_settings',
            __('Main Settings', 'pesapal-payment'),
            array($this, 'settings_section_callback'),
            'pesapal-settings'
        );

        add_settings_field(
            'pesapal_api_mode',
            __('API Mode', 'pesapal-payment'),
            array($this, 'api_mode_callback'),
            'pesapal-settings',
            'pesapal_main_settings'
        );

        add_settings_field(
            'pesapal_consumer_key',
            __('Consumer Key', 'pesapal-payment'),
            array($this, 'consumer_key_callback'),
            'pesapal-settings',
            'pesapal_main_settings'
        );

        add_settings_field(
            'pesapal_consumer_secret',
            __('Consumer Secret', 'pesapal-payment'),
            array($this, 'consumer_secret_callback'),
            'pesapal-settings',
            'pesapal_main_settings'
        );

        add_settings_field(
            'pesapal_verify_credentials',
            __('Verify Credentials', 'pesapal-payment'),
            array($this, 'verify_credentials_callback'),
            'pesapal-settings',
            'pesapal_main_settings'
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('pesapal_options');
                do_settings_sections('pesapal-settings');
                submit_button(__('Save Settings', 'pesapal-payment'));
                ?>
            </form>
        </div>
        <?php
    }

    public function settings_section_callback() {
        echo '<p>' . __('Configure your PesaPal payment gateway settings below.', 'pesapal-payment') . '</p>';
    }

    public function api_mode_callback() {
        $mode = get_option('pesapal_api_mode', 'demo');
        ?>
        <select name="pesapal_api_mode">
            <option value="demo" <?php selected($mode, 'demo'); ?>><?php _e('Demo', 'pesapal-payment'); ?></option>
            <option value="live" <?php selected($mode, 'live'); ?>><?php _e('Live', 'pesapal-payment'); ?></option>
        </select>
        <?php
    }

    public function consumer_key_callback() {
        $key = get_option('pesapal_consumer_key', '');
        ?>
        <input type="text" name="pesapal_consumer_key" value="<?php echo esc_attr($key); ?>" class="regular-text">
        <?php
    }

    public function consumer_secret_callback() {
        $secret = get_option('pesapal_consumer_secret', '');
        ?>
        <input type="password" name="pesapal_consumer_secret" value="<?php echo esc_attr($secret); ?>" class="regular-text">
        <?php
    }

    public function verify_credentials() {
        $api_mode = get_option('pesapal_api_mode', 'demo');
        $consumer_key = get_option('pesapal_consumer_key', '');
        $consumer_secret = get_option('pesapal_consumer_secret', '');

        if (empty($consumer_key) || empty($consumer_secret)) {
            add_settings_error(
                'pesapal_options',
                'credentials_missing',
                __('PesaPal credentials are missing. Please enter both Consumer Key and Consumer Secret.', 'pesapal-payment'),
                'error'
            );
            return false;
        }

        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-pesapal-helper.php';
        $helper = new Pesapal_Helper();
        $token = $helper->get_access_token();

        if (!$token) {
            add_settings_error(
                'pesapal_options',
                'credentials_invalid',
                __('Failed to authenticate with PesaPal. Please verify your credentials and API mode.', 'pesapal-payment'),
                'error'
            );
            return false;
        }

        add_settings_error(
            'pesapal_options',
            'credentials_valid',
            __('PesaPal credentials verified successfully.', 'pesapal-payment'),
            'success'
        );
        return true;
    }

    public function verify_credentials_callback() {
        ?>
        <button type="button" id="verify-pesapal-credentials" class="button button-secondary">
            <?php _e('Verify Credentials', 'pesapal-payment'); ?>
        </button>
        <span id="verification-result"></span>
        <script>
        jQuery(document).ready(function($) {
            $('#verify-pesapal-credentials').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('Verifying...');
                
                $.post(ajaxurl, {
                    action: 'verify_pesapal_credentials',
                    nonce: '<?php echo wp_create_nonce('verify_pesapal_credentials'); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#verification-result').html('<span style="color: green;">✓ ' + response.data.message + '</span>');
                    } else {
                        $('#verification-result').html('<span style="color: red;">✗ ' + response.data.message + '</span>');
                    }
                    button.prop('disabled', false).text('Verify Credentials');
                });
            });
        });
        </script>
        <?php
    }
} 