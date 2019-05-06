<?php

// prevent this file from being executed directly
defined('ABSPATH') or die();

// This class displays the Wordpress settings page in the Admin panel and handles all settings done therein
class Plugin_Options extends VCS_Automat {

	// the instance of this class
	private static $_instance = null;

	// return or create the single instance of this class
	public static function instance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	// all settings with their types and defaults
	private $settings_array = array(
		'vcs_automat_activate_shortcode_bool' => array(
			'slug' => 'frontend_active',
			'datatype' => 'bool', 
			'formtype' => 'checkbox', 
			'default' => 0, 
			'title' => 'Frontend aktiv', 
			'description' => 'Ob die Einstellungs- und Statistikseite verfügbar sein soll.'),
		'vcs_automat_activate_vending_bool' => array(
			'slug' => 'api_active',
			'datatype' => 'bool', 
			'formtype' => 'checkbox', 
			'default' => 0, 
			'title' => 'API aktiv', 
			'description' => 'Ob die Authentifizierung über die VCS API verfügbar sein soll.'),
		'vcs_automat_default_credits' => array(
			'slug' => 'standard_credits',
			'datatype' => 'int', 
			'formtype' => 'number', 
			'default' => 2, 
			'title' => 'Reset Credits', 
			'description' => 'Anzahl der Credits, die nach einem Reset verfügbar sind.'),
		'vcs_automat_time_interval_days' => array(
			'slug' => 'reset_interval',
			'datatype' => 'int', 
			'formtype' => 'number', 
			'default' => 7, 
			'title' => 'Reset Zeitintervall', 
			'description' => 'Anzahl der Tage für das Resetintervall.')
	);

	// all settings with their types and defaults for the rfid submenu
	private $settings_array_rfid = array(
		'vcs_automat_nethz' => array(
			'slug' => 'nethz',
			'datatype' => 'string', 
			'formtype' => 'text',
			'title' => 'nethz', 
			'description' => 'nethz-Kürzel des Benutzers, dessen RFID geändert werden soll.'),
		'vcs_automat_rfid' => array(
			'slug' => 'rfid',
			'datatype' => 'rfid', 
			'formtype' => 'number', 
			'title' => 'RFID', 
			'description' => 'Neue RFID des bestehenden Benutzers.')
	);

	// constructor empty to override the parent class constructor
	public function __construct() {

		// set up logging file and logging function
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/logger.php');
		$this->logger = Logger::instance();
		$this->logger->setup('wordpress', 'INFO');
	}

	// sets up the settings page, its subpages and actions
	public function main() {
		add_menu_page('Einstellungen für den VCS-Automat', 'VCS-Automat', 'manage_options', 'vcs-automat', array($this, 'generate_html'), "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+PHN2ZyAgIHhtbG5zOmRjPSJodHRwOi8vcHVybC5vcmcvZGMvZWxlbWVudHMvMS4xLyIgICB4bWxuczpjYz0iaHR0cDovL2NyZWF0aXZlY29tbW9ucy5vcmcvbnMjIiAgIHhtbG5zOnJkZj0iaHR0cDovL3d3dy53My5vcmcvMTk5OS8wMi8yMi1yZGYtc3ludGF4LW5zIyIgICB4bWxuczpzdmc9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIiAgIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgICB4bWxuczpzb2RpcG9kaT0iaHR0cDovL3NvZGlwb2RpLnNvdXJjZWZvcmdlLm5ldC9EVEQvc29kaXBvZGktMC5kdGQiICAgeG1sbnM6aW5rc2NhcGU9Imh0dHA6Ly93d3cuaW5rc2NhcGUub3JnL25hbWVzcGFjZXMvaW5rc2NhcGUiICAgd2lkdGg9IjI0IiAgIGhlaWdodD0iMjQiICAgdmlld0JveD0iMCAwIDI0IDI0IiAgIHZlcnNpb249IjEuMSIgICBpZD0ic3ZnNCIgICBzb2RpcG9kaTpkb2NuYW1lPSJpY29ubW9uc3RyLWJlZXItNC5zdmciICAgaW5rc2NhcGU6dmVyc2lvbj0iMC45Mi4zICh1bmtub3duKSI+ICA8bWV0YWRhdGEgICAgIGlkPSJtZXRhZGF0YTEwIj4gICAgPHJkZjpSREY+ICAgICAgPGNjOldvcmsgICAgICAgICByZGY6YWJvdXQ9IiI+ICAgICAgICA8ZGM6Zm9ybWF0PmltYWdlL3N2Zyt4bWw8L2RjOmZvcm1hdD4gICAgICAgIDxkYzp0eXBlICAgICAgICAgICByZGY6cmVzb3VyY2U9Imh0dHA6Ly9wdXJsLm9yZy9kYy9kY21pdHlwZS9TdGlsbEltYWdlIiAvPiAgICAgIDwvY2M6V29yaz4gICAgPC9yZGY6UkRGPiAgPC9tZXRhZGF0YT4gIDxkZWZzICAgICBpZD0iZGVmczgiIC8+ICA8c29kaXBvZGk6bmFtZWR2aWV3ICAgICBwYWdlY29sb3I9IiNmZmZmZmYiICAgICBib3JkZXJjb2xvcj0iIzY2NjY2NiIgICAgIGJvcmRlcm9wYWNpdHk9IjEiICAgICBvYmplY3R0b2xlcmFuY2U9IjEwIiAgICAgZ3JpZHRvbGVyYW5jZT0iMTAiICAgICBndWlkZXRvbGVyYW5jZT0iMTAiICAgICBpbmtzY2FwZTpwYWdlb3BhY2l0eT0iMCIgICAgIGlua3NjYXBlOnBhZ2VzaGFkb3c9IjIiICAgICBpbmtzY2FwZTp3aW5kb3ctd2lkdGg9IjE5MjAiICAgICBpbmtzY2FwZTp3aW5kb3ctaGVpZ2h0PSIxMDIyIiAgICAgaWQ9Im5hbWVkdmlldzYiICAgICBzaG93Z3JpZD0iZmFsc2UiICAgICBpbmtzY2FwZTp6b29tPSI5LjgzMzMzMzMiICAgICBpbmtzY2FwZTpjeD0iMTIuMzA1MDg1IiAgICAgaW5rc2NhcGU6Y3k9IjEyIiAgICAgaW5rc2NhcGU6d2luZG93LXg9IjAiICAgICBpbmtzY2FwZTp3aW5kb3cteT0iMzAiICAgICBpbmtzY2FwZTp3aW5kb3ctbWF4aW1pemVkPSIxIiAgICAgaW5rc2NhcGU6Y3VycmVudC1sYXllcj0ic3ZnNCIgLz4gIDxwYXRoICAgICBkPSJNMjMgMTIuNDUyYzAgMi41MzktMS43OTEgNS43NS01IDYuOTYzdi0yLjE2YzMuMTU0LTEuODMgMy45NjktNi4yNTUgMS41NTMtNi4yNTVoLTEuNTUzdi0yaDEuOTEyYzIuMTQ0IDAgMy4wODggMS41MzQgMy4wODggMy40NTJ6bS01IDkuOTc1djEuNTczaC0xNnYtMS41NzNjLjY2NCAwIDEtLjUzOSAxLTEuMjAzdi0xMi44MTdjLTEuMTgxLS41NjktMi0xLjc1NC0yLTMuMTUgMC0yLjI1NyAyLjA4NC0zLjg0MyA0LjIzOC0zLjUwMSAxLjA0Ny0uOTM1IDIuNTAyLTEuMjE0IDMuNzk1LS43OTIuODAxLS42NDIgMS42MTEtLjk2NCAyLjU4Mi0uOTY0IDEuNTE4IDAgMi45NzEuNzY1IDMuNzM4IDEuODM0IDEuODQ4LjEwNCAzLjMyIDEuNjQxIDMuMzIgMy41MTUgMCAxLjM0MS0uNTY3IDIuNTEtMS42NzQgMy4xMDR2MTIuNzcyYy4wMDEuNjYzLjMzNyAxLjIwMiAxLjAwMSAxLjIwMnptLTExLTExLjQyN2MwLS41NTItLjQ0Ny0xLTEtMXMtMSAuNDQ4LTEgMXY4YzAgLjU1Mi40NDcgMSAxIDFzMS0uNDQ4IDEtMXYtOHptNCAwYzAtLjU1Mi0uNDQ3LTEtMS0xcy0xIC40NDgtMSAxdjhjMCAuNTUyLjQ0NyAxIDEgMXMxLS40NDggMS0xdi04em00IDBjMC0uNTUyLS40NDctMS0xLTFzLTEgLjQ0OC0xIDF2OGMwIC41NTIuNDQ3IDEgMSAxczEtLjQ0OCAxLTF2LTh6bTIuMDk4LTUuNjUxYzAtMS4wNzQtLjg3MS0xLjk0NC0xLjk0NC0xLjk0NC0uMjQzIDAtLjQ3Ni4wNDUtLjY4OS4xMjYtLjM2NS0xLjEzNC0xLjQyOS0xLjk1NS0yLjY4NS0xLjk1NS0xLjAyMSAwLTEuOTE4LjU0NC0yLjQxMiAxLjM1OS0uNDEtLjM2NS0uOTUtLjU4Ni0xLjU0MS0uNTg2LS45MDEgMC0xLjY4Mi41MTUtMi4wNjUgMS4yNjYtLjMwOC0uMjA2LS42NzgtLjMyNi0xLjA3Ni0uMzI2LTIuNzkgMC0yLjc1NiAzLjg4OSAwIDMuODg5LjY0NyAwIDEuMjIxLS4zMTcgMS41NzQtLjgwNC40MTIuMzc5Ljk2My42MTEgMS41NjcuNjExLjcwNiAwIDEuMzM3LS4zMTUgMS43NjMtLjgxMy41MTcuNjM3IDEuMzA2IDEuMDQ1IDIuMTg5IDEuMDQ1LjcwMSAwIDEuMzQyLS4yNTYgMS44MzYtLjY3OS4zNTUuNDYuOTEyLjc1NiAxLjUzOC43NTYgMS4wNzQtLjAwMSAxLjk0NS0uODcyIDEuOTQ1LTEuOTQ1eiIgICAgIGlkPSJwYXRoMiIgICAgIHN0eWxlPSJmaWxsOiNmZmZmZmYiIC8+PC9zdmc+");
		add_action('admin_init', array($this, 'admin_initialise'));
		add_submenu_page('vcs-automat', 'Änderung der RFID in der Datenbank', 'RFID ändern', 'manage_options', 'vcs_automat_rfid', array($this, 'generate_html_rfid'));
	}

	// returns the default options of this plugin
	public function default_options() {
		$array = array();
		foreach ($this->settings_array as $key => $value) {
			$array[$key] = $value['default'];
		}
		return $array;
	  }
	   
	// returns the current options of this plugin
	public function get_options() {
		$options = get_option('vcs_automat_options', $this->default_options());
		$this->update_sql_settings($options);
		return $options;
	}
	   
	// updates the current options by saving them to the SQL database
	private function update_sql_settings($options) {
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$db_result = $db->set_settings($options, $this->settings_array);
	}

	// handles the manual RFID update by changing the user's RFID in the database
	private function update_sql_rfidchange($options) {
		$uid = $options['vcs_automat_nethz'];
		$rfid = $options['vcs_automat_rfid'];
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$db_result = $db->change_rfid($uid, $rfid);
	}

	// sets up the settings panel by initialising the settings
	public function admin_initialise() {
		register_setting('vcs_automat_options_group', 'vcs_automat_options', array(
			'type' => 'array', 
			'default' => $this->default_options(), 
			'sanitize_callback' => array($this, 'sanitize_options')
		  )
		);
		register_setting('vcs_automat_rfid_group', 'vcs_automat_rfid', array(
			'type' => 'array', 
			'sanitize_callback' => array($this, 'sanitize_rfidchange')
		  )
		);
	}

	// checks the settings for validity based on datatype to ensure sanitised database inputs, either returns valid settings as array or prints error message to user
	public function sanitize_options($input) {

		$output = $this->get_options();

		if(!is_array($input) || empty($input) || (false === $input)) {
			return $output;
		}
		
		foreach ($this->settings_array as $key => $fields) {

			if (isset($input[$key])) {
				$new_setting = $input[$key];
				$datatype = $fields['datatype'];

				switch ($datatype) {
					case 'bool':
						if ($new_setting == 1 || $new_setting == '1') {
							$output[$key] = 1;
						} else if ($new_setting == 0 || $new_setting == '0' || $new_setting == "") {
							$output[$key] = 0;
						} else {
							add_settings_error('vcs_automat_options_'.$key, $key, __($fields['title'] . ': This field must be a boolean.', 'vcs_automat'));
						}
						break;

					case 'int':
						if (is_numeric($new_setting)) {
							$output[$key] = (int) $new_setting;
						} else {
							add_settings_error('vcs_automat_options_'.$key, $key, __($fields['title'] . ': This field must be an integer.', 'vcs_automat'));
						}
						break;

					case 'string':
						if (is_string($new_setting)) {
							$output[$key] = (string) $new_setting;
						} else {
							add_settings_error('vcs_automat_options_'.$key, $key, __($fields['title'] . ': This field must be a string.', 'vcs_automat'));
						}
						break;

					default:
						add_settings_error('vcs_automat_options_'.$key, $key, __($fields['title'] . ': This datatype is unknown.', 'vcs_automat'));
						break;
				}
			}
		}
		$this->update_sql_settings($output);
		return $output;
	}
	 
	// generates the HTML used for displaying the settings page
	public function generate_html() {
		  
		if (!current_user_can('manage_options')) {
			return;
		}

		// get previously set options
		$options = $this->get_options();
		
		// First show the error or update messages at the head of the page.
		settings_errors();
		settings_errors('vcs_automat_options');
		?>
		<div class="wrap">
		<h1><?= esc_html(get_admin_page_title()); ?></h1>
		<form action="options.php" method="post">
			<?php settings_fields('vcs_automat_options_group'); ?>

			<table class="form-table">

			<?php
				foreach ($this->settings_array as $key => $fields) {
					$formtype = $fields['formtype'];
					$title = $fields['title'];
					$description = $fields['description'];
					$value = $options[$key];
					if ($formtype == 'checkbox' && $value != false) {
						$checked = "checked='checked'";
					} else {
						$checked = "";
					}
			?>
			<?php settings_errors('vcs_automat_options_'.$key); ?>
				<tr>
				<th scope="row">
				<label for="vcs_automat_options[<?php echo esc_attr($key); ?>]">
					<?php esc_html_e($title, 'vcs_automat'); ?>:
				</label>
				</th>
				<td>
				<?php if ($formtype == 'checkbox') { ?>
					<input type="hidden" id="vcs_automat_options[<?php echo esc_attr($key); ?>]" name="vcs_automat_options[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($value); ?>"><input type="checkbox" onclick="this.previousSibling.value=1-this.previousSibling.value" <?php echo $checked; ?>>
				<?php } else { ?>
				<input
					type="<?php echo esc_attr($formtype); ?>"
					id="vcs_automat_options[<?php echo esc_attr($key); ?>]"
					name="vcs_automat_options[<?php echo esc_attr($key); ?>]"
					value="<?php echo esc_attr($value); ?>"
					<?php echo $checked; ?>
				></input>
				<?php } ?>
				<p class="description">
					<?php esc_html_e($description, 'vcs_automat'); ?>
				</p>
				</td>
				</tr>
			<?php
				}
				$reset_data = $this->get_resetdata();
			?>
			<tr>
				<th scope="row">
					<?php esc_html_e('Letzter Reset', 'vcs_automat'); ?>:
				</th>
				<td>
					<?php esc_html_e(date("d.m.y H:i", $reset_data['last_reset'])." Uhr", 'vcs_automat'); ?>
					<p class="description">
					<?php esc_html_e('Zeitpunkt des letzten durchgeführten Resets der Guthaben. Resets erfolgen im Laufe einer Stunde.', 'vcs_automat'); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<?php esc_html_e('Nächster Reset', 'vcs_automat'); ?>:
				</th>
				<td>
					<?php esc_html_e(date("d.m.y H:i", $reset_data['next_reset'])." Uhr", 'vcs_automat'); ?>
					<p class="description">
					<?php esc_html_e('Zeitpunkt des nächsten Resets der Guthaben. Resets erfolgen im Laufe einer Stunde.', 'vcs_automat'); ?>
					</p>
				</td>
			</tr>

			</table>

			<?php submit_button(__('Einstellungen speichern', 'vcs_automat')); ?>

		</form>
		</div>
		<?php
	}

	// reads the data corresponding to last and upcoming credit resets by querying the database
	private function get_resetdata() {
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/sql_interface.php');
		$db = SQLhandler::instance();
		$result = array();
		$to_fetch = array('last_reset', 'next_reset');
		foreach ($to_fetch as $value) {
			$result[$value] = $db->get_setting($value);
		}
		return $result;
	}

	// sanitizes the username and RFID used for updating a user's RFID in the database, then invokes the update in the SQL database
	public function sanitize_rfidchange($input) {

		if(!is_array($input) || empty($input) || (false === $input)) {
			return $output;
		}
		
		foreach ($this->settings_array_rfid as $key => $fields) {

			if (isset($input[$key])) {
				$new_setting = $input[$key];
				$datatype = $fields['datatype'];

				switch ($datatype) {
					case 'string':
						if (is_string($new_setting)) {
							$output[$key] = (string) $new_setting;
						} else {
							add_settings_error('vcs_automat_rfid_'.$key, $key, __($fields['title'] . ': This field must be a string.', 'vcs_automat'));
						}
						break;

					case 'rfid':
						if (is_numeric($new_setting) && strlen((string) $new_setting) == 6) {
							$output[$key] = (int) $new_setting;
						} else {
							add_settings_error('vcs_automat_rfid_'.$key, $key, __($fields['title'] . ': This field must be a rfid.', 'vcs_automat'));
						}
						break;

					default:
						add_settings_error('vcs_automat_rfid_'.$key, $key, __($fields['title'] . ': This datatype is unknown.', 'vcs_automat'));
						break;
				}
			}
		}
		$this->update_sql_rfidchange($output);
		return $output;
	  }

	// generates the HTML used to display the subpage for the RFID change
	public function generate_html_rfid() {
				  
		if (!current_user_can('manage_options')) {
			return;
		}
		
		// First show the error or update messages at the head of the page.
		settings_errors();
		settings_errors('vcs_automat_rfid');
		?>
		<div class="wrap">
		<h1><?= esc_html(get_admin_page_title()); ?></h1>
		<p>Mit diesem Menü kann die RFID eines Benutzers geändert werden, z.B. wenn dieser seine RFID falsch eingegeben oder geändert hat.</p>
		<form action="options.php" method="post">
			<?php settings_fields('vcs_automat_rfid_group'); ?>

			<table class="form-table">

			<?php
				foreach ($this->settings_array_rfid as $key => $fields) {
					$formtype = $fields['formtype'];
					$title = $fields['title'];
					$description = $fields['description'];
			?>
			<?php settings_errors('vcs_automat_rfid_'.$key); ?>
				<tr>
				<th scope="row">
				<label for="vcs_automat_rfid[<?php echo esc_attr($key); ?>]">
					<?php esc_html_e($title, 'vcs_automat'); ?>:
				</label>
				</th>
				<td>
				<input
					type="<?php echo esc_attr($formtype); ?>"
					id="vcs_automat_rfid[<?php echo esc_attr($key); ?>]"
					name="vcs_automat_rfid[<?php echo esc_attr($key); ?>]"
				></input>
				<p class="description">
					<?php esc_html_e($description, 'vcs_automat'); ?>
				</p>
				</td>
				</tr>
				<?php } ?>
			</table>

			<?php submit_button(__('RFID ändern', 'vcs_automat')); ?>

		</form>
		</div>
		<?php
	}
}
?>