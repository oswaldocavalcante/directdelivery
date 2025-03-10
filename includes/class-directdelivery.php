<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://oswaldocavalcante.com
 * @since      1.0.0
 *
 * @package    DirectDelivery
 * @subpackage DirectDelivery/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    DirectDelivery
 * @subpackage DirectDelivery/includes
 * @author     Oswaldo Cavalcante <contato@oswaldocavalcante.com>
 */
class DirectDelivery
{
	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct()
	{
		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Ddw_i18n. Defines internationalization functionality.
	 * - Ddw_Admin. Defines all hooks for the admin area.
	 * - Ddw_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies()
	{
		require_once DDW_ABSPATH . 'includes/class-directdelivery-i18n.php';
		require_once DDW_ABSPATH . 'admin/class-ddw-admin.php';
		require_once DDW_ABSPATH . 'public/class-ddw-public.php';
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Ddw_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale()
	{
		$plugin_i18n = new DirectDelivery_i18n();
		add_action('plugins_loaded', array($plugin_i18n, 'load_plugin_textdomain'));
	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks()
	{
		$plugin_admin = new Ddw_Admin();

		add_action('before_woocommerce_init',       array($plugin_admin, 'declare_wc_compatibility'));
		add_action('admin_enqueue_scripts', 		array($plugin_admin, 'enqueue_styles'));
		add_action('admin_enqueue_scripts', 		array($plugin_admin, 'enqueue_scripts'));

		add_filter('woocommerce_integrations', 		array($plugin_admin, 'add_integration'));
		add_action('add_meta_boxes', 				array($plugin_admin, 'add_meta_box'));

		add_action('wp_ajax_ddw_get_delivery', 		array($plugin_admin, 'ajax_get_delivery'));
		add_action('wp_ajax_ddw_create_delivery', 	array($plugin_admin, 'ajax_create_delivery'));
		add_action('wp_ajax_ddw_cancel_delivery', 	array($plugin_admin, 'ajax_cancel_delivery'));
		add_action('admin_footer', 					array($plugin_admin, 'add_modal_templates'));
		add_filter('plugin_action_links_' . DDW_BASENAME, array($plugin_admin, 'plugin_action_links'));

		// Register admin-specific webhook
		add_action('rest_api_init', 				array($plugin_admin, 'register_webhook'));
		add_action('ddw_change_order_status', 		array($plugin_admin, 'change_order_status'), 10, 2);
	}

	private function define_public_hooks()
	{
		$plugin_public = new Ddw_Public();

		add_filter('woocommerce_cart_shipping_method_full_label', array($plugin_public, 'display_deadline_on_label'), 10, 2);
	}
}
