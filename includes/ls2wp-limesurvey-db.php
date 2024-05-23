<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//Nieuw wpdb object verbonden met limesurvey database
add_action ('init','ls2wp_limesurvey_db', 1);
	function ls2wp_limesurvey_db() {
		global $lsdb;

		//$lsdb = new wpdb(LSDB_USER, LSDB_PASSWORD, LSDB_NAME, LSDB_HOST);		
		
		$lsdb_user = get_option('lsdb_user');
		$lsdb_passw = get_option('lsdb_passw');
		$lsdb_name = get_option('lsdb_name');
		$lsdb_host = get_option('lsdb_host');
		
		$lsdb = new wpdb($lsdb_user, $lsdb_passw, $lsdb_name, $lsdb_host);
		
		$lsdb_prefix = get_option('lsdb_prefix');
		$lsdb->prefix = $lsdb_prefix;
	
	}

//Lijst met survey id en naam van beschikbare surveys
function ls2wp_db_get_surveys() {
	global $lsdb;
	//alle surveys in de database

	$sql = $lsdb->prepare("
		SELECT sid, survey{$lsdb->prefix}title, startdate, expires, active, gsid, survey{$lsdb->prefix}language AS language, datecreated, tokenlength
		FROM {$lsdb->prefix}surveys
		JOIN {$lsdb->prefix}surveys_languagesettings ON {$lsdb->prefix}surveys_languagesettings.survey{$lsdb->prefix}survey_id = {$lsdb->prefix}surveys.sid
		ORDER BY datecreated	
	");
	
	$surveys = $lsdb->get_results($sql);
	
	return $surveys;	
}

function ls2wp_db_get_survey($survey_id) {
	global $lsdb;
	
	$sql = $lsdb->prepare("
		SELECT sid, gsid, survey{$lsdb->prefix}language AS language, survey{$lsdb->prefix}title, startdate, expires, active, datecreated, tokenlength
		FROM {$lsdb->prefix}surveys
		JOIN {$lsdb->prefix}surveys_languagesettings ON {$lsdb->prefix}surveys_languagesettings.survey{$lsdb->prefix}survey_id = {$lsdb->prefix}surveys.sid
		WHERE sid = %d 
	",$survey_id);
	
	$survey = $lsdb->get_results($sql);
	
	if(empty($survey)){
		return false;
	}else return $survey[0];	
}

function ls2wp_db_get_survey_groups(){
	global $lsdb;
	
	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}surveys_groups	
	");
	
	$survey_groups = $lsdb->get_results($sql);
	
	if(empty($survey_groups)){
		return false;
	} else return $survey_groups;	
}

//Zoek survey_ids en tokens bij email
function ls2wp_get_email_surveys_tokens($email, $args) {	
	
	global $lsdb;
	
	if(empty($email)) return false;
		
	$surveys = ls2wp_db_get_surveys();
	
	$surveys = ls2wp_filter_surveys($surveys, $args);
	
	$results = array();
	
	foreach($surveys as $survey) {
		
		$survey_id = $survey->sid;
		
		if(!empty($group_id) && $survey['gsid'] != $group_id) continue;

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

//Zoek survey_id bij token
function ls2wp_get_token_survey_id($token) {
	global $lsdb;
	
	$surveys = ls2wp_db_get_surveys();
	
	foreach($surveys as $survey) {
		$survey_id = $survey['sid'];
		
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

//Alle responsen uit een survey
function ls2wp_db_get_responses_survey($survey_id){
	global $lsdb;
	
	$questions = ls2wp_db_get_questions($survey_id);		

	$qids = array_column($questions, 'qid');

	$answers = ls2wp_db_get_answers($qids);		
	
	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}survey_%d
	", $survey_id);	

	$rows = $lsdb->get_results($sql);
	
	
	if($rows){
		foreach($rows as $row){
		
			$response = ls2wp_translate_sgq_code($row, $questions, $answers);
			$response = ls2wp_add_wp_answer_values($response);
			
			$responses[] = $response;
		}	
	} else return false;
	
	return $responses;
	
}

//Alle responsen uit surveys van gegeven wp-user
function ls2wp_db_get_user_responses($user, $args=array()) {
	global $lsdb;
	
	$default = array(
		'survey_group_id' 	=> '',
		'all_surveys'		=> false,
	);
	
	$args = wp_parse_args($args, $default);
	
	$email = $user->user_email;

	$surveys = ls2wp_get_email_surveys_tokens($email, $args);
	
	$responses = array();
	
	foreach($surveys as $survey_id => $tokens) {
		
		//Alle vragen in deze survey
		$questions = ls2wp_db_get_questions($survey_id);		

		$qids = array_column($questions, 'qid');

		$answers = ls2wp_db_get_answers($qids);			
	
		foreach($tokens as $token){
		
			$sql = $lsdb->prepare("
				SELECT *
				FROM {$lsdb->prefix}survey_%d
				WHERE token = %s
			", $survey_id, $token);

			$result = $lsdb->get_results($sql);			
		
			if(!empty($result[0])){
				$response = ls2wp_translate_sgq_code ($result[0], $questions, $answers);
				
				$response = ls2wp_add_wp_answer_values($response);
			
				$responses[] = $response;
			}
		}		
	}	

	return $responses;
}

//Alle responsen uit surveys met gegeven token
/* function ls2wp_get_responses($survey_ids, $tokens) {
	global $lsdb;
	
	$tokens = (array)$tokens;
	$token_str = implode("','" , $tokens);
	
	foreach($survey_ids as $survey_id) {
		
		$sql = $lsdb->prepare("
			SELECT *
			FROM {$lsdb->prefix}survey_%d
			WHERE token IN ("%s")		
		", $survey_id, $token_str);	
		
		$sql = stripslashes($sql);

		$responses = $lsdb->get_results($sql);
		
		//Alle vragen in deze survey
		$questions = ls2wp_db_get_questions($survey_id);		
	
		$qids = array_column($questions, 'qid');
		
		$answers = ls2wp_db_get_answers($qids);	

		foreach($responses as $response) {
			
			$response_nw = ls2wp_translate_sgq_code ($response, $questions, $answers);
			$responses_nw[] = $response_nw;			
		}	
	}	
	return $responses_nw;
} */

//Alle vragen met antwoorden en beoordelingswaarde bij een token
/* function ls2wp_get_response($token, $survey_id) {
	global $lsdb;
	
	$sql = $lsdb->prepare("
		SELECT *
		From {$lsdb->prefix}survey_%d
		WHERE token = %s
	", $survey_id, $token);
	
	$response = $lsdb->get_row($sql);

	if(!isset($response)) return array();

	//Alle vragen in deze survey
	$questions = ls2wp_db_get_questions($survey_id);

	$qids = array_column($questions, 'qid');

	$answers = ls2wp_db_get_answers($qids);	

	$response_nw = ls2wp_translate_sgq_code ($response, $questions, $answers);
	
	return $response_nw;
} */

//Zet array met sgqa-code voor elke vraag om in array met als key de vraag-code en als value een sub-array met vraagkenmerken.
//Voor sgqa-code zie https://manual.limesurvey.org/SGQA_identifier/nl en https://manual.limesurvey.org/Question_object_types
function ls2wp_translate_sgq_code ($response, $questions, $answers) {

	$survey_id  = reset($questions)->sid;	

	$survey = ls2wp_db_get_survey($survey_id);

	$participant = ls2wp_db_get_participant_by_token($survey_id, $response->token);
	
	$response_nw = new stdClass();

	$response_nw->survey_id = $survey_id;
	$response_nw->completed = $participant->completed;
	$response_nw->group_survey_id = $survey->gsid;
	$response_nw->survey_title = $survey->surveyls_title;
	$response_nw->datecreated = $survey->datecreated;
	$response_nw->survey_title = $survey->surveyls_title;
	

	//Antwoordcodes uitwerken
	foreach ($response as $key => $answer_code) {
		$answer = '';
		//$vraag = '';
		$value = '';		
		
		if($answer_code == 'Y') $answer = 'Ja';
		if($answer_code == 'N') $answer = 'Nee';
		if($answer_code == 'M') $answer = 'Man';
		if($answer_code == 'F') $answer = 'Vrouw';
		
		//splits SGQ in survey_id, group_id en question
		if(is_numeric(substr($key, 0, 2))) {
			$sgq = explode('X', $key);
			$group_id = $sgq[1];
			$qid = $sgq[2];
			
			$group_name = ls2wp_db_get_group_name($group_id, $survey_id);
			
			//questiontype Equation niet meenemen
			if($questions[$qid]->type == '*') continue;
	
			$question_aid = '';
			$question = '';
			$subquestion = '';
			
			//Zet key SGQ-code om in $key vraag-code en vraag
			//als parent_qid dan zijn er subvragen
			if($questions[$qid]->parent_qid != 0) {
				
				$parent_qid = $questions[$qid]->parent_qid;
				
				$question_title = $questions[$parent_qid]->title;
				$question_aid = $questions[$qid]->title;
				$question_code = $question_title.'['.$question_aid.']';
				$question_type = $questions[$parent_qid]->type;				
				$question = !empty($questions[$parent_qid]->question) ? $questions[$parent_qid]->question: ' ';
				$subquestion = !empty($questions[$qid]->question) ? $questions[$qid]->question: ' ';
				
				if(isset($answers[$parent_qid][$answer_code]['answer'])) $answer = $answers[$parent_qid][$answer_code]['answer'];				
				if(isset($answers[$parent_qid][$answer_code]['value'])) $value = $answers[$parent_qid][$answer_code]['value'];
				if(in_array($questions[$parent_qid]->type, ['A','B'])) $value = $answer_code;
				
			} else { //Geen subvragen
				
				$question_code = $questions[$qid]->title;
				$question_title = $questions[$qid]->title;
				$question = $questions[$qid]->question;
				$question_type = $questions[$qid]->type;
				
				if(isset($answers[$qid][$answer_code]['answer'])) $answer = $answers[$qid][$answer_code]['answer'];
				if(isset($answers[$qid][$answer_code]['value'])) $value = $answers[$qid][$answer_code]['value'];
				
				if($questions[$qid]->type == 'N') $value = intval($answer_code);
				
				
			}
		} else {
			$question_code = $key;			
		}
		
		if(empty($question)){
			$response_nw->$question_code = $answer_code;
		} else {
			
			$response_nw->$question_code['answer-code'] = $answer_code;

			if(empty($answer)) {				
				$response_nw->$question_code['answer'] = $answer_code;
			} else {
				$response_nw->$question_code['answer'] = $answer;
			}			
			if(isset($value)) {
				$response_nw->$question_code['value'] = $value;
			} else {
				$response_nw->$question_code['value'] = '';
			}

			$response_nw->$question_code['type'] = $question_type;
			$response_nw->$question_code['title'] = $question_title;
			$response_nw->$question_code['aid'] = $question_aid;
			if(isset($group_id)) $response_nw->$question_code['gid'] = $group_id;
			$response_nw->$question_code['group_name'] = $group_name;
			$response_nw->$question_code['question'] = $question;
			$response_nw->$question_code['subquestion'] = $subquestion;

		}			
	}
	return $response_nw;
}

//Maak array met alle tokens en survey_id
/* function ls2wp_get_tokens() {
	global $lsdb;
	
	$surveys = ls2wp_db_get_surveys();
	$result = array();
	
	foreach($surveys as $survey) {
				
		$survey_id = $survey['sid'];	
		
		$table_name = "{$lsdb->prefix}tokens_{$survey_id}";
		if($lsdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name)continue;		

			$tokens = $lsdb->get_col("
				SELECT token
				FROM {$lsdb->prefix}tokens_{$survey_id}				
			");
		foreach($tokens as $token) {
			$result[$token] = $survey_id;
		}
	}
	return $result;
} */

//Zoek group_name bij groep_id (uit de vraagcode in LS) of bij gid(Limesurvey group_id) 
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

//Alle antwoorden en beoordelingswaarden bij question set (survey)
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
	
	//single quotes verwijderen
	$sql = str_replace("'", "", $sql);
	
	$answers = $lsdb->get_results($sql);

	foreach($answers as $answer) {
		$result[$answer->qid][$answer->code]['answer'] = $answer->answer;
		$result[$answer->qid][$answer->code]['value'] = $answer->assessment_value;
	}
	
	return $result;
}

//antwoord en beoordelingswaarde bij vraag/antwoordcode
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

//Alle vragen van een survey met vraagcode en vraag
//$sgqa: Als functie wordt gebruikt voor omzetten van de sgqa-code uit LS dient de sub-question vraagcode deel van de qid te zijn(zie https://manual.limesurvey.org/SGQA_identifier/nl) en https://manual.limesurvey.org/Question_object_types
function ls2wp_db_get_questions($survey_id, $sgqa=true) {
	global $lsdb;
	$results = array();
	
	$sql = $lsdb->prepare("
	SELECT {$lsdb->prefix}questions.qid, parent_qid, sid, gid, type, title, other, question
	FROM {$lsdb->prefix}questions
	JOIN {$lsdb->prefix}question_l10ns ON {$lsdb->prefix}questions.qid = {$lsdb->prefix}question_l10ns.qid
	WHERE sid = %d
	", $survey_id
	);
	$questions = $lsdb->get_results($sql, OBJECT_K);

	if($sgqa) {
	//De sub-question vraagcode deel van de qid
		foreach($questions as $key => $question){			
			
			if($question->parent_qid == 0) {
				$qcode = $question->qid;
				//vraag toevoegen als de keuze 'anders' met tekstveld wordt getoond
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

//alle deelnamers aan een survey met response
function ls2wp_db_get_participants($survey_id, $name='') {
	global $lsdb;
	
	if(empty($survey_id)) return false;

	if(empty($name)) $name = '%';
	else $name = '%'.$name.'%';
	
	$sql = $lsdb->prepare("
		SELECT firstname, lastname, email, {$lsdb->prefix}tokens_%d.token, completed
		FROM {$lsdb->prefix}tokens_%d
		INNER JOIN {$lsdb->prefix}survey_%d	ON {$lsdb->prefix}tokens_%d.token = {$lsdb->prefix}survey_%d.token
		WHERE firstname LIKE %s OR lastname LIKE %s
	", $survey_id, $survey_id, $survey_id, $survey_id, $survey_id, $name, $name);	

	$participants = $lsdb->get_results($sql);
	
	return $participants;	
}

//Haal participant iut LS mbv token
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
//haal ls-participantgegevens op bij wp_gebruiker.
//$add_participant: Als geen participant, dan een aanmaken.
function ls2wp_db_get_participant($survey_id, $user, $add_participant = true){
	global $lsdb;
	
	if(empty($survey_id)) return false;

	$email = $user->user_email;
		
	$sql = $lsdb->prepare("
		SELECT *
		FROM {$lsdb->prefix}tokens_%d
		WHERE email = %s
	", $survey_id, $email);	
	
	$participant = $lsdb->get_row($sql);

	if(empty($participant) && $add_participant){

		$token = ls2wp_generate_token();
		
		$survey = ls2wp_db_get_survey($survey_id);
		
		$table_name = $lsdb->prefix.'tokens_'.$survey_id;
	
		$participant['firstname'] = $user->first_name;
		$participant['lastname'] = $user->last_name;
		$participant['email'] = $user->user_email;
		$participant['language'] = $survey->language;		
		$participant['token'] = $token;
		
		$success = $lsdb->insert($table_name, $participant);
		
		if($success) $participant = ls2wp_db_get_participant($survey_id, $user);
		
		else return 'Er kon geen enquète deelnemer worden aangemaakt';
	}	
	
	return (array)$participant;	
}




function ls2wp_generate_token($length=15 ){
	
	$bytes = random_bytes(ceil($length / 2));
	$token = substr(bin2hex($bytes), 1, $lenght);

	return $token;
}

//Nagaan of er al een testresultaat met dit token aanwezig is
/* function token_bestaat($token, $post_type) {
	$args = array(
		'posts_per_page'	=> -1,
		'post_type'		=> $post_type,
		'meta_key'		=> 'token. Toegangscode',
		'meta_value'	=> $token
	);
	$clienten = get_posts( $args );

	if ($clienten) $token_bestaat = $clienten[0]->ID;
	else $token_bestaat = false;

	wp_reset_query();

return $token_bestaat;	
	
} */