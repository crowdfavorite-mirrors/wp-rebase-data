<?php
/**
 * Plugin Name: WP Rebase Data
 * Plugin URI: http://github.com/ssmathias/wp-rebase-data
 * Description: Allows a site admin to trigger "save" actions on a variety of data programmatically.
 * Author: Steven Mathias
 * Version: 0.1
 * Author URI: http://github.com/ssmathias/
 **/

define('WP_REBASE_PLUGIN_DIR', trailingslashit(dirname(__file__)));

class WP_Rebase_Data {
	private static $_current;
	private static $_tabs;
	
	public static function admin_init() {
		if (!class_exists('WPRD_Rebase_Posts')) {
			include WP_REBASE_PLUGIN_DIR.'classes/class.rebase.post.php';
		}
		do_action('wp_rebase_load_libraries');
		
		$default_tab = apply_filters('wp_rebase_data_default_tab', '');
		
		self::$_tabs = apply_filters('wp_rebase_admin_tabs', array());
		self::$_current = isset($_POST['wp_rebase_screen']) ? $_POST['wp_rebase_screen'] : $default_tab;
		if (!isset(self::$_tabs[self::$_current])) {
			reset(self::$_tabs);
			self::$_current = key(self::$_tabs);
		}
	}

	public static function admin_enqueue_scripts($hook) {
		if ($hook == 'tools_page_wp-rebase-data') {
			wp_enqueue_script('wp-rebase-data-js', admin_url('admin-ajax.php?action=wp_rebase_data_js&hook='.self::$_current), array('jquery'));
			wp_enqueue_style('wp-rebase-data-css', admin_url('admin-ajax.php').'?action=wp_rebase_data_css&hook='.self::$_current);
		}
	}

	public static function admin_menu() {
		add_submenu_page(
			'tools.php',
			__('Rebase Data', 'sm-wp-rebase-data'),
			__('Rebase Data', 'sm-wp-rebase-data'),
			'manage_options',
			'wp-rebase-data',
			'WP_Rebase_Data::admin_page'
		);
	}
	
	public static function admin_page() {
		if (!current_user_can('manage_options')) {
			echo __('You do not have sufficient privileges to access this page.', 'sm-wp-rebase-data');
			return;
		}
		screen_icon();
		?>
		<h1><?php echo esc_html(__('Rebase Data', 'sm-wp-rebase-data')); ?></h1>
		<ul class="tab-list" id="wp-rebase-tabs">
		<?php
		foreach (self::$_tabs as $hook=>$tabdata) {
			$classes = 'tab';
			if (self::$_current == $hook) { $classes .= ' current-tab'; };
		?>
			<li class="<?php echo esc_attr($classes); ?>"><a href="#<?php echo esc_attr($tabdata['id']); ?>"><?php echo esc_html($tabdata['title']); ?></a></li>
		<?php } ?>
		</ul>
		
		<?php
		do_action('wp_rebase_admin_screen_'.self::$_current);
	}
	
	public static function admin_js() {
		header('Content-Type: text/javascript');
		?>
		function doResave(data) {
			var $ = jQuery;
			if (typeof data.action === "undefined") {
				data.action = "wp_rebase_data";
			}
			jQuery.ajax({
				"method": "POST",
				"async": true,
				"url": ajaxurl,
				"data": data, /*{
					"action": "wp_rebase_data",
					"resave_action": action,
					"post_types": resavePosts.post_types,
					"post_stati": resavePosts.post_stati,
					"paged": resavePosts.page
				},*/
				"success": function(response) {
					var completePercent = 0;
					$(this).trigger("wpRebaseAjaxSuccess", response);
					response = jQuery.parseJSON(response);
					resavePosts.page += 1;
					hasComplete = (response.total_records == response.records_complete);
					if (response.total_records == 0) {
						completePercent = "100%";
					}
					else {
						completePercent = Math.round((response.records_complete / response.total_records) * 100) + "%";
					}
					resavePosts.jForm
						.find(".progress-indicator")
							.find(".progress-bar")
								.width(completePercent)
								.end()
							.find(".numeric-indicator")
								.html(response.records_complete + "/" + response.total_records)
								.end()
							.find(".ajax-errors")
								.append(response.warnings);
								
					if (response.total_records > response.records_complete) {
						// We need to do this again. Loop it with a timeout so we can redraw the screen.
						doResaveAction();
					}
					else {
						resavePosts.jForm
							.find("button, input")
								.removeAttr("disabled");
					}
				},
				"error": function(xhr) {
					$("body").trigger("wpRebaseAjaxError", xhr);
				}
			});
		}
		<?php
		do_action('wp_rebase_admin_scripts');
		if ($_GET['hook']) {
			do_action('wp_rebase_admin_scripts_'.$_GET['hook']);
		}
		exit();
	}
	
	public static function admin_css() {
		header('Content-Type: text/css');
		do_action('wp_rebase_admin_styles');
		if ($_GET['hook']) {
			do_action('wp_rebase_admin_styles_'.$_GET['hook']);
		}
		exit();
	}

	public static function wp_ajax_wp_rebase_data() {
		global $post;
		if (isset($_POST['hook']) && !empty($_POST['hook'])) {
			do_action('wp_rebase_ajax_'.$_POST['hook']);
		}
		exit(-1);
	}

}
add_action('admin_init', 'WP_Rebase_Data::admin_init', 1);
add_action('admin_enqueue_scripts', 'WP_Rebase_Data::admin_enqueue_scripts');
add_action('admin_menu', 'WP_Rebase_Data::admin_menu');
add_action('wp_ajax_wp_rebase_data_js', 'WP_Rebase_Data::admin_js');
add_action('wp_ajax_wp_rebase_data_css', 'WP_Rebase_Data::admin_css');
add_action('wp_ajax_wp_rebase_data', 'WP_Rebase_Data::wp_ajax_wp_rebase_data');
