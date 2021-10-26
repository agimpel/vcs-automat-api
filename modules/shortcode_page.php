<?php

// prevent this file from being executed directly
defined('ABSPATH') or die();

// This class displays the public frontend to the Automat API, use shortcode vcs-automat to invoke this page
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
		$this->logger->setup('wordpress', 'INFO');
    }

    // handles the subpage by determining which content to display based on the GET flag of the request
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
		if(isset($_GET['view']) && $_GET['view'] == 'telegrambot') {
			$this->show_html_telegrambot();
			return;
		}

		// display page with cumulative and individual statistics
		if(isset($_GET['view']) && $_GET['view'] == 'stats') {
			$this->show_html_stats();
			return;
		}

		// display page with settings, this page is restricted to logged-in users affiliated with VCS
		if(isset($_GET['view']) && $_GET['view'] == 'settings') {
			if(!is_user_logged_in()) {
				$this->logger->debug('User is not logged in, show login notice page.');
				$this->show_html_notloggedin();
				return;
			} 
			require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/affiliation_api.php');
			$api = Affiliation_API::instance();
			$is_affiliated = $api->is_affiliated($this->get_uid());
			if(!$is_affiliated) {
				$this->show_html_notaffiliated();
			} else {
				$this->show_html_settings();
			}
			return;
		}

		// in the default case, display the landing page with navigation
		$this->show_html_home();
	}

    // returns the UID of the current user, i.e. the Wordpress login username
	private function get_uid() {
		$user = wp_get_current_user();
		$uid = $user->user_login;
		return $uid;
	}

    // reads the current user's data from the SQL database, if available and populates the userdata array of this class
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
		$this->userdata['credits'] = $result['credits'];
		$this->userdata['rfid'] = $result['rfid'];

		return;
	}

    // reads the current reset information from the SQL database and populates the resetdata array of this class
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

    // sanitises the RFID of the current user by checking for length and non-numeric characters, then sets or updates the user's RFID in the SQL database
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


		if(strlen($rfid) != 6) {
			$this->logger->debug('Provided rfid has an incorrect length of '.strlen($rfid).' characters.');
			$this->attach_message_box('info', 'Bitte gebe die sechsstellige Identifikationsnummer auf der Rückseite der Legi an.');
			return false;
		}

		if(preg_match('/[^0-9]/i', $rfid)) {
			$this->logger->debug('Provided rfid failed regex validation.');
			$this->attach_message_box('info', 'Die Identifikationsnummer kann nur numerische Zeichen enthalten.');
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

    // stores a message to be displayed to the user upon the next page update, possible types: failure (red), info (yellow)
	private function attach_message_box($type, $text) {
		$this->messageboxes[] = array('type' => $type, 'text' => $text);
	}

    // prints previously saved messages to the user. Color is determined by the message's type attribute
	private function print_message_boxes() {
		foreach ($this->messageboxes as $message) { ?>
			<div class="vcs_automat-messagebox vcs_automat-messagebox-<?php echo($message['type']); ?>">
			<span class="vcs_automat-messagebox-dismiss" onclick="this.parentNode.style.display='none';"></span>
			<div>
			<?php echo($message['text']); ?>
			<?php if($message['type'] == 'failure') { ?>
				<br>Sollte dieser Fehler unerwartet sein, kontaktiere uns unter <a href="mailto:bierko@vcs.ethz.ch">bierko@vcs.ethz.ch</a>.
			<?php } ?>
			</div>
			</div>
		<?php }
	}

    // prints $singular if $amount is 1, $plural otherwise to allow text to be correctly specified
	private function singular_plural_format($amount, $singular, $plural) {
		if((int) $amount == 1) {
			echo($singular);
		} else {
			echo($plural);
		}
	}



	//
	// SHOW_HTML_FUNCTIONS
	//

	// shared navigation header of all subpages
	private function show_html_generic_header($title) {
		?>
		<div class="vcs_automat-title"><h1>VCS-Automat: <?php echo($title); ?></h1></div>
		<div class="vcs_automat-subtitle button"><a href="<?php echo(parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH)); ?>">&laquo; zurück zur Übersicht</a></div>
		<br><br>
		<?php
	}

	// deactivated page: shown if the frontend is deactivated via the plugin settings
	private function show_html_deactivated() {
		?>

		<div class="vcs_automat-title"><h1>VCS-Automat</h1></div>
		<div class="vcs_automat-deactivated"><strong>Die Homepage für den VCS-Automaten ist momentan deaktiviert.</strong></div>

		<?php
	}

	// landing page: default with no/unknown GET parameter
	private function show_html_home() {
		$this->prepare_resetdata();
		$this->prepare_userdata();
		$this->print_message_boxes();
		?>
		<div class="vcs_automat-title"><h1>VCS-Automat</h1></div>
		<div class="vcs_automat-info">
			Der VCS-Automat ist ein Getränkeautomat im HXE, an dem Mitglieder der VCS regelmässig mit ihrer Legi Freigetränke erhalten können. Zur Verwendung muss die Identifikationsnummer der Legi unter 'Registrierung' eingetragen werden.
		</div>
		<br>
		<div class="vcs_automat-resetinfo">
			Momentan steht alle <?php echo($this->resetdata['interval']); ?> Tage ein Guthaben von <?php echo($this->resetdata['standard_credits']); ?> <?php $this->singular_plural_format($this->resetdata['standard_credits'],'Freigetränk', 'Freigetränken'); ?> zur Verfügung. Der nächste Reset ist am <?php echo($this->resetdata['date']); ?> um <?php echo($this->resetdata['time']); ?> Uhr.
		</div>
		<br>
		<?php if(!is_null($this->userdata['credits'])) { ?>
		<div class="vcs_automat-creditsinfo">
			Dein Restguthaben beträgt <?php echo($this->userdata['credits']); ?> <?php $this->singular_plural_format($this->userdata['credits'], 'Freigetränk', 'Freigetränke'); ?>.
		</div>
		<br>
		<?php } ?>
		<br>
		<div class="vcs_automat-menuentrywrap" onclick="javascript:location.href='<?php echo(add_query_arg('view', 'settings', parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH))); ?>'">
		<span class="vcs_automat-menuentryicon"><span class="dashicons dashicons-admin-generic"></span></span>
			<span class="vcs_automat-menuentrytext">Registrierung</span>
		</div>
		<br>
		<div class="vcs_automat-menuentrywrap" onclick="javascript:location.href='<?php echo(add_query_arg('view', 'stats', parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH))); ?>'">
			<span class="vcs_automat-menuentryicon"><span class="dashicons dashicons-chart-line"></span></span>
			<span class="vcs_automat-menuentrytext">Statistiken</span>
		</div>
		<br>
		<div class="vcs_automat-menuentrywrap" onclick="javascript:location.href='<?php echo(add_query_arg('view', 'telegrambot', parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH))); ?>'">
			<span class="vcs_automat-menuentryicon"><span class="dashicons dashicons-testimonial"></span></span>
			<span class="vcs_automat-menuentrytext">Infos zum Telegram-Bot</span>
		</div>
		<br><br>
		<span>Kontakt: <a href="mailto:bierko@vcs.ethz.ch">bierko@vcs.ethz.ch</a></span>
		<?php
	}

    // stats page: triggered if $_GET['view'] == 'stats'
	private function show_html_stats() {
		$this->print_message_boxes();
		$this->show_html_generic_header('Statistiken');

		$directory = WP_CONTENT_DIR.'/plugins/vcs-automat-api/img/';
		if (!is_dir($directory)) {
			$this->logger->error("Image directory could not be opened at ".$directory);
			return;
		}
		$scanned_directory = array_diff(scandir($directory), array('..', '.','.gitignore'));
		$default_images = array('hour.svg', 'weekday.svg');
		?>

		<div class="vcs_automat-stats-title"><h2>Durchschnittskonsum</h2></div>
		<div class="vcs_automat-statswrapper">

		<?php
			foreach ($scanned_directory as $key => $value) {
				if (in_array($value, $default_images)) {
					?>
					<div class="vcs_automat-statsentry">
						<a href='<?php echo(WP_PLUGIN_URL.'/vcs-automat-api/img/'.$value.'?'.time()); ?>' target="_blank">
							<img src='<?php echo(WP_PLUGIN_URL.'/vcs-automat-api/img/'.$value.'?'.time()); ?>'>
						</a>
					</div>
					<?php
				}
			}
		?>
		</div>
		<br><br>
		<div class="vcs_automat-stats-title"><h2>Jahreskonsum</h2></div>
		<div class="vcs_automat-statswrapper">

		<?php
			foreach ($scanned_directory as $key => $value) {
				if (preg_match('/year_[0-9]{4}\.svg/i', $value)) {
					?>
					<div class="vcs_automat-statsentry">
						<a href='<?php echo(WP_PLUGIN_URL.'/vcs-automat-api/img/'.$value.'?'.time()); ?>' target="_blank">
							<img src='<?php echo(WP_PLUGIN_URL.'/vcs-automat-api/img/'.$value.'?'.time()); ?>'>
						</a>
					</div>
					<?php
				}
			}
		?>
		</div>
		<?php 
	}

	// settings page: limited to logged-in users, triggered if $_GET['view'] == 'settings'
	private function show_html_settings() {

		// check if necessary data is available
		$this->prepare_userdata();
		if(is_null($this->userdata['uid'])) {
			$this->logger->error('Attempted to update tracking settings for user, but uid is not set.');
			$this->attach_message_box('failure', 'Es ist ein Fehler aufgetreten: Keine Identifikationsnummer bekannt.');
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


		$this->print_message_boxes();
		$this->show_html_generic_header('Registrierung');
		?>
		<div class="vcs_automat-registration-title"><h2>Legi-Identifikationsnummer</h2></div>
			Die Legi-Identifikationsnummer ist die sechstellige Nummer auf der Rückseite deiner Legi, siehe die Beispiellegi auf dem Bild.<br>Es ist nicht deine eigentliche Leginummer!<br><br>
			<img src='<?php echo(WP_PLUGIN_URL.'/vcs-automat-api/img/legi.jpg'); ?>'>
			<br><br>
		<div class="vcs_automat-registration-info">
			<?php if (!$this->userdata['registered']) { ?>
				Deine Legi ist noch nicht für den Bierautomaten registriert. Hier kannst du die Identifikationsnummer deiner Legi eintragen, um den Bierautomaten verwenden zu können. Beachte, dass du deine Legi online nur einmalig registrieren kannst.
				<br>
				Die Identifikationsnummer ist nicht die Leginummer, sondern die sechstellige Zahl unter dem Unterschriftfeld auf der Rückseite der Legi.
				<br><br>
				<form action="<?php echo($_SERVER['REQUEST_URI']); ?>" method="post">
					<input type="text" name="vcs_automat-rfid" placeholder="Deine Legi-RFID">
					<br>
					<input type="submit" name="submit" value="Registrieren">
					<?php wp_nonce_field('vcs_automat-set-rfid', 'vcs_automat-set-rfid-nonce'); ?>
				</form>
			<?php } else { ?>
				Du bist bereits für den Bierautomaten registriert, daher kannst du deine Identifikationsnummer nicht mehr online ändern. Wenn sich deine Identifikationsnummer verändert hat, z.B. durch eine neue Legi, melde dich unter <a href="mailto:bierko@vcs.ethz.ch">bierko@vcs.ethz.ch</a> mit deinem nethz-Kürzel und deiner neuen Identifikationsnummer.
			<?php } ?>

		</div>
		<?php 
	}

	// notloggedin page: shown if the settings page is accessed, but the user is not logged in
	private function show_html_notloggedin() {
		$this->show_html_generic_header('Anmeldung');
		?>
		<br><br>
		<div id="vcs_automat-notloggedin">Bitte <a href="/login/">einloggen</a>, um dich für den Automaten zu registrieren!</div>
		<br><br>
		<?php
	}

	// notaffiliated page: shown if the settings page is accessed, but the user is not affiliated to VCS
	private function show_html_notaffiliated() {
		$this->show_html_generic_header('Anmeldung');
		?>
		<br><br>
		<div id="vcs_automat-notaffiliated">Registrierung ist nur für VCS-Mitglieder möglich!</div>
		<br><br>
		<?php
	}

	// telegrambot page: triggered if $_GET['view'] == 'telegrambot'
	private function show_html_telegrambot() {
		$this->print_message_boxes();
		$this->show_html_generic_header('Telegram-Bot');
		?>
		<div class="vcs_automat-telegram-title"><h2>Allgemeines</h2></div>
		<div class="vcs_automat-telegram-info">
			Telegram ist ein kostenloser Messaging-Dienst, der auf allen Smartphones und online verfügbar ist. Als sogenannter Telegram-Bot kann ebenfalls direkt mit dem Automaten kommuniziert werden, sodass wichtige Informationen wie das eigene Guthaben oder der Füllstand des Automaten einfach abgerufen werden können.
			<br><br>
			Um den Telegram-Bot zu verwenden, suche in Telegram nach <i>@VCS_BierautomatBot</i> oder öffne Telegram mit dem untenstehenden Link.
			<br><br>
			<div class="vcs_automat-menuentrywrap" onclick="javascript:location.href='https://telegram.me/VCS_BierautomatBot'">
				<span class="vcs_automat-menuentryicon"><span class="dashicons dashicons-testimonial"></span></span>
				<span class="vcs_automat-menuentrytext">Telegram-Bot öffnen</span>
			</div>
		</div>
		<br><br><br>
		<div class="vcs_automat-telegram-title"><h2>Befehle</h2></div>
		<div class="vcs_automat-telegram-info">
			Folgende Befehle sind verfügbar:
			<br><br>
			<table id="vcs_automat-telegram-commands">
				<tr>
					<td><i>Allgemeine Informationen anzeigen</i></td>
					<td>Zeigt Informationen zum Reset der Guthaben, sowie den Zeitpunkt des nächsten Resets.</td>
				</tr>
				<tr>
					<td><i>Guthaben überprüfen</i></td>
					<td>Zeigt dein verbleibendes Guthaben, wenn du deine Identifikationsnummer registriert hast.</td>
				</tr>
				<tr>
					<td><i>Füllstand überprüfen</i></td>
					<td>Zeigt den momentanen Füllstand jedes Slots des Automaten an.</td>
				</tr>
				<tr>
					<td><i>Problem melden</i></td>
					<td>Sendet eine Meldung an die Verantwortlichen des Automaten.</td>
				</tr>
				<tr>
					<td><i>Hilfe</i></td>
					<td>Zeigt zusätzliche Hinweise zu allen Befehlen.</td>
				</tr>
			</table>
		</div>

		<?php	
	}
}
?>
