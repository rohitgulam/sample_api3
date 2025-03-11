jQuery(document).ready(function($) {
    // Modal handling
    var modal = $('#pesapal-modal');
    var span = $('.pesapal-close');

    // When the user clicks the button, open the modal
    $('.pesapal-buy-button').on('click', function() {
        var amount = $(this).data('amount');
        var currency = $(this).data('currency');
        var amountSpecified = $(this).data('amount-specified');
        
        $('#modal-currency').val(currency);
        $('.currency-symbol').text(currency);
        
        if (amountSpecified === 'true') {
            // Fixed amount
            $('#modal-amount').val(amount);
            $('#amount-field').hide();
            $('#amount-display').show();
            $('#display-amount').text(currency + ' ' + parseFloat(amount).toFixed(2));
        } else {
            // User can enter amount
            $('#modal-amount').val('');
            $('#amount-field').show();
            $('#amount-display').hide();
        }
        
        modal.show();
    });

    // When the user clicks on <span> (x), close the modal
    span.on('click', function() {
        modal.hide();
    });

    // When the user clicks anywhere outside of the modal, close it
    $(window).on('click', function(event) {
        if ($(event.target).is(modal)) {
            modal.hide();
        }
    });

    // Update amount display when user enters amount
    $('#modal-amount').on('input', function() {
        var amount = $(this).val();
        var currency = $('#modal-currency').val();
        if (amount && amount > 0) {
            $('#display-amount').text(currency + ' ' + parseFloat(amount).toFixed(2));
        }
    });

    // Form submission
    $('#pesapal-payment-form').on('submit', function(e) {
        e.preventDefault();
        
        var amount = $('#modal-amount').val();
        if (!amount || parseFloat(amount) <= 0) {
            alert('Please enter a valid amount');
            return;
        }

        var form = $(this);
        var submitButton = form.find('input[type="submit"]');
        var formData = new FormData(this);
        formData.append('action', 'process_pesapal_payment');
        formData.append('nonce', pesapal_ajax.nonce);

        // Clear any existing error messages
        $('.pesapal-error-message').remove();

        // Disable submit button and show loading state
        submitButton.prop('disabled', true).val('Processing...');

        $.ajax({
            url: pesapal_ajax.ajax_url,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Replace modal content with iframe
                    $('#pesapal-modal-body').html(response.data.iframe_html);
                } else {
                    // Show error message
                    var errorMessage = response.data.message || 'Payment processing failed';
                    $('<div class="pesapal-error-message">' + errorMessage + '</div>')
                        .insertBefore(form);
                    submitButton.prop('disabled', false).val('Make Payment');
                }
            },
            error: function(xhr, status, error) {
                var errorMessage = 'An error occurred: ' + error;
                $('<div class="pesapal-error-message">' + errorMessage + '</div>')
                    .insertBefore(form);
                submitButton.prop('disabled', false).val('Make Payment');
            }
        });
    });
}); 