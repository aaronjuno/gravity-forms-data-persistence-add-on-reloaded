<?php
/*
  Plugin Name: Gravity Forms Data Persistence Add-On Reloaded
  Plugin URI: http://www.robertiseley.com/gfdp-reloaded
  Description: This is a <a href="http://www.gravityforms.com/" target="_blank">Gravity Form</a> plugin. A big limitation with Gravity Form is, in case of big multipage forms, if you close or refresh the page during somewhere midle of some step. all the steps data will loose. this plugin solves that problem. This is an updated version of asthait's plugin.
  Author: Robert Iseley
  Version: TRUNK
  Author URI: http://www.robertiseley.com
  Orginal Plugin by: asthait
 */

namespace GFDP;

new core;

class core {

	static $version = "TRUNK";

	function __construct() {

		if ( is_admin() ) {
			// Admin hooks/filters
			add_filter( 'gform_tooltips', array( $this, 'tooltips' ) );
			add_filter( 'gform_form_settings', array( $this, 'form_settings' ), 50, 2 );
		} else {
			// Front-end hooks/filters
			add_action( 'wp_head', array( $this, 'version' ) );
		}

	}

	function version() {
		echo "<!-- Gravity Forms Data Persistence Add-On Reloaded Version " . self::$version . " -->";
	}

	//Add persistent settings to the form settings
	function form_settings( $form_settings, $form ) {

		$settings[] = '
        <tr>
			<th>Persistence ' . gform_tooltip( 'gfdp_persist', '', true ) . ' </th>
			<td>
				<select name="gfdp_persist" id="gfdp_persist">
					<option value="off" ' . selected( rgar( $form, "gfdp_persist" ), 'off', false ) . '>Off</option>
					<option value="submit_only" ' . selected( rgar( $form, "gfdp_persist" ), 'submit_only', false ) . '>Save data on page change/submit only</option>
					<option value="ajax" ' . selected( rgar( $form, "gfdp_persist" ), 'ajax', false ) . '>Save data with ajax</option>
				</select>
			</td>
        </tr>';

		$settings[] = '
        <tr class="gfdp-all">
        	<th>Multiple Entries ' . gform_tooltip( "gfdp_multiple_entries", '', true ) . ' </th>
        	<td>
            <input type="checkbox" name="gfdp_multiple_entries" id="gfdp_multiple_entries" ' . checked( rgar( $form, "gfdp_multiple_entries" ), '1', false ) . '" value="1" />
            <label for="gfdp_multiple_entries">Allow multiple entries</label>
			</td>
        </tr>';

		$settings[] = '
        <tr class="gfdp-all">
        	<th>Clear Persistence ' . gform_tooltip( "gfdp_clear_persist", '', true ) . ' </th>

			<td>
            <input type="checkbox" name="gfdp_persist_clear" id="gfdp_persist_clear" ' . checked( rgar( $form, "gfdp_persist_clear" ), '1', false ) . '" value="1" />
            <label for="gfdp_persist_clear"> Clear persistence on submit</label>
			</td>
        </tr>';

		$settings[] = '
        <tr class="gfdp-ajax">
        	<th>Ajax Timer ' . gform_tooltip( "gfdp_ajax_timer", '', true ) . ' </th>

			<td>
            <input type="text" name="gfdp_ajax_timer" id="gfdp_ajax_timer" value="' . rgar( $form, "gfdp_ajax_timer" ) . '"/>
            <label for="gfdp_ajax_timer">Minutes</label>
			</td>
        </tr>';

		$form_settings["Persistence"] = $settings;

		return $form_settings;
	}

	// Filter to add a new tooltip
	function tooltips( $tooltips ) {
		$tooltips["gfdp_persist"]          = "<h6>Persistency</h6>Select to save users progress with form so they may continue at another time. Must be a logged in user.";
		$tooltips["gfdp_multiple_entries"] = "<h6>Multiple Entries Allowed</h6>This will allow multiple entry from same user. User can not edit their last and the previous entry not removed from the entry list";
		$tooltips["gfdp_no_persist"]       = "<h6>No Persist</h6>Checking this will removed this field(s) from the persistence data. User will have to re-enter information upon returning to the form. This does not affect the submission of an entry. Useful for sensitive information.";
		$tooltips["gfdp_clear_persist"]    = "<h6>Clear Persist</h6>This option will delete the persistence data when a form is submitted. Allow the user to return to a fresh blank form.";
		$tooltips["gfdp_ajax_timer"]       = "<h6>AJAX Timer</h6>If using ajax (auto-submitting), form will be silently submitted after this amount of time if use has not progressed to next page. Default = 10 Minutes";

		return $tooltips;
	}
}


/*

// Register garlic script for local persistence
add_action( 'wp_enqueue_scripts', 'ri_gfdp_script_register' );
function ri_gfdp_script_register() {
	wp_enqueue_script( 'jquery' );
}

// Render persistence data before form output
add_filter( "gform_pre_render", "ri_pre_populate_the_form" );
function ri_pre_populate_the_form( $form ) {
	if ( ! empty( $form['isPersistent'] ) || ! empty( $form['ri_gfdp_persist'] ) ) {
		$current_page = GFFormDisplay::get_current_page( $form["id"] );
		if ( $current_page == 1 ) {
			$option_key = ri_getFormOptionKeyForGF( $form );
			if ( get_option( $option_key ) ) {
				$_POST = json_decode( get_option( $option_key ), true );
				foreach ( $form['fields'] as $field ) {
					if ( rgar( $field, "allowsPrepopulate" ) ) {
						if ( is_array( rgar( $field, "inputs" ) ) ) {
							foreach ( $field["inputs"] as $input ) {
								if ( ! empty( $_GET[ $input['name'] ] ) ) {
									$_POST[ 'input_' . str_replace( '.', '_', $input['id'] ) ] = $_GET[ $input['name'] ];
								}
							}
						} else {
							if ( ! empty( $_GET[ $field['inputName'] ] ) ) {
								$_POST[ 'input_' . $field['id'] ] = $_GET[ $field['inputName'] ];
							}
						}
					}
				}
			}
		}
	}

	return $form;
}

// Updating data via ajax auto save
add_action( 'wp_ajax_gfdp_save', 'ri_gfdp_ajax' );
function ri_gfdp_ajax() {
	global $wpdb; // this is how you get access to the database
	parse_str( $_POST['form'], $data );
	$form_id = isset( $data['gform_submit'] ) ? $data["gform_submit"] : 0;
	if ( $form_id ) {
		$form_info     = RGFormsModel::get_form( $form_id );
		$is_valid_form = $form_info && $form_info->is_active;
		if ( $is_valid_form ) {
			$form = RGFormsModel::get_form_meta( $form_id );
			$form = RGFormsModel::add_default_properties( $form );
			ri_gfdp_ajax_save( $form );
			echo "Saved";
		} else {
			echo "Invalid Form";
		}
	} else {
		echo "Invalid Form";
	}

	die(); // this is required to terminate immediately and return a proper response
}


//The js for ajax call
add_action( 'gform_enqueue_scripts', 'ri_gfdp_js_enqueue', 90, 2 );
function ri_gfdp_js_enqueue( $form, $is_ajax ) {
	if ( $form['ri_gfdp_persist'] == 'ajax' && is_user_logged_in() ) {
		add_action( 'wp_footer', 'ri_gfdp_js' );
	}
}

function ri_gfdp_js() {
	?>
	<script type="text/javascript">
		var changed = false;

		function gfdp_events() {
			jQuery('form').on('change keyup', function () {
				changed = true;
			})
		}

		jQuery(document).ready(gfdp_events);
		jQuery(document).ajaxComplete(gfdp_events);


		function gfdp_ajax($) {
			if (changed == true) {
				var data = {
					'action': 'gfdp_save',
					'form': jQuery('form').serialize()
				};

				jQuery.ajax({
					url: '<?php echo admin_url('admin-ajax.php'); ?>',
					type: 'POST',
					data: data,
					success: function (response) {
						changed = false;
					}
				})
			}
		}
		;

		jQuery(document).ready(setInterval(gfdp_ajax, 10000));
	</script> <?php
}

// Saving data from ajax call
function ri_gfdp_ajax_save( $form, $coming_from_page, $current_page ) {
	if ( $form['ri_gfdp_persist'] == 'ajax' ) {
		if ( is_user_logged_in() ) {
			$option_key = ri_getFormOptionKeyForGF( $form );
			parse_str( $_POST['form'], $data );
			$data = ri_gfdp_sanitize_data( $data, $form );
			update_option( $option_key, json_encode( $data ) );
		}
	}
}

function ri_gfdp_sanitize_data( $data, $form ) {
	foreach ( $form['fields'] as $field ) {
		if ( $field['ri_gfdp_no_persist'] ) {
			if ( is_array( $field['inputs'] ) ) {
				foreach ( $field['inputs'] as $input ) {
					$data[ 'input_' . str_replace( '.', '_', $input['id'] ) ] = '';
				}
			} else {
				$data[ 'input_' . $field['id'] ] = '';
			}
		}
	}

	return $data;
}

// Updating persistence data on page change
add_action( "gform_post_paging", "ri_page_changed", 10, 3 );
function ri_page_changed( $form, $coming_from_page, $current_page ) {
	if ( $form['isPersistent'] || $form['ri_gfdp_persist'] ) {
		if ( is_user_logged_in() ) {
			$option_key = ri_getFormOptionKeyForGF( $form );
			$data       = ri_gfdp_sanitize_data( $_POST, $form );
			update_option( $option_key, json_encode( $data ) );
		}
	}
}

// Updating or clearning persistence data on form submission
add_action( "gform_post_submission", "ri_set_post_content", 10, 2 );
function ri_set_post_content( $entry, $form ) {
	if ( $form['isPersistent'] || $form['ri_gfdp_persist'] ) {
		//Update form data in wp_options table
		if ( is_user_logged_in() ) {
			$option_key = ri_getFormOptionKeyForGF( $form );

			if ( $form['isEnablePersistentClear'] || $form['ri_gfdp_persist_clear'] ) {
				delete_option( $option_key );
			} else {
				$data = ri_gfdp_sanitize_data( $_POST, $form );
				update_option( $option_key, json_encode( $data ) );
			}

			$entry_option_key = ri_getEntryOptionKeyForGF( $form );
			if ( get_option( $entry_option_key ) ) {
				//Delete old entry from GF tables
				if ( isset( $form['ri_gfdp_persist'] ) ) {

					if ( ! $form['ri_gfdp_multiple_entries'] ) {
						RGFormsModel::delete_lead( get_option( $entry_option_key ) );
					}
				} else {
					if ( ! $form['isEnableMulipleEntry'] ) {
						RGFormsModel::delete_lead( get_option( $entry_option_key ) );
					}
				}
			}
		}

		//Update entry in wp_options table
		update_option( $entry_option_key, $entry['id'] );
	}
}

// Create and return option table key for a form and user
function ri_getFormOptionKeyForGF( $form ) {

	global $current_user;
	get_currentuserinfo();

	$option_key = $current_user->user_login . '_GF_' . $form['id'];

	return $option_key;
}

// Create and return option table key for user form entry
function ri_getEntryOptionKeyForGF( $form ) {

	global $current_user;
	get_currentuserinfo();

	$option_key = $current_user->user_login . '_GF_' . $form['id'] . '_entry';

	return $option_key;
}



add_filter( 'gform_pre_form_settings_save', 'ri_gfdp_save_form_settings' );
function ri_gfdp_save_form_settings( $form ) {

	//Remove old setting names
	unset( $form['isPersistent'] );
	unset( $form['isEnableMulipleEntry'] );
	unset( $form['isEnablePersistentClear'] );

	//update settings
	$form['ri_gfdp_persist']          = rgpost( 'ri_gfdp_persist' );
	$form['ri_gfdp_multiple_entries'] = rgpost( 'ri_gfdp_multiple_entries' );
	$form['ri_gfdp_persist_clear']    = rgpost( 'ri_gfdp_persist_clear' );

	return $form;

}

;

// Action to inject supporting script to the form editor page
add_action( "gform_advanced_settings", "ri_editor_script_persistency" );
function ri_editor_script_persistency() {
	?>
	<script type='text/javascript'>
		if (typeof form != 'undefined') {
			if (typeof form.isPersistent != 'undefined') {
				jQuery("#ri_gfdp_persist").val('submit_only');
			}
			if (typeof form.isEnableMulipleEntry != 'undefined') {
				jQuery("#ri_gfdp_multiple_entries").attr("checked", form.isEnableMulipleEntry);
			}
			if (typeof form.isEnablePersistentClear != 'undefined') {
				jQuery("#ri_gfdp_persist_clear").attr("checked", form.isEnablePersistentClear);
			}
		}
	</script>
<?php
}

add_action( 'gform_field_advanced_settings', 'ri_gfdp_advanced_settings', 10, 2 );
function ri_gfdp_advanced_settings( $position, $form_id ) {
	if ( $position == 550 ) {
		?>
		<li class="field_ri_gfdp_no_persist_setting">
			<input type="checkbox" id="ri_gfdp_no_persist" name="ri_gfdp_no_persist"
			       onclick="SetFieldProperty('ri_gfdp_no_persist', this.checked);"/>
			<label for="ri_gfdp_no_persist" class="inline">
				<?php _e( 'Do not allow persistence', 'ri_gfdp' ); ?>
				<?php gform_tooltip( 'ri_gfdp_no_persist' ); ?>
			</label>
		</li>
	<?php
	}
}

//Action to inject supporting script to the form editor page
add_action( "gform_editor_js", "ri_gfdp_editor_script", 11 );
function ri_gfdp_editor_script() {
	?>
	<script type='text/javascript'>
		//adding setting to fields of type "text"
		//fieldSettings["text"] += ", .field_ri_gfdp_no_persist_setting";

		//binding to the load field settings event to initialize the checkbox
		jQuery(document).bind("gform_load_field_settings", function (event, field, form) {
			jQuery("#ri_gfdp_no_persist").attr("checked", field["ri_gfdp_no_persist"] == true);
		});
	</script>
<?php
}
