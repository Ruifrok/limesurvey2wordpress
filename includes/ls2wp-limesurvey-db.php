<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//New wpdb object connected witht limesurvey database
add_action ('init','ls2wp_limesurvey_db', 1);
	function ls2wp_limesurvey_db() {

		$use_rpc = get_option('use_rpc');
		if($use_rpc) return;
		
		global $lsdb;
		
		$lsdb_user = get_option('lsdb_user');
		$lsdb_passw = get_option('lsdb_passw');
		$lsdb_name = get_option('lsdb_name');
		$lsdb_host = get_option('lsdb_host');
		
		if(empty($lsdb_user)) return;
		
		$lsdb = new wpdb($lsdb_user, $lsdb_passw, $lsdb_name, $lsdb_host);
		
		$lsdb_prefix = get_option('lsdb_prefix');
		$lsdb->prefix = $lsdb_prefix;
	
	}

//Array of objects containing limited set of survey data
function ls2wp_db_get_surveys() {
	
	global $lsdb;

	$id_string = get_option('ls_survey_ids');
	$gid_string = get_option('ls_survey_group_ids');
	
	if(empty($id_string) && empty($gid_string)) return false;	
	
	if(empty($id_string)) $id_string = ''; else $id_string = str_replace(",", "','", $id_string);
	if(empty($gid_string)) $gid_string = ''; else $gid_string = str_replace(",", "','", $gid_string);

	$sql = $lsdb->prepare("
		SELECT sid, survey{$lsdb->prefix}title, startdate, expires, active, gsid, survey{$lsdb->prefix}language AS language, anonymized, datecreated, tokenlength
		FROM {$lsdb->prefix}surveys		
		JOIN {$lsdb->prefix}surveys_languagesettings ON {$lsdb->prefix}surveys_languagesettings.survey{$lsdb->prefix}survey_id = {$lsdb->prefix}surveys.sid
		WHERE sid in (%s) OR gsid in (%s)
		ORDER BY datecreated	
	", $id_string, $gid_string);
	
	$sql = stripslashes($sql);
	
	$surveys = $lsdb->get_results($sql);
	
	return $surveys;	
}

//object containing limited set of survey data
function ls2wp_db_get_survey($survey_id) {
	global $lsdb;
	
	$sql = $lsdb->prepare("
		SELECT sid, gsid, survey{$lsdb->prefix}language AS language, survey{$lsdb->prefix}title, anonymized, startdate, expires, active, datecreated, tokenlength, listpublic, assessments
		FROM {$lsdb->prefix}surveys
		JOIN {$lsdb->prefix}surveys_languagesettings ON {$lsdb->prefix}surveys_languagesettings.survey{$lsdb->prefix}survey_id = {$lsdb->prefix}surveys.sid
		WHERE sid = %d 
	",$survey_id);
	
	$survey = $lsdb->get_results($sql);
	
	if(empty($survey)){
		return false;
	}else return $survey[0];	
}

//Array containing objects with survey group data
function ls2wp_db_get_survey_groups(){
	global $lsdb;
	
	$sql = "SELECT * FROM {$lsdb->prefix}surveys_groups";
	
	$survey_groups = $lsdb->get_results($sql);
	
	if(empty($survey_groups)){
		return false;
	} else return $survey_groups;	
}

//Array with key survey id and value an array with tokens(usually only one token)
function ls2wp_get_email_surveys_tokens($email) {

	global $lsdb;
	
	if(empty($email)) return false;
		
	$surveys = ls2wp_db_get_surveys();
	
	$results = array();
	
	foreach($surveys as $survey) {
		
		$survey_id = $survey->sid;
		
		$table_name = "{$lsdb->prefix}tokens_{$survey_id}";
		if($lsdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)continue;		

		$sql = $lsdb->prepare("
			SELECT token
			FROM {$lsdb->prefix}tokens_%d
			WHERE email = %s			
		", $survey_id, $email);

		$rows = $lsdb->get_results($sql);
		
		if(!empty($rows)){
			foreach($rows as $row){
				$results[$survey_id][] = $row->token;
			}
		}			
	}
	
	return $results;
}

//Get survey id for a token
function ls2wp_db_get_token_survey_id($token) {
	global $lsdb;
	
	$surveys = ls2wp_db_get_surveys();
	
	foreach($surveys as $survey) {
		$survey_id = $survey->sid;
		
		$table_name = "{$lsdb->prefix}tokens_{$survey_id}";
		if($lsdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)continue;
		
		$sql = $lsdb->prepare("
			SELECT tid
			FROM {$lsdb->prefix}tokens_%d
			WHERE token = %s			
		", $survey_id, $token);

		$tid = $lsdb->get_var($sql);
		
		if(!empty($tid)) return $survey_id;		
	}
	return false;
}

//All raw responses of a survey (question key is sgq code)
//see: https://www.limesurvey.org/manual/SGQA_identifier
function ls2wp_db_get_responses($survey_id){
	global $lsdb;

	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}survey_%d
	", $survey_id);	

	$rows = $lsdb->get_results($sql);

	return $rows;
	
}

//Get response by token
function ls2wp_db_get_response_by_token($survey_id, $token){

	global $lsdb;

	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}survey_%d
		WHERE token = %s
	", $survey_id, $token);	

	$row = $lsdb->get_row($sql);

	if($row){

		$questions = ls2wp_db_get_questions($survey_id);

		$answers = ls2wp_db_get_answers($survey_id);			
			
		$row = (array)$row;
	
		$response = ls2wp_translate_sgq_code($row, $questions, $answers);
		$response = ls2wp_add_wp_answer_values($response);

	} else return false;
	
	return $response;
	
}

//All responses of a survey with assessment values and question data
function ls2wp_db_get_responses_survey($survey_id){
	global $lsdb;
	
	$rows = ls2wp_db_get_responses($survey_id);
	
	$questions = ls2wp_db_get_questions($survey_id);

	$answers = ls2wp_db_get_answers($survey_id);		
	
	if($rows){
		foreach($rows as $row){
			
			$row = (array)$row;
		
			$response = ls2wp_translate_sgq_code($row, $questions, $answers);
			$response = ls2wp_add_wp_answer_values($response);
			
			$responses[] = (array)$response;
		}	
	} else return false;
	
	return $responses;
	
}

//Get an array with key:token en value: completed
function ls2wp_db_tokens_completed($survey_id){
	
	$responses = ls2wp_db_get_responses($survey_id);

	$token_completed = false;

	foreach($responses as $response){
		
		if(empty($response->token)) continue;
		
		if(empty($response->submitdate)) $token_completed[$response->token] = 'N';
		else $token_completed[$response->token] = $response->submitdate;
	}
	
	return $token_completed;		
	
}

//Response in survey belonging to email address
function ls2wp_db_get_participant_response($survey_id, $email) {
	
	global $lsdb;

	$participant = ls2wp_db_get_participant($survey_id, $email);

	$token = $participant->token;
	
	$result = array();
		
	$survey = ls2wp_db_get_survey($survey_id);
	if($survey->anonymized == 'Y') return false;
	
	//all questions in this survey
	$questions = ls2wp_db_get_questions($survey_id);
	
	$answers = ls2wp_db_get_answers($survey_id);
	
	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}survey_%d
		WHERE token = %s
	", $survey_id, $token);

	$result = $lsdb->get_results($sql);
	
	if($lsdb->last_error) return $lsdb->last_error;

	if(!empty($result[0])){
		
		$resp = (array)$result[0];
		
		$response = ls2wp_translate_sgq_code ($resp, $questions, $answers);
		
		$response = ls2wp_add_wp_answer_values($response);			
		
	}	

	return $response;
}

//Transform array with keys sgqa-code into array with key the question-code and value a sub-array with question properties.
//for sgqa-code see https://manual.limesurvey.org/SGQA_identifier/nl and https://manual.limesurvey.org/Question_object_types
function ls2wp_translate_sgq_code ($response, $questions, $answers) {

	$survey_id  = reset($questions)->sid;	

	$survey = ls2wp_db_get_survey($survey_id);

	$response_nw['survey_id'] = $survey_id;
	$response_nw['group_survey_id'] = $survey->gsid;
	$response_nw['survey_title'] = $survey->surveyls_title;
	$response_nw['datecreated'] = $survey->datecreated;
	$response_nw['survey_title'] = $survey->surveyls_title;
	

	//decode question codes
	foreach ($response as $key => $answer_code) {
		$answer = '';
		$value = '';		
		
		if($answer_code == 'Y') $answer = __('Yes', 'ls2wp');
		if($answer_code == 'N') $answer = __('No', 'ls2wp');
		if($answer_code == 'M') $answer = __('Male', 'ls2wp');
		if($answer_code == 'F') $answer = __('Female', 'ls2wp');
		
		//split SGQ in survey_id, group_id and question
		if(is_numeric(substr($key, 0, 2))) {
			$sgq = explode('X', $key);
			$group_id = $sgq[1];
			$qid = $sgq[2];
			
			$group_name = ls2wp_db_get_group_name($group_id, $survey_id);
			
			//remove questiontype Equation
			if($questions[$qid]->type == '*') continue;
	
			$question_aid = '';
			$question = '';
			$subquestion = '';
			
			//Transform SGQ-code in $key question-code en question
			//If parent_qid exists it is a subquestion
			if($questions[$qid]->parent_qid != 0) {
				
				$parent_qid = $questions[$qid]->parent_qid;
				
				$question_title = $questions[$parent_qid]->title;
				$question_aid = $questions[$qid]->title;
				$question_code = $question_title.'['.$question_aid.']';
				$question_type = $questions[$parent_qid]->type;	
				$question_relevance = $questions[$parent_qid]->relevance;	
				$question = !empty($questions[$parent_qid]->question) ? $questions[$parent_qid]->question: ' ';
				$subquestion = !empty($questions[$qid]->question) ? $questions[$qid]->question: ' ';
				
				if(isset($answers[$parent_qid][$answer_code]['answer'])) $answer = $answers[$parent_qid][$answer_code]['answer'];				
				if(isset($answers[$parent_qid][$answer_code]['value'])) $value = $answers[$parent_qid][$answer_code]['value'];
				if(in_array($questions[$parent_qid]->type, ['A','B'])) $value = $answer_code;
				
			} else { //No subquestions
				
				$question_code = $questions[$qid]->title;
				$question_title = $questions[$qid]->title;
				$question = !empty($questions[$qid]->question) ? $questions[$qid]->question: ' ';
				$question_type = $questions[$qid]->type;
				$question_relevance = $questions[$qid]->relevance;
				
				if(isset($answers[$qid][$answer_code]['answer'])) $answer = $answers[$qid][$answer_code]['answer'];
				if(isset($answers[$qid][$answer_code]['value'])) $value = $answers[$qid][$answer_code]['value'];
				
				if($questions[$qid]->type == 'N') $value = intval($answer_code);		
				
			}
		} else {
			$question_code = $key;			
		}
		
		if(empty($question)){
			$response_nw[$question_code] = $answer_code;
		} else {
			
			$response_nw[$question_code]['answer_code'] = $answer_code;

			if(empty($answer)) {				
				$response_nw[$question_code]['answer'] = $answer_code;
			} else {
				$response_nw[$question_code]['answer'] = $answer;
			}			
			if(isset($value)) {
				$response_nw[$question_code]['value'] = $value;
			} else {
				$response_nw[$question_code]['value'] = '';
			}

			$response_nw[$question_code]['type'] = $question_type;
			$response_nw[$question_code]['relevance'] = $question_relevance;
			$response_nw[$question_code]['title'] = $question_title;
			$response_nw[$question_code]['aid'] = $question_aid;
			if(isset($group_id)) $response_nw[$question_code]['gid'] = $group_id;
			$response_nw[$question_code]['group_name'] = $group_name;
			$response_nw[$question_code]['question'] = $question;
			$response_nw[$question_code]['subquestion'] = $subquestion;

		}			
	}
	return $response_nw;
}

//Find group_name of question group_id (from the questioncode in LS) or of gid(Limesurvey group_id) 
function ls2wp_db_get_group_name($group_id, $sid) {
	global $lsdb;
	
	if(is_numeric($group_id)) {
		
	$sql = $lsdb->prepare("
		SELECT group_name
		FROM {$lsdb->prefix}group_l10ns
		WHERE gid = %d
		", $group_id);		
		
	$group_name = $lsdb->get_var($sql);	
		
	} else {
	
	$group_id = $group_id.'%';

	$sql = $lsdb->prepare("
		SELECT group_name, {$lsdb->prefix}questions.gid, title
		FROM {$lsdb->prefix}questions
		JOIN {$lsdb->prefix}group_l10ns on {$lsdb->prefix}questions.gid = {$lsdb->prefix}group_l10ns.gid
		WHERE title LIKE %s AND sid = %d
		", $group_id, $sid);
	
	$group_name = $lsdb->get_var($sql);
	}	
	return $group_name;
	
}

//All anwers and assessment values for a question set (survey)
function ls2wp_db_get_answers($survey_id) {
	global $lsdb;

	$questions = ls2wp_get_questions($survey_id, false);
	
	$qids = array_column($questions, 'qid');

	if(empty($qids)) return array();
	
	$result = array();
	
	$qids_str = implode(',', $qids);
	
	$sql = $lsdb->prepare("
		SELECT {$lsdb->prefix}answers.qid, code ,answer, assessment_value, sortorder
		FROM {$lsdb->prefix}answers
		JOIN {$lsdb->prefix}answer_l10ns ON {$lsdb->prefix}answers.aid = {$lsdb->prefix}answer_l10ns.aid
		WHERE {$lsdb->prefix}answers.qid IN (%s)
		ORDER BY sortorder
	", $qids_str);
	
	//Remove single quotes
	$sql = str_replace("'", "", $sql);
	
	$answers = $lsdb->get_results($sql);

	foreach($answers as $answer) {
		$result[$answer->qid][$answer->code]['answer'] = $answer->answer;
		$result[$answer->qid][$answer->code]['value'] = $answer->assessment_value;
	}
	
	return $result;
}

//answer and assessment value of question/answer code
function ls2wp_get_answer($qid, $antw) {
	global $lsdb;
	
	$sql = $lsdb->prepare("
		SELECT answer, assessment_value
		FROM {$lsdb->prefix}answers
		JOIN {$lsdb->prefix}answer_l10ns ON {$lsdb->prefix}answers.aid = {$lsdb->prefix}answer_l10ns.aid
		WHERE qid = %d AND code = %s
	", $qid, $antw);
	
	$answer = $lsdb->get_row($sql);
	
	return $answer;
}

//All questoons of a survey with question code and question
//$sgqa: If the function is used to translate the sgqa-code from LS, the sub-question questioncode has to be part of the qid(see https://manual.limesurvey.org/SGQA_identifier/nl) and https://manual.limesurvey.org/Question_object_types
function ls2wp_db_get_questions($survey_id, $sgqa=true) {
	global $lsdb;
	$results = array();

	$sql = $lsdb->prepare("
	SELECT {$lsdb->prefix}questions.qid, parent_qid, sid, gid, type, relevance,title, other, question
	FROM {$lsdb->prefix}questions
	JOIN {$lsdb->prefix}question_l10ns ON {$lsdb->prefix}questions.qid = {$lsdb->prefix}question_l10ns.qid
	WHERE sid = %d
	", $survey_id
	);
	$questions = $lsdb->get_results($sql, OBJECT_K);

	if($sgqa) {
	//The sub-question questioncode is part of the qid
		foreach($questions as $key => $question){			
			
			if($question->parent_qid == 0) {
				$qcode = $question->qid;
				//Add a question if the question 'others' with a textfield is shown
				if($question->other == 'Y') {
					
					$question_other = new stdClass;
					
					$question_other->qid = $qcode;
					$question_other->parent_qid = $qcode;
					$question_other->sid = $question->sid;
					$question_other->gid = $question->gid;
					$question_other->type = 'T';
					$question_other->title = 'other';
					$question_other->other = 'N';
					$question_other->question = 'Anders';					
					
					$results[$qcode.'other'] = $question_other;
				}				
			} else {
				$qcode = $question->parent_qid.$question->title;
			}
			$results[$qcode] = $question;

		}
	} elseif(!empty($questions)) {	

		foreach($questions as $key => $question){
			$qcode = $question->qid;
			$results[$qcode] = $question;
		}
	}

	return $results;		
}

//all participants of a survey
function ls2wp_db_get_participants($survey_id, $name='') {
	global $lsdb;
	
	if(!ls2wp_participant_table_exists($survey_id)) return 'Error: No survey participants table';
	
	if(empty($name)) $name = '%';
	else $name = '%'.$name.'%';

	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}tokens_%d	
		WHERE firstname LIKE %s OR lastname LIKE %s
	", $survey_id, $name, $name);

	$participants = $lsdb->get_results($sql);
	
	return $participants;	
}

//Get participant from LS by token
function ls2wp_db_get_participant_by_token($survey_id, $token){
	global $lsdb;
	
	if(empty($survey_id)) return false;
	
	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}tokens_%d
		WHERE token = %s
	", $survey_id, $token);	
	
	$participant = $lsdb->get_row($sql);
	
	return $participant;	
}
//Get participant from LS by email.
//$add_participant: If no participant is found, then add a new participant.
function ls2wp_db_get_participant($survey_id, $email, $add_participant = false){
	global $lsdb;
	
	if(!ls2wp_participant_table_exists($survey_id)) return ['status' => 'Error: No survey participants table'];
		
	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}tokens_%d
		WHERE email = %s
	", $survey_id, $email);	
	
	$participant = $lsdb->get_row($sql);

	if(empty($participant) && $add_participant){

		$user = get_user_by('email', $email);
		
		$token = ls2wp_generate_token();
		
		$survey = ls2wp_db_get_survey($survey_id);
		
		$table_name = $lsdb->prefix.'tokens_'.$survey_id;
	
		$participant['firstname'] = $user->first_name;
		$participant['lastname'] = $user->last_name;
		$participant['email'] = $user->user_email;
		$participant['language'] = $survey->language;		
		$participant['token'] = $token;
		
		$participant = apply_filters('ls2wp_add_participant_properties', $participant, $survey_id, $user);
		
		$success = $lsdb->insert($table_name, $participant);
		
		if($success) $participant = ls2wp_db_get_participant($survey_id, $email);
		
		else return 'Er kon geen enquète deelnemer worden aangemaakt';
	}	
	
	return $participant;	
}

//Generate a new Limesurvey token
function ls2wp_generate_token($length=15 ){
	
    $string = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $string_length = strlen($string);
    $token = '';
    for ($i = 0; $i < $length; $i ++) {
        $token = $token . $string[rand(0, $string_length - 1)];
    }	

	return $token;
}

//determine if partcipant table exists	
function ls2wp_participant_table_exists($survey_id){
	
	global $lsdb;
	
	$table_name = $lsdb->prefix.'tokens_'.$survey_id;	

	if($lsdb->get_var( $lsdb->prepare( "SHOW TABLES LIKE %s", $table_name )) == $table_name) return $table_name;
	else return false; 
}

//determine if response table exists	
function ls2wp_response_table_exists($survey_id){
	
	global $lsdb;
	
	$table_name = $lsdb->prefix.'survey_'.$survey_id;	

	if($lsdb->get_var( $lsdb->prepare( "SHOW TABLES LIKE %s", $table_name )) == $table_name) return $table_name;
	else return false; 
}