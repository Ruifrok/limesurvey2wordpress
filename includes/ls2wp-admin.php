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
		<h1><?php _e('LS2WP settings', 'ls2wp');?></h1>	
		<h2><?php _e('Introduction', 'ls2wp');?></h2>
		<div style="width:60%">
			<p><?php _e('It is important to understand that the concept of users in Wordpress and Limesurvey is different. In Limesurvey those who take a survey are called participants. Participant data are not stored in the Limesurvey user database. In Limesurvey users are only those who have rights to add or change surveys depending on their user rights.', 'ls2wp');?></p>
			<p><?php _e('This plugin can only read data from the Limesurvey database or add Limesurvey partcipants to surveys. It has no functionality to add or change Limesurvey users or add or change surveys.', 'ls2wp');?></p>
			
			<p><?php _e('The prefered way to access Limesurvey data is to link to the Limesurvey database. It is a lot faster and more efficient. If you do not have access to the Limesurvey database, the JSON/RPC interface can be used. This is a relatively slow connection. To improve speed when using JSON/RPC, data are stored in transients. This might limit the amount of data that can be accessed.', 'ls2wp');?></p>
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
			
			if(empty($surveys)) $surveys = array();
			
			if(!empty($surveys) && count($surveys) > 1){
				usort($surveys, function ($a, $b){return strcmp($a->surveyls_title, $b->surveyls_title);});
			}

			?>
			<form class="import-form" method="post">
				
				<h2><?php _e('Import survey responses and participants.', 'ls2wp');?></h2>

				<p><?php _e('Retrieving data with JSON-RPC is very slow. To keep loading speeds acceptable data are stored in the database.', 'ls2wp');?></p>
				<p><?php _e('Survey data are stored in transients with a maximum duration of 24 hours. This means it can take 24 hours before changes in the survey in Limesurvey are avilable in Wordpress', 'ls2wp')?></p>
				<p><?php _e('Responses and participant data are stored in a database table. These data should be imported manually with the import form below. When new responses or participamts are added in Limesurvey you have to perform an new import to make them available. Incomplete responses are updated at import.', 'ls2wp');?></p>
				<p><?php _e('The link between a wordpress user and a Limesurvey participant is his email address. A users email address is updated in Limesurvey when the wp-user email address is changed', 'ls2wp');?></p>
				<label>
				<?php _e('Import participants and responses of:', 'ls2wp') ?>
				</label>
				<select id="survey" name="survey" required>
					<option value=""><?php _e('Select survey', 'ls2wp');?></option>
					<?php
					foreach($surveys as $survey) {
						
						$selected = (isset($_POST['survey']) && $_POST['survey'] == $survey->sid) ? 'selected' : '';
						
						echo '<option value="'.esc_attr($survey->sid).'" '.esc_attr($selected).'>'.esc_html($survey->surveyls_title).'('.esc_html($survey->sid).')</option>';		
					}	
					?>
				</select>	
				<?php wp_nonce_field('import survey data');?>
				<input type="submit" id="select-import-survey" value="<?php _e('Import survey data', 'ls2wp');?>">				
			</form>
			
			<?php
			if(isset($_POST) && isset($_POST['imported'])){
				
				if($_POST['imported']['participants'] == 'No survey participants found.') $_POST['imported']['participants'] = 0
				
			?>
			<p>Geimporteerde responses: <?php echo $_POST['imported']['responses'] ?></p>
			<p>Geimporteerde participants: <?php echo $_POST['imported']['participants'] ?></p>
			<?php } ?>
		</div>
		<?php
	}

//Voeg setting secties toe
add_action( 'admin_init', 'ls2wp_admin_init' );
	function ls2wp_admin_init(){
		$page = 'ls2wp';		
	
		add_settings_section(
			'survey_ids',
			__('Basic settings', 'ls2wp'),
			'ls2wp_expl_surveys',
			$page,
			array('before_section' => '<div class="base-settings">', 'after_section' => '</div>'),
			);
			
			register_setting($page, 'ls_survey_ids');
			add_settings_field(
				'ls_survey_ids',
				__('Comma seperated list of surevey ids', 'ls2wp'),
				'ls2wp_survey_ids_input',
				$page,
				'survey_ids'
			);			
			register_setting($page, 'ls_url');
			add_settings_field(
				'ls_url',
				__('URL of the Limesurvey install', 'ls2wp'),
				'ls2wp_ls_url_input',
				$page,
				'survey_ids'
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
				'lsdb-credentials'
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
				'lsdb-credentials'
			);
			register_setting($page, 'lsdb_host');
			add_settings_field(
				'lsdb_host',
				__('Hostname of the Limesurvey database', 'ls2wp'),
				'ls2wp_db_host_input',
				$page,
				'lsdb-credentials'
			);
			register_setting($page, 'lsdb_prefix');
			add_settings_field(
				'lsdb_prefix',
				__('Prefix of the tables in the Limesurvey database', 'ls2wp'),
				'ls2wp_db_prefix_input',
				$page,
				'lsdb-credentials'
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
				'ls-rpc-credentials'
			);
			
			register_setting($page, 'ls_rpc_passw');
			add_settings_field(
				'ls_rpc_passw',
				__('Password of the Limesurvey account', 'ls2wp'),
				'ls2wp_rpc_passw_input',
				$page,
				'ls-rpc-credentials'
			);		
	
				
	}


function ls2wp_expl_surveys(){
	
	?>	
	<ul><?php _e('Give the following info:', 'ls2wp')?> 
		<li><?php _e('Which surveys are made available on your wordpress website. A comma separated list of survey ids.', 'ls2wp');?></li>
		<li><?php _e('If you want to use the database of the Limesurvey installation', 'ls2wp');?></li>
		<li><?php _e('If you want to use the json/rpc interface of Limesurvey', 'ls2wp');?></li>
	</ul>
	<p></p>
	<?php
}

function ls2wp_expl_ls_credentials(){
	
	global $lsdb;
	
	if(isset($lsdb) && $lsdb->error){
	
	?>
		<div style="border:3px solid red; padding:10px;width:60%;">
			<?php
			if($lsdb->error->errors['db_connect_fail'][0]) echo $lsdb->error->errors['db_connect_fail'][0];
			if($lsdb->error->errors['db_select_fail'][0]) echo $lsdb->error->errors['db_select_fail'][0];
			?>
		</div>
	<?php
	}
	if(WP_DEBUG){?>
		
		<div style="border:3px solid red; padding:10px;width:60%;">
			<p><?php _e('WP_DEBUG is on (WP_DEBUG = true in wp-config.php). To prevent locking yourself out from wp-admin due to an error in the Limesurvey database credentials, changing the input fields is not possible.', 'ls2wp');?>WP_DEBUG staat aan ('WP_DEBUG = true' in wp-config.php). Om te voorkomen dat u niet meer bij wp-admin kunt bij een fout in de LSDB credentials, kunt u de inputvelden niet veranderen.</p> 
			<p><?php _e('Swich off WP_DEBUG (WP_DEBUG = false in wp-config.php)to be able to change the credentials of the Limesurvey database.', 'ls2wp')?>Schakel WP_DEBUG uit ('WP_DEBUG = false' in wp-config.php) om de credentials van de Limesurvey database te kunnen wijzigen!! </p>
		</div>
	
	<?php } ?>
	<div>
		<p><?php _e('If Limesurvey and Wordpress are installed on the same server, you should fill in the database credentials you used installing Limesurvey.', 'ls2wp');?></p>
		<p><?php _e('If you want to connect to a database on an other server, the hostname should be the IP-address of the server where the Limesurvey database is installed', 'ls2wp');?></p>
		
	</div>
	<?php
}

function ls2wp_survey_ids_input(){
	?>	
		<textarea type="textarea" id="ls_survey_ids" name="ls_survey_ids" rows=5 cols=50><?php echo get_option('ls_survey_ids'); ?></textarea>
	
	<?php	
}

function ls2wp_use_rpc_input(){
	?>	
		<input type="checkbox" id="use_rpc" name="use_rpc" <?php if(get_option('use_rpc') == 'on') echo 'checked=true'; ?>">

	
	<?php	
}

function ls2wp_db_user_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';	
	?>	
		<input <?php echo $readonly;?> type="text" id="lsdb_user" name="lsdb_user" value="<?php echo get_option('lsdb_user'); ?>">
	
	<?php	
}
function ls2wp_db_passw_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';			
	?>	
		<input <?php echo $readonly;?> type="password" id="lsdb_passw" name="lsdb_passw" value="<?php echo get_option('lsdb_passw'); ?>">
	
	<?php	
}
function ls2wp_db_name_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';			
	?>	
		<input <?php echo $readonly;?> type="text" id="lsdb_name" name="lsdb_name" value="<?php echo get_option('lsdb_name'); ?>">
	
	<?php	
}
function ls2wp_db_host_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';			
	?>	
		<input <?php echo $readonly;?> type="text" id="lsdb_host" name="lsdb_host" value="<?php echo get_option('lsdb_host'); ?>">
	
	<?php	
}

function ls2wp_db_prefix_input(){
	
	$readonly = '';
	if(WP_DEBUG) $readonly = 'readonly';			
	?>	
		<input <?php echo $readonly;?> type="text" id="lsdb_prefix" name="lsdb_prefix" value="<?php echo get_option('lsdb_prefix'); ?>">
	
	<?php	
}

function ls2wp_expl_rpc_credentials(){
	?>
	<div>
		<p><?php _e('To be able to use the JSON-RPC interface, the JSON-RPC interface should be switched on in Limesurvey and a Limesurvey user should be defined with the proper rights on the database. Take the following steps:');?></p>
		<ol>
			<li><?php _e('Login to your Limesurvey install and navigat to Global settings -> Interfaces and choose JSON-RPC as active interface.', 'ls2wp');?></li>
			<li><?php _e('Navigate to Manage survey administrators. Create a user with all richts on the participant database and on surveys.', 'ls2wp');?></li>	
			<li><?php _e('Add the credentials of this user to the fields below.', 'ls2wp');?></li>
		</ol>
	</div>		
	<?php
}
function ls2wp_rpc_user_input(){		
	?>	
		<input type="text" id="ls_rpc_user" name="ls_rpc_user" value="<?php echo get_option('ls_rpc_user'); ?>">
	
	<?php	
}

function ls2wp_rpc_passw_input(){		
	?>	
		<input type="password" id="ls_rpc_passw" name="ls_rpc_passw" value="<?php echo get_option('ls_rpc_passw'); ?>">
	
	<?php	
}

function ls2wp_ls_url_input(){		
	?>	
		<input type="url" id="ls_url" name="ls_url" required value="<?php echo get_option('ls_url'); ?>">
	
	<?php	
}

function ls2wp_import_rpc_data(){
	?>
	<h1><?php _e('Import participants and responses using the JSON-RPC interface', 'ls2wp');?></h1>		
	<?php
	
	echo ls2wp_form_select_survey(__('Send'), $args=array());	

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
	<h1><?php _e('LS2WP assessemnt values of LS-surveys', 'ls2wp');?></h1>		
	<?php
	
	echo ls2wp_form_select_survey(__('Send'));
	
	if(isset($_GET['survey'])) {
		
		if(is_numeric($_GET['survey']) && $_GET['survey'] > 100000 && $_GET['survey'] < 1000000){
			
			$qav = ls2wp_question_answer_values($_GET['survey']);
	
			echo ls2wp_survey_answer_values_form($qav);
		} else {
			echo 'ongeldige survey id';
		}
	}
}
		
//formulier voor kiezen van survey.
function ls2wp_form_select_survey($submit_value) {

	$surveys = ls2wp_get_surveys();	
	
	if(count($surveys) > 1){
		usort($surveys, function ($a, $b){return strcmp($a->surveyls_title, $b->surveyls_title);});
	}		
	
	ob_start();

	?>
	<form class="import-form" method="get">
		
		<h2><?php _e('Select a survey.');?></h2>		
		
		<label>
		Toon antwoordwaardes van:	

		<select id="survey" name="survey" required>
			<option value="">Kies survey</option>
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
		
		if(isset($_GET['survey'])){
			$answer_values = get_option($_GET['survey'].'_answer_values');		
			
			if(empty($answer_values) && isset($_GET['survey'])){
				?>
				<h3>Er zijn nog geen antwoordvaardes opgeslagen.</h3>
				<p>Indien er een voorgaande survey met dezelfde vraagcodes is, kunt U die antwoordwaardes importeren</p>
				<label>
				Importeer antwoordwaardes van:
					<select id="impsurvey" name="impsurvey">
						<option value="">Kies survey</option>
						<?php
						foreach($surveys as $survey) {
							//if( $survey->gsid != $gsid) continue;	
							
							$imp_selected = (isset($_GET['impsurvey']) && $_GET['impsurvey'] == $survey->sid) ? 'selected' : '';
							
							echo '<option value="'.esc_attr($survey->sid).'" '.esc_attr($imp_selected).'>'.esc_html($survey->surveyls_title).'('.esc_html($survey->sid).')</option>';		
						}	
						?>
					</select>
				</label>
				<p><b>Vergeet niet het formulier op te slaan</b></p>
		<?php 
			}
		}
		
		if(is_admin()){
			
			$curr_scr = get_current_screen();
			$page_name = explode('page_', $curr_scr->id)[1];
			
		?>
			<input type="hidden" name="page" value="<?php echo $page_name;?>">
		<?php 
		} ?>
		<p>
		<input type="submit" id="select-survey" value="<?php echo $submit_value;?>">
		</p>
	</form>
	<?php
	
	return ob_get_clean();
}

//Maak een array met alle groepen, vragen en antwoorden uit een survey
function ls2wp_question_answer_values($survey_id) {
	if(empty($survey_id)) return array();
	
	$qav = array();
	$qav['gsid'] = '';
	
	$survey = ls2wp_get_survey($survey_id);

	$qav['sid'] = $survey_id;	
	if($survey->gsid) $qav['gsid'] = $survey->gsid;
	if($survey->surveyls_title) $qav['surveyls_title'] = $survey->surveyls_title;
	
	$questions = ls2wp_get_questions($survey_id, false);
	
	ksort($questions);		

	$answers = ls2wp_get_answers($survey_id);

	foreach ($questions as $question) {
		
		//De equation-questions met berekende score niet meenemen
		if(strstr($question->title, 'score') || strstr($question->title, 'ver')) continue;
		
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
			'type'			=> $question->type,
			'title'			=> $questions[$question->parent_qid]->title.'['.$question->title.']',
			'title_short'	=> $question->title,
			'question'		=> $question->question,
			);					
		}		
	}

	return $qav;
}
//Overzicht van alle vraagwaardes en
//formulier om waardes van ja/nee vragen en meerkeuzevragen en kleurgrenzen in te vullen
//Voor codering van vraagtype zie https://manual.limesurvey.org/Question_object_types#Current_question_types
//Voor lijst met vraagtypes die een assessment value accepteren zie https://manual.limesurvey.org/Assessments#How_question_types_are_evaluated
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
		<h2>Antwoordwaardes voor <?php echo $qav['surveyls_title']?></h2>
		<ul>
			<li>Als de waardes in LimeSurvey zijn opgegeven als assessment value worden deze weergegeven onder de vraag. Sub-vragen krijgen meestal de waardes van de hoofdvraag.</li>
			<li>Bij ja/nee-vragen en meer keuze vragen, kan de waarde van subvragen worden ingevuld en opgeslagen.</li>
			
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
					
						if(in_array($question['type'], ['G', 'S', 'T', 'U'])) continue;//open tekt vragen
						
						//Vragen waarvan waarde niet in Lime Survey kan worden opgegeven 
						if(in_array($question['type'], array('Y', 'M')) ){	
							?>
							<h4 class="question"><?php echo esc_html($question['title'].'. '.$question['question']);?> (vraagtype: <?php echo esc_html($question['type']);?>)</h4>
							<fieldset class="question-form">						
								<?php if($question['type'] == 'Y') { ?>
									<label for="<?php echo esc_attr($question['title'].'-y');?>">Ja: </label>
									<input 
									type="number" 
									id="<?php echo esc_attr($question['title'].'-y'); ?>" 
									name="<?php echo esc_attr($question['title']);?>[ja]" 
									value="<?php if(isset($answer_values[$question['title']]['ja'])) echo esc_attr($answer_values[$question['title']]['ja']);?>">
									<label for="<?php echo esc_attr($question['title'].'-n');?>">Nee: </label>
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
								
								<h4>Sub-vragen</h4>
								<ul>
								<?php
								foreach($question['sub_questions'] as $sub_question) {								
									?>
									<div>
										<li><?php echo esc_html($sub_question['title'].'. '.$sub_question['question']);?></li>
										<label for="<?php echo esc_attr($sub_question['title']).'-chk';?>">Waarde: </label>
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
							//vragen met waarde uit Lime Survey							
							?>
							<h4 class="question"><?php echo esc_html($question['title'].'. '.$question['question']);?> (vraagtype: <?php echo esc_html($question['type']);?>)</h4>
							
							<?php if(in_array($question['type'], array('A', 'B')) ){							
								?>
								<p>Bij een 5-punts array of 10-punts array is de vraagwaarde gelijk aan het antwoord</p>
							
							<?php 
							
							} ?>
							
							<table class="answer-value">
								<tr>
									<th>Antwoord:</th>
								
								<?php
								foreach ($question['answers'] as $antwoord){
									?>					
									<td><?php echo esc_html($antwoord['answer']);?> </td>
									<?php				
								}
								?>
								</tr>
								<tr>
									<th>Waarde:</th>
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
								<h4>Sub-vragen</h4>
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
				<p>Alleen beheerders kunnen nieuwe waardes instellen!</p>
				<input type="hidden" id="survey-id" name="survey-id" value="<?php echo esc_attr($survey_id);?>">
				<?php wp_nonce_field('save answer values');?>
				<input type="submit" id="answer-values" name="answervalues" value="Opslaan" onclick="<?php if(!current_user_can('manage_options')) echo "event.preventDefault();alert('Alleen beheerders kunnen antwoordwaardes wijzigen');"?>">	
			</div>
		</form>
	</div>
	<?php	
	return ob_get_clean();	
}

//De ingevulde vraagwaardes opslaan in option
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

add_action( 'wp_ajax_import_survey_data', 'import_survey_data' );
	function import_survey_data(){
		
		check_ajax_referer( 'ls2wp' );
		
		$survey_id = $_POST['survey_id'];
		
		if(empty($survey_id)){
			$ajax_response = '<div class="ajax-response"><b>Kies eerst een survey</b></div>';
		} else {
			
			$resps = new Ls2wp_RPC_Responses();
			$parts = new Ls2wp_RPC_Participants();
			
			$n_resps = $resps->ls2wp_import_responses($survey_id);
			$n_parts = $parts->ls2wp_import_participants($survey_id);	

			ob_start()
			?>
			<div class="ajax-response">
				
				<p>Geimporteerde responses: <?php echo $n_resps;?></p>
				<p>Geimporteerde participants: <?php echo $n_parts;?></p>
			</div>
			<?php
			$ajax_response = ob_get_clean();
		}
		
		wp_send_json($ajax_response);
		
	}