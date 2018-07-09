<?php


// prevent this file from being executed directly
defined('ABSPATH') or die();



class Shortcode_Page extends VCS_Automat {

    // the instance of this class
	private static $_instance = null;

	// return or create the single instance of this class
	public static function instance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
    }
	
	// data of user
	private $userdata = array(
		'registered' => false, // is the user known to the users table
		'uid' => null, // uid in database
		'rfid' => null, // rfid in database
		'credits' => null, // remaining credits
		'total_consumption' => null, // total number of used credits
		'consumption_data' => array() // usage-time data
	);

	private $messageboxes = array();

    // constructor empty to override the parent class constructor
    public function __construct() {
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/logger.php');
		$this->logger = Logger::instance();
		$this->logger->setup('wordpress', 'DEBUG');
		$this->logger->debug('Wordpress plugin enabled.');
    }


    public function vcs_automat() {

		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$enabled = $db->get_setting('frontend_active');
		if($enabled != '1') {
			$this->logger->debug('Frontend is deactivated, show error page.');
			$this->show_html_deactivated();
			return;
		}

		if(isset($_GET['page']) && $_GET['page'] == 'telegrambot') {
			$this->show_html_telegrambot();
			return;
		}

		if(!is_user_logged_in()) {
			$this->logger->debug('User is not logged in, show login notice page.');
			$this->show_html_notloggedin();
			return;
		}


		$this->logger->debug('Frontend is activated and user is logged in, collect data and show page.');
		$this->prepare_data();

		if(isset($_GET['page']) && $_GET['page'] == 'tracking') {
			$this->show_html_active_tracking();
			return;
		}
		
		$this->show_html_active_main();
		


	}


	private function get_uid() {
		$user = wp_get_current_user();
		$uid = $user->user_login;
		return $uid;
	}


	private function prepare_data() {
		$uid = $this->get_uid();
		$this->userdata['uid'] = $uid;
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$result = $db->search_uid($uid);

		if($result == false) {
			$this->logger->debug('User with UID '.$uid.' is not registered.');
			$this->userdata['registered'] = false;
			return;
		}

		$this->logger->debug('User with UID '.$uid.' is registered.');
		$this->userdata['registered'] = true;
		$this->userdata['credits'] = $result['credits'];
		$this->userdata['rfid'] = $result['rfid'];

		return;
	}

	private function show_html_generic_header() {
		?>

		<div id="vcs_automat-title"><h1>VCS-Bierautomat</h1></div>
		<div id="vcs_automat-subtitle">Der VCS-Bierautomat steht im HXE und erlaubt den Studierenden der VCS regelmässig kostenlos Bier zu trinken!</div>

		<?php
	}

	private function show_html_notloggedin() {
		$this->show_html_generic_header();
		?>
		<br><br>
		<div id="vcs_automat-notloggedin">Bitte <a href="/login/">einloggen</a>, um dich für den Bierautomaten anzumelden und deine Statistik zu sehen!</div>
		<br>
		<div id="vcs_automat-notloggedin-button"><a href="/login/">Zum Login</a></div>

		<?php
	}


	private function show_html_deactivated() {
		$this->show_html_generic_header();
		?>

		<br><br>
		<div id="vcs_automat-deactivated"><strong>Die Anmeldung für den VCS-Bierautomaten ist momentan deaktiviert.</strong></div>

		<?php
	}



	private function set_rfid($rfid) {

		if(is_null($this->userdata['uid'])) {
			$this->logger->error('Attempted to set rfid, but uid is not set.');
			return false;
		}

		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$result = $db->search_uid($this->userdata['uid']);
		if(!$result) {
			$this->logger->debug('Set rfid: uid is not known, insert into database.');
			$standard_credits = $db->get_setting('standard_credits');
			$result = $db->add_user($this->userdata['uid'], $standard_credits, $rfid);
		} else {
			$this->logger->debug('Set rfid: uid is known, update in database.');
			$result = $db->change_rfid($this->userdata['uid'], $rfid);
		}
		$this->logger->debug('Submission of new rfid finished -> '.(string)$result);
		return $result;
	}


	private function attach_message_box($type, $text) {
		$this->messageboxes[] = array('type' => $type, 'text' => $text);
	}


	private function print_message_boxes() {
		foreach ($this->messageboxes as $message) { ?>
			<div class="vcs_automat-messagebox vcs_automat-messagebox-<?php echo($message['type']); ?>">
			<?php echo($message['text']); ?>
			<?php if($message['type'] == 'failure') { ?>
				<br>Sollte dieser Fehler unerwartet sein, kontaktiere uns unter <a href="mailto:bierko@vcs.ethz.ch">bierko@vcs.ethz.ch</a>.
			<?php } ?>
			</div>
		<?php }
	}




	private function show_html_telegrambot() {
		$this->print_message_boxes();
		$this->show_html_generic_header();
	}



	private function show_html_active_tracking() {

		// process post data
		if (isset($_POST['vcs_automat-tracking'])) {
			$this->logger->debug('Switch of tracking setting was invoked.');
			if (isset($_POST['vcs_automat-tracking-switch-nonce']) && wp_verify_nonce($_POST['vcs_automat-tracking-switch-nonce'], 'vcs_automat-tracking-switch')) {
				$this->logger->debug('Nonce is valid, continue with switching of setting.');

			} else {

			}
		} 

		$this->print_message_boxes();
		$this->show_html_generic_header();
	}



	private function show_html_active_main() {

		// process post data
		if (isset($_POST['vcs_automat-rfid'])) {
			$this->logger->debug('Submitting of new rfid was invoked.');
			if (isset($_POST['vcs_automat-set-rfid-nonce']) && wp_verify_nonce($_POST['vcs_automat-set-rfid-nonce'], 'vcs_automat-set-rfid')) {
				$this->logger->debug('Nonce is valid, continue with submission of new rfid.');
				if($this->set_rfid($_POST['vcs_automat-rfid'])) {
					$this->prepare_data();
					$this->attach_message_box('success', 'Identifikationsnummer der Legi erfolgreich geändert.');
				} else {
					$this->attach_message_box('failure', 'Beim Ändern der Identifikationsnummer der Legi ist ein Fehler aufgetreten.');
				}
			} else {
				$this->logger->info('Nonce for submission of new rfid was invalid.');
			}
		} 


		$this->print_message_boxes();
		$this->show_html_generic_header();


		?>

		<br><br>

		<div id="vcs_automat-registration-title"><h2>Registrierung</h2></div>
		<div id="vcs_automat-registration-info">
			<?php if (!$this->userdata['registered']) { ?>
				Deine Legi ist noch nicht für den Bierautomaten registriert. Hier kannst du die Identifikationsnummer deiner Legi eintragen, um den Bierautomaten verwenden zu können.
			<?php } else { ?>
				Deine Legi ist bereits für den Bierautomaten registriert. Du kannst hier aber die Identifikationsnummer deiner Legi ändern, z.B. wenn du eine neue Legi erhalten hast.
			<?php } ?>

		<form action="<?php echo($_SERVER['REQUEST_URI']); ?>" method="post">
		<div>
		<label for="vcs_automat-rfid">ID: </label>
		<input type="text" name="vcs_automat-rfid" placeholder="Deine Legi-RFID"></input>
		</div>
		<input type="submit" name="submit" value="Registrieren"></input>
		<?php wp_nonce_field('vcs_automat-set-rfid', 'vcs_automat-set-rfid-nonce'); ?>
		</form>
		</div>

		<?php if ($this->userdata['registered']) { ?>
		<br><br>
		<div id="vcs_automat-statistics-title"><h2>Statistik</h2></div>
		<div id="vcs_automat-statistics-info">
			<?php if (!is_null($this->userdata['credits'])) { ?>
				Guthaben: <?php echo($this->userdata['credits']); ?> Getränke
				<br>
			<?php } ?>
			<?php if (!is_null($this->userdata['total_consumption'])) { ?>
				Gesamtkonsum: <?php echo($this->userdata['total_consumption']); ?> Getränke
				<br>
			<?php } ?>
		</div>
		<?php } ?>

		<?php

	}




}






?>