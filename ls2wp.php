<?php
/**
 * Plugin Name:       LS2WP
 * Description:       Load Limesurveydata to Wordpress
 * Requires at least: 5.2
 * Requires PHP:      8.0
 * Author:            Rob Ruifrok
 * Author URI:        https://ruifrok.net
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:		  ls2wp
 * Domain Path:		  /languages
 * Requires Plugins:  
 */
 
include_once('includes/ls2wp-JsonRPCClient.php');
include_once('includes/ls2wp-functions.php');
include_once('includes/ls2wp-admin.php');
include_once('includes/ls2wp-limesurvey-rpc.php');
include_once('includes/ls2wp-limesurvey-db.php');
include_once('includes/ls2wp-reporting.php');

if(in_array( 'm-chart/m-chart.php' , apply_filters('active_plugins', get_option('active_plugins')))){ 
	include_once('includes/ls2wp-m-chart-integration.php');
}


add_action('admin_enqueue_scripts', 'ls2wp_admin_style');
	function ls2wp_admin_style() {
		wp_enqueue_style('ls2wp-admin-styles', plugin_dir_url(__FILE__).'assets/admin-style.css');
		wp_enqueue_script('ls2wp-admin-scripts', plugin_dir_url(__FILE__).'assets/ls2wp-admin.js', array('jquery'));
		
		$ls2wp_nonce = wp_create_nonce( 'ls2wp' );
		wp_localize_script( 'ls2wp-admin-scripts', 'ls2wp', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => $ls2wp_nonce,
		));		
	}

add_action( 'wp_enqueue_scripts', 'wp2ls_enqueue_style' );
	function wp2ls_enqueue_style(){
		wp_enqueue_style('ls2wp-style', plugin_dir_url(__FILE__).'assets/style.css');
		wp_enqueue_script( 'ls2wp-charts', 'https://www.gstatic.com/charts/loader.js' );
	}

add_action( 'init', 'wp2ls_load_textdomain' );
	function wp2ls_load_textdomain() {	  
		load_plugin_textdomain( 'ls2wp', false, 'ls2wp/languages/' );	  
	}

$ls_url = get_option('ls_url');
if(!empty($ls_url)) $ls_url = trailingslashit($ls_url);
define('LS2WP_SITEURL', $ls_url);

//Make tabels rpc_participants and rpc_responses on plugin activation
register_activation_hook( __FILE__, 'make_ls2wp_tables' );
	function make_ls2wp_tables(){
		$resps = new Ls2wp_RPC_Responses();
		$parts = new Ls2wp_RPC_Participants();
		
		$resps->ls2wp_create_resp_table();
		$parts->ls2wp_create_participant_table();
	}