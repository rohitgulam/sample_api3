<?php
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class Pesapal_Payments_List extends WP_List_Table {
    public function __construct() {
        parent::__construct(array(
            'singular' => 'payment',
            'plural' => 'payments',
            'ajax' => false
        ));
    }

    public function get_columns() {
        return array(
            'id' => 'ID',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'email' => 'Email',
            'phone_number' => 'Phone',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'payment_status' => 'Status',
            'created_at' => 'Date'
        );
    }

    public function prepare_items() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'pesapal_payments';

        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table_name");

        $this->set_pagination_args(array(
            'total_items' => $total_items,
            'per_page' => $per_page
        ));

        $this->items = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d OFFSET %d",
                $per_page,
                ($current_page - 1) * $per_page
            ),
            ARRAY_A
        );
    }

    public function column_default($item, $column_name) {
        return $item[$column_name];
    }

    public function column_amount($item) {
        return number_format($item['amount'], 2) . ' ' . $item['currency'];
    }

    public function column_created_at($item) {
        return date('Y-m-d H:i:s', strtotime($item['created_at']));
    }
} 