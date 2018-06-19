<?php


// prevent this file from being executed directly
defined('ABSPATH') or die();



require_once('../modules/logger.php');
$logger = Logger::instance();

// the shared secret, used to sign the POST data (using HMAC with SHA512)
// Not documented anywhere, generate random password with high entropy and set as environment variable on server via httpd.conf
// if (getenv('github_webhook_secret') != '') {
// 	$secret = getenv('github_webhook_secret');
// } else {
// 	$logger->debug('Environment variable with secret is empty! Request denied.');
// 	http_response_code(403);
// 	die("Forbidden\n");
// }

$secret = '1234';

$post_data = file_get_contents('php://input');
$signature = hash_hmac('sha512', $post_data, $secret);

$logger->info("Received request from {$_SERVER['REMOTE_ADDR']} at ".date('d.m.Y H:i:s'));

// check if request is signed
if (!isset($_SERVER['HTTP_X_SIGNATURE'])) {
	$logger->warning('Request denied: No signature.', 2);
	http_response_code(403);
	exit("Forbidden\n");
}

// check if request is correctly signed
if (!hash_equals($signature, $_SERVER['HTTP_X_SIGNATURE']) ) {
	$logger->warning('Request denied: Signature hash does not match.');
	http_response_code(403);
	exit("Forbidden\n");
}

// check if request has correct fields
if (!($_SERVER['REQUEST_METHOD'] === 'POST')) {
	$logger->warning('Request denied: Not a POST request.');
	http_response_code(403);
	exit("Forbidden\n");
}

$logger->info('Request accepted: All checks passed.');
return json_decode($post_data, true);

?>
