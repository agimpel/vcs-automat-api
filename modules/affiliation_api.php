<?php


// prevent this file from being executed directly
defined('ABSPATH') or die();



class Affiliation_API {

	// the instance of this class
	private static $_instance = null;

	// return or create the single instance of this class
	public static function instance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	
    private $api_url = null;
    private $api_key = null;
	private $logger = null;

	

	public function __construct() {
		if (defined('VCS_AUTOMAT_PLUGIN_DIR')) {
			require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/logger.php');
		} else {
			require_once('../modules/logger.php');
		}
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
			if (!(isset($config_array["api_url"]) && isset($config_array["api_key"]))) {
				// settings are not set
				$this->logger->error("Relevant settings are not present in config file at ".$path);
				return;
			} else {
                $this->api_url = $config_array["api_url"];
                $this->api_key = $config_array["api_key"];
            }
		} catch (Exception $e) {
			// something went wrong upon trying to open the file
			$this->logger->error("Error occurred while trying to read settings file at ".$path);
			return;
		}
	}


    private function get_request($url) {
        
        $this->logger->debug("Posting GET request to {$url}.");

        $request_headers = array();
        $request_headers[] = 'X-API-Key: '. $this->api_key;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $request_headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response_body = curl_exec($ch);

        if($response_body === false) {
            $this->logger->error("GET request to {$url} was not successful.");
            return null;
        }
        return json_decode($response_body, true);
    }


    public function is_affiliated($uid) {
        
        $this->logger->debug("Checking affiliation of {$uid}.");

        $url = $this->api_url.'/'.$uid;
        $answer = $this->get_request($url);

        if(isset($answer['is_vcs_member']) && $answer['is_vcs_member'] == 'true') {
            $this->logger->debug("UID {$uid} is affiliated.");
            return true;
        }
        $this->logger->debug("UID {$uid} is not affiliated.");
        return false;
    }

}



?>