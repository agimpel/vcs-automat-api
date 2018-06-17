<?php


// prevent this file from being executed directly
defined('ABSPATH') or die();



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
			'datatype' => 'bool', 
			'formtype' => 'checkbox', 
			'default' => 0, 
			'title' => 'Frontend aktiv', 
			'description' => 'Ob die Anmeldeseite mit Statistiken verfügbar sein soll.'),
		'vcs_automat_activate_vending_bool' => array(
			'datatype' => 'bool', 
			'formtype' => 'checkbox', 
			'default' => 0, 
			'title' => 'API aktiv', 
			'description' => 'Ob die Validierung über die VCS API verfügbar sein soll.'),
		'vcs_automat_default_credits' => array(
			'datatype' => 'int', 
			'formtype' => 'number', 
			'default' => 2, 
			'title' => 'Standard Credits', 
			'description' => 'Zahl der Credits, die standardmässig nach einem Reset verfügbar sind.'),
		'vcs_automat_time_interval_days' => array(
			'datatype' => 'int', 
			'formtype' => 'number', 
			'default' => 7, 
			'title' => 'Zeitintervall Reset', 
			'description' => 'Zahl der Tage für einen Reset.')
	);


    // constructor empty to override the parent class constructor
    public function __construct() {

    }


	public function main() {
		add_menu_page('Einstellungen für den VCS-Automat', 'VCS-Automat', 'manage_options', 'vcs-automat', array($this, 'generate_html'));
		add_action('admin_init', array($this, 'admin_initialise'));
		add_filter('update_option_vcs_automat_options', array($this, 'options_changed'), 10, 2);
	}


	public function default_options() {
		$array = array();
		foreach ($this->settings_array as $key => $value) {
			$array[$key] = $value['default'];
		}
		return $array;
	  }
	   

	  public function get_options() {
		return get_option('vcs_automat_options', $this->default_options());
	  }
	   

	  public function admin_initialise() {
		register_setting('vcs_automat_options_group', 'vcs_automat_options', array(
			'type' => 'array', 
			'default' => $this->default_options(), 
			'sanitize_callback' => array($this, 'sanitize_options')
		  )
		);
	  }

	  public function options_changed($old, $new) {
		echo("SOMETHING CHANGED");
	  }


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

					default:
						add_settings_error('vcs_automat_options_'.$key, $key, __($fields['title'] . ': This datatype is unknown.', 'vcs_automat'));
						break;
				}
			}
		}
	   
		return $output;
	  }
	  


	  public function generate_html() {
		  
		if (!current_user_can('manage_options')) {
			return;
		}

		// get previously set options
		$options = $this->get_options();
		
		// First show the error or update messages at the head of the page.
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
			?>

			</table>

			<?php submit_button(__('Einstellungen speichern', 'vcs_automat')); ?>

		</form>
		</div>
		<?php
	}

}






?>