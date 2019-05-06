<?php

define('ABSPATH', 1); // required so that other scripts are not killed

// set up logging file and logging function
require_once('../modules/logger.php');
$logger = Logger::instance();
$logger->setup('endpoint_info', 'INFO');

// exit if the API is disabled
require_once('../modules/sql_interface.php');
$db = SQLhandler::instance();
$enabled = $db->get_setting('api_active');
if ($enabled !== '1') {
	$logger->debug('Dismissing, API is disabled.');
	require_once('../modules/http_agent.php');
	$response = new HTTP_Agent();
	$response->send_response(503); //503: Service Unavailable
	exit(); // Execution stops here
}

// catch HTTP request
require_once('../modules/http_agent.php');
$request = new HTTP_Agent();
$request->catch_request();

// if request is invalid based on signature, timestamp and nonce: dismiss
if (!$request->valid_request()) {
	$logger->info('Dismissing request based on invalidity.');
	$response = new HTTP_Agent();
	$response->send_response(403); //403: Forbidden
	exit(); // Execution stops here
}

// return information about API
$logger->info('Info request valid.');
require_once('../modules/sql_interface.php');
$db = SQLhandler::instance();
$db_result = array();
foreach (array('last_reset', 'next_reset', 'standard_credits', 'reset_interval') as $setting) {
	$db_result[$setting] = $db->get_setting($setting);
}
$logger->debug('Query for settings succeeded.');
$response = new HTTP_Agent();
$response->send_response(200, $db_result); //200: OK
exit(); // Execution stops here
?>