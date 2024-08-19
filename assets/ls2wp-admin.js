jQuery( document ).ready(function($) {

	
	if($($('#use_rpc')).is(':checked')){
		$('.rpc-credentials').show();
		$('.lsdb-credentials').hide();
	} else {
		$('.rpc-credentials').hide();
	}	
	
	$('#use_rpc').on('click',function(){
		
		$('.lsdb-credentials').toggle();
		$('.rpc-credentials').toggle();
	});

	var test = $('#select-import-survey').parent();
	
	//console.log(test);
	
	$('form.import-form').on('click', '#select-import-survey', function(){
		
		$('.import-form').submit(function(event){
			event.preventDefault();
		});	
		
		$('.ajax-response').remove();

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

				$('.import-form').append(response);

			}
		});	
		
	});
	
});