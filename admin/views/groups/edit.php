<div class="group-accounts groups edit">
	<div class="system-alert message">
		<div class="padder">
			<p>
				<?=lang( 'accounts_groups_edit_warning' )?>
			</p>
		</div>
	</div>

	<hr />

	<?=form_open()?>

		<!--	BASICS	-->
		<fieldset>

			<legend><?=lang( 'accounts_groups_edit_basic_legend' )?></legend>
			<?php

				//	Display Name
				$_field					= array();
				$_field['key']			= 'label';
				$_field['label']		= lang( 'accounts_groups_edit_basic_field_label_label' );
				$_field['default']		= $group->label;
				$_field['required']		= TRUE;
				$_field['placeholder']	= lang( 'accounts_groups_edit_basic_field_placeholder_label' );

				echo form_field( $_field );

				// --------------------------------------------------------------------------

				//	Name
				$_field					= array();
				$_field['key']			= 'slug';
				$_field['label']		= lang( 'accounts_groups_edit_basic_field_label_slug' );
				$_field['default']		= $group->slug;
				$_field['required']		= TRUE;
				$_field['placeholder']	= lang( 'accounts_groups_edit_basic_field_placeholder_slug' );

				echo form_field( $_field );

				// --------------------------------------------------------------------------

				//	Description
				$_field					= array();
				$_field['key']			= 'description';
				$_field['type']			= 'textarea';
				$_field['label']		= lang( 'accounts_groups_edit_basic_field_label_description' );
				$_field['default']		= $group->description;
				$_field['required']		= TRUE;
				$_field['placeholder']	= lang( 'accounts_groups_edit_basic_field_placeholder_description' );

				echo form_field( $_field );

				// --------------------------------------------------------------------------

				//	Default Homepage
				$_field					= array();
				$_field['key']			= 'default_homepage';
				$_field['label']		= lang( 'accounts_groups_edit_basic_field_label_homepage' );
				$_field['default']		= $group->default_homepage;
				$_field['required']		= TRUE;
				$_field['placeholder']	= lang( 'accounts_groups_edit_basic_field_placeholder_homepage' );

				echo form_field( $_field );

				// --------------------------------------------------------------------------

				//	Registration Redirect
				$_field					= array();
				$_field['key']			= 'registration_redirect';
				$_field['label']		= lang( 'accounts_groups_edit_basic_field_label_registration' );
				$_field['default']		= $group->registration_redirect;
				$_field['required']		= FALSE;
				$_field['placeholder']	= lang( 'accounts_groups_edit_basic_field_placeholder_registration' );

				echo form_field( $_field, lang( 'accounts_groups_edit_basic_field_tip_registration' ) );

			?>

		</fieldset>

		<!--	PERMISSIONS	-->
		<fieldset id="permissions">

			<legend><?=lang( 'accounts_groups_edit_permission_legend' )?></legend>

			<p class="system-alert message">
				<?=lang( 'accounts_groups_edit_permission_warn' )?>
			</p>
			<p>
				<?=lang( 'accounts_groups_edit_permission_intro' )?>
			</p>

			<hr />

			<?php

				//	Enable Super User status for this user group
				$_field					= array();
				$_field['key']			= 'acl[superuser]';
				$_field['label']		= lang( 'accounts_groups_edit_permissions_field_label_superuser' );
				$_field['default']		= isset( $group->acl['superuser'] ) && $group->acl['superuser'] ? TRUE : FALSE;
				$_field['required']		= FALSE;
				$_field['id']			= 'super-user';

				echo form_field_boolean( $_field );

				// --------------------------------------------------------------------------

				$_visible = $_field['default'] ? 'none' : 'block';
				echo '<div id="toggle-superuser" style="display:' . $_visible . ';">';

					foreach ( $loaded_modules as $detail ) :

						if ( $detail->class_name == 'dashboard' ) :

							continue;

						endif;

						$_field					= array();
						$_field['label']		= $detail->name;
						$_field['default']		= FALSE;

						//	Build the field. Sadly, can't use the form helper due to the crazy multidimensional array
						//	that we're building here. Saddest of the sad pandas.

						echo '<div class="field">';

							//	Module permission
							if ( $this->input->post() ) :

								$_selected = isset( $_POST['acl']['admin'][$detail->class_index] );

							else :

								$_selected = isset( $group->acl['admin'][$detail->class_index] );

							endif;

							echo '<span class="label">';
								echo $detail->name;
							echo '</span>';
							echo '<span class="input togglize-me">';
								echo '<div class="toggle toggle-modern"></div>';
								echo form_checkbox( 'acl[admin][' . $detail->class_index . ']', TRUE, $_selected );
								echo '<div class="mask">Disable additional permissions in order to deactivate this module.</div>';
							echo '</span>';

							//	Extra permissions
							if ( ! empty( $detail->extra_permissions ) ) :
							echo '<div class="extra-permissions">';

								foreach ( $detail->extra_permissions as $permission => $label ) :

									if ( $this->input->post() ) :

										$_selected = isset( $_POST['acl']['admin'][$detail->class_index][$permission] );

									else :

										$_selected = isset( $group->acl['admin'][$detail->class_index][$permission] );

									endif;

									echo '<span class="label" style="font-weight:normal;">' . $label . '</span>';
									echo '<span class="input togglize-me-extra">';
										echo '<div class="toggle toggle-modern"></div>';
										echo form_checkbox( 'acl[admin][' . $detail->class_index . '][' . $permission . ']', TRUE, $_selected );
									echo '</span>';

								endforeach;

							echo '</div>';
							endif;

							echo '<div class="clear"></div>';
						echo '</div>';

					endforeach;

				echo '</div>';

			?>

		</fieldset>

		<p>
			<?=form_submit( 'submit', lang( 'action_save_changes' ), 'class="awesome"' )?>
		</p>

	<?=form_close()?>
</div>


<script style="text/javascript">
<!--//

	$(function(){

		//	Show/hide modules based on super user status
		$('.field.boolean .toggle').on('toggle', function (e, active) {

			if ( active )
			{
				$( '#toggle-superuser' ).slideUp();
			}
			else
			{
				$( '#toggle-superuser' ).slideDown();
			}

		});

		// --------------------------------------------------------------------------

		$( '.togglize-me' ).each(function()
		{
			var _checkbox	= $(this).find('input[type=checkbox]');

			$(this).find('.toggle').css({
				'width':		'100px',
				'height':		'30px',
				'text-align':	'center'
			}).toggles({
				checkbox:	_checkbox,
				click:		true,
				drag:		true,
				clicker:	_checkbox,
				on:			_checkbox.is(':checked'),
				text:
				{
					on:		'ON',
					off:	'OFF'
				}
			}).on( 'toggle', function(e,active)
			{
				if ( active === true )
				{
					_checkbox.closest( 'div.field' ).find( 'div.extra-permissions' ).slideDown();
				}
				else
				{
					_checkbox.closest( 'div.field' ).find( 'div.extra-permissions' ).slideUp();
				}
			});

			//	Initial state
			if ( _checkbox.is(':checked') )
			{
				_checkbox.closest( 'div.field' ).find( 'div.extra-permissions' ).show();
			}
			else
			{
				_checkbox.closest( 'div.field' ).find( 'div.extra-permissions' ).hide();
			}

			_checkbox.hide();
		});

		$( '.togglize-me-extra' ).each(function()
		{
			var _checkbox	= $(this).find('input[type=checkbox]');

			$(this).find('.toggle').css({
				'width':		'100px',
				'height':		'30px',
				'text-align':	'center'
			}).toggles({
				checkbox:	_checkbox,
				click:		true,
				drag:		true,
				clicker:	_checkbox,
				on:			_checkbox.is(':checked'),
				text:
				{
					on:		'ON',
					off:	'OFF'
				}
			}).on( 'toggle', function(e,active)
			{
				if ( active === true )
				{
					_checkbox.closest( 'div.field' ).find( 'span.togglize-me .mask' ).fadeIn();
				}
				else
				{
					//	Check if any others are toggled on, if not then remove mask
					if ( _checkbox.closest( 'div.field' ).find( '.togglize-me-extra input:checked' ).length === 0 )
					{
						_checkbox.closest( 'div.field' ).find( 'span.togglize-me .mask' ).fadeOut();
					}
				}
			});

			//	If any extra permissions are checked then show the mask
			if ( _checkbox.closest( 'div.field' ).find( '.togglize-me-extra input:checked' ).length !== 0 )
			{
				_checkbox.closest( 'div.field' ).find( 'span.togglize-me .mask' ).show();
			}

			_checkbox.hide();
		});

	});

//-->
</script>