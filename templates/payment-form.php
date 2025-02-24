<div class="pesapal-container">
    <form id="pesapal-payment-form" class="pesapal-form">
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

        <div class="form-group">
            <label for="amount"><?php _e('Amount', 'pesapal-payment'); ?></label>
            <input type="number" name="amount" required />
        </div>

        <input type="submit" value="<?php _e('Make Payment', 'pesapal-payment'); ?>" class="button" />
    </form>
</div> 