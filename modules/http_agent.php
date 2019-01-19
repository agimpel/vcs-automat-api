<?php


// prevent this file from being executed directly
defined('ABSPATH') or die();



class HTTP_Agent {

    // relevant variables of this class
    private $is_active = false;
    private $is_valid = false;
    private $logger = null;
    private $timestamp_delta = 30; //sec
    
    // credentials
    private $secret = null;

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

		// try to get the config data
		$path = "/opt/vcs-automat-misc/server/settings.ini";
		try {
			$config_array = parse_ini_file($path);
			if ($config_array == false) {
				// file path is not usable
				$this->logger->error("Could not open settings file at ".$path);
				return;
			}
			if (!isset($config_array["hmac_secret"])) {
				// settings are not set
                $this->logger->error("Relevant settings are not present in config file at ".$path);
                $this->is_active = false;
				return;
			} else {
                $this->secret = $config_array["hmac_secret"];
                $this->is_active = true;
            }
		} catch (Exception $e) {
			// something went wrong upon trying to open the file
            $this->logger->error("Error occurred while trying to read settings file at ".$path);
            $this->is_active = false;
			return;
		}
    }


    public function catch_request() {
        
        $this->logger->info("Received request from {$_SERVER['REMOTE_ADDR']}.");

        // collect data
        $this->data_raw = file_get_contents('php://input');
        $this->data_json = json_decode($this->data_raw, true);

        if (isset($_SERVER['HTTP_X_SIGNATURE'])) {
            $this->logger->debug('Request has signature: '.$_SERVER['HTTP_X_SIGNATURE']);
            $this->signature = $_SERVER['HTTP_X_SIGNATURE'];
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


    public function valid_request() {

        $this->logger->debug('Validating request.');
        $valid = $this->verify_signature() && $this->verify_timestamp() && $this->verify_nonce();
        if ($valid) {
            $this->logger->debug('Request was valid.');
            $this->is_valid = true;
            return true;
        } else {
            $this->logger->info('Request was invalid.');
            $this->is_valid = false;
            return false;
        }
    }


    public function extract_data($keys) {
        if (!$this->is_valid) {
            $this->logger->error('Request is not validated. Dismissing.');
            return null;
        }
        $extracted_data = array();
        foreach ($keys as $key) {
            if (isset($this->data_json[$key])) {
                $extracted_data[$key] = $this->data_json[$key];
            } else {
                $this->logger->error('Key '.$key.' is not present in the json data');
                $extracted_data[$key] = null;
            }
        }
        return $extracted_data;
    }


    public function send_response($code, $data = array()) {
        $this->logger->info('Responding with status code '.$code.'.');

        if (!is_array($data)) {
            $this->logger->error('Provided data is not an array.');
            $this->data_json = array();
        } else {
            $this->data_json = $data;
        }
        $this->data_json['nonce'] = bin2hex(random_bytes(10)).(string)time();
        $this->data_json['timestamp'] = time();
        $this->data_raw = json_encode($this->data_json);
        $this->signature = hash_hmac('sha512', $this->data_raw, $this->secret);

        if (!$this->verify_signature()) {
            $this->logger->error('HTTP Response failed its own signature verification.');
            http_response_code(500); // 500: Internal Server Error
            return;
        }

        header("Content-Type:application/json");
        header("X-SIGNATURE:".$this->signature);
        http_response_code($code);
        echo($this->data_raw);
    }


    private function verify_signature() {
        if (!$this->is_active) {
            // secret has not been set up, dismiss
            $this->logger->info('Dismissing request, as secret is not set up.');
            return false;
        }
        if (is_null($this->signature)) {
            // request does not have a signature, dismiss
            $this->logger->info('Dismissing request, as request does not have a signature.');
            return false;
        }

        // request is only valid if the provided signature matches the hash as calculated by the server
        $target_signature = hash_hmac('sha512', $this->data_raw, $this->secret);
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
            $this->logger->info('Dismissing request, as request does not have a timestamp.');
            return false;
        }
        if (!isset($_SERVER['REQUEST_TIME'])) {
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
            $this->logger->info('Dismissing request, as request does not have a nonce.');
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
            // nonce was used before, dismiss
            $this->logger->warning('Dismissing request, as provided nonce was already known.');
            return false;
        } else {
            // none is new, go ahead
            $this->logger->debug('Nonce verification successful.');
            return true;
        }
    }



}



?>