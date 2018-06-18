<?php

define('ABSPATH', 1);

// set up logging file and logging function
require_once('../modules/logger.php');
$logger = Logger::instance();
$logger->setup('endpoint_report', 'DEBUG');

// perform verification of HTTP request via the HMAC-SHA512 signature and if valid, read the data
$verified_data = require_once('../modules/request_verification.php');
$logger->debug('Attempting reporting.');


// check if a rfid is provided in the POST data
if (!isset($verified_data['rfid'])) {
	$logger->warning('Report denied: No rfid provided.');
  	http_response_code(400);
  	exit("Bad Request\n");
}

// check if the rfid string contains only digits
if (preg_match('#[^0-9]#', $verified_data['rfid'])) {
	$logger->warning('Report denied: Provided rfid contains non-digits.');
	http_response_code(400);
	exit("Bad Request\n");
}

// check if the rfid string has the correct length
if (strlen($verified_data['rfid']) != 6) {
	$logger->warning('Report denied: Provided rfid does not have correct length.');
	http_response_code(400);
	exit("Bad Request\n");
}

$logger->info('Report proceeds: Provided rfid = '.$verified_data['rfid'].' passed checks.');
	$rfid = $verified_data['rfid'];
	$time = time();
	if (isset($verified_data['slot']) || !preg_match('#[^0-9]#', $verified_data['slot'])) {
		$slot = $verified_data['slot'];
	} else {
		$slot = "-1";
}


// check if the provided rfid is registered in the database. If not, respond with HTTP status 404, otherwise print the information about user as JSON
require_once('../modules/sql_interface.php');
$db = new SQLhandler;
$db_result = $db->report_rfid($rfid);
if ($db_result == False) {
	$logger->warning('Report failed: Request could not be processed.');
	http_response_code(500);
	exit("Internal Server Error\n");
} else {
	$legi = $db_result['legi'];
	$logger->warning('Report succeeded: Request could be processed.');
	http_response_code(201);
}

$db->archive_usage($legi, $slot, $time);


exit();

?>
