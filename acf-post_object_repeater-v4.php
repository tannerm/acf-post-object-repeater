<?php

class acf_field_post_object_repeater extends acf_field {
	
	// vars
	var $settings, // will hold info such as dir / path
		$defaults; // will hold default field options
		
		
	/*
	*  __construct
	*
	*  Set name / label needed for actions / filters
	*
	*  @since	3.6
	*  @date	23/01/13
	*/
	
	function __construct()
	{
		// vars
		$this->name = 'post_object_repeater';
		$this->label = __('Post Object Repeater');
		$this->category = __("Relational",'acf'); // Basic, Content, Choice, etc
		$this->defaults = array(
			// add default here to merge into your field. 
			// This makes life easy when creating the field options as you don't need to use any if( isset('') ) logic. eg:
			//'preview_size' => 'thumbnail'
		);
		
		
		// do not delete!
    	parent::__construct();
    	
    	
    	// settings
		$this->settings = array(
			'path' => apply_filters('acf/helpers/get_path', __FILE__),
			'dir' => apply_filters('acf/helpers/get_dir', __FILE__),
			'version' => '1.0.0'
		);

		add_action( 'wp_ajax_por_update_repeater_select',        array( $this, 'ajax_get_dropdown' ) );
		add_action( 'wp_ajax_nopriv_por_update_repeater_select', array( $this, 'ajax_get_dropdown' ) );
	}
	
	
	/*
	*  create_options()
	*
	*  Create extra options for your field. This is rendered when editing a field.
	*  The value of $field['name'] can be used (like below) to save extra data to the $field
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field	- an array holding all the field's data
	*/
	
	function create_options( $field )
	{
		// defaults?
		/*
		$field = array_merge($this->defaults, $field);
		*/
		
		// key is needed in the field names to correctly save the data
		$key = $field['name'];
		
		
		// Create Field Options HTML
		$object_fields = $this->get_post_object_fields();
		$groups = $this->get_group_repeaters();

		?>
		<tr class="field_option field_option_<?php echo $this->name; ?>">
			<td class="label">
				<label><?php _e("Related Post Object",'acf'); ?></label>
				<p class="description"><?php _e("Related Post Object Field",'acf'); ?></p>
			</td>
			<td>
				<?php

				do_action('acf/create_field', array(
					'type'		=>	'select',
					'name'		=>	'fields['.$key.'][post_object]',
					'value'		=>	$field['post_object'],
					'layout'	=>	'horizontal',
					'choices'	=>	$object_fields
				));

				?>
			</td>
		</tr>
		<tr class="field_option field_option_<?php echo $this->name; ?>">
			<td class="label">
				<label><?php _e("Related Repeater",'acf'); ?></label>
				<p class="description"><?php _e("Select the repeater field to populate the dropdown",'acf'); ?></p>
			</td>
			<td>
				<?php

				do_action('acf/create_field', array(
					'type'		=>	'select',
					'name'		=>	'fields['.$key.'][repeater]',
					'value'		=>	$field['repeater'],
					'layout'	=>	'horizontal',
					'choices'	=>	$groups
				));

				?>
			</td>
		</tr>
		<?php
		
	}

	protected function get_post_object_fields() {
		$fields = array();
		$post_id = '';

		if ( ! empty( $_POST['post_id'] ) ) {
			$post_id = $_POST['post_id'];
		}

		if ( ! empty( $_GET['post'] ) ) {
			$post_id = $_GET['post'];
		}

		if ( ! $post_id ) {
			return $fields;
		}

		$meta = get_post_meta( (int) $post_id );

		foreach( $meta as $id => $field ) {
			if ( empty( $field[0] ) ) {
				continue;
			}

			if ( 0 !== strpos( $id, 'field_' ) ) {
				continue;
			}

			$field = maybe_unserialize( $field[0] );

			if ( 'post_object' != $field['type'] ) {
				continue;
			}

			$fields[$field['key']] = $field['label'];

		}

		return $fields;

	}

	protected function get_group_repeaters() {
		global $wpdb;
		$fields = array();

		$query = "
			SELECT ID
			FROM $wpdb->posts
			WHERE post_status = 'publish'
			AND post_type = 'acf'
			";

		$groups = $wpdb->get_results( $query );
		$groups = wp_list_pluck( (array) $groups, 'ID' );

		if ( ! $groups ) {
			return $fields;
		}

		foreach( $groups as $id ) {
			$title = get_the_title( $id );
			$meta = get_post_meta( $id );

			foreach( $meta as $id => $field ) {
				if ( empty( $field[0] ) ) {
					continue;
				}

				if ( 0 !== strpos( $id, 'field_' ) ) {
					continue;
				}

				$field = maybe_unserialize( $field[0] );

				if ( 'repeater' != $field['type'] ) {
					continue;
				}

				$fields[$title][$field['key']] = $field['label'];

			}

		}

		return $fields;

	}
	
	
	/*
	*  create_field()
	*
	*  Create the HTML interface for your field
	*
	*  @param	$field - an array holding all the field's data
	*
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/
	function create_field( $field ) {
		echo $this->get_dropdown( get_the_ID(), $field );
	}

	/**
	 * Get dropdown
	 *
	 * @param $current_post_id
	 * @param $field
	 *
	 * @return string
	 */
	protected function get_dropdown( $current_post_id, $field, $object_id = null ) {
		$post_object = get_post_meta( $field['field_group'], $field['post_object'], true );

		// trim value
		$field['value'] = trim( $field['value'] );

		if ( ! $object_id ) {
			$object_id = get_post_meta( $current_post_id, $post_object['name'] );
		}

		// posts containing repeaters that we will use to populate the select
		$post_ids = apply_filters( 'por_repeater_objects', (array) $object_id, $field );

		$args = array(
			'posts_per_page' => -1,
			'post_type'      => 'any',
			'post__in'       => $post_ids,
		);

		$posts = get_posts( $args );

		// Get the ACF group that contains the repeater we will be using
		$repeater_post = get_posts( "meta_key={$field['repeater']}&post_type=acf" );

		if ( count( $repeater_post ) !== 1 ) {
			return '';
		}

		$repeater_post = $repeater_post[0];

		// Get the repeater field info
		$repeater_field = get_post_meta( $repeater_post->ID, $field['repeater'], true );

		if ( empty( $repeater_field['name'] ) ) {
			return '';
		}

		$groups = array();

		// Gather and sort all the values
		foreach( $posts as $post ) {
			$num_locations = get_post_meta( $post->ID, $repeater_field['name'], true );

			for( $i = 0; $i < $num_locations; $i ++ ) {
				$groups[$post->ID][$i] = get_post_meta( $post->ID, "{$repeater_field['name']}_{$i}_name", true );
			}

		}

		ob_start(); ?>

		<div class="por-select-container">
			<div class="acf-loading"></div>

			<?php do_action( 'por_repeater_before', $field ); ?>

			<select id="<?php echo $field['id']; ?>" class="<?php echo $field['class']; ?>" name="<?php echo $field['name']; ?>" data-field='<?php echo json_encode( $field ); ?>' >

				<?php foreach( $groups as $group_id => $values ) : ?>

					<?php if ( count( $groups ) > 1 ) : ?>
						<optgroup label="<?php echo get_the_title( $group_id ); ?>">
					<?php endif; ?>

						<?php if ( $field['allow_null'] ) : ?>
							<option value="null">- <?php echo __("Select",'acf'); ?> -</option>
						<?php endif; ?>

						<?php foreach( $values as  $value_id => $value ) : $value = $group_id . "_" . $value_id; ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $field['value'], $value ); ?>><?php echo get_post_meta( $group_id, "locations_{$value_id}_name", true );?></option>
						<?php endforeach; ?>

					<?php if ( count( $groups ) > 1 ) : ?>
						</optgroup>
					<?php endif; ?>

				<?php endforeach; ?>

			</select>

			<?php do_action( 'por_repeater_after', $field ); ?>

		</div>
		<?php
		return ob_get_clean();
	}

	public function ajax_get_dropdown() {

		if ( empty( $_POST['nonce'] ) ) {
			die();
		}

		if ( ! wp_verify_nonce( $_POST['nonce'], 'acf_nonce' ) ) {
			die();
		}

		ob_clean();
		echo json_encode( $this->get_dropdown( (int) $_POST['post_id'], $_POST['field'], (int) $_POST['object_id'] ) );
		die();
	}

	/*
	*  input_admin_enqueue_scripts()
	*
	*  This action is called in the admin_enqueue_scripts action on the edit screen where your field is created.
	*  Use this action to add CSS + JavaScript to assist your create_field() action.
	*
	*  $info	http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function input_admin_enqueue_scripts()
	{
		// Note: This function can be removed if not used
		
		
		// register ACF scripts
		wp_register_script( 'acf-input-post_object_repeater', $this->settings['dir'] . 'js/input.js', array('acf-input'), $this->settings['version'] );
		wp_register_style( 'acf-input-post_object_repeater', $this->settings['dir'] . 'css/input.css', array('acf-input'), $this->settings['version'] );
		
		
		// scripts
		wp_enqueue_script(array(
			'acf-input-post_object_repeater',
		));

		// styles
		wp_enqueue_style(array(
			'acf-input-post_object_repeater',
		));
		
		
	}
	
	
	/*
	*  input_admin_head()
	*
	*  This action is called in the admin_head action on the edit screen where your field is created.
	*  Use this action to add CSS and JavaScript to assist your create_field() action.
	*
	*  @info	http://codex.wordpress.org/Plugin_API/Action_Reference/admin_head
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function input_admin_head()
	{
		// Note: This function can be removed if not used
	}
	
	
	/*
	*  field_group_admin_enqueue_scripts()
	*
	*  This action is called in the admin_enqueue_scripts action on the edit screen where your field is edited.
	*  Use this action to add CSS + JavaScript to assist your create_field_options() action.
	*
	*  $info	http://codex.wordpress.org/Plugin_API/Action_Reference/admin_enqueue_scripts
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function field_group_admin_enqueue_scripts()
	{
		// Note: This function can be removed if not used
	}

	
	/*
	*  field_group_admin_head()
	*
	*  This action is called in the admin_head action on the edit screen where your field is edited.
	*  Use this action to add CSS and JavaScript to assist your create_field_options() action.
	*
	*  @info	http://codex.wordpress.org/Plugin_API/Action_Reference/admin_head
	*  @type	action
	*  @since	3.6
	*  @date	23/01/13
	*/

	function field_group_admin_head()
	{
		// Note: This function can be removed if not used
	}


	/*
	*  load_value()
	*
		*  This filter is applied to the $value after it is loaded from the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value found in the database
	*  @param	$post_id - the $post_id from which the value was loaded
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$value - the value to be saved in the database
	*/
	
	function load_value( $value, $post_id, $field )
	{
		// Note: This function can be removed if not used
		return $value;
	}
	
	
	/*
	*  update_value()
	*
	*  This filter is applied to the $value before it is updated in the db
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value - the value which will be saved in the database
	*  @param	$post_id - the $post_id of which the value will be saved
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$value - the modified value
	*/
	
	function update_value( $value, $post_id, $field )
	{
		// Note: This function can be removed if not used
		return $value;
	}
	
	
	/*
	*  format_value()
	*
	*  This filter is applied to the $value after it is loaded from the db and before it is passed to the create_field action
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value	- the value which was loaded from the database
	*  @param	$post_id - the $post_id from which the value was loaded
	*  @param	$field	- the field array holding all the field options
	*
	*  @return	$value	- the modified value
	*/
	
	function format_value( $value, $post_id, $field )
	{
		// defaults?
		/*
		$field = array_merge($this->defaults, $field);
		*/
		
		// perhaps use $field['preview_size'] to alter the $value?
		
		
		// Note: This function can be removed if not used
		return $value;
	}
	
	
	/*
	*  format_value_for_api()
	*
	*  This filter is applied to the $value after it is loaded from the db and before it is passed back to the API functions such as the_field
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$value	- the value which was loaded from the database
	*  @param	$post_id - the $post_id from which the value was loaded
	*  @param	$field	- the field array holding all the field options
	*
	*  @return	$value	- the modified value
	*/
	
	function format_value_for_api( $value, $post_id, $field )
	{
		// defaults?
		/*
		$field = array_merge($this->defaults, $field);
		*/
		
		// perhaps use $field['preview_size'] to alter the $value?
		
		
		// Note: This function can be removed if not used
		return $value;
	}
	
	
	/*
	*  load_field()
	*
	*  This filter is applied to the $field after it is loaded from the database
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - the field array holding all the field options
	*
	*  @return	$field - the field array holding all the field options
	*/
	
	function load_field( $field )
	{
		// Note: This function can be removed if not used
		return $field;
	}
	
	
	/*
	*  update_field()
	*
	*  This filter is applied to the $field before it is saved to the database
	*
	*  @type	filter
	*  @since	3.6
	*  @date	23/01/13
	*
	*  @param	$field - the field array holding all the field options
	*  @param	$post_id - the field group ID (post_type = acf)
	*
	*  @return	$field - the modified field
	*/

	function update_field( $field, $post_id )
	{
		// Note: This function can be removed if not used
		return $field;
	}

	
}


// create field
new acf_field_post_object_repeater();

?>
