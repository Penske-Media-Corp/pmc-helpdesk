pmc_helpdesk = {
	form_html: false,

	show_activity_view: function() {
		jQuery( '#pmc-helpdesk-background-activity-container' ).css( 'background-image', 'url("' + pmc_helpdesk_vars._activity_indicator_url + '")' );
		jQuery( '#pmc-helpdesk-background-activity-container' ).fadeIn( 'fast' );
	},

	hide_activity_view: function() {
		jQuery( '#pmc-helpdesk-background-activity-container' ).fadeOut( 'fast' );
	},

	store_form_html: function() {
		pmc_helpdesk.form_html = jQuery( 'form[name="pmc-helpdesk-form"]' ).html();
	},

	restore_form_html: function() {
		if ( pmc_helpdesk.form_html ) {
			jQuery( 'form[name="pmc-helpdesk-form"]' ).html( pmc_helpdesk.form_html );
		}
	},

	toggle_form: function() {
		jQuery( 'form[name="pmc-helpdesk-form"]' ).submit( function( e ) {
			e.preventDefault();
			return false;
		} );
		jQuery( '#pmc-helpdesk-form-wrapper' ).slideToggle( 'fast' );
	},

	/**
	 * For those times you want to ensure you're actually closing the form and not accidentally toggling it open.
	 */
	close_form: function() {
		jQuery( '#pmc-helpdesk-form-wrapper' ).slideUp( 'fast', function() {
			pmc_helpdesk.restore_form_html();
		});

	},

	send: function() {
		pmc_helpdesk.store_form_html();
		pmc_helpdesk.show_activity_view();

		var data = {
			action: 'pmc-helpdesk-form',
			pmc_helpdesk_nonce: jQuery("#pmc_helpdesk_nonce").val(),
			fields: jQuery( 'form[name="pmc-helpdesk-form"]' ).serialize()
		};

		jQuery.post( pmc_helpdesk_vars._ajax_url, data, pmc_helpdesk.handle_response );
	},

	handle_response : function( response ) {
		pmc_helpdesk.hide_activity_view();

		var response_class, response_html;
		if ( true === response.success ) {
			response_class = 'success';
		} else {
			response_class = 'error';
		}
		response_html = '<p class="' + response_class + '">' + response.data + '</p>' + '<p><input type="submit" onclick="pmc_helpdesk.close_form();" class="button button-primary" value="' + pmc_helpdesk_vars.affirm + '" /></p>';
		jQuery( 'form[name="pmc-helpdesk-form"]' ).html( response_html );

		// (Re-)attach event handler to the new submit button
		jQuery( 'form[name="pmc-helpdesk-form"]' ).submit( function( e ) {
			e.preventDefault();
			return false;
		} );
	}

};

//EOF