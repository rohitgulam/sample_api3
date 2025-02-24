<?php
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="pesapal-iframe-container">
    <h2><?php _e('Complete Your Payment', 'pesapal-payment'); ?></h2>
    <div class="pesapal-iframe-wrapper">
        <iframe src="<?php echo esc_url($iframe_url); ?>" 
                width="100%" 
                height="700px" 
                scrolling="auto" 
                frameBorder="0">
            <p><?php _e('Browser unable to load iFrame', 'pesapal-payment'); ?></p>
        </iframe>
    </div>
</div> 