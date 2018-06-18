<?php


// prevent this file from being executed directly
defined('ABSPATH') or die();



class Logger {

	// the instance of this class
	private static $_instance = null;

	// return or create the single instance of this class
	public static function instance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	// relevant class variables
	private $is_active = false;
	private $log_dir = null;
	private $log = null;
	private $possible_loglevels = array('ERROR' => 1, 'WARNING' => 2, 'INFO' => 3, 'DEBUG' => 4);
	private $loglevel = 'DEBUG'; //default




	public function setup($log, $loglevel, $dir = "/var/vcs-automat/logs") {

		if (!is_dir($dir)) {
			// file path for logs is not a directory
			return;
		}

		if (array_key_exists($loglevel, $this->possible_loglevels)) {
			$this->loglevel = $loglevel;
		}

		$this->log_dir = $dir;
		$log = preg_replace('/[^a-z0-9]+/', '-', strtolower($log));

		try {
			$file = fopen($this->log_dir.'/'.$log.'.log', 'w');
			if ($file == false) {
			// file path for this log is not usable
			return;
			}
		} catch (Exception $e) {
			// something went wrong upon trying to open the file
			return;
		}

		fclose($file);
		$this->log = $this->log_dir.'/'.$log.'.log';
		$this->is_active = true;
	}



	public function debug($msg) {
		$this->log_string($msg, 'DEBUG');
	}

	public function info($msg) {
		$this->log_string($msg, 'INFO');
	}

	public function warning($msg) {
		$this->log_string($msg, 'WARNING');
	}

	public function error($msg) {
		$this->log_string($msg, 'ERROR');
	}


	private function log_string($msg, $level) {

		if (!$this->is_active) {
			// the logger is not activated, dismiss
			return;
		}

		if ($this->possible_loglevels[$level] > $this->possible_loglevels[$this->loglevel]) {
			// the call's loglevel is too high, it is too unimportant
			return;
		}


		$trace = debug_backtrace(2, $limit = 4);
		$function = false;
		$class = false;
		if (isset($trace[3])) {
			$caller = $trace[3];
			$function = $caller['function'];
			if (isset($caller['class'])) {
			$class = $caller['class'];
			}
		}
		$call_info = '';
		if ($class) {
			$call_info = $call_info.$class.': ';
		}
		if ($function) {
			$call_info = $call_info.$function;
		}


		$call_info = '['.$class_info.']';
		$level = '['.$level.']';

		file_put_contents($this->log, $level.$call_info." ".$msg."\n", FILE_APPEND);
	}


}

?>