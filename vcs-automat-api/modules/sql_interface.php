<?php


// prevent this file from being executed directly
defined('ABSPATH') or die();



class SQLhandler {

	// the instance of this class
	private static $_instance = null;

	// return or create the single instance of this class
	public static function instance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	
	private $SQLconn;
	private $users_table = "users";
	private $archive_table = "archive";
	private $nonce_table = "nonces";
	private $settings_table = "settings";
	private $logger = null;
	private $is_active = false;

	

	public function __construct() {
		if (defined('VCS_AUTOMAT_PLUGIN_DIR')) {
			require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/logger.php');
		} else {
			require_once('../modules/logger.php');
		}
		$this->logger = Logger::instance();
		
		// try to connect to the SQL database
		$this->SQLconn = new mysqli("localhost", "vcs_automat", "password", "vcs_automat");
		if ($this->SQLconn->connect_errno) {
			$this->logger->error("Failed to connect to SQL database: (" . $this->SQLconn->connect_errno . ") " . $this->SQLconn->connect_error);
		} else {
			$this->is_active = true;
		}
	}

	public function __destruct() {
		$this->SQLconn->close();
	}


	public function search_rfid($rfid) {
		$to_fetch = array('uid','credits','rfid');
		$rfid = $this->SQLconn->escape_string($rfid);
		$res = $this->SQLconn->query("SELECT ".implode(",", $to_fetch)." FROM ".$this->users_table." WHERE rfid = '".$rfid."'");
		if ($res == False) {
			$this->logger->info('Entry not found in database.');
			return False;
		} else {
			return $res->fetch_assoc();
		}
	}


	public function search_uid($uid) {
		$to_fetch = array('uid','credits','rfid');
		$uid = $this->SQLconn->escape_string($uid);
		$res = $this->SQLconn->query("SELECT ".implode(",", $to_fetch)." FROM ".$this->users_table." WHERE uid = '".$uid."'");
		if ($res == False) {
			$this->logger->info('Entry not found in database.');
			return False;
		} else {
			return $res->fetch_assoc();
		}
	}


	public function add_user($uid, $credits, $rfid) {
		$target_columns = array('uid', 'credits', 'rfid');
		$target_data = array($uid, $credits, $rfid);
		foreach ($target_data as $key => $value) {
			$target_data[$key] = $this->SQLconn->escape_string($value);
		}
		$res = $this->SQLconn->query("INSERT INTO ".$this->users_table." (".implode(",", $target_columns).") VALUES ('".implode("','", $target_data)."')");

		if ($res == False) {
			$this->logger->error('Could not add a new user. uid = '.$uid.', credits = '.$credits.', rfid = '.$rfid);
			return False;
		} else {
			return True;
		}
	}


	public function change_rfid($uid, $new_rfid) {
		$uid = $this->SQLconn->escape_string($uid);
		$new_rfid = $this->SQLconn->escape_string($new_rfid);
		$res = $this->SQLconn->query("UPDATE ".$this->users_table." SET rfid = '".$new_rfid."' WHERE uid = '".$uid."'");

		if ($res == False) {
			$this->logger->error('Could not change a RFID. uid = '.$uid.', new_rfid = '.$new_rfid);
			return False;
		} else {
			return True;
		}
	}


	public function delete_user($uid) {
		$uid = $this->SQLconn->escape_string($uid);
		$res = $this->SQLconn->query("DELETE FROM ".$this->users_table." WHERE uid = '".$uid."'");

		if ($res == False) {
			$this->logger->error('Could not delete a user from the users table. uid = '.$uid);
			return False;
		}

		$res = $this->SQLconn->query("DELETE FROM ".$this->archive_table." WHERE uid = '".$uid."'");

		if ($res == False) {
			$this->logger->error('Could not delete a user from the archive table. uid = '.$uid);
			return False;
		}

		return True;
	}


	public function set_settings($new_settings, $settings_array) {
		foreach ($settings_array as $key => $fields) {
			if (isset($new_settings[$key])) {
				$new_setting = $this->SQLconn->escape_string($new_settings[$key]);
				$slug = $fields['slug'];
				$res = $this->SQLconn->query("UPDATE ".$this->settings_table." SET value = '".$new_setting."' WHERE name = '".$slug."'");
				if ($this->SQLconn->errno) {
					$this->logger->error('CRITICAL: Setting '.$slug.' could not be updated in database. Error code: '.$this->SQLconn->errno);
				} else {
					$this->logger->debug('Setting '.$slug.' updated in database.');
				}
			} else {
				continue;
			}
		}
	}



	public function report_rfid($rfid) {
		$to_fetch = array('uid','credits','rfid');
		$rfid = $this->SQLconn->escape_string($rfid);
		$res = $this->SQLconn->query("SELECT ".implode(",", $to_fetch)." FROM ".$this->users_table." WHERE rfid = '".$rfid."'");

		if ($res == False) {
			$this->logger->error('CRITICAL: Entry not found in database.');
			return False;
		} else {
			$data = $res->fetch_assoc();
		}

		if ((int) $data['credits'] <= 0) {
			$this->logger->error('CRITICAL: Credits equal or less than Zero. uid = '.$data['uid'].', credits = '.$data['credits'].', rfid = '.$data['rfid']);
			return False;
		}

		$new_credits = (int) $data['credits'] - 1;
		$res = $this->SQLconn->query("UPDATE ".$this->users_table." SET credits = ".$new_credits." WHERE rfid = '".$rfid."'");

		if ($res == False) {
			$this->logger->error('CRITICAL: Entry could not be updated in database.');
			return False;
		} else {
			return $data;
		}
	}


	public function archive_usage($uid, $slot, $time) {
		$target_columns = array('uid', 'slot', 'time');
		$target_data = array($uid, $slot, $time);
		foreach ($target_data as $key => $value) {
			$target_data[$key] = $this->SQLconn->escape_string($value);
		}
		$res = $this->SQLconn->query("INSERT INTO ".$this->archive_table." (".implode(",", $target_columns).") VALUES ('".implode("','", $target_data)."')");

		if ($res == False) {
			$this->logger->error('Could not add a usage record.');
			return False;
		} else {
			return True;
		}
	}



	public function verify_nonce($nonce) {
		$nonce = $this->SQLconn->real_escape_string($nonce);
		$result = $this->SQLconn->query("SELECT * FROM ".$this->nonce_table." WHERE nonce = '$nonce'");

		if (is_null($result)) {
			$this->logger->info('No database connection.');
			return false;
		} elseif ($result->num_rows == 0) {
			$this->logger->debug('Provided nonce was not yet known.');
			$this->SQLconn->query("INSERT INTO ".$this->nonce_table." (nonce, timestamp) VALUES ('$nonce', ".time().")");
			return true;
		} else {
			$this->logger->info('Provided nonce was already known.');
			return false;
		}
	}


	public function get_setting($key) {
		$key = $this->SQLconn->real_escape_string($key);
		$result = $this->SQLconn->query("SELECT value FROM ".$this->settings_table." WHERE name = '".$key."'");
		if (!$result || $result->num_rows == 0) {
			$this->logger->info('Setting '.$key.' could not be fetched.');
			return null;
		} else {
			$this->logger->debug('Setting '.$key.' was fetched.');
			$value = $result->fetch_assoc();
			return $value['value'];
		}
	}


}



?>
