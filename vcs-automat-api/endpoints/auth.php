<?php

define('ABSPATH', 1);

// set up logging file and logging function
require_once('../modules/logger.php');
$logger = Logger::instance();
$logger->setup('endpoint_auth', 'DEBUG');

// perform verification of HTTP request via the HMAC-SHA512 signature and if valid, read the data
$verified_data = require_once('../modules/request_verification.php');
$logger->debug('Attempting authentication.');


// check if a rfid is provided in the POST data
if (!isset($verified_data['rfid'])) {
	$logger->warning('Auth denied: No rfid provided.');
	http_response_code(400);
	exit("Bad Request\n");
}

// check if the rfid string contains only digits
if (preg_match('#[^0-9]#', $verified_data['rfid'])) {
	$logger->warning('Auth denied: Provided rfid contains non-digits.');
	http_response_code(400);
	exit("Bad Request\n");
}

// check if the rfid string has the correct length
if (strlen($verified_data['rfid']) != 6) {
	$logger->warning('Auth denied: Provided rfid does not have correct length.');
	http_response_code(400);
	exit("Bad Request\n");
}

$logger->info('Auth proceeds: Provided rfid = '.$verified_data['rfid'].' passed checks.');
$rfid = $verified_data['rfid'];


// check if the provided rfid is registered in the database. If not, respond with HTTP status 404, otherwise print the information about user as JSON
require_once('../modules/sql_interface.php');
$db = SQLhandler::instance();
$db_result = $db->search_rfid($rfid);
if ($db_result == False) {
	$logger->warning('Auth denied: Provided rfid was unknown');
	http_response_code(404);
	exit("RFID unknown\n");
} else {
	$logger->info('Auth succeeded: Provided rfid was known.');
	echo(json_encode($db_result));
}


exit();

?>
