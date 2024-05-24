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
		wp_enqueue_style('admin-styles', plugin_dir_url(__FILE__).'assets/admin-style.css');
		wp_enqueue_script('ls2wp-admin-scripts', plugin_dir_url(__FILE__).'assets/ls2wp-admin.js', array('jquery'));
	}

add_action( 'init', 'wp2ls_load_textdomain' );
	function wp2ls_load_textdomain() {	  
		load_plugin_textdomain( 'ls2wp', false, 'ls2wp/languages/' );	  
	}

$ls_url = get_option('ls_url');
define('LS2WP_SITEURL', $ls_url);


add_filter('ls2wp_survey_filter_args','ls2wp_survey_filter_new_args', 5, 2);
	function ls2wp_survey_filter_new_args($args){
		
		//$args['survey_group_id'] = 4;
		//$args['all_surveys'] = true;
		
		return $args;
	}


//shortcode voor testfuncties
add_shortcode('testfuncties', 'test_code');
	function test_code() {		
	
		ob_start();

		$survey_id = 696413;
		//$survey_id = 863694;
		//$survey_id = 311591;
		//$survey_id = 516331;
		$token = 'Idlbjspu9WrJ9Wb';
		
		$user = get_userdata(3);
		$email = $user->user_email;

		//print_obj(count($test));
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