<?php
require_once "wp-load.php";
global $wpdb;
echo "Statuses Schema:\n";
print_r($wpdb->get_results("SHOW COLUMNS FROM wp_psmsc_statuses"));
echo "\nStatuses Data:\n";
print_r($wpdb->get_results("SELECT id, name, is_active, behavior FROM wp_psmsc_statuses LIMIT 10"));
echo "\nTickets Schema:\n";
print_r($wpdb->get_results("SHOW COLUMNS FROM wp_psmsc_tickets"));
