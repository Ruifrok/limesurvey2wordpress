<?php

//Alle surveys in de LS database
function ls2wp_get_surveys(){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$surveys = ls2wp_rpc_get_surveys();
			
	} else {		
		$surveys = ls2wp_db_get_surveys();
		
	}
	
	return $surveys;
}

//Limited set of survey data
function ls2wp_get_survey($survey_id){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$surveys = ls2wp_rpc_get_survey($survey_id);
			
	} else {		
		$surveys = ls2wp_db_get_survey($survey_id);
		
	}
	
	return $surveys;
}

//Alle vragen van een survey
function ls2wp_get_questions($survey_id){

	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$questions = ls2wp_rpc_get_questions($survey_id);
			
	} else {		
		$questions = ls2wp_db_get_questions($survey_id, false);
		
	}	
	
	return $questions;
}

//alle antwoorden met assessment value
function ls2wp_get_answers($survey_id){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$answers = ls2wp_rpc_get_answers($survey_id);
			
	} else {		
		$answers = ls2wp_db_get_answers($survey_id);
		
	}	
	
	return $answers;	
}

//Zoek group name bij group id
function  ls2wp_get_group_name($group_id, $survey_id){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$group_name = ls2wp_rpc_get_group_name($group_id, $survey_id);
			
	} else {		
		$group_name = ls2wp_db_get_group_name($group_id, $survey_id);
		
	}	
	
	return $group_name;		
	
}

//Alle responsen uit een survey
function ls2wp_get_responses_survey($survey_id){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$responses = ls2wp_rpc_get_responses_survey($survey_id);		
	} else {		
		$responses = ls2wp_db_get_responses_survey($survey_id);		
	}
	
	return $responses;
}

//Alle deelnemers aan een survey
function ls2wp_get_participants($survey_id, $name = ''){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$participants = ls2wp_rpc_get_participants($survey_id, $name);
	} else {		
		$participants = ls2wp_db_get_participants($survey_id, $name);
	}
	
	return $participants;
}

//haal ls-participantgegevens op bij wp_gebruiker.
//$add_participant: Als geen participant, dan een aanmaken.
function ls2wp_get_participant($survey_id, $user, $add_participant = false){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$participant = ls2wp_rpc_get_participant($survey_id, $user, $add_participant);
	} else {

		
		$participant = ls2wp_db_get_participant($survey_id, $user, $add_participant);
		
	}

	return $participant;
}

//Alle responsen van een deelnemer
function ls2wp_get_user_responses($user, $args=array()){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$responses = ls2wp_rpc_get_user_responses($user, $args);
	} else {		
		$responses = ls2wp_db_get_user_responses($user, $args);
	}
	
	return $responses;
}

//filter surveys op survey group id en/of aangeboden surveys
function ls2wp_filter_surveys($surveys, $args){

	$args = apply_filters('ls2wp_survey_filter_args', $args);
	
	if(!empty($args['survey_group_id'])){
		
		foreach($surveys as $key => $survey){		
			
			if($survey->gsid != $args['survey_group_id']){
				unset($surveys[$key]);				
			}			
		}
		$surveys = array_values($surveys);		
	}

	if(!$args['all_surveys']){
		
		$id_string = get_option('ls_survey_ids');
		if(!empty($id_string)) {

			$survey_ids = explode( ',', $id_string);
			
			foreach($surveys as $key => $survey){
				
				if(!in_array($survey->sid, $survey_ids)){
					unset($surveys[$key]);
				}			
			}			
		}
		$surveys = array_values($surveys);
	}
	
	return $surveys;
}

//Voeg de antwoordwaardes uit de settings page toe aan response
function ls2wp_add_wp_answer_values($response){
	
	$wp_answer_values = get_option($response->survey_id.'_answer_values');

	if(empty($wp_answer_values)) return $response;

	foreach($response as $q_code => $question){
		
		if(!is_array($question)) continue;
	
		if(empty($wp_answer_values[$question['title']])) continue;
//print_obj($question);			
		if($question['type'] == 'M' && !empty($question['answer_code'])){

			$response->$q_code['value'] = $wp_answer_values[$question['title']][$question['aid']]['value'];
		}
		
	}	
	
	return $response;
}

//Ophalen url naar nog niet ingevulde survey. Als ingevuld dan return false
function ls2wp_get_ls_survey_url($survey_id, $user, $add_participant = true){	
	
	$participant = ls2wp_get_participant($survey_id, $user, $add_participant);

	if(!is_array($participant) || empty($participant['usesleft'])) return false;
	
	$survey_url = LS2WP_SITEURL.'index.php/'.$survey_id.'?token='.$participant['token'].'&newtest=Y';
	
	return $survey_url;
}

//Bepaal of er een actieve en niet ingevulde survey is met gebruiker als participant
//Voeg survey url met token toe aan survey data 
function ls2wp_ls_active_surveys($user, $add_participant = true){
	
	$id_string = get_option('ls_survey_ids');

	$survey_actief = false;
	
	$active_surveys = array();

	if(!empty($id_string)) {

		$survey_ids = explode( ',', $id_string);
		
		$surveys = ls2wp_get_surveys();

		foreach($surveys as $survey){
			
			if(in_array($survey->sid, $survey_ids) && $survey->active == 'Y'){
			
				$survey->url = ls2wp_get_ls_survey_url($survey->sid, $user, $add_participant);
				$survey->user_name = $user->display_name;
				
				if($survey->url) $active_surveys[] = $survey;
			}
		}
	}
	
	if(count($active_surveys) > 1){
		usort($active_surveys, function ($a, $b) {
			return strcmp($a->surveyls_title, $b->surveyls_title);
		});	
	}	
	
	return $active_surveys;
}

//update email in Limesurvey partcipant als email in wp_user wordt aangepast
add_action( 'profile_update', 'ls2wp_check_user_email_updated', 10, 2 );
	function ls2wp_check_user_email_updated( $user_id, $old_user_data ) {
		
		$old_user_email = $old_user_data->data->user_email;

		$user = get_userdata( $user_id );
		$new_user_email = $user->user_email;	
		
		if ( $new_user_email === $old_user_email ) return;
		
		$use_rpc = get_option('use_rpc');
		
		if($use_rpc){

			$id_string = get_option('ls_survey_ids');
			if(!empty($id_string)) $survey_ids = explode( ',', $id_string);
			
			$rpc_client = new \ls2wp\jsonrpcphp\JsonRPCClient( LS2WP_RPCURL );
			$s_key= $rpc_client->get_session_key( LS2WP_USER, LS2WP_PASSWORD );

			if(is_array($s_key)){		
				return $s_key['status'];
			}

			foreach($survey_ids as $survey_id){
				
				$participant = ls2wp_get_participant($survey_id, $user);
				
				if(!empty($participant)){
					
					$result = $rpc_client->set_participant_properties($s_key, $survey_id,['email' => $old_user_email],['email' => $new_user_email]);
					
				}
				
			}			

			$rpc_client->release_session_key( $s_key);			
			
		} else {
		
			global $lsdb;		
				
			$survey_tokens = ls2wp_get_email_surveys_tokens($old_user_email);
		
			foreach($survey_tokens as $survey_id => $tokens){
				foreach($tokens as $token){
					$lsdb->update($lsdb->prefix.'tokens_'.$survey_id, ['email' => $new_user_email], ['token' => $token]);
				}					
			}			
		}
	}
