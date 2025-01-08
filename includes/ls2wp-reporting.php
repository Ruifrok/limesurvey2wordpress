<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//Array with all column labels in a table for a question group. Key is q_code; value is question
function ls2wp_labels_question_group($response, $group){

	//if(!is_array($response)) return false;
	
	$labels = array();

	$survey_id = $response['survey_id'];
	
	$wp_answer_values = get_option($response['survey_id'].'_answer_values');

	foreach($response as $q_code => $data){
		
		//skip general data
		if(!is_array($data)) continue;		
		
		if($group != $data['gid'] && $group != $data['group_name'])continue;
		
		if(empty($n_subq[$data['title']])) $n_subq[$data['title']] = 0;		
		$n_subq[$data['title']] = $n_subq[$data['title']] + 1;
			
		$labels[$data['title']] = $data['question'];
		
		if(!empty($wp_answer_values[$data['title']]['default'])) $labels[$data['title']] = $labels[$data['title']].'(default='.$wp_answer_values[$data['title']]['default'].')';
		
		$labels[$q_code] = $data['subquestion'];		
	}
	//If only one subquestion is present, remove the label for the main question
	foreach($n_subq as $k => $n){
		
		if($n <= 1) unset($labels[$k]);
	}

	ksort($labels);
			
	//Filter to change the array with labels
	$labels = apply_filters('ls2wp_table_labels', $labels, $response, $group);

	return $labels;
}

//Array with all values in single reponse table row. Key is q_code; value is an array(title, type, anwer, value)
function ls2wp_data_q_group($response, $group){
	
	$group_data = array();

	$survey_id = $response['survey_id'];

	$answer_values = get_option($survey_id.'_answer_values');
	
	$labels = ls2wp_labels_question_group($response, $group);
	
	foreach($labels as $q_code => $label){
		
		if(empty($response[$q_code])){
			foreach($response as $code => $data){
				
				if(!is_array($data)) continue;	
				
				if(empty($group_data[$q_code])){
					
					$group_data[$q_code] = array(
						'value' => '',
						'has_answer' => '',
					);	
				}
				
				if(strstr($code, $q_code) && $data['type'] == 'M'){
					
					if(!empty($answer_values[$q_code]['default'])) $default = $answer_values[$q_code]['default'];
					else $default = 0;
					
					if(empty($group_data[$q_code]['value'])) $group_data[$q_code]['value'] = $default;	
					if(!empty($data['answer']) && !$group_data[$q_code]['has_answer']) $group_data[$q_code]['has_answer'] = true;
					
					$group_data[$q_code]['value'] = $group_data[$q_code]['value'] + (float)$data['value'];
				}								
			}			
			
		} else {
		
			$group_data[$q_code] = array(
				'title' => $response[$q_code]['title'],
				'type' => $response[$q_code]['type'],
				'answer' => $response[$q_code]['answer'], 
				'value' => $response[$q_code]['value'],
			);
		}
	}

	//Filter to change the array with table data for a single survey participant
	$group_data = apply_filters('ls2wp_response_table_data', $group_data, $response, $group);
	
	return $group_data;	
}

//Make a table with single response question group results
function ls2wp_make_resp_grptable($survey_ids, $group, $email){
	
	if(!is_array($survey_ids)) $survey_ids = (array)$survey_ids;
	
	foreach($survey_ids as $k => $survey_id){		
		
		$use_rpc = get_option('use_rpc');
		
		if($use_rpc){
		
		
		
		} else {
			if(!ls2wp_response_table_exists($survey_id)) return 'No response table found for survey '.$survey_id.'!!';
		}			
	}	
	
	foreach($survey_ids as $survey_id){
	
		$s_data = array();
		
		$response = ls2wp_get_participant_response($survey_id, $email);
				
		if(empty($q_labels)){
			$q_labels = ls2wp_labels_question_group($response, $group);
			
			$labels['survey'] = 'Survey';		

			$labels = array_merge($labels, $q_labels);
			
		}			

		$s_data = ls2wp_data_q_group($response, $group);

		$title['survey_title']['value'] = $response['survey_title'];		
		
		$s_data = array_merge($title, $s_data);
		
		$data[$survey_id] = $s_data;
	}
	
		if(is_numeric($group)) $group_name = ls2wp_get_group_name($group, $response['survey_id']);
		else $group_name = $group;
	

	ob_start();		
	?>
	<h2><?php echo esc_html($group_name);?></h2>
	<div class="ls2wp-table-wrapper">
		<table class="group-response-table">
			<thead>
				<tr>
					<?php
					foreach($labels as $label){ ?>
					
						<th style="max-width:200px;"><?php echo esc_html($label);?></th>					
						
					<?php }?>
					
				</tr>
			</thead>
			<tbody>
			<?php
			foreach($data as $s_data){
			?>
				<tr>
					<?php
					foreach($s_data as $cel){
						if(empty($cel['answer'])){?>
						
							<td><?php echo esc_html($cel['value']); ?></td>
							
						<?php 
						} else {
		
							?>
						
							<td><?php echo esc_html($cel['answer'].'('.$cel['value'].')'); ?></td>
						
						<?php }
					}?>
				</tr>
			<?php } ?>
			</tbody>
		</table>
	</div>
	<?php

	return ob_get_clean();	
}


add_shortcode('ls2wpresptable', 'ls2wp_response_table');
	function ls2wp_response_table($atts = [] ){
		$tab_atts = shortcode_atts(
			array(
				'surveyids' => '',
				'groupname' => '',
				'email' => '',
			), $atts
		);

		$survey_ids = explode(',', str_replace(' ', '', $tab_atts['surveyids']));
		
		return ls2wp_make_resp_grptable($survey_ids, $tab_atts['groupname'], $tab_atts['email']);

	}

//Array with all values in survey table row. Key is q_code; value is array(n_answer, value_sum, answer)
function ls2wp_data_survey($responses, $group){
	
	$survey_data = array();
	
	$survey_data['n_responses'] = 0;

	foreach($responses as $response){
		
		//Only complete responses
		if(empty($response['submitdate'])) continue;
		
		$survey_data['n_responses']++;
		
		$resp_data = ls2wp_data_q_group($response, $group);

		foreach($resp_data as $q_code => $data){

			if(empty($survey_data[$q_code]['n_answer'])) $survey_data[$q_code]['n_answer'] = 0;
			if(empty($survey_data[$q_code]['value_sum'])) $survey_data[$q_code]['value_sum'] = 0;
			
			if(!empty($resp_data[$q_code]['answer']) || !empty($resp_data[$q_code]['has_answer'])){
				$survey_data[$q_code]['n_answer'] = $survey_data[$q_code]['n_answer'] + 1;				
			}			

			if(!empty($data['type']) && $data['type'] == 'M'){

				if(empty($survey_data[$q_code]['answer'])) $survey_data[$q_code]['answer'] = '';
				
				if(!empty($data['answer']) && strstr($q_code, 'other')) $survey_data[$q_code]['answer'] = $survey_data[$q_code]['answer'].$data['answer'].', ';
				elseif(!empty($data['answer']) && empty($survey_data[$q_code]['answer'])) $survey_data[$q_code]['answer'] = $data['answer'];				
			}
			
			$survey_data[$q_code]['value_sum'] = $survey_data[$q_code]['value_sum'] + (float)$resp_data[$q_code]['value'];
			
		}
		
	}	
		
	return $survey_data;	
}

//Make a table with survey question group results
function ls2wp_make_survey_grptable($survey_ids, $group){
	
	if(!is_array($survey_ids)) $survey_ids = (array)$survey_ids;
	
	$use_rpc = get_option('use_rpc');
	
	foreach($survey_ids as $k => $survey_id){
		
		if($use_rpc){
			
		} else {
		
			if(!ls2wp_response_table_exists($survey_id)) return 'No response table found for survey '.$survey_id.'!!';
		}			
	}	
	
	foreach($survey_ids as $survey_id){
		
		$s_data = array();
		
		$responses = ls2wp_get_responses_survey($survey_id);
	
		if(empty($q_labels)){
			$q_labels = ls2wp_labels_question_group($responses[0], $group);
			
			$labels['survey'] = 'Survey';		

			$labels = array_merge($labels, $q_labels);

		}
		
		if(is_numeric($group)) $group_name = ls2wp_get_group_name($group, $responses[0]['survey_id']);
		else $group_name = $group;
		
		$data = ls2wp_data_survey($responses, $group);
		
		$s_data[] = $responses[0]['survey_title'].'</br>n='. $data['n_responses'];
			
		foreach($data as $cel){
			
			if(!is_array($cel)) continue;
			
			if(empty($cel['answer'])){
				if($cel['n_answer']) $s_data[] = number_format(($cel['value_sum'] / $cel['n_answer']), 1);
				else $s_data[] = '';
			}else $s_data[] = $cel['answer'].'('.$cel['n_answer'].')';
		}
		
		$table_data[$survey_id] = $s_data;
		
	}

	
	ob_start();		
	?>
	<h2><?php echo esc_html($group_name);?></h2>
	<div class="ls2wp-table-wrapper">
		<table class="group-response-table">
			<thead>
				<tr>
					<?php

					foreach($labels as $label){ ?>
					
						<th style="max-width:200px;"><?php echo esc_html($label);?></th>					
						
					<?php }?>
					
				</tr>
			</thead>
			<tbody>
			<?php
				foreach($table_data as $survey_id => $survey_data){ ?>
				
					<tr>
						<?php
				
						foreach($survey_data as $cel){ ?>
							
							<td><?php echo wp_kses_post($cel); ?></td>		
						
						<?php }?>
					</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>

	<?php

	return ob_get_clean();	
}

add_shortcode('ls2wpsurveytable', 'ls2wp_survey_table');
	function ls2wp_survey_table($atts = [] ){
		$tab_atts = shortcode_atts(
			array(
				'surveyids' => '',
				'groupname' => '',
			), $atts
		);

		$survey_ids = explode(',', str_replace(' ', '', $tab_atts['surveyids']));
		
		return ls2wp_make_survey_grptable($survey_ids, $tab_atts['groupname']);
	}
	
//Make an array for the labels of a barchart
function ls2wp_labels_question($survey_id, $question_title){	

	$qav = ls2wp_question_answer_values($survey_id);
	
	$labels = array();

	foreach($qav['groups'] as $group){
		
		foreach($group['questions'] as $q_data){
		
			if($q_data['title'] != $question_title) continue;

			if($q_data['type'] == 'M'){
				foreach($q_data['sub_questions'] as $sub_question){
					
					$labels[$sub_question['title']]	= $sub_question['question'];
					
				}
				//move label 'other' to end of array
				$key1 = array_key_first($labels);
				if(strstr($key1, 'other')){
					$other = $labels[$key1];
					unset($labels[$key1]);
					$labels[$key1] = $other;
				}				
			} elseif($q_data['type'] == 'A'){
				$labels = [1,2,3,4,5];
			} elseif($q_data['type'] == 'B'){
				$labels = [1,2,3,4,5,6,7,8,9,10];
			}				
			else $labels = array_column($q_data['answers'], 'answer');			
		}
	}
	return $labels;
}

//Add data to the tabel labels
function ls2wp_chart_data_survey($responses, $q_code){
	
	$survey_id = $responses[0]['survey_id'];
	
	$q_titles = array_unique(array_column($responses[0], 'title'));

	if(isset($responses[0][$q_code])) $question_title = $responses[0][$q_code]['title'];
	elseif(in_array($q_code, $q_titles)) $question_title = $q_code;
	else $question_title = false;
		
	$labels = ls2wp_labels_question($survey_id, $question_title);

	$mchart_data['data_points'] = array_fill_keys($labels, 0);
	$mchart_data['n_responses'] = 0;

	foreach($responses as $response){
		
		//Only complete responses and responses where q_code exists
		if(empty($response['submitdate'])) continue;
		
		foreach($response as $code => $q_data){
		
			if(!is_array($q_data) || $q_data['title'] != $question_title) continue;
			
			if($q_data['type'] == 'M' && !empty($q_data['answer_code'])){
			
				$mchart_data['data_points'][$q_data['subquestion']] = $mchart_data['data_points'][$q_data['subquestion']] + 1;
				$mchart_data['question'] = $q_data['question'];
			} elseif(!empty($response[$q_code]['answer']) && $code == $q_code){				
				$mchart_data['data_points'][$response[$q_code]['answer']] = $mchart_data['data_points'][$response[$q_code]['answer']] + 1;
				$mchart_data['question'] = $response[$q_code]['subquestion'];
			} elseif(!isset($responses[0][$q_code]) && !in_array($q_code, $q_titles)) $mchart_data['question'] = $q_code.' not found';
		
			
		}
		$mchart_data['n_responses']++;
	}
	
	if(!$question_title) $mchart_data['question'] = $q_code.' not found';

	return $mchart_data;	
}

//Make an input array for google graphs bar or column chart
function ls2wp_g_graphs_data($survey_ids, $q_code){

		if(!is_array($survey_ids)) $survey_ids = (array)$survey_ids;

		$g_data = array();

		$use_rpc = get_option('use_rpc');
	
		foreach($survey_ids as $survey_id){
			
			if($use_rpc){
				
			} else {
			
				if(!ls2wp_response_table_exists($survey_id)) continue;
			}
				
			$responses = ls2wp_get_responses_survey($survey_id);
		
			if(empty($responses)) continue;
	
			$mchart_data = ls2wp_chart_data_survey($responses, $q_code);		
				
			$points = $mchart_data['data_points'];
			if(empty($points)) $points = [0, 0];
	
			$question = $mchart_data['question'];

			if(empty($g_data)){
		
				foreach($points as $answer => $n_answer ){			
				
					$g_data['data'][] = [$answer, $n_answer];
				
				}
			} else {
				
				$n_answers = array_values($points);

				foreach($n_answers as $k => $n_answer){
					
					$g_data['data'][$k][] = $n_answer;
					
				}
			}			
		}
		
		if(empty($g_data['data'])) $g_data['data'] = array();
	
		$series[] = ['labels'];
		foreach($survey_ids as $survey_id){
			$survey = ls2wp_get_survey($survey_id);
				
			$series[0][] = $survey->surveyls_title;
		}		
		
		$g_data['data'] = array_merge($series, $g_data['data']);

		$g_data['question'] = $question;
		
		$g_data = apply_filters('ls2wp_google_charts_data', $g_data, $survey_ids, $q_code);
	
		return $g_data;		
	
}

//Make a google charts column chart(vertical) or bar chart(horizontal)
function ls2wp_google_column_chart($data, $title, $direction){

	$id = 'column-'.uniqid();

	ob_start();
	?>
	<script>
	google.charts.load("current", {packages:['corechart']});
	google.charts.setOnLoadCallback(drawChart);	

	function drawChart() {
		
		var chartData = <?php echo json_encode($data);?>;	
	
		var data = google.visualization.arrayToDataTable(chartData);

		var view = new google.visualization.DataView(data);

		var options = {
		vAxis: {title: "Number", format: '#'},
		hAxis: {title: "Assesment"},			
		<?php if($direction == 'bar'){?>
		hAxis: {title: "Number", format: '#'},
		vAxis: {title: ""},		
		<?php } ?>
		title: "<?php echo esc_html($title);?>",
		titleTextStyle:{fontSize: 15, color: '#535353'},
		//legend: {position: 'top', alignment: 'start', maxLines: 3},
		width: "100%",
		height: 300,
		backgroundColor:{fill: 'transparent'},
		chartArea: {width: "60%", height: "70%"},
		bar: {groupWidth: "70%"},
		colors: ['#1859f4', '#FF008A', '#6D339D', '#6e96f4', '#ff6bb9', '#9055C0' ],
		};
		
		<?php if($direction == 'column'){ ?>
		var chart = new google.visualization.ColumnChart(document.getElementById("<?php echo esc_html($id);?>"));
		<?php } elseif($direction == 'bar'){?>
		var chart = new google.visualization.BarChart(document.getElementById("<?php echo esc_html($id);?>"));
		<?php } ?>
		
		chart.draw(view, options);
	}
	</script>
	

	<div id="<?php echo esc_html($id);?>" ></div>
	
	<?php
	
	return ob_get_clean();
}

//Shortcode adds a google chats bar chart
//Because wordpress doesnot accept squere brackets in shortcode attrubutes, square brackets in a questioncode have to be replaced with curly braces.
add_shortcode('ls2wpgooglecolumnchart', 'ls2wp_google_chart');
	function ls2wp_google_chart( $atts){
		$chart_atts = shortcode_atts(
			array(
				'surveyids' => '',
				'questioncode' => '',
				'direction' => 'column',
			), $atts
		);
		
		$chart_atts['questioncode'] = str_replace('{', '[', $chart_atts['questioncode']);
		$chart_atts['questioncode'] = str_replace('}', ']', $chart_atts['questioncode']);
		
		$survey_ids = explode(',', str_replace(' ', '', $chart_atts['surveyids']));
		
		$use_rpc = get_option('use_rpc');
		
		foreach($survey_ids as $k => $survey_id){
			
			if($use_rpc){
				
			} else {
			
				if(!ls2wp_response_table_exists($survey_id)) unset($survey_ids[$k]);			
			}
		}
	
		$g_data = ls2wp_g_graphs_data($survey_ids, $chart_atts['questioncode']);
	
		$data = $g_data['data'];
	
		$title = $g_data['question'];

		return ls2wp_google_column_chart($data, $title, $chart_atts['direction']);
	}


