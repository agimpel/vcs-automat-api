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
		'tracking' => null, // is tracking for this user activated
		'rfid' => null, // rfid in database
		'credits' => null, // remaining credits
		'total_consumption' => null, // total number of used credits
		'consumption_data' => array() // usage-time data
	);

	// data of cumulative usage
	private $overalldata = array(
		'total_consumption' => null,

	);

	// data for credit reset
	private $resetdata = array(
		'interval' => null,
		'standard_credits' => null,
		'date' => null,
		'time' => null
	);

	// stores messages to display to user
	private $messageboxes = array();


    // constructor necessary to override the parent class constructor
    public function __construct() {
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/logger.php');
		$this->logger = Logger::instance();
		$this->logger->setup('wordpress', 'DEBUG');
    }



    public function vcs_automat() {

		// check if frontend is activated, show error notice if not
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$enabled = $db->get_setting('frontend_active');
		if($enabled != '1') {
			$this->logger->debug('Frontend is deactivated, show error page.');
			$this->show_html_deactivated();
			return;
		}

		// display page with information about the telegram bot
		if(isset($_GET['page']) && $_GET['page'] == 'telegrambot') {
			$this->show_html_telegrambot();
			return;
		}

		// display page with cumulative and individual statistics
		if(isset($_GET['page']) && $_GET['page'] == 'stats') {
			$this->show_html_stats();
			return;
		}

		// display page with settings, this page is restricted to logged-in users
		if(isset($_GET['page']) && $_GET['page'] == 'settings') {
			if(!is_user_logged_in()) {
				$this->logger->debug('User is not logged in, show login notice page.');
				$this->show_html_notloggedin();
			} else {
				$this->show_html_settings();
			}
			return;
		}

		// in the default case, display the landing page with navigation
		$this->show_html_home();
	}



	private function get_uid() {
		$user = wp_get_current_user();
		$uid = $user->user_login;
		return $uid;
	}


	private function prepare_userdata() {
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
		$this->userdata['tracking'] = $result['tracking'];
		$this->userdata['credits'] = $result['credits'];
		$this->userdata['rfid'] = $result['rfid'];

		return;
	}


	private function prepare_resetdata() {
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$result = array();
		$to_fetch = array('reset_interval', 'standard_credits', 'next_reset');
		foreach ($to_fetch as $value) {
			$result[$value] = $db->get_setting($value);
		}

		$this->resetdata['interval'] = $result['reset_interval'];
		$this->resetdata['standard_credits'] = $result['standard_credits'];
		$this->resetdata['date'] = date('d.m.y', $result['next_reset']);
		$this->resetdata['time'] = date('G', $result['next_reset']);
		return;
	}



	private function set_rfid($rfid) {

		if(is_null($this->userdata['uid'])) {
			$this->logger->error('Attempted to set rfid, but uid is not set.');
			return false;
		}

		if($rfid == '') {
			$this->logger->debug('Provided rfid is empty.');
			$this->attach_message_box('info', 'Bitte gebe eine Identifikationsnummer an.');
			return false;
		}

		if(preg_match('/[^a-z0-9]/i', $rfid)) {
			$this->logger->debug('Provided rfid failed regex validation.');
			$this->attach_message_box('info', 'Die Identifikationsnummer enthält nur alphanumerische Zeichen.');
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
			</div><br><br>
		<?php }
	}


	public function send_data_download() {
		
		if(!isset($_POST['vcs_automat-get-data-nonce']) || !wp_verify_nonce($_POST['vcs_automat-get-data-nonce'], 'vcs_automat-get-data')) {
			$this->logger->debug('Data download requested with valid nonce.');
			return;
		}

		$this->prepare_userdata();
		if(is_null($this->userdata['uid'])) {
			$this->logger->error('Attempted to get user data for download, but uid is not set.');
			return;
		}

		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$result = $db->get_archive_data($this->userdata['uid']);

		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename=vcs_automat_data.csv;');
		$f = fopen('php://output', 'w');
		fputcsv($f, array('unixtime', 'time', 'slot'), ",");

		if(!$result) {
			$this->logger->debug('Get user data: uid is not known in archive.');
			return false;
		}
		$this->logger->debug('Get user data: uid is known, print to csv.');
		
		foreach ($result as $line) {
			fputcsv($f, $line, ",");
		}
		return;
	}



	private function singular_plural_format($amount, $singular, $plural) {
		if((int) $amount > 1) {
			echo($plural);
		} else {
			echo($singular);
		}
	}





	//
	// SHOW_HTML_FUNCTIONS
	//


	// shared navigation header of all subpages
	private function show_html_generic_header($title) {
		?>
		<div id="vcs_automat-title"><h1>VCS-Automat: <?php echo($title); ?></h1></div>
		<div id="vcs_automat-subtitle"><a href="<?php echo(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)); ?>">&laquo; zurück zur Übersicht</a></div>
		<br><br>
		<?php
	}


	// deactivated page: shown if the frontend is deactivated via the plugin settings
	private function show_html_deactivated() {
		?>

		<div id="vcs_automat-title"><h1>VCS-Automat</h1></div>
		<div id="vcs_automat-deactivated"><strong>Die Homepage für den VCS-Automaten ist momentan deaktiviert.</strong></div>

		<?php
	}


	// landing page: default with no/unknown GET parameter
	private function show_html_home() {
		$this->prepare_resetdata();
		$this->prepare_userdata();
		$this->print_message_boxes();
		?>
		<div id="vcs_automat-title"><h1>VCS-Automat</h1></div>
		<div id="vcs_automat-info">
			Der VCS-Automat ist ein Getränkeautomat im HXE, an dem Mitglieder der VCS regelmässig mit ihrer Legi Freigetränke erhalten können. Zur Verwendung muss die Identifikationsnummer der Legi unter 'Einstellungen' eingetragen werden.
		</div>
		<br>
		<div id="vcs_automat-resetinfo">
			Momentan steht alle <?php echo($this->resetdata['interval']); ?> Tage ein Guthaben von <?php echo($this->resetdata['standard_credits']); ?> <?php $this->singular_plural_format($this->resetdata['standard_credits'],'Freigetränk', 'Freigetränken'); ?> zur Verfügung. Der nächste Reset ist am <?php echo($this->resetdata['date']); ?> um <?php echo($this->resetdata['time']); ?> Uhr.
		</div><br>
		<?php if(!is_null($this->userdata['credits'])) { ?>
		<div id="vcs_automat-resetinfo">
			Dein Restguthaben beträgt <?php echo($this->userdata['credits']); ?> <?php $this->singular_plural_format($this->userdata['credits'], 'Freigetränk', 'Freigetränke'); ?>.
		</div><br>
		<?php } ?>
		<a href="<?php echo(add_query_arg('page', 'settings', parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH))); ?>">Einstellungen</a><br>
		<a href="<?php echo(add_query_arg('page', 'stats', parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH))); ?>">Statistiken</a><br>
		<a href="<?php echo(add_query_arg('page', 'telegrambot', parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH))); ?>">Telegram-Bot</a><br>


		<?php

	}


	// settings page: limited to logged-in users, triggered if $_GET['page'] == 'settings'
	private function show_html_settings() {

		// check if necessary data is available
		$this->prepare_userdata();
		if(is_null($this->userdata['uid'])) {
			$this->logger->error('Attempted to update tracking settings for user, but uid is not set.');
			$this->attach_message_box('failure', 'Es ist ein Fehler aufgetreten: Keine Identifikationsnummer bekannt.');
			$this->show_html_home();
		}
		if(is_null($this->userdata['tracking'])) {
			$this->logger->error('Attempted to update tracking settings for user, but tracking preference is not set.');
			$this->attach_message_box('failure', 'Es ist ein Fehler aufgetreten: Keine Präferenz bekannt.');
			$this->show_html_home();
		}

		// process post data for RFID change
		if (isset($_POST['vcs_automat-rfid'])) {
			$this->logger->debug('Submitting of new rfid was invoked.');
			if (isset($_POST['vcs_automat-set-rfid-nonce']) && wp_verify_nonce($_POST['vcs_automat-set-rfid-nonce'], 'vcs_automat-set-rfid')) {
				$this->logger->debug('Nonce is valid, continue with submission of new rfid.');
				if($this->set_rfid($_POST['vcs_automat-rfid'])) {
					$this->prepare_userdata();
					$this->attach_message_box('success', 'Identifikationsnummer der Legi erfolgreich geändert.');
				} else {
					$this->attach_message_box('failure', 'Beim Ändern der Identifikationsnummer der Legi ist ein Fehler aufgetreten.');
				}
			} else {
				$this->logger->info('Nonce for submission of new rfid was invalid.');
			}
		}

		// process post data for switch of tracking preference
		if (isset($_POST['vcs_automat-tracking-switch-nonce']) && wp_verify_nonce($_POST['vcs_automat-tracking-switch-nonce'], 'vcs_automat-tracking-switch')) {
			$this->logger->debug('Switch of tracking preference requested.');
			require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
			$db = SQLhandler::instance();
			if($db->change_tracking($this->userdata['uid'], $this->userdata['tracking'])) {
				$this->prepare_userdata();
				$this->attach_message_box('success', 'Einstellung für die Datenerhebung geändert.');
			} else {
				$this->attach_message_box('failure', 'Beim Ändern der Einstellung für die Datenerhebung ist ein Fehler aufgetreten.');
			}
		}

		// process post data for data deletion
		if (isset($_POST['vcs_automat-deletion-nonce']) && wp_verify_nonce($_POST['vcs_automat-deletion-nonce'], 'vcs_automat-deletion')) {
			$this->logger->debug('Switch of tracking preference requested.');
			require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
			$db = SQLhandler::instance();
			if($db->delete_archive_data($this->userdata['uid'])) {
				$this->attach_message_box('success', 'Löschung der Nutzungsdaten erfolgreich.');
			} else {
				$this->attach_message_box('failure', 'Beim Löschen der Nutzungsdaten ist ein Fehler aufgetreten.');
			}
		}


		$this->print_message_boxes();
		$this->show_html_generic_header('Einstellungen');
		?>
		<div id="vcs_automat-registration-title"><h2>Registrierung</h2></div>
		<div id="vcs_automat-registration-info">
			<?php if (!$this->userdata['registered']) { ?>
				Deine Legi ist noch nicht für den Bierautomaten registriert. Hier kannst du die Identifikationsnummer deiner Legi eintragen, um den Bierautomaten verwenden zu können.
			<?php } else { ?>
				Deine Legi ist bereits für den Bierautomaten registriert. Du kannst hier aber die Identifikationsnummer deiner Legi ändern, z.B. wenn du eine neue Legi erhalten hast.
			<?php } ?>
			<br><br>
			<form action="<?php echo($_SERVER['REQUEST_URI']); ?>" method="post">
				<input type="text" name="vcs_automat-rfid" placeholder="Deine Legi-RFID"></input>
				<br>
				<input type="submit" name="submit" value="Registrieren"></input>
				<?php wp_nonce_field('vcs_automat-set-rfid', 'vcs_automat-set-rfid-nonce'); ?>
			</form>
		</div>

		<br><br><br>
		<div id="vcs_automat-tracking-switch-title"><h2>Einstellungen für die Datenerhebung</h2></div>
		<div id="vcs_automat-tracking-switch">
			Durch den Automaten werden die Zeit und der gewählte Schacht bei der Verwendung zusammen mit der Identifikationsnummer des Nutzers gespeichert. Dies erlaubt es, den Nutzern Statistiken zu ihrem Nutzungsverhalten zu zeigen. Diese personenbezogene Erhebung der Daten kann deaktiviert werden, dadurch können jedoch keine persönlichen Nutzungsstatistiken mehr dargestellt werden.<br><br>
			Die personenbezogene Datenerhebung ist für dich <?php if($this->userdata['tracking']) { ?> aktiviert. <?php } else { ?> deaktiviert. <?php } ?>	
			<br><br>
			<form action="<?php echo($_SERVER['REQUEST_URI']); ?>" method="post">
				<?php wp_nonce_field('vcs_automat-tracking-switch', 'vcs_automat-tracking-switch-nonce'); ?>
				<input type="submit" name="submit" value="Datenerhebung <?php if($this->userdata['tracking']) { echo('ausschalten'); } else { echo('einschalten'); } ?>"></input>
			</form>
		</div>

		<br><br><br>
		<div id="vcs_automat-tracking-deletion-title"><h2>Löschen der Nutzungsdaten</h2></div>
		<div id="vcs_automat-tracking-deletion">
			Die durch den Automaten erhobenen persönlichen Daten können vollständig gelöscht werden, sodass keine Nutzungsdaten mehr mit deiner Identifikationsnummer in Verbindung stehen. Dein Verbrauch erscheint jedoch anonymisiert weiterhin in der Gesamtstatistik.<br><br>Achtung: Das Löschen der persönlichen Nutzungsdaten ist irreversibel.
			<br><br>
			<form action="<?php echo($_SERVER['REQUEST_URI']); ?>" method="post">
				<?php wp_nonce_field('vcs_automat-deletion', 'vcs_automat-deletion-nonce'); ?>
				<input type="submit" name="submit" value="Nutzungsdaten löschen"></input>
			</form>
		</div>
		<?php 
	}


	// notloggedin page: shown if the settings page is accessed, but the user is not logged in
	private function show_html_notloggedin() {
		$this->show_html_generic_header('Anmeldung');
		?>
		<br><br>
		<div id="vcs_automat-notloggedin">Bitte <a href="/login/">einloggen</a>, um deine Einstellungen zu verwalten!</div>
		<br>
		<div id="vcs_automat-notloggedin-button"><a href="/login/">Zum Login</a></div>

		<?php
	}


	// stats page: triggered if $_GET['page'] == 'stats'
	private function show_html_stats() {
		$this->prepare_userdata();
		$this->print_message_boxes();
		$this->show_html_generic_header('Statistik');


		if ($this->userdata['registered']) { ?>
			<div id="vcs_automat-statistics-title"><h2>Persönliche Statistik</h2></div>
			<div id="vcs_automat-statistics-stats">
				<?php if (!is_null($this->userdata['total_consumption'])) { ?>
					Gesamtkonsum: <?php echo($this->userdata['total_consumption']); ?> Getränke
					<br>
				<?php } ?>
			</div><br><br>
			<form action="<?php echo(add_query_arg('download_data','true')); ?>" method="post">
				<input type="submit" name="submit" value="Nutzungsdaten herunterladen"></input><?php wp_nonce_field('vcs_automat-get-data', 'vcs_automat-get-data-nonce'); ?>
			</form>
			<?php }
	}

	// telegrambot page: triggered if $_GET['page'] == 'telegrambot'
	private function show_html_telegrambot() {
		$this->print_message_boxes();
		$this->show_html_generic_header('Telegram-Bot');
	}





}






?>