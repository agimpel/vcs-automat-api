<?php

define('ABSPATH', 1);

// set up logging file and logging function
require_once('../modules/logger.php');
$logger = Logger::instance();
$logger->setup('endpoint_auth', 'DEBUG');

// exit if the API is disabled
require_once('../modules/sql_interface.php');
$db = SQLhandler::instance();
$enabled = $db->get_setting('api_active');
if ($enabled !== '1') {
	$logger->info('Dismissing, API is disabled.');
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

// get provided rfid and check its validity
$request_data = $request->extract_data(array('rfid'));
$rfid = $request_data['rfid'];
if (is_null($rfid)) {
	$logger->error('RFID was not provided in request.');
	$response = new HTTP_Agent();
	$response->send_response(400); //400: Bad Request
	exit(); // Execution stops here
}

// proceed with verification against database
$logger->info('Auth proceeds: Provided rfid = '.$rfid.' passed checks.');
require_once('../modules/sql_interface.php');
$db = SQLhandler::instance();
$db_result = $db->search_rfid($rfid);
if ($db_result == False) {
	$logger->info('Auth denied: Provided rfid was unknown');
	$response = new HTTP_Agent();
	$response->send_response(401); //401: Unauthorized
	exit(); // Execution stops here
} else {
	$logger->info('Auth succeeded: Provided rfid was known.');
	$response = new HTTP_Agent();
	$response->send_response(200, $db_result); //200: OK
	exit(); // Execution stops here
}

?>