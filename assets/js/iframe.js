var isMobile = woopay_string.is_mobile;
var scrolltop = 0;

function fluidDialog() {
    var $visible = jQuery( '.ui-dialog:visible' );

    $visible.each( function () {
        var $this = jQuery( this );
        var dialog = $this.find( '.ui-dialog-content' ).data( 'ui-dialog' );

		if ( dialog.options.fluid ) {
            var wWidth = jQuery( window ).width();
            var hHeight = jQuery( window ).height();
			var tHeight = parseInt( jQuery( '.ui-dialog-titlebar' ).height() ) + 20;

			if ( wWidth < ( parseInt( dialog.options.maxWidth ) + 200 ) || hHeight < ( parseInt( dialog.options.maxHeight ) ) ) {
                $this.css( 'max-width', '100%' );
				$this.css( 'max-height', '100%' );
				$this.css( 'width', '100%' );
				$this.css( 'height', '100%' );

				jQuery( '#LGD_PAYMENTWINDOW' ).css( 'height', hHeight - tHeight + 'px' );
				jQuery( '#LGD_PAYMENTWINDOW_IFRAME' ).css( 'height', hHeight - tHeight + 'px' );
				jQuery( '#wpadminbar' ).css( 'display', 'none' );

				//jQuery( 'html' ).addClass( 'p8-change-height' );
				//jQuery( 'body' ).addClass( 'p8-change-height' );
            } else {
				$this.css( 'height', dialog.options.maxHeight + 'px' );
				$this.css( 'width', dialog.options.maxWidth + 'px' );
                $this.css( 'max-width', dialog.options.maxWidth + 'px' );

				jQuery( '#LGD_PAYMENTWINDOW' ).css( 'height', dialog.options.maxHeight - tHeight + 'px' );
				jQuery( '#LGD_PAYMENTWINDOW_IFRAME' ).css( 'height',  dialog.options.maxHeight - tHeight + 'px' );
				jQuery( '#wpadminbar' ).css( 'display', '' );

				//jQuery( 'html' ).removeClass( 'p8-change-height' );
				//jQuery( 'body' ).removeClass( 'p8-change-height' );
            }
            dialog.option( 'position', dialog.options.position );
        }
    });
}

jQuery( window ).resize( function() {
	fluidDialog();
});

jQuery( document ).on( 'dialogopen', '.ui-dialog', function ( event, ui ) {
	jQuery( 'html' ).addClass( 'p8-hide-scrollbar' );
	jQuery( 'body' ).addClass( 'p8-hide-scrollbar' );

	jQuery( 'head' ).append( '<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1">' );

	fluidDialog();
});

jQuery( document ).on( 'dialogclose', function ( event, ui ) {
	jQuery( '#wpadminbar' ).css( 'display', '' );

	if ( jQuery( '#LGD_RESPCODE' ).val() == '' ) {
		payment_return( 'S053', '', '' );
	}

	jQuery( 'html' ).removeClass( 'p8-hide-scrollbar' );
	jQuery( 'body' ).removeClass( 'p8-hide-scrollbar' );

	jQuery( 'html' ).removeClass( 'p8-change-height' );
	jQuery( 'body' ).removeClass( 'p8-change-height' );
});

function doIframe() {
	jQuery( 'body' ).prepend( '<div id="LGD_PAYMENTWINDOW" name="LGD_PAYMENTWINDOW" style="display:none;"><iframe id="LGD_PAYMENTWINDOW_IFRAME" name="LGD_PAYMENTWINDOW_IFRAME" width="100%" height="100%" frameborder="0"></iframe></div>' );

	jQuery( '#LGD_PAYMENTWINDOW' ).dialog({
		autoOpen: false,
		autoResize: true,
		modal: true,
		fluid: true,
		resizable: false,
		draggable: false,
		height: 695,
		maxHeight: 695,
		width: 'auto',
		maxWidth: 680,
		title: woopay_string.lguplus_payment,
	});

	jQuery( '#LGD_PAYMENTWINDOW' ).dialog( 'open' );
	jQuery( '#LGD_PAYMENTWINDOW' ).focus();
	jQuery( '.ui-dialog-titlebar-close' ).blur();

	jQuery( 'iframe' ).wrap( function() {
		var jQuerythis = jQuery( this );

		return jQuery('<div></div>').css({
			width: jQuery( this ).attr( 'width' ),
			height: jQuery( this ).attr( 'height' ),
			'overflow-y': 'hidden',
			'-webkit-overflow-scrolling': 'touch'
		});
	});

	setEscrowData();
}