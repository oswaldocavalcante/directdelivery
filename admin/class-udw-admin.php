<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://oswaldocavalcante.com
 * @since      1.0.0
 *
 * @package    Udw
 * @subpackage Udw/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Udw
 * @subpackage Udw/admin
 * @author     Oswaldo Cavalcante <contato@oswaldocavalcante.com>
 */

require_once UDW_ABSPATH . 'integrations/uberdirect/class-udw-ud-api.php';

class Udw_Admin
{
	private $udw_ud_api;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct()
	{
		$this->udw_ud_api = new Udw_Ud_Api();
	}

	public function is_woocommerce_active()
	{
		$active_plugins = (array) get_option('active_plugins', array());
		if (is_multisite()) {
			$active_plugins = array_merge($active_plugins, get_site_option('active_sitewide_plugins', array()));
		}
		if (in_array('woocommerce/woocommerce.php', $active_plugins) || array_key_exists('woocommerce/woocommerce.php', $active_plugins)) {
			return true;
		} else {
			return false;
		}
	}

	public function add_integration($integrations)
	{
		if ($this->is_woocommerce_active())
		{
			include_once  UDW_ABSPATH . 'integrations/woocommerce/class-udw-wc-integration.php';
			$integrations[] = 'Udw_Wc_Integration';

			return $integrations;
		}
		else {
			wp_admin_notice(__('Please install and activate WooCommerce to use Uber Direct!', 'uberdirect'), array('type' => 'error'));
		}

		return null;
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles()
	{
		wp_enqueue_style('uberdirect', plugin_dir_url(__FILE__) . 'assets/css/udw-admin.css', array(), UDW_VERSION, 'all');
	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts()
	{
		wp_enqueue_script('uberdirect', plugin_dir_url(__FILE__) . 'assets/js/udw-admin.js', array('jquery'), UDW_VERSION, false);

		wp_localize_script(
			'uberdirect',
			'udw_delivery_params',
			array(
				'url' => admin_url('admin-ajax.php'),
				'nonce' => wp_create_nonce('udw_nonce_delivery'),
			)
		);
	}

	public function register_settings()
	{
		register_setting('uberdirect_settings', 'udw-api-customer-id', 		array('type' => 'string', 'default' => ''));
		register_setting('uberdirect_settings', 'udw-api-client-id', 		array('type' => 'string', 'default' => ''));
		register_setting('uberdirect_settings', 'udw-api-client-secret', 	array('type' => 'string', 'default' => ''));
	}

	public function add_meta_box()
	{
		add_meta_box('wc-udw-widget', __('Uber Direct', 'udw-widget'), array($this, 'render_meta_box'), 'shop_order', 'side', 'high');
	}

	public function render_meta_box($wc_post)
	{
		global $post;
		$order_id = isset($post) ? $post->ID : $wc_post->get_id();
		$order = wc_get_order($order_id);
		$delivery_status = 'undefined';

		if ($order->meta_exists('_udw_delivery_id'))
		{
			$delivery_id = $order->get_meta('_udw_delivery_id');
			$delivery = $this->udw_ud_api->get_delivery($delivery_id);
			$delivery_status = $delivery->status;
		}

		?>
		<div id="udw-metabox-container">
			<div id="udw-metabox-status">
				<? esc_html_e(sprintf(__('Status: %s', 'uberdirect'), $delivery_status)); ?>
			</div>
			<div id="udw-metabox-action">
				<?php if (in_array($delivery_status, array('undefined', 'pending', 'canceled', 'returned'))) { ?>
				<a href="#" class="button button-primary" id="udw-button-pre-send" data-order-id="<?php esc_attr_e($order_id); ?>">
					<?php esc_html_e('Send', 'uberdirect'); ?>
				</a>
				<?php } elseif ($order->meta_exists('_udw_delivery_id')) { ?>
				<p>
					<? esc_html_e(sprintf(__('Courier: %s', 'uberdirect'), 		$delivery->courier->name)); ?>
					<? esc_html_e(sprintf(__('Vehicle: %s', 'uberdirect'), 		$delivery->courier->vehicle_type)); ?>
					<? esc_html_e(sprintf(__('Phone number: %s', 'uberdirect'), $delivery->courier->phone_number)); ?>
					<? esc_html_e(sprintf(__('Tracking URL: %s', 'uberdirect'), $delivery->courier->tracking_url)); ?>
				</p>
				<?php } ?>
			</div>
		</div>
		<?php
	}

	public function ajax_get_delivery()
	{
		if (isset($_POST['security']) && check_ajax_referer('udw_nonce_delivery', 'security')) 
		{
			$order_id = $_POST['order_id'];
			$order = wc_get_order($order_id);

			if ($order->meta_exists('_udw_delivery_id')) 
			{
				$delivery_id = $order->get_meta('_udw_delivery_id');
				$delivery = $this->udw_ud_api->get_delivery($delivery_id);
				wp_send_json_success($delivery);
			} 
			else {
				wp_send_json_success(json_decode($order));
			}
		}
	}

	public function ajax_create_delivery()
	{
		if (isset($_POST['security']) && check_ajax_referer('udw_nonce_delivery', 'security')) 
		{
			$order_id = $_POST['order_id'];
			$order = wc_get_order($order_id);

			$dropoff_name 			= $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name();
			$dropoff_address 		= str_replace('<br/>',', ',$order->get_formatted_shipping_address());
			$dropoff_notes 			= $order->get_shipping_address_2();
			$dropoff_phone_number 	= $order->get_billing_phone();
			$manifest_items 		= array();

			foreach ($order->get_items() as $item) {
				$manifest_items[] = new ManifestItem($item->get_name(), $item->get_quantity());
			}

			$delivery = $this->udw_ud_api->create_delivery($order_id, $dropoff_name, $dropoff_address, $dropoff_notes, $dropoff_phone_number, $manifest_items);

			$tip = (float) $order->get_shipping_total() - ($delivery->fee / 100); // Fee is in cents
			if ($tip > 0) {
				$delivery = $this->udw_ud_api->update_delivery($delivery->id, $tip); // Updates the delivery repassing the difference between the quote and the real delivery to Uber for tax issues
			}

			if (!$order->meta_exists('_udw_delivery_id')) 
			{
				$order->add_meta_data('_udw_delivery_id', $delivery->id);
				$order->save_meta_data();
			}

			wp_send_json_success($delivery);
		}
	}

	public function add_modal_templates(): void
	{
		include_once 'views/udw-modal-quote.php';
		include_once 'views/udw-modal-delivery.php';
	}
}
