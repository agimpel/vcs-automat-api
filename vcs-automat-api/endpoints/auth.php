<?php

// set up logging file and logging function
define('LOGFILE', '../logs/auth.php.log');
require_once('../modules/logger.php');

// perform verification of HTTP request via the HMAC-SHA512 signature and if valid, read the data
$verified_data = require_once('../modules/request_verification.php');
log_msg('Attempting authentication.', 2);


// check if a rfid is provided in the POST data
if (!isset($verified_data['rfid'])) {
  log_msg('Auth denied: No rfid provided.', 2);
  http_response_code(400);
  exit("Bad Request\n");
}

// check if the rfid string contains only digits
if (preg_match('#[^0-9]#', $verified_data['rfid'])) {
  log_msg('Auth denied: Provided rfid contains non-digits.', 2);
  http_response_code(400);
  exit("Bad Request\n");
}

// check if the rfid string has the correct length
if (strlen($verified_data['rfid']) != 6) {
  log_msg('Auth denied: Provided rfid does not have correct length.', 2);
  http_response_code(400);
  exit("Bad Request\n");
}

log_msg('Auth proceeds: Provided rfid = '.$verified_data['rfid'].' passed checks.', 2);
$rfid = $verified_data['rfid'];


// check if the provided rfid is registered in the database. If not, respond with HTTP status 404, otherwise print the information about user as JSON
require_once('../modules/sql_interface.php');
$db = new SQLhandler;
$db_result = $db->search_rfid($rfid);
if ($db_result == False) {
  log_msg('Auth denied: Provided rfid was unknown', 2);
  http_response_code(404);
  exit("RFID unknown\n");
} else {
  log_msg('Auth succeeded: Provided rfid was known.', 2);
  http_response_code(200);
  echo(json_encode($db_result));
}


exit();

?>
