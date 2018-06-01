<?php

// set up logging file and logging function
define('LOGFILE', '../logs/auth.php.log');
require_once('../modules/logger.php');

// perform verification of HTTP request via the HMAC-SHA512 signature and if valid, read the data
$verified_data = require_once('../modules/request_verification.php');


print_r($verified_data);







?>
