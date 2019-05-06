<?php

define('ABSPATH', 1); // required so that other scripts are not killed

// set up logging file and logging function
require_once('../modules/logger.php');
$logger = Logger::instance();
$logger->setup('endpoint_report', 'INFO');

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

// get provided rfid and slot and check their validity
$request_data = $request->extract_data(array('rfid', 'slot'));
$rfid = $request_data['rfid'];
$slot = $request_data['slot'];
if (is_null($rfid)) {
	$logger->error('RFID number was not provided in request.');
	$response = new HTTP_Agent();
	$response->send_response(400); //400: Bad Request
	exit(); // Execution stops here
}
if (is_null($slot) || preg_match('#[^0-9]#', $slot)) {
	$logger->error("Slot number ".$slot." was invalid in request.");
	$slot = -1;
}

// proceed with reporting of vending
$logger->info('Report proceeds: Provided rfid = '.$rfid.' passed checks.');
require_once('../modules/sql_interface.php');
$db = SQLhandler::instance();
$db->archive_usage($slot, time());
$db_result = $db->report_rfid($rfid);
if ($db_result == False) {
	$logger->warning('Report failed.');
	$response = new HTTP_Agent();
	$response->send_response(500); //500: Internal Server Error
	exit(); // Execution stops here
} else {
	$logger->debug('Report succeeded.');
	$response = new HTTP_Agent();
	$response->send_response(200); //200: OK
	exit(); // Execution stops here
}
?>