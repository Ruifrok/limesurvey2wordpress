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

add_action('admin_enqueue_scripts', 'ls2wp_admin_style');
	function ls2wp_admin_style() {
		wp_enqueue_style('ls2wp_admin-styles', plugin_dir_url(__FILE__).'assets/admin-style.css');
		wp_enqueue_script('ls2wp-admin-scripts', plugin_dir_url(__FILE__).'assets/ls2wp-admin.js', array('jquery'));
		
		$ls2wp_nonce = wp_create_nonce( 'ls2wp' );
		wp_localize_script( 'ls2wp-admin-scripts', 'ls2wp', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce'    => $ls2wp_nonce,
		));		
	}

add_action( 'init', 'wp2ls_load_textdomain' );
	function wp2ls_load_textdomain() {	  
		load_plugin_textdomain( 'ls2wp', false, 'ls2wp/languages/' );	  
	}

$ls_url = trailingslashit(get_option('ls_url'));
define('LS2WP_SITEURL', $ls_url);

//Make tabels rpc_participants and rpc_responses on plugin activation
register_activation_hook( __FILE__, 'make_ls2wp_tables' );
	function make_ls2wp_tables(){
		$resps = new Ls2wp_RPC_Responses();
		$parts = new Ls2wp_RPC_Participants();
		
		$resps->ls2wp_create_resp_table();
		$parts->ls2wp_create_participant_table();
	}


//shortcode voor testfuncties
add_shortcode('testfuncties', 'test_code');
	function test_code() {		
	
		global $lsdb;		
		ob_start();

		$survey_id = 297532;
		//$survey_id = 779253;
		//$survey_id = 863694;
		//$survey_id = 311591;
		//$survey_id = 516331;
		$token = 'Idlbjspu9WrJ9Wb';
		
		$user = get_userdata(2);
		$email = $user->user_email;		
		
		$resps = new Ls2wp_RPC_Responses();
		$parts = new Ls2wp_RPC_Participants();
		
		//$test = $resps->ls2wp_import_responses($survey_id);
		$test =ls2wp_ls_active_surveys($user);
		
		print_obj($test);

		$test1 = get_transient('test1');
		$test2 = get_transient('test2');
		$test3 = get_transient('test3');

		
		
		print_obj($test1);
		print_obj($test2);
		print_obj($test3);
		delete_transient('test1');
		delete_transient('test2');
		delete_transient('test3');
		
		return ob_get_clean();
	
	}