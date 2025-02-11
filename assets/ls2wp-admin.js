jQuery( document ).ready(function($) {

	
	if($($('#use_rpc')).is(':checked')){
		$('.rpc-credentials').show();
		$('.lsdb-credentials').hide();
		$('.rpc-credentials .ls2wp-required input').prop('required', true);
		$('.lsdb-credentials .ls2wp-required input').prop('required', false);
		
	} else {
		$('.rpc-credentials').hide();
		$('.rpc-credentials .ls2wp-required input').prop('required', false);
		$('.lsdb-credentials .ls2wp-required input').prop('required', true);
	}	
	
	$('#use_rpc').on('click',function(){		
		
		if($($('#use_rpc')).is(':checked')){
			$('.rpc-credentials').show();
			$('.lsdb-credentials').hide();
			$('.rpc-credentials .ls2wp-required input').prop('required', true);
			$('.lsdb-credentials .ls2wp-required input').prop('required', false);
			
		} else {
			$('.rpc-credentials').hide();
			$('.lsdb-credentials').show();
			$('.rpc-credentials .ls2wp-required input').prop('required', false);
			$('.lsdb-credentials .ls2wp-required input').prop('required', true);
		}

	});

	// Import LS-data
	$('form.import-form').on('click', '#select-import-survey', function(){
		
		$('.import-form').submit(function(event){
			event.preventDefault();
		});	
		
		$('.import-ajax-response').remove();

		var surveyId = $('#survey').val();

		$.ajax({
			url : ls2wp.ajax_url,
			type : 'post',
			data : {
				action : 'import_survey_data',
				_ajax_nonce: ls2wp.nonce,
				survey_id:surveyId,				
			},
			success : function( response ) {

				$('.import-form').prepend(response);

			}
		});	
		
	});
	
	//Delete transients
	$('form.clear-transient-form').on('click', '#clear-transients', function(){
		
		$('.clear-transient-form').submit(function(event){
			event.preventDefault();
		});	
		
		$('.delete-transients-ajax-response').remove();

		var surveyId = $('#survey-transients').val();		

		$.ajax({
			url : ls2wp.ajax_url,
			type : 'post',
			data : {
				action : 'clear_survey_transients',
				_ajax_nonce: ls2wp.nonce,
				survey_id:surveyId,				
			},
			success : function( response ) {

				$('.clear-transient-form').prepend(response);

			}
		});	
		
	});	
	
	
});