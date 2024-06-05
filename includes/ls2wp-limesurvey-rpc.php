<?php

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly


add_action('init', 'ls2wp_set_ls_cred');
	function ls2wp_set_ls_cred(){		
		
		$ls_rpcurl = LS2WP_SITEURL.'index.php/admin/remotecontrol';
		$ls_user_name = get_option('ls_rpc_user');
		$ls_passw = get_option('ls_rpc_passw');
		
/* 		$ls_url = 'http://localhost/vragenlijst/';
		$ls_rpcurl = $ls_url.'index.php/admin/remotecontrol';
		$ls_user_name = 'mhp';
		$ls_passw = '19Eelco89'; */			

		define( 'LS2WP_RPCURL', LS2WP_SITEURL.'index.php/admin/remotecontrol'); 
		define( 'LS2WP_USER', $ls_user_name);
		define( 'LS2WP_PASSWORD', $ls_passw);
	}


//haal ls-participantgegevens op bij email.
//$add_participant: Als geen participant, dan een aanmaken.
function ls2wp_rpc_get_participant($survey_id, $email, $add_participant = false){
	
	$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
	$s_key = $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

	if(is_array($s_key)){		
		return $s_key['status'];
	}	

	$participant = $rpc_client->get_participant_properties($s_key, $survey_id, array('email' => $email));
	
	//Als er nog geen ls-participant is voor deze client, dan toevoegen
	if(empty($participant['token']) && $add_participant){
		
		$survey_props = $rpc_client->get_survey_properties($s_key, $survey_id);
		$lang = $survey_props['language'];
		
		$user = get_user_by('email', $email);
		
		$participant_data[0]['firstname'] = $user->first_name;
		$participant_data[0]['lastname'] = $user->last_name;
		$participant_data[0]['email'] = $user->user_email;
		$participant_data[0]['language'] = $lang;
		
		$nw_participants = $rpc_client->add_participants($s_key, $survey_id, $participant_data);
		if(empty($nw_participants['status']) )$participant = $nw_participants[0];

		if(empty($participant['token'])) return 'Er kon geen enquète deelnemer worden aangemaakt';
	}
	
	$rpc_client->release_session_key( $s_key);
	
	return (object)$participant;
}

//alle basis responses van een survey
function ls2wp_export_responses($survey_id){
	
	$responses = get_transient('responses_'.$survey_id);
	
	if(empty($responses)){
		$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
		$s_key= $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

		if(is_array($s_key)){		
			return $s_key['status'];
		}
		
		$b64_resp = $rpc_client->export_responses($s_key, $survey_id, 'json');
		
		$json_resp = base64_decode($b64_resp);
		$responses = json_decode($json_resp, true)['responses'];
		
		set_transient('responses_'.$survey_id, $responses, DAY_IN_SECONDS);
			
		$rpc_client->release_session_key( $s_key);		
	}
	
	return $responses;
}

//fieldmap van de vragen van een survey met toevoeging van antwoordopties met assessment values
function ls2wp_get_ls_q_fieldmap($survey_id){

	$ls_fieldmap = get_transient('fieldmap_'.$survey_id);
		
	if(empty($ls_fieldmap)){
		
		$ls_fieldmap = array();

		$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
		$s_key= $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

		if(is_array($s_key)){		
			return $s_key['status'];
		}

		$fields = $rpc_client->get_fieldmap($s_key, $survey_id);

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

		set_transient('fieldmap_'.$survey_id, $ls_fieldmap, DAY_IN_SECONDS);
		
		$rpc_client->release_session_key( $s_key);
	}		
			
	return $ls_fieldmap;
	
}

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
			$questions[$quest['sqid']]['title'] = $quest['aid'];
			$questions[$quest['sqid']]['other'] = $quest['other'];
			$questions[$quest['sqid']]['question'] = $quest['subquestion'];
			
			if(empty($questions[$quest['qid']])){
				$questions[$quest['qid']]['qid'] = $quest['qid'];
				$questions[$quest['qid']]['parent_qid'] = 0;
				$questions[$quest['qid']]['sid'] = $quest['sid'];
				$questions[$quest['qid']]['gid'] = $quest['gid'];
				$questions[$quest['qid']]['type'] = $quest['type'];
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

//Array met antwoordcode en antwoordwaardes; key is question id
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

//Zoek group name bij group id
function  ls2wp_rpc_get_group_name($group_id, $survey_id){
	
	$fieldmap = ls2wp_get_ls_q_fieldmap($survey_id);
	
	foreach($fieldmap as $quest){
		
		if($quest['gid'] == $group_id) return $quest['group_name'];

	}	
}

//Alle responses van een survey met fieldmap data en assessment values.
function ls2wp_rpc_get_responses_survey($survey_id){
	
	$responses = ls2wp_export_responses($survey_id);
	
	$fieldmap = ls2wp_get_ls_q_fieldmap($survey_id);
	
	$completed = ls2wp_tokens_completed($survey_id);

	$survey = ls2wp_rpc_get_survey($survey_id);

	foreach($responses as $response){
		
		$response = ['survey_title' => $survey->surveyls_title] + (array)$response;
		
		$response_nw = ls2wp_add_field_data($response, $fieldmap);
		
		if($survey->anonymized == 'N' && is_array($completed)){
			$response_nw['completed'] = !empty($completed[$response['token']]) ? $completed[$response['token']] : '';
		} else $response_nw['completed'] = '';
		
		$response_nw = ls2wp_add_wp_answer_values($response_nw);
		
		$responses_nw[] = $response_nw;
	}

	return $responses_nw;
	
}

//Voeg fieldmap data en assessment values toe aan response
function ls2wp_add_field_data($response, $fieldmap){

	$survey_id = reset($fieldmap)['sid'];
	
	$response_nw['survey_id'] = $survey_id;

	$props = ls2wp_get_survey_props($survey_id);
	
	$response_nw['gsid'] = $props['gsid'];		
	$response_nw['datecreated'] = $props['datecreated'];
	
	$completed = ls2wp_tokens_completed($survey_id);

	if($props['anonymized'] == 'N' && is_array($completed)){
		$response_nw['completed'] = !empty($completed[$response['token']]) ? $completed[$response['token']] : '';
	} else $response_nw['completed'] = '';

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

			if($answer_code == 'Y') $response_nw[$key]['answer'] = 'Ja';
			if($answer_code == 'N') $response_nw[$key]['answer'] = 'Nee';
			if($answer_code == 'M') $response_nw[$key]['answer'] = 'Man';
			if($answer_code == 'F') $response_nw[$key]['answer'] = 'Vrouw';				
			
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

//Array with limited dataset of all surveys
function ls2wp_rpc_get_surveys(){
	
	$surveys_nw = get_transient('ls_surveys');
	
	if(empty($surveys_nw)){

		$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
		$s_key= $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

		if(is_array($s_key)){		
			return $s_key['status'];
		}	
		
		$surveys = $rpc_client->list_surveys($s_key);

		foreach($surveys as $survey){
		
			$props = ls2wp_get_survey_props($survey['sid']);
		
			$survey['gsid'] = $props['gsid'];
			$survey['language'] = $props['language'];
			$survey['anonymized'] = $props['anonymized'];
			$survey['datecreated'] = $props['datecreated'];
			$survey['tokenlength'] = $props['tokenlength'];
				
			$surveys_nw[] = (object)$survey;
		}
		
		set_transient('ls_surveys', $surveys_nw, DAY_IN_SECONDS);
		
		$rpc_client->release_session_key( $s_key);		
	}
		
	return $surveys_nw;		
}


function ls2wp_rpc_get_survey($survey_id){
	
	$surveys = ls2wp_rpc_get_surveys();
	
	foreach($surveys as $survey){
		if($survey->sid == $survey_id) {			
			return $survey;
		}
	}
	
}

//Get survey with all properties
function ls2wp_rpc_get_survey_props($survey_id){
	
	$surveys = ls2wp_rpc_get_surveys();
	
	foreach($surveys as $survey){
		
		if($survey->sid == $survey_id){
			
			$props = ls2wp_get_survey_props($survey_id);
			
			$survey->gsid = $props['gsid'];
			
			return $survey;
		}		
	}	
}

//Alle responses van user in de aangeboden surveys
//Als $all_serveys = true dan wordt in alle surveys in de database gezocht
function ls2wp_rpc_get_participant_responses($email, $args = array()){
	
	$defaults = array(
		'survey_group_id' 	=> '',
		'all_surveys'		=> false,		
	);
	
	$args = wp_parse_args($args, $defaults);
	
	$surveys = ls2wp_rpc_get_surveys();

	$surveys =ls2wp_filter_surveys($surveys, $args);

	$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
	$s_key= $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

	if(is_array($s_key)){		
		return $s_key['status'];
	}	
		
	$responses = array();
		
	foreach($surveys as $survey){

		$participants = ls2wp_rpc_get_participants($survey->sid);
		
		if(!is_array($participants)) continue;

		foreach($participants as $participant){


			if(!is_object($participant) || empty($participant->token) || empty($participant->email) || $participant->email != $email || $survey->anonymized == 'Y') continue;	
		
			$b64_resp = $rpc_client->export_responses_by_token($s_key, $survey->sid, 'json', $participant->token);

			if(is_string($b64_resp)){
				$json_resp = base64_decode($b64_resp);
				$resp = json_decode($json_resp, true);		
				
				$response = ($resp['responses'][0]);
				$response = ['survey_title' => $survey->surveyls_title] + $response;
		
				$fieldmap = ls2wp_get_ls_q_fieldmap($survey->sid);

				$response_nw = ls2wp_add_field_data($response, $fieldmap);
				
				$response_nw = ls2wp_add_wp_answer_values($response_nw);
				
				$responses[] = $response_nw;
			}					
		}
	}
	$rpc_client->release_session_key( $s_key);

	return $responses;
}

//Alle eigenschappen van een survey
function ls2wp_get_survey_props($survey_id){
	
	$survey_props = get_transient('survey_props_'.$survey_id);
	
	if(empty($survey_props)){
		$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
		$s_key= $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

		if(is_array($s_key)){		
			return $s_key['status'];
		}

		$survey_props = $rpc_client->get_survey_properties($s_key, $survey_id);

		$rpc_client->release_session_key( $s_key);
		
		set_transient('survey_props_'.$survey_id, $survey_props, DAY_IN_SECONDS);
	}
	
	return $survey_props;	
}

//Alle deelnemers aan een survey
function ls2wp_rpc_get_participants($survey_id, $name = ''){
	
	$participants = get_transient('participants_'.$survey_id);
	
	if(empty($participants)){
		$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
		$s_key= $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

		if(is_array($s_key)){		
			return $s_key['status'];
		}
		
		$atts = array('emailstatus', 'language', 'blacklisted', 'sent', 'remindersent', 'remindercount', 'completed', 'usesleft', 'validfrom', 'validuntil', 'mpid', 'attribute_1', 'attribute_2', 'attribute_3');
		
		$participants = $rpc_client->list_participants($s_key, $survey_id, 0, 10000, false, $atts);
	
		if(!empty($participants['status'])) return $participants['status'];
		
		foreach($participants as $key => $participant){

			$participants[$key] = (object)($participant['participant_info'] + $participant);
			
			unset($participants[$key]->participant_info);
			
			
		}
		
		set_transient('participants_'.$survey_id, $participants, DAY_IN_SECONDS);
		
		$rpc_client->release_session_key( $s_key);
	}

	if(!empty($name)){
		
		foreach($participants as $key => $participant){

			if(!stristr($participant->firstname, $name) && !stristr($participant->lastname, $name)) continue;
			
			$participants_nw[] = $participant;
		}
		$participants = $participants_nw;
	}	
	return $participants;	
}

//Een array met key:token en value: completed
function ls2wp_tokens_completed($survey_id){	
		
	$participants = ls2wp_rpc_get_participants($survey_id);
	
	if(!is_array($participants)) return $participants;

	foreach($participants as $participant){
		$token_completed[$participant->token] = $participant->completed;
	}
	
	return $token_completed;	
}

