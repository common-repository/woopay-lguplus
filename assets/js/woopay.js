var testmode = woopay_string.testmode;
var checkoutURL = woopay_string.checkoutURL;
var responseURL = woopay_string.responseURL;
var payForm = document.LGD_PAYINFO;

var LGD_window_type = document.getElementById( 'CST_WINDOW_TYPE' ).value;

function payment_return( LGD_RESPCODE, LGD_RESPMSG, LGD_PAYKEY ) {
	document.getElementById( 'LGD_RESPCODE' ).value = LGD_RESPCODE;
	document.getElementById( 'LGD_RESPMSG' ).value = LGD_RESPMSG;
	document.getElementById( 'LGD_PAYKEY' ).value = LGD_PAYKEY;

	if ( LGD_window_type == 'iframe' ) {
		jQuery( '#LGD_PAYMENTWINDOW' ).dialog( 'close' );
	}

	if ( LGD_RESPCODE == 'S053' ) {
		alert( woopay_string.cancel_msg );
	}

	payForm.action = responseURL;
	payForm.target = '_self';
	payForm.submit();
}

function returnToCheckout() {
	payForm.action = checkoutURL;
	payForm.submit();
}

function getFormObject() {
	return document.getElementById( 'LGD_PAYINFO' );
}

function launchCrossPlatform() {
	lgdwin = open_paymentwindow( document.getElementById( 'LGD_PAYINFO' ), document.getElementById( 'CST_PLATFORM' ).value, LGD_window_type );
}

function startWooPay() {
	if ( testmode ) {
		if ( ! confirm( woopay_string.testmode_msg ) ) {
			payForm.action = checkoutURL;
			payForm.submit();
			return false;
		}
	}

	if ( LGD_window_type == 'iframe' ) {
		doIframe();
	} else {
		setEscrowData();
	}
}

function setEscrowData() {
	if ( document.getElementById( 'LGD_ESCROW_USEYN' ).value == 'Y' ) {
		var chr30 = String.fromCharCode(30);
		var chr31 = String.fromCharCode(31);
		var goodListArray = new Array();
		var goodListItem = new Array();

		goodList = document.getElementById('LGD_PAYINFO').LGD_GOODINFO.value;
		var goodListArray = goodList.split( chr30 );

		for ( i = 0; i < goodListArray.length; i++ ) {
			goodListItem = goodListArray[i].split( chr31 );
			jQuery( '#LGD_PAYINFO' ).append( '<input type=\"hidden\" id=\"' + goodListItem[0] + '\" name=\"' + goodListItem[0] + '\" value=\"' + goodListItem[1] + '\"> ');
		}
	}

	setTimeout( 'launchCrossPlatform();', 500 );
}

jQuery( document ).ready(function() {
	setTimeout( 'startWooPay();', 500 );
});