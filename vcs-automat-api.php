<?php

/*
Plugin Name:  VCS Automat API
Plugin URI:   https://github.com/agimpel/vcs-automat-api
GitHub Plugin URI: agimpel/vcs-automat-api
GitHub Plugin URI: https://github.com/agimpel/vcs-automat-api
Description:  API für den Automaten der VCS.
Version:      1.0
Author:       Andreas Gimpel
Author URI:   mailto:andreas.gimpel@agimpel.com
License:      MIT
License URI:  https://directory.fsf.org/wiki/License:Expat
*/




// prevent this file from being executed directly
defined('ABSPATH') or die();







class VCS_Automat {

	// the instance of this class
	private static $_instance = null;

	// return or create the single instance of this class
	public static function instance() {
		if (is_null(self::$_instance)) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}


	// instances of sub classes
	private $_shortcode_page_instance = null;
	private $_plugin_options_instance = null;
	private $logger = null;


	// constructor: adds all hooks, actions and includes
	public function __construct() {

		// declare plugin directory as constant, without the trailing slash
		define('VCS_AUTOMAT_PLUGIN_DIR', untrailingslashit(plugin_dir_path(__FILE__)));

		// set up logging file and logging function
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/logger.php');
		$this->logger = Logger::instance();
		$this->logger->setup('wordpress', 'INFO');

		// if the admin dashboard is displayed (does not check if user is allowed to change settings), load the settings page for the vending machine
		if (is_admin()) {
			require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/plugin_options.php');
			add_action('admin_menu', array($this, 'plugin_menu'));
		}

		// always load the following things for the front-end
		require_once(VCS_AUTOMAT_PLUGIN_DIR . '/modules/shortcode_page.php');
		add_shortcode('vcs_automat', array($this, 'shortcode_vcs_automat'));
		add_action('wp_enqueue_scripts', array($this, 'scripts_callback'));
	}


	public function plugin_menu() {
		$this->_plugin_options_instance = Plugin_Options::instance();
		$this->_plugin_options_instance->main();
	}


	public function shortcode_vcs_automat() {
		$this->_shortcode_page_instance = Shortcode_Page::instance();
		return $this->_shortcode_page_instance->vcs_automat();
	}


	public function scripts_callback() {
		wp_register_style('vcs-automat', plugins_url('css/main.css', __FILE__));
		wp_enqueue_style('vcs-automat');
		wp_enqueue_style('dashicons-style', get_stylesheet_uri(), array('dashicons'), '1.0');
	}
}


// declare the instance of the VCS_Automat class as a global
$GLOBALS['vcs_automat'] = VCS_Automat::instance();
?>