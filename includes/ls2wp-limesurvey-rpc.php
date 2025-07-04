<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


add_action('init', 'ls2wp_set_ls_cred');
	function ls2wp_set_ls_cred(){		

		$use_rpc = get_option('use_rpc');
		if(!$use_rpc) return;	
		
		global $ls2wp_error;
		$ls2wp_error = new WP_Error;
		
		if(empty(LS2WP_SITEURL)) return;		
		
		global $rpc_client;
		global $s_key;
		
		$ls_rpcurl = LS2WP_SITEURL.'index.php/admin/remotecontrol';
		$ls_user_name = get_option('ls_rpc_user');
		$ls_passw = get_option('ls_rpc_passw');
		
		define( 'LS2WP_RPCURL', LS2WP_SITEURL.'index.php/admin/remotecontrol'); 
		define( 'LS2WP_USER', $ls_user_name);
		define( 'LS2WP_PASSWORD', $ls_passw);
		
		$headers = @get_headers($ls_rpcurl); 

		if($headers && strpos( $headers[0], '200')) { 
		
			global $rpc_client;
			global $s_key;
			
			$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
			$s_key= $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

			if(is_array($s_key) && isset($s_key['status'])){
				
				$ls2wp_error->add('rpc_connection_error', $s_key['status']);
				
			}	
			
		} else {
			
			$ls2wp_error->add('rpc_connection_error', 'The remote control url "'.$ls_rpcurl.'" is not a valid remote controll url' );
		}
	}
//Release sesseion key at end of page load	
add_action('shutdown', 'ls2wp_release_s_key');
	function ls2wp_release_s_key(){
		
		$use_rpc = get_option('use_rpc');
		
		if($use_rpc) {		
		
			global $rpc_client;
			global $s_key;

			if(!empty($rpc_client)) $rpc_client->release_session_key( $s_key);
		}
		
	}

//fieldmap van de vragen van een survey met toevoeging van antwoordopties met assessment values
function ls2wp_get_ls_q_fieldmap($survey_id){

	$ls_fieldmap = get_transient('fieldmap_'.$survey_id);
		
	if(empty($ls_fieldmap)){
		
		global $rpc_client;
		global $s_key;		
		
		$ls_fieldmap = array();

		if(is_array($s_key)){		
			return $s_key['status'];
		}
	
		$fields = $rpc_client->get_fieldmap($s_key, $survey_id);	

		if(isset($fields['status'])) {
			
			return false;
			
		}

		foreach($fields as $k => $field){
		
			$sgq = explode('X', $field['fieldname']);
				
			if(is_numeric($sgq[0])){
				
				if(empty($field['aid'])){
					$key = $field['title'];
				} else {
				$key = $field['title'].'['.$field['aid'].']';
				}
				
				$q_props = $rpc_client->get_question_properties($s_key, $field['qid']);

				$field['answeroptions'] = $q_props['answeroptions'];
		
				$ls_fieldmap[$key] = $field;
						
			}			
		}	

		set_transient('fieldmap_'.$survey_id, $ls_fieldmap, MONTH_IN_SECONDS);		
		
	}		
			
	return $ls_fieldmap;
	
}

//Make an array with all questions
function ls2wp_rpc_get_questions($survey_id){	

	$fieldmap = ls2wp_get_ls_q_fieldmap($survey_id);
	
	foreach($fieldmap as $quest){		

		if(isset($quest['sqid'])){

			if(!isset($quest['other'])) $quest['other'] = 'N';
			
			$questions[$quest['sqid']]['qid'] = $quest['sqid'];
			$questions[$quest['sqid']]['parent_qid'] = $quest['qid'];
			$questions[$quest['sqid']]['sid'] = $quest['sid'];
			$questions[$quest['sqid']]['gid'] = $quest['gid'];
			$questions[$quest['sqid']]['type'] = $quest['type'];
			if(!isset($quest['relevance'])) $questions[$quest['sqid']]['relevance'] = $quest['SQrelevance'];
			else $questions[$quest['sqid']]['relevance'] = $quest['relevance'];
			$questions[$quest['sqid']]['title'] = $quest['aid'];
			$questions[$quest['sqid']]['other'] = $quest['other'];
			$questions[$quest['sqid']]['question'] = $quest['subquestion'];			
			
			if(empty($questions[$quest['qid']])){
				$questions[$quest['qid']]['qid'] = $quest['qid'];
				$questions[$quest['qid']]['parent_qid'] = 0;
				$questions[$quest['qid']]['sid'] = $quest['sid'];
				$questions[$quest['qid']]['gid'] = $quest['gid'];
				$questions[$quest['qid']]['type'] = $quest['type'];
				if(!isset($quest['relevance'])) $questions[$quest['qid']]['relevance'] = $quest['SQrelevance'];
				else $questions[$quest['qid']]['relevance'] = $quest['relevance'];				
				$questions[$quest['qid']]['title'] = $quest['title'];
				$questions[$quest['qid']]['other'] = $quest['other'];
				$questions[$quest['qid']]['question'] = $quest['question'];
			}
			
			
		} else {
			
			if(!isset($quest['other'])) $quest['other'] = 'N';
			
			$questions[$quest['qid']]['qid'] = $quest['qid'];
			$questions[$quest['qid']]['parent_qid'] = 0;
			$questions[$quest['qid']]['sid'] = $quest['sid'];
			$questions[$quest['qid']]['gid'] = $quest['gid'];
			$questions[$quest['qid']]['type'] = $quest['type'];
			if(!isset($quest['relevance']) && isset($quest['SQrelevance'])) $questions[$quest['qid']]['relevance'] = $quest['SQrelevance'];
			elseif(isset($quest['relevance'])) $questions[$quest['qid']]['relevance'] = $quest['relevance'];
			else $questions[$quest['qid']]['relevance'] = null;
			$questions[$quest['qid']]['title'] = $quest['title'];			
			$questions[$quest['qid']]['title'] = $quest['title'];
			$questions[$quest['qid']]['other'] = $quest['other'];
			$questions[$quest['qid']]['question'] = $quest['question'];			
			
		}		
	}
	
	foreach($questions as $key => $question){
		
		$questions_nw[$key] = (object)$question;
		
	}
	
	return $questions_nw;
}

//Array with answer code en answer values; key is question id
function ls2wp_rpc_get_answers($survey_id){
	
	$fieldmap = ls2wp_get_ls_q_fieldmap($survey_id);

	foreach($fieldmap as $quest){
		
		if(empty($quest['answeroptions']) || !is_array($quest['answeroptions'])) continue;
		
		foreach($quest['answeroptions'] as $key => $answer){
		
			$answers[$quest['qid']][$key]['answer'] = $answer['answer'];
			$answers[$quest['qid']][$key]['value'] = $answer['assessment_value'];
		}
	}		

	return $answers;
}

//Search group name of group id
function  ls2wp_rpc_get_group_name($group_id, $survey_id){
	
	$fieldmap = ls2wp_get_ls_q_fieldmap($survey_id);
	
	foreach($fieldmap as $quest){
		
		if($quest['gid'] == $group_id) return $quest['group_name'];

	}	
}

//Array with limited dataset of registered surveys
function ls2wp_rpc_get_surveys(){

	$surveys_nw = get_transient('ls_surveys');
	
	if(empty($surveys_nw)) $surveys_nw = array();
	
	if(count($surveys_nw) == 0){

		global $rpc_client;
		global $s_key;	

		$id_string = get_option('ls_survey_ids');	
		
		if(empty($id_string)) $survey_ids = array();
		 else $survey_ids = explode(',', str_replace(' ', '', $id_string));
		
		$gid_string = get_option('ls_survey_group_ids');
		
		if(empty($gid_string)) $survey_group_ids = array();
		else $survey_group_ids = explode(',', str_replace(' ', '', $gid_string));
		
		if(is_array($s_key)){		
			return $s_key['status'];
		}	
	
		if(!empty($rpc_client))$surveys = $rpc_client->list_surveys($s_key);
		else $surveys = array();
		
		$surveys_nw = array();

		foreach($surveys as $survey){
		
			$props = ls2wp_rpc_get_survey_props($survey['sid']);
		
			if(!in_array($survey['sid'], $survey_ids) && !in_array($props['gsid'], $survey_group_ids)) continue;		
		
			$survey['gsid'] = $props['gsid'];
			$survey['language'] = $props['language'];
			$survey['anonymized'] = $props['anonymized'];
			$survey['datecreated'] = $props['datecreated'];
			$survey['tokenlength'] = $props['tokenlength'];
			$survey['assessments'] = $props['assessments'];
				
			$surveys_nw[] = (object)$survey;
		}
		
		set_transient('ls_surveys', $surveys_nw, MONTH_IN_SECONDS);
	
	}
		
	return $surveys_nw;		
}


//Limited dataset of registered survey
function ls2wp_rpc_get_survey($survey_id){
	
	$surveys = ls2wp_rpc_get_surveys();
	
	if(is_string($surveys)) $surveys = array();

	foreach($surveys as $survey){
		if($survey->sid == $survey_id) {			
			return $survey;
		}
	}
	
}

//Array with survey groups
function ls2wp_rpc_get_survey_groups(){
	
	$survey_groups = get_transient('ls_survey_groups');
	
	if(empty($survey_groups)) $survey_groups = array();
	
	if(count($survey_groups) == 0){
		
		global $rpc_client;
		global $s_key;			
		
		if(is_array($s_key)){		
			return $s_key['status'];
		}	
		
		$survey_groups = $rpc_client->list_survey_groups($s_key);
		
		set_transient('ls_surveys_groups', $survey_groups, MONTH_IN_SECONDS);
		
	}
		
	return $survey_groups;		
}


//Alle properties of a survey
function ls2wp_rpc_get_survey_props($survey_id){

	$survey_props = get_transient('survey_props_'.$survey_id);
	
	if(empty($survey_props)){
		
		global $rpc_client;
		global $s_key;

		if(is_array($s_key)){		
			return $s_key['status'];
		}

		$survey_props = $rpc_client->get_survey_properties($s_key, $survey_id);
		
		set_transient('survey_props_'.$survey_id, $survey_props, MONTH_IN_SECONDS);
	}
	
	return $survey_props;	
}


class Ls2wp_RPC_Responses {
	
	public $survey_id;
	public $table_name;
	
	public function __construct($survey_id = '') {
		
		global $wpdb;
		
		$this->table_name = $wpdb->prefix . 'ls2wp_rpc_responses';
				
		$this->survey_id = $survey_id;
	}
	
	public function ls2wp_create_resp_table() {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		//$table_name = $wpdb->prefix . 'mhp_consulten';
		$table_name = $this->table_name;


			$sql = "CREATE TABLE $table_name (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			survey_id varchar(8),
			ls_resp_id varchar(8),
			submitdate varchar(50),
			lastpage int(4) UNSIGNED NOT NULL,
			startlanguage varchar(8),
			seed varchar(20),
			token varchar(25),
			questions text NOT NULL,
			PRIMARY KEY  (id),
			KEY token (token),
			KEY survey_id (survey_id)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			$success = empty( $wpdb->last_error );


		return $success;

	}

	//import reponses
	public function ls2wp_import_responses($survey_id){	

		global $rpc_client;
		global $s_key;	

		$ls_resp_ids = $this->get_imported_resp_ids($survey_id);
		$incomplete_ls_resp_ids = $this->get_imported_incomplete_ls_resp_ids($survey_id);

		if(empty($ls_resp_ids)) $from_ls_resp_id = 1;		
		else $from_ls_resp_id = max($ls_resp_ids) + 1;

		if(is_array($s_key)){		
			return $s_key['status'];
		}
		
		$b64_resps = $rpc_client->export_responses($s_key, $survey_id, 'json', null, 'all', 'code', 'short', $from_ls_resp_id );
		
		if(!is_string($b64_resps)){
			$responses = array();
		} else {
		
			$json_resps = base64_decode($b64_resps);
			$responses = json_decode($json_resps, true)['responses'];	
		}
		
		if(!empty($incomplete_ls_resp_ids)){
			
			foreach($incomplete_ls_resp_ids as $incomplete_ls_resp_id){
					
				$b64_incomp_resp = $rpc_client->export_responses($s_key, $survey_id, 'json', null, 'all', 'code', 'short', $incomplete_ls_resp_id, $incomplete_ls_resp_id );

				$json_incomplete_response = base64_decode($b64_incomp_resp);
				$incomplete_response = json_decode($json_incomplete_response, true)['responses'];

				$responses = array_merge($responses, $incomplete_response);
			}
		}

		$n = 0;
		
		foreach($responses as $response){
			
			foreach($response as $key => $val){	
				
				$resp_q['survey_id'] = $survey_id;
				
				if($key == 'id') $resp_q['ls_resp_id'] = $val;
				elseif($key == 'submitdate') $resp_q[$key] = $val;
				elseif($key == 'lastpage') $resp_q[$key] = $val;
				elseif($key == 'startlanguage') $resp_q[$key] = $val;
				elseif($key == 'seed') $resp_q[$key] = $val;
				elseif($key == 'token') $resp_q[$key] = $val;
				else $questions[$key] = $val;
				
			}			
			
			$resp_q['questions'] = serialize($questions);
			
			if(!empty($incomplete_ls_resp_ids) && in_array($resp_q['ls_resp_id'], $incomplete_ls_resp_ids)){
				
				$this-> update_response_by_ls_resp_id($resp_q, $resp_q['ls_resp_id']);			
				
			} else{
	
				$this->insert_response( $resp_q);
				
				$n++;
			}
		}
		return $n;
	}

	//Add response to WP response table
	public function insert_response($response){
		
		global $wpdb;
		
		$table_name = $this->table_name;
		
		$wpdb->insert($table_name, $response);		
	}

	//Update response in WP response table
	public function update_response_by_ls_resp_id($response, $ls_resp_id){
		
		global $wpdb;
		
		$table_name = $this->table_name;
		
		$wpdb->update($table_name, $response, array('survey_id' => $response['survey_id'], 'ls_resp_id' => $ls_resp_id));
	}
	
	//Get all limesurvey response ids of a survey in the WP response table
	public function get_imported_resp_ids($survey_id){
		
		global $wpdb;
		
		$table_name = $this->table_name;		
		
		$sql = $wpdb->prepare('
			SELECT ls_resp_id
			FROM %s
			WHERE survey_id = %s
		', [$table_name, $survey_id]);
		
		$sql = str_replace("'", "", $sql);

		$resp_ids = $wpdb->get_col($sql);		
		
		return $resp_ids;
	}

	//Get an array with key:token en value: completed from the WP response table
	public function ls2wp_rpc_tokens_completed($survey_id){	
			
		$responses = $this->get_responses($survey_id);
		
		$token_completed = false;

		foreach($responses as $response){
			
			if(empty($response->token)) continue;
			
			if(empty($response->submitdate)) $token_completed[$response->token] = 'N';
			else $token_completed[$response->token] = $response->submitdate;
		}
		
		return $token_completed;	
	}

	//Get array with tokens of incomplete responses in the WP response table
	public function get_imported_incomplete_tokens($survey_id){
		
		global $wpdb;
		
		$table_name = $this->table_name;		
		
		$sql = $wpdb->prepare('
			SELECT token
			FROM '.$table_name.'
			WHERE survey_id = %s and submitdate IS NULL	and token IS NOT NULL		
		', $survey_id);

		$tokens = $wpdb->get_col($sql);		
		
		return $tokens;
	}	

	//Get array with limesurvey response ids of incomplete responses in the WP response table
	public function get_imported_incomplete_ls_resp_ids($survey_id){
		
		global $wpdb;
		
		$table_name = $this->table_name;		
		
		$sql = $wpdb->prepare('
			SELECT ls_resp_id
			FROM '.$table_name.'
			WHERE survey_id = %s and submitdate IS NULL			
		', $survey_id);

		$resp_ids = $wpdb->get_col($sql);		
		
		return $resp_ids;
	}

	//get all responses of survey from responsetable in WP
	public function get_responses($survey_id){
		
		global $wpdb;
		
		$table_name = $this->table_name;
		
		$sql = $wpdb->prepare('
			SELECT *
			FROM '.$table_name.'
			WHERE survey_id = %s		
		', $survey_id);

		$responses = $wpdb->get_results($sql);
		
		foreach($responses as $response){
			
			$response = $this->unserialize_questions($response);
			
		}
		
		return $responses;	
	}

	//All answercodes of a questiom(column in survey responsew table)
	public function ls2wp_rpc_get_question_answercodes($survey_id, $q_code){
		
		$resps = new Ls2wp_RPC_Responses();
		$responses = $resps->get_responses($survey_id);	

		foreach($responses as $response){
			
			if(empty($response->submitdate)) continue;
		
			$answer_codes[] = $response->$q_code;
		}
		return $answer_codes;
	}

	//get response of survey by token from responsetable in WP
	public function ls2wp_rpc_get_response_by_token($survey_id, $token){
		global $wpdb;
		
		$table_name = $this->table_name;
		
		$sql = $wpdb->prepare('
			SELECT *
			FROM '.$table_name.'
			WHERE survey_id = %d AND token = %s	
		', $survey_id, $token);

		$response = $wpdb->get_row($sql);
		
		if($response){
			
			$fieldmap = ls2wp_get_ls_q_fieldmap($survey_id);

			$survey = ls2wp_rpc_get_survey($survey_id);
			
			$response = $this->unserialize_questions($response);			
			
			$response = ['survey_title' => $survey->surveyls_title] + (array)$response;
			
			$response = $this->ls2wp_add_field_data($response, $fieldmap);
			
			$response = ls2wp_add_wp_answer_values($response);			
			
			return $response;
			
		} else return false;	
	}		

	private function unserialize_questions($response){
	
		$response->questions = unserialize($response->questions);
		
		foreach($response->questions as $qcode => $answ){
			
			$response->$qcode = $answ;
			
		}
		
		unset($response->questions);	
	
		return $response;
	}

	//All responses of a survey with fieldmap data and assessment values.
	public function ls2wp_rpc_get_responses_survey($survey_id){
		
		$responses = $this->get_responses($survey_id);
	
		$fieldmap = ls2wp_get_ls_q_fieldmap($survey_id);

		$survey = ls2wp_rpc_get_survey($survey_id);

		$responses_nw = array();
		
		foreach($responses as $response){
		
			if(!empty($survey->surveyls_title)) $response = ['survey_title' => $survey->surveyls_title] + (array)$response;
			else $response = ['survey_title' => 'Title not found'] + (array)$response;
			
			$response_nw = $this->ls2wp_add_field_data($response, $fieldmap);
			
			$response_nw = ls2wp_add_wp_answer_values($response_nw);
			
			$responses_nw[] = $response_nw;
		}

		return $responses_nw;
		
	}

	//Add fieldmap data en assessment values to a response
	public function ls2wp_add_field_data($response, $fieldmap){

		$survey_id = reset($fieldmap)['sid'];
		
		$response_nw['survey_id'] = $survey_id;

		$props = ls2wp_rpc_get_survey_props($survey_id);
		
		$response_nw['gsid'] = $props['gsid'];		
		$response_nw['datecreated'] = $props['datecreated'];

		foreach($response as $key => $answer_code) {		
			
			if(isset($fieldmap[$key])){
		
				if($fieldmap[$key]['type'] == '*') continue;
		
				$response_nw[$key]['answer_code'] = $answer_code;
			
				if($fieldmap[$key]['answeroptions'] == 'No available answer options'){
					$response_nw[$key]['answer'] = $answer_code;
					$response_nw[$key]['value'] = false;					
				} else {

					if(empty($fieldmap[$key]['answeroptions'][$answer_code]['answer'])) $fieldmap[$key]['answeroptions'][$answer_code]['answer'] = false;
					if(empty($fieldmap[$key]['answeroptions'][$answer_code]['assessment_value'])) $fieldmap[$key]['answeroptions'][$answer_code]['assessment_value'] = false;
					
					
					$response_nw[$key]['answer'] = $fieldmap[$key]['answeroptions'][$answer_code]['answer'];
					$response_nw[$key]['value'] = $fieldmap[$key]['answeroptions'][$answer_code]['assessment_value'];			
				
				}

				if($answer_code == 'Y') $response_nw[$key]['answer'] = __('Yes', 'ls2wp');
				if($answer_code == 'N') $response_nw[$key]['answer'] = __('No', 'ls2wp');
				if($answer_code == 'M') $response_nw[$key]['answer'] = __('Male', 'ls2wp');
				if($answer_code == 'F') $response_nw[$key]['answer'] = __('Female', 'ls2wp');				
				
				$response_nw[$key]['type'] = $fieldmap[$key]['type'];
				$response_nw[$key]['title'] = $fieldmap[$key]['title'];
				$response_nw[$key]['aid'] = $fieldmap[$key]['aid'];
				$response_nw[$key]['gid'] = $fieldmap[$key]['gid'];
				$response_nw[$key]['group_name'] = $fieldmap[$key]['group_name'];
				$response_nw[$key]['question'] = $fieldmap[$key]['question'];
				if(isset($fieldmap[$key]['subquestion'])) $response_nw[$key]['subquestion'] = $fieldmap[$key]['subquestion'];
				else $response_nw[$key]['subquestion'] = false;								
				
			} else {
				$response_nw[$key] = $answer_code;
			}			
		}
		
		return $response_nw;
	}
		
	//Response in survey belonging to email address
	public function ls2wp_rpc_get_participant_response($survey_id, $email){
		
		global $wpdb;
		
		$parts = new Ls2wp_RPC_Participants();
		
		$participant = $parts->ls2wp_rpc_get_participant($survey_id, $email);
		$token = $participant->token;
		
		$table_name = $this->table_name;
		
		$sql = $wpdb->prepare('
			SELECT *
			FROM '.$table_name.'
			WHERE token = %s		
		', $token);
	
		$resp = $wpdb->get_results($sql);	
	
		$response = $resp[0];
			
		$response = $this->unserialize_questions($response);
		
		$survey = ls2wp_rpc_get_survey($response->survey_id);
		
		$fieldmap = ls2wp_get_ls_q_fieldmap($response->survey_id);
		
		$response = ['survey_title' => $survey->surveyls_title] + (array)$response;
		
		$response = $this->ls2wp_add_field_data($response, $fieldmap);
		
		$response = ls2wp_add_wp_answer_values($response);
	
		return $response;
	}	
}

class Ls2wp_RPC_Participants {
	
	public $survey_id;
	public $table_name;
	
	public function __construct($survey_id = '') {
		
		global $wpdb;
		
		$this->table_name = $wpdb->prefix . 'ls2wp_rpc_participants';
				
		$this->survey_id = $survey_id;
	}
	
	public function ls2wp_create_participant_table() {

		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		
		$table_name = $this->table_name;

			$sql = "CREATE TABLE $table_name (
			id int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			survey_id varchar(8),
			firstname varchar(30),
			lastname varchar(50),
			email varchar(50),
			tid int(8),
			token varchar(25),
			language varchar(5),
            validfrom varchar(50),
            validuntil varchar(50),
			attribute_1 varchar(50),
			attribute_2 varchar(50),
			attribute_3 varchar(50),			
			PRIMARY KEY  (id),
			KEY token (token),
			KEY email (email)
			) $charset_collate;";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			$success = empty( $wpdb->last_error );


		return $success;

	}
	
	public function ls2wp_import_participants($survey_id){
		
		global $rpc_client;
		global $s_key;			
		
		$ls_token_ids = $this->get_imported_token_ids($survey_id);

		if(empty($ls_token_ids)) $from_ls_token_id = 1;		
		else $from_ls_token_id = max($ls_token_ids) + 1;
		
		$to_ls_token_id = $from_ls_token_id + 500;

		if(is_array($s_key)){		
			return $s_key['status'];
		}
		
		$atts = array('language', 'validfrom', 'validuntil', 'attribute_1', 'attribute_2', 'attribute_3');
		
		$participants = $rpc_client->list_participants($s_key, $survey_id, $from_ls_token_id, $to_ls_token_id, false, $atts);			

		if(!empty($participants['status'])) return $participants['status'];

		$n = 0;
	
		foreach($participants as $participant){
			
			$participant['survey_id'] = $survey_id;

			$participant = $participant['participant_info'] + $participant;
			
			unset($participant['participant_info']);
					
			$this->insert_participant($participant);		
			
			$n++;
		}
		
		return $n;
	}	
	
	//Add participant to WP participant table
	public function insert_participant($participant){
		
		global $wpdb;
		
		$table_name = $this->table_name;
		
		$wpdb->insert($table_name, $participant);
		
	}
	
	public function get_imported_token_ids($survey_id){
		
		global $wpdb;
		
		$table_name = $this->table_name;
		
		
		$sql = $wpdb->prepare('
			SELECT tid
			FROM '.$table_name.'
			WHERE survey_id = %s		
		', $survey_id);

		$token_ids = $wpdb->get_col($sql);		
		
		return $token_ids;
	}

	//get all tokens of a participant in WP participant table
	public function ls2wp_get_participant_tokens($email){
		
		global $wpdb;
		
		$table_name = $this->table_name;

		$sql = $wpdb->prepare('
			SELECT token
			FROM '.$table_name.'
			WHERE email = %s		
		', $email);

		$tokens = $wpdb->get_col($sql);	

		return $tokens;
		
	}
	
	//get participants from participant table in WP
	public function ls2wp_rpc_get_participants($survey_id, $name = ''){
		
		global $wpdb;

		if(empty($name)) $name = '%';
		else $name = '%'.$name.'%';
		
		$table_name = $this->table_name;
		
		$sql = $wpdb->prepare('
			SELECT *
			FROM '.$table_name.'
			WHERE survey_id = %d AND (firstname LIKE %s OR lastname LIKE %s)	
		', $survey_id, $name, $name);

		$participants = $wpdb->get_results($sql);
		
		return $participants;	
	}	


	//Get participant data by email.
	//$add_participant == true: If no participant, add participant in Limesurvey participant table.
	public function ls2wp_rpc_get_participant($survey_id, $email, $add_participant = false){
		
		global $wpdb;
		global $rpc_client;
		global $s_key;	
		
		if(!is_email($email)) return false;
		
		$table_name = $this->table_name;
		
		$sql = $wpdb->prepare('
			SELECT *
			FROM '.$table_name.'
			WHERE survey_id = %d AND email = %s	
		', $survey_id, $email);

		$participant = $wpdb->get_row($sql);

		if(empty($participant)){
		
			if(is_array($s_key)){		
				return $s_key['status'];
			}	

			$participant = (object)$rpc_client->get_participant_properties($s_key, $survey_id, array('email' => $email));
			
			//If no participant is found, create a new participant
			if($participant->status) unset($participant->status);
			
			if(empty($participant->token) && $add_participant){
				
				$survey_props = $rpc_client->get_survey_properties($s_key, $survey_id);
				$lang = $survey_props['language'];
				
				$user = get_user_by('email', $email);
				
				$participant->firstname = $user->first_name;
				$participant->lastname = $user->last_name;
				$participant->email = $user->user_email;
				$participant->language = $lang;
				
				$participant = apply_filters('ls2wp_add_participant_properties', $participant, $survey_id, $user);
				
				$participant_data[0] = (array)$participant;
				
				//new participant limesurvey database
				$nw_participants = $rpc_client->add_participants($s_key, $survey_id, $participant_data);
				
				if(empty($nw_participants['status']) )$participant = (object)$nw_participants[0];
				
				if(empty($participant->token)) return __('A new survey participant could not be added', 'ls2wp');
			}

			//new participant in WP participant table
			$this->ls2wp_import_participants($survey_id);			
			
		}
		
		return $participant;
	}	
}