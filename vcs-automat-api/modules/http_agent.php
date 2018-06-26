<?php


// prevent this file from being executed directly
defined('ABSPATH') or die();



class HTTP_Agent {

    // relevant variables of this class
    private $is_active = false;
    private $logger = null;
    private $timestamp_delta = 3;
    
    // credentials
    private $signature_secret = false;

    // data of HTTP request
    private $data_raw = null;
    private $data_json = null;
    private $signature = null;
    private $timestamp = null;
    private $nonce = null;
    


    public function __construct() {
        // set up of logging
        require_once('../modules/logger.php');
        $this->logger = Logger::instance();
    }


    public function catch_request() {
        
        $this->logger->info("Received request from {$_SERVER['REMOTE_ADDR']}.");

        // collect data
        $this->data_raw = file_get_contents('php://input');
        $this->data_json = json_decode($this->data_raw, true);

        if (isset($SERVER['HTTP_X_SIGNATURE'])) {
            $this->logger->debug('Request has signature: '.$SERVER['HTTP_X_SIGNATURE']);
            $this->signature = $SERVER['HTTP_X_SIGNATURE'];
        }

        if (isset($this->data_json['timestamp'])) {
            $this->logger->debug('Request has timestamp: '.$this->data_json['timestamp']);
            $this->timestamp = $this->data_json['timestamp'];
        }

        if (isset($this->data_json['nonce'])) {
            $this->logger->debug('Request has nonce: '.$this->data_json['nonce']);
            $this->nonce = $this->data_json['nonce'];
        }
        
    }


    private function verify_signature() {
        if (!$this->is_active) {
            // secret has not been set up, dismiss
            $this->logger->info('Dismissing request, as secret is not set up.');
            return false;
        }
        if (is_null($this->signature)) {
            // request does not have a signature, dismiss
            $this->logger->info('Dismissing request, as request does not have a signature');
            return false;
        }

        // request is only valid if the provided signature matches the hash as calculated by the server
        $target_signature = hash_hmac('sha512', $this->data_raw, $this->$secret);
        $comparison_result = hash_equals($target_signature, $this->signature);

        if (!$comparison_result) {
            // signatures are not equal, dismiss
            $this->logger->warning('Dismissing request, as provided signature was invalid.');
            return false;
        } else {
            // signatures are equal, go ahead
            $this->logger->debug('Signature verification successful.');
            return true;
        }
    }



    private function verify_timestamp() {
        if (is_null($this->timestamp)) {
            // request does not have a signature, dismiss
            $this->logger->info('Dismissing request, as request does not have a timestamp');
            return false;
        }
        if (!$isset($_SERVER['REQUEST_TIME'])) {
            // secret has not been set up, dismiss
            $this->logger->info('Dismissing request, as php provides no request time.');
            return false;
        }

        // request is only valid if the provided timestamp is close to the server's time to prevent nonce reuse after database cleaning
        $target_time = $_SERVER['REQUEST_TIME'];
        $comparison_result = ($this->timestamp <= $target_time + $this->timestamp_delta) && ($this->timestamp >= $target_time - $this->timestamp_delta);

        if (!$comparison_result) {
            // timestamps are not close, dismiss
            $this->logger->warning('Dismissing request, as provided timestamp was inaccurate.');
            return false;
        } else {
            // timestamps are close, go ahead
            $this->logger->debug('Timestamp verification successful.');
            return true;
        }
    }


    private function verify_nonce() {
        if (is_null($this->nonce)) {
            // request does not have a nonce, dismiss
            $this->logger->info('Dismissing request, as request does not have a nonce');
            return false;
        }
        try {
            require_once('../modules/sql_interface.php');
            $sqlconn = SQLhandler::instance();
        } catch (Exception $e) {
            $this->logger->error('Could not connect to database: '.$e);
            return false;
        }

        // request is only valid if the provided nonce has never been used before
        $comparison_result = $sqlconn->verify_nonce($this->nonce);

        if (!$comparison_result) {
            // timestamps are not close, dismiss
            $this->logger->warning('Dismissing request, as provided nonce was already known.');
            return false;
        } else {
            // timestamps are close, go ahead
            $this->logger->debug('Nonce verification successful.');
            return true;
        }
    }



}



?>