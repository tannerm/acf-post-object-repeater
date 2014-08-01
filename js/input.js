(function($){
	"use strict";

	var initializeField = function($el) {
		var SELF = this;

		SELF.field = {};
		SELF.$postObjectContainer = '';

		SELF.init = function(){
			var $postObject;

			SELF.field = $el.find('select').attr('data-field');

			if ( ! SELF.field ) {
				return;
			}

			SELF.field = JSON.parse( SELF.field );

			if ( ! SELF.field.post_object ) {
				return;
			}

			$('.field_key-' + SELF.field.post_object + ' select').on('change', SELF.updateRepeaterSelect);
		};

		SELF.updateRepeaterSelect = function(e) {
			var $this = $(this),
			    ajax_data;

			ajax_data = {
				'action' : 'por_update_repeater_select',
				'post_id' : acf.post_id,
				'object_id' : $this.val(),
				'field' : SELF.field,
				'nonce' : acf.nonce
			};

			$el.find('.acf-loading').show();
			$el.find('select').prop('disabled', true);

			$.ajax({
				url: acf.ajaxurl,
				dataType: 'json',
				data: ajax_data,
				async: true,
				type: 'POST',
				error: function(error){
					$el.find('select').prop('disabled', false);
					console.log(error);
					alert('Something went wrong, please try again.');
				},
				success: function( output ) {
					$el.find('.acf-loading').hide();
					$el.find('.por-select-container').remove();
					$el.append(output);
				}
			});

		};

		SELF.init();

	};

	if( typeof acf.add_action !== 'undefined' ) {
	
		/*
		*  ready append (ACF5)
		*
		*  These are 2 events which are fired during the page load
		*  ready = on page load similar to $(document).ready()
		*  append = on new DOM elements appended via repeater field
		*
		*  @type	event
		*  @date	20/07/13
		*
		*  @param	$el (jQuery selection) the jQuery element which contains the ACF fields
		*  @return	n/a
		*/
		
		acf.add_action('ready append', function( $el ){
			
			// search $el for fields of type 'post_object_repeater'
			acf.get_fields({ type : 'post_object_repeater'}, $el).each(function(){
				
				initialize_field( $(this) );
				
			});
			
		});
		
		
	} else {
		
		
		/*
		*  acf/setup_fields (ACF4)
		*
		*  This event is triggered when ACF adds any new elements to the DOM. 
		*
		*  @type	function
		*  @since	1.0.0
		*  @date	01/01/12
		*
		*  @param	event		e: an event object. This can be ignored
		*  @param	Element		postbox: An element which contains the new HTML
		*
		*  @return	n/a
		*/
		
		$(document).live('acf/setup_fields', function(e, postbox){
			var i = 0;
			$(postbox).find('.field[data-field_type="post_object_repeater"]').each(function(){
				i ++;

				new initializeField( $(this) );

			});
		
		});
	
	
	}


})(jQuery);
