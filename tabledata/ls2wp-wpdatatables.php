<?php

include_once('../../../../wp-blog-header.php');

header("HTTP/1.1 200 OK"); // Forcing the 200 OK header as WP can return 404 otherwise




	$surveys = ls2wp_db_get_surveys();
	
	foreach($surveys as $survey){
		
		$table_data[] = get_object_vars($survey);
		
	}

	$data = serialize($table_data);

	echo $data;
	
