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

	
	
});