<?php
class Pesapal_DB {
    private $wpdb;
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $wpdb->prefix . 'pesapal_payments';
    }

    public function insert_payment($payment_data) {
        $result = $this->wpdb->insert(
            $this->table_name,
            array(
                'first_name' => $payment_data['first_name'],
                'last_name' => $payment_data['last_name'],
                'email' => $payment_data['email'],
                'phone_number' => $payment_data['phone_number'],
                'amount' => $payment_data['amount'],
                'currency' => $payment_data['currency'],
                'transaction_id' => $payment_data['transaction_id'],
                'payment_status' => $payment_data['payment_status']
            ),
            array(
                '%s', '%s', '%s', '%s', '%f', '%s', '%s', '%s'
            )
        );

        return $result ? $this->wpdb->insert_id : false;
    }

    public function update_payment_status($transaction_id, $status) {
        return $this->wpdb->update(
            $this->table_name,
            array('payment_status' => $status),
            array('transaction_id' => $transaction_id),
            array('%s'),
            array('%s')
        );
    }

    public function get_payment($transaction_id) {
        return $this->wpdb->get_row(
            $this->wpdb->prepare(
                "SELECT * FROM {$this->table_name} WHERE transaction_id = %s",
                $transaction_id
            )
        );
    }
} 