<?php
require_once 'wp-load.php';

// Turn on stackboost modules
$options = get_option( 'stackboost_settings', [] );
$options['modules'] = ['ticket_metrics' => 1];
update_option( 'stackboost_settings', $options );
