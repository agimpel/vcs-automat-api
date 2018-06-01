<?php

require_once('../modules/logger.php');

// the shared secret, used to sign the POST data (using HMAC with SHA512)
// Not documented anywhere, generate random password with high entropy and set as environment variable on server via httpd.conf
// if (getenv('github_webhook_secret') != '') {
// 	$secret = getenv('github_webhook_secret');
// } else {
// 	log_msg('Environment variable with secret is empty! Request denied.', 3);
// 	http_response_code(403);
// 	die("Forbidden\n");
// }

$secret = '1234';

$post_data = file_get_contents('php://input');
$signature = hash_hmac('sha512', $post_data, $secret);

log_msg("\n\n=== Received request from {$_SERVER['REMOTE_ADDR']} at ".date('d.m.Y H:i:s')." ===", 3);


$data = json_decode($post_data, true);


// check if request is signed
if (!isset($_SERVER['HTTP_X_SIGNATURE'])) {
  log_msg('Request denied: No signature.', 2);
  http_response_code(403);
  exit("Forbidden\n");
}

// check if request is correctly signed
if (!hash_equals($signature, $_SERVER['HTTP_X_SIGNATURE']) ) {
  log_msg('Request denied: Signature hash does not match.', 2);
  http_response_code(403);
  exit("Forbidden\n");
}

// check if request has correct fields
if (!($_SERVER['REQUEST_METHOD'] === 'POST')) {
  log_msg('Request denied: Not a POST request.', 2);
  http_response_code(403);
  exit("Forbidden\n");
}

log_msg('Request accepted: All checks passed.', 2);
return $data;

?>
