<?php
if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

//create additional metabox in m-chart post type to give survey_ids and questioncode for the chart
add_action('add_meta_boxes_m-chart', 'ls2wp_m_chart_metabox');
	function ls2wp_m_chart_metabox(){
		
		add_meta_box('ls2wp', 'Ls2wp data', 'ls2wp_m_chart_data_metabox', 'm-chart', 'normal', 'core');			
		
	}
//Save the data from the metabox
add_action('save_post', 'save_ls2wp_m_chart_meta');
	function save_ls2wp_m_chart_meta($post_id){
		
		$post_type = get_post_type($post_id);		
		if($post_type != 'm-chart')	return;
		
		if(isset($_POST['_ls2wp-nonce']) && !wp_verify_nonce($_POST['_ls2wp-nonce'], 'save ls2wp m-chart data')) wp_die('not authorised');
		
		$survey_ids = sanitize_text_field($_POST['ls2wp-survey-ids']);
		$q_code = sanitize_text_field($_POST['ls2wp-question-code']);
	
		update_post_meta($post_id, 'ls2wp_survey_ids', $survey_ids);
		update_post_meta($post_id, 'ls2wp_question_code', $q_code);
	}

//HTML forr the metabox
function ls2wp_m_chart_data_metabox($post){
	
	wp_nonce_field('save ls2wp m-chart data', '_ls2wp-nonce'); ?>

	  <p>
		<label for="ls2wp-survey-ids"><?php esc_html_e( 'Add a survey id or a comma seperated list of survey ids.', 'ls2wp'); ?></label>
		<br />
		<input class="widefat" type="text" name="ls2wp-survey-ids" id="ls2wp-survey-ids" value="<?php echo esc_attr( get_post_meta( $post->ID, 'ls2wp_survey_ids', true )); ?>" />
	  </p>
	  <p>
		<label for="ls2wp-question_code"><?php esc_html_e( 'The question code(title) of the question you want to display', 'ls2wp'); ?></label>
		<br />
		<input class="widefat" type="text" name="ls2wp-question-code" id="ls2wp-question-code" value="<?php echo esc_attr( get_post_meta( $post->ID, 'ls2wp_question_code', true )); ?>" size="30"/>
	  </p>	  
	  
	<?php 
}

//Add the surveydata to the m-chart chart_args
add_filter('m_chart_chart_args', 'ls2wp_m_chart', 20, 4);
	function ls2wp_m_chart($chart_args, $chart, $chart_meta, $args){		

			$survey_ids_str = get_post_meta($chart->ID, 'ls2wp_survey_ids', true);
			
			$survey_ids = explode(',', str_replace(' ', '', $survey_ids_str));

			$q_code = get_post_meta($chart->ID, 'ls2wp_question_code', true);
		
			$use_rpc = get_option('use_rpc');
			
			$key = 0;
			
			foreach($survey_ids as $survey_id){
				
				if($use_rpc){
					
					
				} else {
				
					if(!ls2wp_response_table_exists($survey_id)) continue;
				}
			
				$responses = ls2wp_get_responses_survey($survey_id);
	
				if(empty($responses)) continue;
			
				$mchart_data = ls2wp_chart_data_survey($responses, $q_code);
		
				$labels = array_keys($mchart_data['data_points']);
				
				$chart_args['options']['plugins']['title']['text'] = $mchart_data['question'];
				
				$chart_args['data']['labels'] = $labels;
				
				$chart_args['data']['datasets'][$key]['label'] = $responses[0]['survey_title'];
				$chart_args['data']['datasets'][$key]['data'] = array_values($mchart_data['data_points']);
			
				$key++;					
			}
		
		$chart_args['options']['scales']['x']['ticks']['precision'] = 0;
		$chart_args['options']['scales']['y']['ticks']['precision'] = 0;
		$chart_args['options']['scales']['x']['ticks']['grace'] = 1;
		$chart_args['options']['scales']['y']['ticks']['grace'] = 1;
	
		return $chart_args;
	}
