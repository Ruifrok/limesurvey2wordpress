<?php

//All registered surveys
function ls2wp_get_surveys(){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$surveys = ls2wp_rpc_get_surveys();
			
	} else {		
		$surveys = ls2wp_db_get_surveys();
		
	}
	
	return $surveys;
}

//All  survey groups
function ls2wp_get_survey_groups(){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$surveys = ls2wp_rpc_get_survey_groups();
			
	} else {		
		$surveys = ls2wp_db_get_survey_groups();
		
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

//All questions of a survey
function ls2wp_get_questions($survey_id){

	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$questions = ls2wp_rpc_get_questions($survey_id);
			
	} else {		
		$questions = ls2wp_db_get_questions($survey_id, false);
		
	}	
	
	return $questions;
}

//all answers with assessment values
function ls2wp_get_answers($survey_id){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$answers = ls2wp_rpc_get_answers($survey_id);
			
	} else {		
		$answers = ls2wp_db_get_answers($survey_id);
		
	}	
	
	return $answers;	
}

//find question group name by group id
function  ls2wp_get_group_name($group_id, $survey_id){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {		
		$group_name = ls2wp_rpc_get_group_name($group_id, $survey_id);
			
	} else {		
		$group_name = ls2wp_db_get_group_name($group_id, $survey_id);
		
	}	
	
	return $group_name;		
	
}

//Get array with key: group_id and value: group_name
function  ls2wp_get_group_names($response){

	foreach($response as $question){
		
		if(!is_array($question)) continue;
		
		$group_names[$question['gid']] = $question['group_name'];
		
	}
	
	return $group_names;
	
}

//All responses of a survey
function ls2wp_get_responses_survey($survey_id){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {	

		$resps = new Ls2wp_RPC_Responses();
		$responses = $resps->ls2wp_rpc_get_responses_survey($survey_id);		
	} else {		
		$responses = ls2wp_db_get_responses_survey($survey_id);		
	}
	
	return $responses;
}

//All participants of a survey
function ls2wp_get_participants($survey_id, $name = ''){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {

		$parts = new Ls2wp_RPC_Participants();	
		$participants = $parts->ls2wp_rpc_get_participants($survey_id, $name);
	} else {		
		$participants = ls2wp_db_get_participants($survey_id, $name);
	}
	
	return $participants;
}

//Get an array with key:token and value: completed
function ls2wp_tokens_completed($survey_id){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {

		$resps = new Ls2wp_RPC_Responses();	
		$token_completed = $resps->ls2wp_rpc_tokens_completed($survey_id);
	} else {		
		$token_completed = ls2wp_db_tokens_completed($survey_id);
	}
	
	return $token_completed;
}


//Get ls-participant data by email.
//$add_participant = true: If no participant is found, add a new participant.
function ls2wp_get_participant($survey_id, $email, $add_participant = false){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {	

		$parts = new Ls2wp_RPC_Participants();
		$participant = $parts->ls2wp_rpc_get_participant($survey_id, $email, $add_participant);
	} else {

		
		$participant = ls2wp_db_get_participant($survey_id, $email, $add_participant);
		
	}

	return $participant;
}

//Response in survey belonging to email address
function ls2wp_get_participant_response($survey_id, $email){
	
	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {

		$resps = new Ls2wp_RPC_Responses();	
		$response = $resps->ls2wp_rpc_get_participant_response($survey_id, $email);
	} else {		
		$response = ls2wp_db_get_participant_response($survey_id, $email);
	}
	
	return $response;
}

//Get response by token
function ls2wp_get_response_by_token($survey_id, $token){

	$use_rpc = get_option('use_rpc');
	
	if($use_rpc) {

		$resps = new Ls2wp_RPC_Responses();	
		$response = $resps->ls2wp_rpc_get_response_by_token($survey_id, $token);
	} else {		
		$response = ls2wp_db_get_response_by_token($survey_id, $token);
	}	
	
	return $response;
}

//Add assessment values from the settings page to the response
function ls2wp_add_wp_answer_values($response){
	
	$wp_answer_values = get_option($response['survey_id'].'_answer_values');	

	if(empty($wp_answer_values)) return $response;

	foreach($response as $q_code => $question){
		
		if(!is_array($question)) continue;
	
		if(empty($wp_answer_values[$question['title']])) continue;
		
		if($question['type'] == 'M' && !empty($question['answer_code'])){

			$response[$q_code]['value'] = $wp_answer_values[$question['title']][$question['aid']]['value'];
		}		
	}	
	
	return $response;
}

//Get url to a survey for a wordpress user. 
//If no participant is found for this user add this user as a participant.
function ls2wp_get_ls_survey_url($survey_id, $user, $add_participant = true){	

	$participant = ls2wp_get_participant($survey_id, $user->user_email, $add_participant);
	
	if(is_array($participant)) return false;

	$survey_url = LS2WP_SITEURL.'index.php/'.$survey_id.'?token='.$participant->token.'&newtest=Y';
	
	return $survey_url;
}

//determine if a wordpress user has no response or incomplete response in survey
function ls2wp_survey_active($user, $survey_id, $add_participant = true){
	
	$participant = ls2wp_get_participant($survey_id, $user->user_email, $add_participant);
	
	$active = false;
	
	$response = ls2wp_get_response_by_token($survey_id, $participant->token);

	if(empty($response) || empty($response['submitdate'])) $active = true;
	
	return $active;
}

//update email address in Limesurvey partcipant when email in wp_user is updated
add_action( 'profile_update', 'ls2wp_check_user_email_updated', 10, 2 );
	function ls2wp_check_user_email_updated( $user_id, $old_user_data ) {
		
		$old_user_email = $old_user_data->data->user_email;

		$user = get_userdata( $user_id );
		$new_user_email = $user->user_email;	
		
		if ( $new_user_email === $old_user_email ) return;
	
		$use_rpc = get_option('use_rpc');
		
		if($use_rpc){
			
			global $wpdb;
			global $rpc_client;
			global $s_key;
			
			$parts = new Ls2wp_RPC_Participants();
			
			$surveys = ls2wp_get_surveys();
			
			$survey_ids = array_column($surveys, 'sid');

			foreach($survey_ids as $survey_id){
				
				$participant = ls2wp_get_participant($survey_id, $old_user_email);
				
				if($participant->status) continue;
			
				if(!empty($participant)){
					
					$result = $rpc_client->set_participant_properties($s_key, $survey_id,['email' => $old_user_email],['email' => $new_user_email]);
							
					$wpdb->update($wpdb->prefix . 'ls2wp_rpc_participants', ['email' => $new_user_email], ['email' => $old_user_email]);
					
				}				
			}
			
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
