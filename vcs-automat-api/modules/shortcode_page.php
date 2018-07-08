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
		'registered' => true, // is the user known to the users table
		'uid' => 1234, // uid in database
		'rfid' => 1234, // rfid in database
		'credits' => 2, // remaining credits
		'total_consumption' => 3, // total number of used credits
		'consumption_data' => array() // usage-time data
	);

    // constructor empty to override the parent class constructor
    public function __construct() {
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/logger.php');
		$this->logger = Logger::instance();
		$this->logger->setup('wordpress', 'DEBUG');
		$this->logger->debug('Wordpress plugin enabled.');
    }


    public function vcs_automat() {

		if(!is_user_logged_in()) {
			$this->logger->debug('User is not logged in, show login notice page.');
			$this->show_html_notloggedin();
			return;
		}

		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$enabled = $db->get_setting('frontend_active');
		if($enabled != '1') {
			$this->logger->debug('Frontend is deactivated, show error page.');
			$this->show_html_deactivated();
			return;
		}

		$this->logger->debug('Frontend is activated and user is logged in, collect data and show page.');
		$this->prepare_data();
		$this->show_html_active();
	}



	private function prepare_data() {
		$uid = get_current_user_id();
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		// $result = $db->



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


	private function show_html_active() {
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