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
	private $logger = null;

	public function __construct() {
		require_once('../modules/logger.php');
		$this->logger = Logger::instance();
		
		// try to connect to the SQL database
		$this->SQLconn = new mysqli("localhost", "vcs_automat", "password", "vcs_automat");
		if ($this->SQLconn->connect_errno) {
			$this->logger->error("Failed to connect to SQL database: (" . $this->SQLconn->connect_errno . ") " . $this->SQLconn->connect_error);
			http_response_code(400);
			exit("Bad Request\n");
		}
	}

	public function __destruct() {
		$this->SQLconn->close();
	}


	public function search_rfid($rfid) {
		$to_fetch = array('nethz','legi','credits','rfid');
		$query = $this->SQLconn->escape_string("SELECT ".implode(",", $to_fetch)." FROM ".$this->users_table." WHERE rfid = ".$this->check_rfid($rfid));
		$res = $this->SQLconn->query($query);
		if ($res == False) {
			$this->logger->info('Entry not found in database.');
			return False;
		} else {
			return $res->fetch_assoc();
		}
	}


	public function add_user($nethz, $legi, $credits, $rfid) {
		$target_columns = array('nethz', 'legi', 'credits', 'rfid');
		$target_data = array($nethz, $legi, $credits, $rfid);
		$query = $this->SQLconn->escape_string("INSERT INTO ".$this->users_table." (".implode(",", $target_columns).") VALUES (".implode(",", $target_data).")");
		$res = $this->SQLconn->query($query);

		if ($res == False) {
			$this->logger->error('Could not add a new user. nethz = '.$nethz.', legi = '.$legi.', credits = '.$credits.', rfid = '.$rfid);
			return False;
		} else {
			return True;
		}
	}


	public function change_rfid($legi, $new_rfid) {
		$query = $this->SQLconn->escape_string("UPDATE ".$this->users_table." SET rfid = ".$this->check_rfid($new_rfid)." WHERE legi = ".$legi);
		$res = $this->SQLconn->query($query);

		if ($res == False) {
			$this->logger->error('Could not change a RFID. legi = '.$legi.', new_rfid = '.$new_rfid);
			return False;
		} else {
			return True;
		}
	}


	public function delete_user($legi) {
		$query = $this->SQLconn->escape_string("DELETE FROM ".$this->users_table." WHERE legi = ".$legi);
		$res = $this->SQLconn->query($query);

		if ($res == False) {
			$this->logger->error('Could not delete a user from the users table. legi = '.$legi);
			return False;
		}

		$query = $this->SQLconn->escape_string("DELETE FROM ".$this->archive_table." WHERE legi = ".$legi);
		$res = $this->SQLconn->query($query);

		if ($res == False) {
			$this->logger->error('Could not delete a user from the archive table. legi = '.$legi);
			return False;
		}

		return True;
	}


	public function report_rfid($rfid) {
		$to_fetch = array('nethz','legi','credits','rfid');
		$query = $this->SQLconn->escape_string("SELECT ".implode(",", $to_fetch)." FROM ".$this->users_table." WHERE rfid = ".$this->check_rfid($rfid));
		$res = $this->SQLconn->query($query);

		if ($res == False) {
			$this->logger->error('CRITICAL: Entry not found in database.');
			return False;
		} else {
			$data = $res->fetch_assoc();
		}

		if ((int) $data['credits'] <= 0) {
			$this->logger->error('CRITICAL: Credits equal or less than Zero. nethz = '.$data['nethz'].', legi = '.$data['legi'].', credits = '.$data['credits'].', rfid = '.$data['rfid']);
			return False;
		}

		$new_credits = (int) $data['credits'] - 1;
		$query = $this->SQLconn->escape_string("UPDATE ".$this->users_table." SET credits = ".$new_credits." WHERE rfid = ".$this->check_rfid($rfid));
		$res = $this->SQLconn->query($query);

		if ($res == False) {
			$this->logger->error('CRITICAL: Entry could not be updated in database.');
			return False;
		} else {
			return $data;
		}
	}


	public function archive_usage($legi, $slot, $time) {
		$target_columns = array('legi', 'slot', 'time');
		$target_data = array($legi, $slot, $time);
		$query = $this->SQLconn->escape_string("INSERT INTO ".$this->archive_table." (".implode(",", $target_columns).") VALUES (".implode(",", $target_data).")");
		$res = $this->SQLconn->query($query);

		if ($res == False) {
			$this->logger->error('Could not add a usage record.');
			return False;
		} else {
			return True;
		}
	}



	private function check_rfid($rfid) {
		if (preg_match('#[^0-9]#', $rfid) || (strlen($rfid) != 6)) {
			$this->logger->warning('Database access denied: Provided rfid does not fulfill constraints.', 2);
			http_response_code(400);
			exit("Bad Request\n");
		} else {
			return $rfid;
		}
	}



}



?>
