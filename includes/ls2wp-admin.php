<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//Add ls2wp settings menu page
add_action('admin_menu', 'ls2wp_settings_page');
	function ls2wp_settings_page() {

		add_menu_page( 
			__('LS2WP settings', 'ls2wp'),
			'LS2WP', 
			'manage_options', 
			'ls2wp', 
			'ls2wp_settings_display',
			);
	}
	function ls2wp_settings_display(){

		?>
		<h1><?php esc_html_e('LS2WP settings', 'ls2wp');?></h1>	
		<h2><?php esc_html_e('Introduction', 'ls2wp');?></h2>
		<div>
			<p><?php esc_html_e('It is important to understand that the concept of users in Wordpress and Limesurvey is different. In Limesurvey those who take a survey are called participants. Participant data are not stored in the Limesurvey user database. In Limesurvey users are only those who have rights to add or change surveys depending on their user rights.', 'ls2wp');?></p>
			<p><?php esc_html_e('This plugin can only read data from the Limesurvey database or add Limesurvey partcipants to surveys. It has no functionality to add or change Limesurvey users or add or change surveys.', 'ls2wp');?></p>
			
			<p><?php esc_html_e('The prefered way to access Limesurvey data is to link to the Limesurvey database. It is a lot faster and more efficient than using the JSON-RPC interface. If you do not have access to the Limesurvey database, the JSON/RPC interface can be used. This is a relatively slow connection. To improve speed when using JSON/RPC, data are stored in transients and two tables.', 'ls2wp');?></p>
		</div>		
		
		<form class="ls2wp-settings" action="options.php" method="post">
			<?php
			settings_fields( 'ls2wp' );
			do_settings_sections( 'ls2wp' );
			
			$submit_text = __('Save', 'ls2wp');
	
			submit_button($submit_text);
			?>	 
		 </form>
		 
			 <div class="rpc-credentials import-surveys">
			 
				 <?php			 
				 
				$surveys = ls2wp_get_surveys();
				
				if(empty($surveys) || is_string($surveys)) $surveys = array();
				
				if(!empty($surveys) && count($surveys) > 1){
					usort($surveys, function ($a, $b){return strcmp($a->surveyls_title, $b->surveyls_title);});
				}

				?>
				
				<h2><?php esc_html_e('Import survey responses and participants.', 'ls2wp');?></h2>

				<div>
					<p><?php esc_html_e('Retrieving data with JSON-RPC is slow. To keep loading speeds acceptable data are stored in the Wordpress database.', 'ls2wp');?></p>
					<p><?php esc_html_e('Survey data are stored in transients with a maximum duration of a month. This means you should clear the transients before changes in the survey in Limesurvey are avilable in Wordpress. Use the clear transients button below!', 'ls2wp')?></p>
					<p><?php esc_html_e('Responses and participant data are stored in a database table. These data should be imported manually with the import form below. When new responses or participamts are added in Limesurvey you have to perform an new import to make them available. Incomplete responses are updated at import.', 'ls2wp');?></p>
					<p><?php esc_html_e('The link between a wordpress user and a Limesurvey participant is his email address. A participant email address is updated in Limesurvey when the wp-user email address is changed', 'ls2wp');?></p>
				</div>
				
				<form class="import-form" method="post">				

					<label>
					<?php esc_html_e('Import participants and responses of:', 'ls2wp') ?>
					</label>
					<select id="survey" name="survey" required>
						<option value=""><?php esc_html_e('Select survey', 'ls2wp');?></option>
						<?php
						foreach($surveys as $survey) {
							
							$selected = (isset($_POST['survey']) && $_POST['survey'] == $survey->sid) ? 'selected' : '';
							
							echo '<option value="'.esc_attr($survey->sid).'" '.esc_attr($selected).'>'.esc_html($survey->surveyls_title).'('.esc_html($survey->sid).')</option>';		
						}	
						?>
					</select>	
					<?php wp_nonce_field('import survey data');?>
					<input type="submit" id="select-import-survey" value="<?php esc_html_e('Import survey data', 'ls2wp');?>">				
				</form>
				
				
			</div>
			<div class="rpc-credentials clear-transients">
				<h2><?php esc_html_e('Clear transients', 'ls2wp')?></h2>
				
				<p><?php esc_html_e('To improve speed the following data are stored in a transient with a maximum duration of a month;', 'ls2wp');?></p>
				<ul>
					<li><?php esc_html_e('The fieldmap of a survey is stored in a transient with the name fieldmap_{survey_id}. The fieldmap is loaded witth the jSON-RPC functiom "get_fieldmap()". The fieldmap links the question, answer and assesment value to the question code and answer code. ', 'ls2wp');?></li>
					<li><?php esc_html_e('The survey properties of a survey are stored in a transient survey_props_{$survey_id}. The surveyproperties are loaded with the JSON-RPC function "get_survey_properties()" ', 'ls2wp');?></li>
				</ul>
				<p><?php esc_html_e('In case text in Limesurvey has been changed, you have to clear the transients to display the new text.', 'ls2wp');?></p>
				<form class="clear-transient-form" method="post">				

					<label>
					<?php esc_html_e('Clear transients of:', 'ls2wp') ?>
					</label>
					<select id="survey-transients" name="survey-transients" required>
						<option value=""><?php esc_html_e('Select survey', 'ls2wp');?></option>
						<?php
						foreach($surveys as $survey) {
							
							$selected = (isset($_POST['survey']) && $_POST['survey'] == $survey->sid) ? 'selected' : '';
							
							echo '<option value="'.esc_attr($survey->sid).'" '.esc_attr($selected).'>'.esc_html($survey->surveyls_title).'('.esc_html($survey->sid).')</option>';		
						}	
						?>
					</select>	
					<?php wp_nonce_field('clear survey transients');?>
					<input type="submit" id="clear-transients" value="<?php esc_html_e('Clear survey transients', 'ls2wp');?>">				
				</form>			
			
			</div>
		
		<?php
	}

//Add setting sections
add_action( 'admin_init', 'ls2wp_admin_init' );
	function ls2wp_admin_init(){
		$page = 'ls2wp';		
	
		add_settings_section(
			'survey_ids',
			__('Basic settings', 'ls2wp'),
			'ls2wp_expl_surveys',
			$page,
			array(
				'before_section' => '<div class="base-settings">',
				'after_section' => '</div>',
				),
			);
			
			register_setting($page, 'ls_survey_ids', array('sanitize_callback' => 'validate_survey_ids'));
			add_settings_field(
				'ls_survey_ids',
				__('Comma seperated list of surevey ids', 'ls2wp'),
				'ls2wp_survey_ids_input',
				$page,
				'survey_ids'
			);
			register_setting($page, 'ls_survey_group_ids', array('sanitize_callback' => 'validate_survey_group_ids'));
			add_settings_field(
				'ls_survey_group_ids',
				__('Comma seperated list of surevey group ids', 'ls2wp'),
				'ls2wp_survey_group_ids_input',
				$page,
				'survey_ids'
			);			
			register_setting($page, 'ls_url', array('sanitize_callback' => 'validate_ls_url'));
			add_settings_field(
				'ls_url',
				__('URL of the Limesurvey install', 'ls2wp'),
				'ls2wp_ls_url_input',
				$page,
				'survey_ids',
				array(
					'label_for' => 'ls_url',
					'class' => 'ls2wp-required',
				),
			);			
			register_setting($page, 'use_rpc');
			add_settings_field(
				'use_rpc',
				__('Use the JSON-RPC interface', 'ls2wp'),
				'ls2wp_use_rpc_input',
				$page,
				'survey_ids'
			);
		
		add_settings_section(
			'lsdb-credentials',
			__('Credentials for the Limesurvey database', 'ls2wp'),
			'ls2wp_expl_ls_credentials',
			$page,
			array('before_section' => '<div class="lsdb-credentials">', 'after_section' => '</div>'),
			);		
			register_setting($page, 'lsdb_user');
			add_settings_field(
				'lsdb_user',
				__('Username of the Limesurvey database', 'ls2wp'),
				'ls2wp_db_user_input',
				$page,
				'lsdb-credentials',
				array(
					'label_for' => 'lsdb_user',
					'class' => 'ls2wp-required',
				),				
			);
			register_setting($page, 'lsdb_passw');
			add_settings_field(
				'lsdb_passw',
				__('Password of the Limesurvey database', 'ls2wp'),
				'ls2wp_db_passw_input',
				$page,
				'lsdb-credentials'
			);	
			
			register_setting($page, 'lsdb_name');
			add_settings_field(
				'lsdb_name',
				__('Name of the Limesurvey database', 'ls2wp'),
				'ls2wp_db_name_input',
				$page,
				'lsdb-credentials',
				array(
					'label_for' => 'lsdb_name',
					'class' => 'ls2wp-required',
				),				
			);
			register_setting($page, 'lsdb_host');
			add_settings_field(
				'lsdb_host',
				__('Hostname of the Limesurvey database', 'ls2wp'),
				'ls2wp_db_host_input',
				$page,
				'lsdb-credentials',
				array(
					'label_for' => 'lsdb_host',
					'class' => 'ls2wp-required',
				),				
			);
			register_setting($page, 'lsdb_prefix');
			add_settings_field(
				'lsdb_prefix',
				__('Prefix of the tables in the Limesurvey database', 'ls2wp'),
				'ls2wp_db_prefix_input',
				$page,
				'lsdb-credentials',
				array(
					'label_for' => 'lsdb_prefix',
					'class' => 'ls2wp-required',
				),				
			);
			
		add_settings_section(
			'ls-rpc-credentials',
			__('Credentials for the Limesurvey json-rpc interface', 'ls2wp'),
			'ls2wp_expl_rpc_credentials',
			$page,
			array('before_section' => '<div class="rpc-credentials">', 'after_section' => '</div>'),
			);			
		
			register_setting($page, 'ls_rpc_user');
			add_settings_field(
				'ls_rpc_user',
				__('Username of the Limesurvey account', 'ls2wp'),
				'ls2wp_rpc_user_input',
				$page,
				'ls-rpc-credentials',
				array(
					'label_for' => 'ls_rpc_user',
					'class' => 'ls2wp-required',
				),				
			);
			
			register_setting($page, 'ls_rpc_passw');
			add_settings_field(
				'ls_rpc_passw',
				__('Password of the Limesurvey account', 'ls2wp'),
				'ls2wp_rpc_passw_input',
				$page,
				'ls-rpc-credentials',
				array(
					'label_for' => 'ls_rpc_passw',
					'class' => 'ls2wp-required',
				),				
			);		
	
				
	}


function ls2wp_expl_surveys(){
	
	?>
	<p><?php esc_html_e('Give the following info:', 'ls2wp')?> </p>
	<ul>
		<li><?php esc_html_e('Which surveys are made available on your wordpress website. A comma separated list of survey ids and/or a list of survey group ids.', 'ls2wp');?></li>
		<li><?php esc_html_e('The url to the the Limesurvey installation', 'ls2wp');?></li>
		<li><?php esc_html_e('If you want to use the json/rpc interface of Limesurvey', 'ls2wp');?></li>
	</ul>
	<p></p>
	<?php
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc && !empty(LS2WP_SITEURL)){
		
		$surveys = ls2wp_get_surveys();
		
		if(empty($surveys) || is_string($surveys)) $surveys = array();
		
		$sids = array_column($surveys, 'sid');
		
		$survey_id_string = get_option('ls_survey_ids');
		
		$survey_ids = explode(',', str_replace(' ', '', $survey_id_string));

		foreach($survey_ids as $survey_id){
			
			if(!in_array($survey_id, $sids) && !empty($survey_id_string)){
			echo '<div class="ls2wp-alert">No results found for survey id '.esc_html($survey_id).'. Check if the survey exists and if the survey_id is correct<br></div> ';
			}
		}
		
		$survey_groups = ls2wp_get_survey_groups();
		
		if(empty($survey_groups) || is_string($survey_groups)) $survey_groups = array();
		
		$gsids = array_column($survey_groups, 'gsid');
		
		$survey_group_id_string = get_option('ls_survey_group_ids');
		
		$survey_group_ids = explode(',', str_replace(' ', '', $survey_group_id_string));

		foreach($survey_group_ids as $survey_group_id){
			
			if(!in_array($survey_group_id, $gsids) && !empty($survey_group_id_string)){
			echo '<div class="ls2wp-alert">No results found for survey group id '.esc_html($survey_group_id).'. Check if the survey group exists and if the survey_group_id is correct<br></div> ';
			}
		}		
	} else {		
		
		$sid_errors = get_settings_errors('ls_survey_ids');
		
		if(!empty($sid_errors)){

			$sid_error_msg = $sid_errors[0]['message'];			
			echo '<div class="ls2wp-alert">'.esc_html($sid_error_msg).'</div>';
		
		}		
		
		$sgid_errors = get_settings_errors('ls_survey_group_ids');
	
		if(!empty($sgid_errors)){

			$sgid_error_msg = $sgid_errors[0]['message'];			
			echo '<div class="ls2wp-alert">'.esc_html($sgid_error_msg).'</div>';
		
		}		
	}
}

function ls2wp_expl_ls_credentials(){
	
	global $lsdb;
	
	if(isset($lsdb) && $lsdb->error){
	
	?>
		<div class="ls2wp-alert">
			<?php
			if($lsdb->error->errors['db_connect_fail'][0]) echo wp_kses_post($lsdb->error->errors['db_connect_fail'][0]);
			if($lsdb->error->errors['db_select_fail'][0]) echo wp_kses_post($lsdb->error->errors['db_select_fail'][0]);
			?>
		</div>
	<?php
	}
	if(WP_DEBUG){?>
		
		<div class="ls2wp-alert">
			<p><?php esc_html_e('WP_DEBUG is on (WP_DEBUG = true in wp-config.php). To prevent locking yourself out from wp-admin due to an error in the Limesurvey database credentials, changing the input fields is not possible.', 'ls2wp');?></p> 
			<p><?php esc_html_e('Swich off WP_DEBUG (WP_DEBUG = false in wp-config.php)to be able to change the credentials of the Limesurvey database.', 'ls2wp')?></p>
		</div>
	
	<?php } ?>
	<div>
		<p><?php esc_html_e('If Limesurvey and Wordpress are installed on the same server, you should fill in the database credentials you used installing Limesurvey.', 'ls2wp');?></p>
		<p><?php esc_html_e('If you want to connect to a database on an other server, the hostname should be the IP-address of the server where the Limesurvey database is installed', 'ls2wp');?></p>
		<p><?php esc_html_e('Probably you have to grant access to this external database by the current domain in the settings of the external database', 'ls2wp');?></p>
		
	</div>
	<?php
}

function ls2wp_survey_ids_input(){

	?>	
		<textarea type="textarea" id="ls_survey_ids" name="ls_survey_ids" rows=5 cols=50><?php echo esc_html(get_option('ls_survey_ids')); ?></textarea>
		
	<?php	
}
function validate_survey_ids($survey_id_string){
	
	$old_survey_id_string = get_option('ls_survey_ids');
	
	$use_rpc = get_option('use_rpc');
	
	$survey_ids = explode(',', str_replace(' ', '', $survey_id_string));	
	
	if($use_rpc){
		
		if($old_survey_id_string != $survey_id_string) delete_transient('ls_surveys');
	
	} elseif(!empty($survey_id_string)){

		foreach($survey_ids as $survey_id){
			
			$table_exists = ls2wp_response_table_exists($survey_id);
			
			if(!$table_exists){
				add_settings_error('ls_survey_ids', 'ls_survey_ids', 'No response table found for survey '.$survey_id.'!! Check if the survey id exists and is correct.');
			
				return $old_survey_id_string;
			}
		}
	}
	return $survey_id_string;	
}

function ls2wp_survey_group_ids_input(){

	?>	
		<textarea type="textarea" id="ls_survey_group_ids" name="ls_survey_group_ids" rows=5 cols=50><?php echo esc_html(get_option('ls_survey_group_ids')); ?></textarea>
		
	<?php	
}
function validate_survey_group_ids($survey_group_id_string){
	
	global $lsdb;
set_transient('test1', $survey_group_id_string, 900);	
	$old_survey_group_id_string = get_option('ls_survey_group_ids');

	$survey_group_ids = explode(',', str_replace(' ', '', $survey_group_id_string));	
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc){
		
		if($old_survey_group_id_string != $survey_group_id_string){ 
			delete_transient('ls_survey_groups');
			delete_transient('ls_surveys');
		}
		
	} elseif(!empty($survey_group_id_string)) {
		
		$db_survey_groups = ls2wp_db_get_survey_groups();
set_transient('test2', $old_survey_group_id_string, 900);		
		if(!$db_survey_groups) $db_survey_groups = array();
		
		$db_survey_group_ids = array_column($db_survey_groups, 'gsid');

		foreach($survey_group_ids as $survey_group_id){
			
			
			if(!empty($survey_group_id) && !in_array($survey_group_id, $db_survey_group_ids)){
				add_settings_error('ls_survey_group_ids', 'ls_survey_group_ids', 'Survey group '.$survey_group_id.' not found!!');
			
				return $old_survey_group_id_string;
			}
		}
	}
	return $survey_group_id_string;	
}

function ls2wp_ls_url_input(){
	
		$ls_url_errors = get_settings_errors('ls_url');
		
		if(!empty($ls_url_errors)){

			$ls_url_error_msg = $ls_url_errors[0]['message'];			
			echo '<div class="ls2wp-alert">'.esc_html($ls_url_error_msg).'</div>';
		
		}	
	
	?>	
		<input required type="url" id="ls_url" name="ls_url" required value="<?php echo esc_url(get_option('ls_url')); ?>">
	
	<?php
}
function validate_ls_url($ls_url){
	
	$old_ls_url = get_option('ls_url');	
	
	if(!empty($ls_url)){

		$headers = @get_headers($ls_url); 

		if($headers && !strpos( $headers[0], '404')) {
			return $ls_url;
		} else {	
			
				add_settings_error('ls_url', 'ls_url', 'The Limesurvey URL  "'.$ls_url.'" is not valid! Check if the URL is correct.');
			
				return $old_ls_url;
		}				
	}
	return $ls_url;	
}

function ls2wp_use_rpc_input(){
	?>	
		<input type="checkbox" id="use_rpc" name="use_rpc" <?php if(get_option('use_rpc') == 'on') echo esc_html('checked=true');?>	
	<?php	
}

function ls2wp_db_user_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';
	
	?>	
		<input <?php echo esc_attr($readonly);?> type="text" id="lsdb_user" name="lsdb_user" value="<?php echo esc_html(get_option('lsdb_user')); ?>">

	<?php	
}
function ls2wp_db_passw_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';			
	?>	
		<input <?php echo esc_html($readonly);?> type="password" id="lsdb_passw" name="lsdb_passw" value="<?php echo esc_html(get_option('lsdb_passw')); ?>">
	
	<?php	
}
function ls2wp_db_name_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';			
	?>	
		<input <?php echo esc_html($readonly);?> type="text" id="lsdb_name" name="lsdb_name" value="<?php echo esc_html(get_option('lsdb_name')); ?>">
	
	<?php	
}
function ls2wp_db_host_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';			
	?>	
		<input <?php echo esc_html($readonly);?> type="text" id="lsdb_host" name="lsdb_host" value="<?php echo esc_html(get_option('lsdb_host')); ?>">
	
	<?php	
}

function ls2wp_db_prefix_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';			
	?>	
		<input <?php echo esc_html($readonly);?> type="text" id="lsdb_prefix" name="lsdb_prefix" value="<?php echo esc_html(get_option('lsdb_prefix')); ?>">
	
	<?php	
}

function ls2wp_expl_rpc_credentials(){
	
	global $ls2wp_error;
	
	?>
	<div style="width:60%">
		<p><?php esc_html_e('To be able to use the JSON-RPC interface, the JSON-RPC interface should be switched on in Limesurvey and a Limesurvey user should be defined with the proper rights on the database. Take the following steps:', 'ls2wp');?></p>
		<ol>
			<li><?php esc_html_e('Login to your Limesurvey install and navigate to Global settings -> Interfaces and choose JSON-RPC as active interface.', 'ls2wp');?></li>
			<li><?php esc_html_e('Navigate to Manage survey administrators. Create a user with at least all richts on the participant database and on surveys.', 'ls2wp');?></li>	
			<li><?php esc_html_e('Add the credentials of this user to the fields below.', 'ls2wp');?></li>
		</ol>
	</div>		
	<?php

	$use_rpc = get_option('use_rpc');
	
	if($use_rpc){
	
		$error = $ls2wp_error->get_error_message();

		if($error){ ?>
			<div class="ls2wp-alert">
				<?php echo esc_html($error);?>
			</div>
	<?php }
	}
	
}

function ls2wp_rpc_user_input(){		
	?>	
		<input type="text" id="ls_rpc_user" name="ls_rpc_user" value="<?php echo esc_html(get_option('ls_rpc_user')); ?>">
	
	<?php	
}

function ls2wp_rpc_passw_input(){		
	?>	
		<input type="password" id="ls_rpc_passw" name="ls_rpc_passw" value="<?php echo esc_html(get_option('ls_rpc_passw')); ?>">
	
	<?php	
}

function ls2wp_import_rpc_data(){
	?>
	<h1><?php esc_html_e('Import participants and responses using the JSON-RPC interface', 'ls2wp');?></h1>		
	<?php
	
	echo wp_kses_post(ls2wp_form_select_survey(__('Send', 'ls2wp'), $args=array()));	

}

//ls2wp question values submenu page toevoegen	
add_action('admin_menu', 'ls2wp_answer_values');
	function ls2wp_answer_values() {
		$page = 'ls2wp_answer_values';
		
		add_submenu_page(
			'ls2wp', 
			__('Assessemnt values of LS-surveys', 'ls2wp'), 
			__('Assessemnt values', 'ls2wp'), 
			'manage_options', 
			'ls2wp_answer_values', 
			'ls2wp_answer_values_page',
			);
			

	}
	
function ls2wp_answer_values_page(){
	?>
	<h1><?php esc_html_e('LS2WP assessemnt values of LS-surveys', 'ls2wp');?></h1>		
	<?php
	
	echo ls2wp_form_select_survey(__('Send', 'ls2wp'));

	if(isset($_GET['survey'])) {
		
		if(is_numeric($_GET['survey']) && $_GET['survey'] > 100000 && $_GET['survey'] < 1000000){

			$qav = ls2wp_question_answer_values($_GET['survey']);
	
			echo ls2wp_survey_answer_values_form($qav);
		} else {
			
			esc_html_e('invalid survey id', 'ls2wp');
		}
	}
}
		
//form to select a survey.
function ls2wp_form_select_survey($submit_value) {

	$surveys = ls2wp_get_surveys();	
	
	if(count($surveys) > 1){
		usort($surveys, function ($a, $b){return strcmp($a->surveyls_title, $b->surveyls_title);});
	}		

	ob_start();

	?>
	<form class="import-form" method="get">
		
		<h2><?php esc_html_e('Select a survey.', 'ls2wp');?></h2>		
		
		<label>
		<?php esc_html_e('Show assessment values of: ', 'ls2wp'); ?>	

		<select id="survey" name="survey" required>
			<option value=""><?php esc_html_e('Select survey', 'ls2wp'); ?></option>
			<?php
			foreach($surveys as $survey) {
				//if( $survey->gsid != $gsid) continue;	
				
				$selected = (isset($_GET['survey']) && $_GET['survey'] == $survey->sid) ? 'selected' : '';
				
				echo '<option value="'.esc_attr($survey->sid).'" '.esc_attr($selected).'>'.esc_html($survey->surveyls_title).'('.esc_html($survey->sid).')</option>';		
			}	
			?>
		</select>
		</label>
		<?php
		
		if(isset($_GET['survey']) && is_numeric($_GET['survey']) && $_GET['survey'] > 100000 && $_GET['survey'] < 1000000){
			
			$answer_values = get_option($_GET['survey'].'_answer_values');		
			
			if(empty($answer_values) && isset($_GET['survey'])){
				?>
				<h3><?php esc_html_e('There are no saved assessment values yet.', 'ls2wp');?> </h3>
				<p><?php esc_html_e('If there is a previous survey using the same question codes, it is possible to import these assessment values.', 'ls2wp');?></p>
				<label>
				<?php esc_html_e('Import assessment values of:', 'ls2wp');?>
					<select id="impsurvey" name="impsurvey">
						<option value=""><?php esc_html_e('Select survey', 'ls2wp');?></option>
						<?php
						foreach($surveys as $survey) {
							//if( $survey->gsid != $gsid) continue;	
							
							$imp_selected = (isset($_GET['impsurvey']) && $_GET['impsurvey'] == $survey->sid) ? 'selected' : '';
							
							echo '<option value="'.esc_attr($survey->sid).'" '.esc_attr($imp_selected).'>'.esc_html($survey->surveyls_title).'('.esc_html($survey->sid).')</option>';		
						}	
						?>
					</select>
				</label>
				<p><b><?php esc_html_e('Do not forget to save the form!', 'ls2wp');?></b></p>
		<?php 
			}
		}
		
		if(is_admin()){
			
			$curr_scr = get_current_screen();
			$page_name = explode('page_', $curr_scr->id)[1];
			
		?>
			<input type="hidden" name="page" value="<?php echo esc_attr($page_name);?>">
		<?php 
		} ?>
		<p>
		<input type="submit" id="select-survey" value="<?php echo esc_html($submit_value);?>">
		</p>
	</form>
	<?php
	
	return ob_get_clean();
}

//Make an array with all groups, questions and answers from a survey
function ls2wp_question_answer_values($survey_id) {	
	
	$qav = array();
	$qav['gsid'] = '';
	
	$survey = ls2wp_get_survey($survey_id);

	$qav['sid'] = $survey_id;	
	if($survey->gsid) $qav['gsid'] = $survey->gsid;
	if($survey->surveyls_title) $qav['surveyls_title'] = $survey->surveyls_title;
	
	$questions = ls2wp_get_questions($survey_id, false);
	
	if(!$questions) return false;

	ksort($questions);		

	$answers = ls2wp_get_answers($survey_id);

	foreach ($questions as $question) {
		
		//remove questiontype Equation
		if($question->type == '*') continue;

		
		if(empty($question->parent_qid)) {
			
			$qav['groups'][$question->gid]['group_name'] = ls2wp_get_group_name($question->gid, $survey_id);	
			$qav['groups'][$question->gid]['questions'][$question->qid]['question'] = $question->question;
			$qav['groups'][$question->gid]['questions'][$question->qid]['type'] = $question->type;
			$qav['groups'][$question->gid]['questions'][$question->qid]['title'] = $question->title;
						
			if(!empty($answers[$question->qid])) {
				$qav['groups'][$question->gid]['questions'][$question->qid]['answers'] = $answers[$question->qid];
			} else {
				$qav['groups'][$question->gid]['questions'][$question->qid]['answers'] = array();	
			}	
			if(!isset($qav['groups'][$question->gid]['questions'][$question->qid]['sub_questions'])) $qav['groups'][$question->gid]['questions'][$question->qid]['sub_questions'] = array();
			if($question->other == 'Y'){
				$qav['groups'][$question->gid]['questions'][$question->qid]['sub_questions']['other'] = array(
				'qid'			=> $question->qid,
				'type'			=> 'T',
				'title'			=> $questions[$question->qid]->title.'[other]',
				'title_short'	=> 'other',
				'question'		=> 'Anders',
				);
			}				
			
		} else {
			$qav['groups'][$question->gid]['questions'][$question->parent_qid]['sub_questions'][$question->qid] = array(
			'qid'			=> $question->qid,
			//'type'			=> $question->type,
			'type'			=> $questions[$question->parent_qid]->type,
			'title'			=> $questions[$question->parent_qid]->title.'['.$question->title.']',
			'title_short'	=> $question->title,
			'question'		=> $question->question,
			);					
		}		
	}

	return $qav;
}

//Overview of all assessment values and
//form to add assessment values of yes/no questions and multiple choice questions
//See also https://manual.limesurvey.org/Question_object_types#Current_question_types and
//https://manual.limesurvey.org/Assessments#How_question_types_are_evaluated
function ls2wp_survey_answer_values_form($qav) {	

	if(isset($_GET['survey']) && is_numeric($_GET['survey'])) $survey_id = $_GET['survey'];

	if(empty($qav['groups'])) return '<p>Er kunnen geen gegevens worden gevonden voor survey met id '.$survey_id.' </p>';
	
	$gsid = 'gsid_'.$qav['gsid'];
	
	if(isset($survey_id)) $answer_values = get_option($survey_id.'_answer_values');

	ob_start();

	if(empty($answer_values) && isset($_GET['impsurvey']) && is_numeric($_GET['impsurvey'])){
		
		$impsurvey_id = $_GET['impsurvey'];
		
		if(isset($impsurvey_id)) $answer_values = get_option($impsurvey_id.'_answer_values');		
	}
	
	if(empty($answer_values)) $answer_values = array();

	?>
	<div class="answervalues">
		<h2><?php esc_html_e('Assessment values for: ', 'ls2wp');  echo esc_html($qav['surveyls_title']);?></h2>
		<ul>
			<li><?php esc_html_e('Assessment values from Limesurvey are shown below the question.', 'ls2wp');?></li>
			<li><?php esc_html_e('Subquestions get the assessment value of the parent question.', 'ls2wp');?></li>
			<li><?php esc_html_e('Assessment values of yes/no questions and multiple choice questions can be added to the form and saved.', 'ls2wp');?></li>
			
		</ul>

		<form class="answervalues-form" method="post" >
			<?php		
	
			foreach ($qav['groups'] as $gid => $group) {
				
				?>
				<h3><?php echo esc_html($group['group_name']);?></h3>
				<div class="group-form">
				
				<?php

					if(count($group['questions']) > 1){
						usort($group['questions'], function ($a, $b) {
							return strcmp($a['title'], $b['title']);
						});
					}
					
					
					foreach($group['questions'] as $question) {
					
						if(in_array($question['type'], ['G', 'S', 'T', 'U'])) continue;//open text questions
						
						//questions that can not be assessed in Limesurvey
						if(in_array($question['type'], array('Y', 'M')) ){
							?>
							<h4 class="question"><?php echo esc_html($question['title'].'. '.$question['question']).'('. __('Question type: ', 'ls2wp').esc_html($question['type']).')';?></h4>
							<fieldset class="question-form">						
								<?php if($question['type'] == 'Y') { ?>
									<label for="<?php echo esc_attr($question['title'].'-y');?>"><?php esc_html_e('Yes: ', 'ls2wp');?></label>
									<input 
									type="number" 
									id="<?php echo esc_attr($question['title'].'-y'); ?>" 
									name="<?php echo esc_attr($question['title']);?>[ja]" 
									value="<?php if(isset($answer_values[$question['title']]['ja'])) echo esc_attr($answer_values[$question['title']]['ja']);?>">
									<label for="<?php echo esc_attr($question['title'].'-n');?>"><?php esc_html_e('No: ', 'ls2wp');?></label>
									<input 
									type="number" 
									id="<?php echo esc_attr($question['title'].'-n');?>" 
									name="<?php echo esc_attr($question['title']);?>[nee]" 
									value="<?php if(isset($answer_values[$question['title']]['nee'])) echo esc_attr($answer_values[$question['title']]['nee']);?>">
							</fieldset>
							<?php }
							
							if(!empty($question['sub_questions'])) {
												
								if(count($question['sub_questions']) > 1){
									usort($question['sub_questions'], function ($a, $b) {
										return strcmp($a['title'], $b['title']);
									});	
								}
								
								?>
								<p><?php esc_html_e('The default value is the assessment value when none of the subquestions is ticked.', 'ls2wp');?></p>
								<p><?phpesc_html_e('Assessment values of subquestions are added to the default value. Subquestion assessment values can be negative, thus lowering the final score.', 'ls2wp');?></p>
								<label>
									<?php esc_html_e('Default value: ', 'ls2wp');?>
									<input type="number" id="<?php echo esc_attr($question['title'].'-start');?>" name="<?php echo esc_attr($question['title'].'[default]');?>" value="<?php echo esc_attr($answer_values[$question['title']]['default']);?>">
								
								</label>								
								<h4>Sub-vragen</h4>
								<ul>
								<?php
								foreach($question['sub_questions'] as $sub_question) {								
									?>
									<div>
										<li><?php echo esc_html($sub_question['title'].'. '.$sub_question['question']);?></li>
										<label for="<?php echo esc_attr($sub_question['title']).'-chk';?>"><?php esc_html_e('Value: ', 'ls2wp');?></label>
										<input 
										type="number" 
										id="<?php echo esc_attr($sub_question['title'].'-chk');?>" 
										name="<?php echo esc_attr($sub_question['title'].'[value]');?>" 
										value="<?php if(isset($answer_values[$question['title']][$sub_question['title_short']]['value'])) echo esc_attr($answer_values[$question['title']][$sub_question['title_short']]['value']);?>">
									</div>
									<?php	
								}
								?>
								</ul>
								<?php
							}					
						} else {						
							//question with assessment value from Limesuvey						
							?>
							<h4 class="question"><?php echo esc_html($question['title'].'. '.$question['question']).'('. __('Question type: ', 'ls2wp').esc_html($question['type']).')';?></h4>
							
							<?php if(in_array($question['type'], array('A', 'B')) ){							
								?>
								<p><?php esc_html_e('For 5 points array or a 10 points array the assessment value is equal to the answer.', 'ls2wp');?></p>
							
							<?php 
							
							} ?>
							
							<table class="answer-value">
								<tr>
									<th><?php esc_html_e('Answer:', 'ls2wp');?></th>
								
								<?php
								foreach ($question['answers'] as $antwoord){
									?>					
									<td><?php echo esc_html($antwoord['answer']);?> </td>
									<?php				
								}
								?>
								</tr>
								<tr>
									<th><?php esc_html_e('Value: ', 'ls2wp');?></th>
								<?php
								foreach ($question['answers'] as $antwoord){ 
									?>					
									<td><?php echo esc_html($antwoord['value']);?> </td>
									<?php				
								}
								?>
								</tr>
							</table>			
							<?php
							
							if(!empty($question['sub_questions'])) {
								?>
								<h4><?php esc_html_e('Subquestions', 'ls2wp');?></h4>
								<table>
							
								<?php
								foreach($question['sub_questions'] as $sub_question) {
									?>
									<tr>
										<td><?php echo esc_html($sub_question['title'].'. '.$sub_question['question']);?></td>	
									</tr>
									<?php
								}
								?>
								</table>
								<?php
							}					
						}					
					}
				?>
				</div>
				<?php
			}
		?>			
			<div class="submit-answer-values">
				<p><?php esc_html_e('Only site administrators are able to change assessment values!','ls2wp');?></p>
				<input type="hidden" id="survey-id" name="survey-id" value="<?php echo esc_attr($survey_id);?>">
				<?php wp_nonce_field('save answer values');?>
				<input type="submit" id="answer-values" name="answervalues" value="Opslaan" onclick="<?php if(!current_user_can('manage_options')) echo "event.preventDefault();alert('Only site administrators are able to change assessment values!');"?>">	
			</div>
		</form>
	</div>
	<?php	
	return ob_get_clean();	
}

//Save the assessment values in the options table
add_action('init', 'ls2wp_save_answer_values');
	function ls2wp_save_answer_values() {
		
		if(isset($_POST) && isset($_POST['answervalues'])) {
	
			if(isset($_POST['_wpnonce']) && !wp_verify_nonce($_POST['_wpnonce'], 'save answer values')) wp_die('niet geautoriseerd');

			if(is_numeric($_POST['survey-id'])) $survey_id = $_POST['survey-id'];
			
			$answer_values = array();
			foreach($_POST as $question_code => $values) {
				
				if(is_array($values)){
					foreach	($values as $key => $value){
						$answer_values[$question_code][$key] = $value;				
					}
				} else {					
					if($question_code == 'survey-id') {
						$answer_values[$question_code] = $values;
					} else {
						$answer_values[$question_code] = $values;
					}
				}
			}
			
			$answer_values = map_deep($answer_values, 'sanitize_text_field');
			
			if (current_user_can('manage_options')) {				
				update_option($survey_id.'_answer_values', $answer_values);
			}
		}		
	}

//Execute the survey data import request when using json-rpc
add_action( 'wp_ajax_import_survey_data', 'import_survey_data' );
	function import_survey_data(){
		
		check_ajax_referer( 'ls2wp' );
		
		$survey_id = $_POST['survey_id'];
		
		if(empty($survey_id)){
			ob_start();
			?>
				<div class="import-ajax-response"><b><?php esc_html_e('Select a survey!', 'ls2wp');?></b></div>
			<?php
			$ajax_response = ob_get_clean();
		} else {
			
			$resps = new Ls2wp_RPC_Responses();
			$parts = new Ls2wp_RPC_Participants();
			
			$n_resps = $resps->ls2wp_import_responses($survey_id);
			$n_parts = $parts->ls2wp_import_participants($survey_id);	

			ob_start()
			?>
			<div class="import-ajax-response">
				
				<p><?php esc_html_e('Imported responses: ', 'ls2wp'); echo esc_html($n_resps);?></p>
				<p><?php esc_html_e('Imported participants: ', 'ls2wp'); echo esc_html($n_parts);?></p>
			</div>
			<?php
			$ajax_response = ob_get_clean();
		}
				
		wp_send_json($ajax_response);
		
		wp_die();
	}
	
//Delete transients when using json-rpc
add_action( 'wp_ajax_clear_survey_transients', 'clear_survey_transients' );
	function clear_survey_transients(){
		
		check_ajax_referer( 'ls2wp' );
		
		$survey_id = $_POST['survey_id'];
		
		if(empty($survey_id)){
			
			ob_start()
			?>			
			<div class="delete-transients-ajax-response"><b><?php esc_html_e('Select a survey!', 'ls2wp');?></b></div>
			<?php
			$ajax_response = ob_get_clean();
		} else {
			
			delete_transient('ls_surveys');
			delete_transient('ls_survey_groups');
			delete_transient('survey_props_'.$survey_id);
			delete_transient('fieldmap_'.$survey_id);

			ob_start()
			?>
			<div class="delete-transients-ajax-response">
				
				<p><?php esc_html_e('The transients are deleted.', 'ls2wp');?></p>
			</div>
			<?php
			$ajax_response = ob_get_clean();
		}
		
		wp_send_json($ajax_response);
		
	}