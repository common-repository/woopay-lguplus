function deleteLog(msg, url, file) {
	var confirm_delete = confirm(msg);

	if (confirm_delete) {
		var params = "file="+file;

		jQuery.ajax({
			type: 		'POST',
			url: 		url,
			data: 		params,
			success: 	function( code ) {
					try {
						var result = jQuery.parseJSON( code );

						if (result.result == 'success') {
							alert(result.message);
							location.reload();
						} else if (result.result == 'failure') {
							alert(result.message);
							location.reload();
						} else {
							throw "Invalid response";
						}
					} catch(err) {
						jQuery(document).prepend(code);
					}
				},
			dataType: 	"html"
		});
	}
}