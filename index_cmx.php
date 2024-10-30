<?php
/**
 * Plugin Name:       ContentMX Content Publisher
 * Plugin URI:        https://contentmx.com/wordpress-plugin
 * Description:       This plugin connects your WordPress blog/website with your ContentMX account allowing you to publish authorized content from your favorite vendors and manufacturers directly to WordPress. This plug-in works securely with ContentMX co-branded platforms such as Microsoft DMC (Digital Marketing Content OnDemand), TD SYNNEX DEMANDSolv, and Arrow's Curated Content.
 * Version:           1.0.6
 * Requires at least: 5.2
 * Requires PHP:      5.6
 * Text Domain:       contentmx-post
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 */

include_once(plugin_dir_path( __FILE__ ).'/inc/functions.php');

register_activation_hook( __FILE__, 'contentmx_content_manager\activate_cmx_ccm_env' );

//ajax function
add_action('wp_ajax_nopriv_cmx_ajxpost', 'contentmx_content_manager\cmx_ajxpost');
add_action('wp_ajax_cmx_ajxpost', 'contentmx_content_manager\cmx_ajxpost');
add_action('wp_ajax_cmx_delete_id', 'contentmx_content_manager\cmx_delete_id');

//add rest endpoint
// /wp-json/cmx_cnct/v1/cmxconnect  
add_action('rest_api_init', function () {
	register_rest_route('cmx_cnct/v1', 'cmxconnect', array(
		'methods' => array('POST','GET'),
		'callback' => 'contentmx_content_manager\cmx_connect_wp_rest'
	));
});

add_action('admin_menu', 'contentmx_content_manager\cmx_menu_page');