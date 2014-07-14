<?php

	if ( $return_to  ) :

		$_returns = '?';
		$_returns .= $return_to ? 'return_to=' . urlencode( $return_to ) : '';

	else :

		$_returns = '';

	endif;

	// --------------------------------------------------------------------------

	//	Write the HTML for the register form
?>
<div class="row">
	<div class="well well-lg <?=BS_COL_SM_6?> <?=BS_COL_SM_OFFSET_3?>">
		<?=form_open( $form_url, 'class="form form-horizontal"'  )?>
		<p>
			<?=lang( 'auth_register_extra_message' )?>
		</p>
		<hr />
		<?php

			if ( APP_NATIVE_LOGIN_USING == 'EMAIL' || APP_NATIVE_LOGIN_USING != 'USERNAME' ) :

				if ( empty( $required_data['email'] ) ) :

					$_field			= 'email';
					$_label			= lang( 'form_label_email' );
					$_placeholder	= lang( 'auth_register_email_placeholder' );
					$_default		= '';

					?>
					<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
						<label class="<?=BS_COL_SM_3?> control-label" for="input-<?=$_field?>"><?=$_label?></label>
						<div class="<?=BS_COL_SM_9?>">
							<?=form_input( $_field, set_value( $_field, $_default ), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "' )?>
							<?=form_error( $_field, '<p class="help-block">', '</p>' )?>
						</div>
					</div>
					<?php

				endif;

			endif;

			// --------------------------------------------------------------------------

			if ( APP_NATIVE_LOGIN_USING == 'USERNAME' || APP_NATIVE_LOGIN_USING != 'EMAIL' ) :

				if ( empty( $required_data['username'] ) ) :

					$_field			= 'username';
					$_label			= lang( 'form_label_username' );
					$_placeholder	= lang( 'auth_register_username_placeholder' );
					$_default		= '';

					?>
					<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
						<label class="<?=BS_COL_SM_3?> control-label" for="input-<?=$_field?>"><?=$_label?></label>
						<div class="<?=BS_COL_SM_9?>">
							<?=form_input( $_field, set_value( $_field, $_default ), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "' )?>
							<?=form_error( $_field, '<p class="help-block">', '</p>' )?>
						</div>
					</div>
					<?php

				endif;

			endif;

			// --------------------------------------------------------------------------

			if ( ! $required_data['first_name'] || ! $required_data['last_name'] ) :

				$_field			= 'first_name';
				$_label			= lang( 'form_label_first_name' );
				$_placeholder	= lang( 'auth_register_first_name_placeholder' );
				$_default		= ! empty( $required_data['first_name'] ) ? $required_data['first_name'] : '';

				?>
				<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
					<label class="<?=BS_COL_SM_3?> control-label" for="input-<?=$_field?>"><?=$_label?></label>
					<div class="<?=BS_COL_SM_9?>">
						<?=form_input( $_field, set_value( $_field, $_default ), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "' )?>
						<?=form_error( $_field, '<p class="help-block">', '</p>' )?>
					</div>
				</div>
				<?php

				// --------------------------------------------------------------------------

				$_field			= 'last_name';
				$_label			= lang( 'form_label_last_name' );
				$_placeholder	= lang( 'auth_register_last_name_placeholder' );
				$_default		= ! empty( $required_data['last_name'] ) ? $required_data['last_name'] : '';

				?>
				<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
					<label class="<?=BS_COL_SM_3?> control-label" for="input-<?=$_field?>"><?=$_label?></label>
					<div class="<?=BS_COL_SM_9?>">
						<?=form_input( $_field, set_value( $_field, $_default ), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "' )?>
						<?=form_error( $_field, '<p class="help-block">', '</p>' )?>
					</div>
				</div>
				<?php

			endif;

		?>
		<hr />
		<div class="form-group">
			<div class="<?=BS_COL_SM_OFFSET_3?> <?=BS_COL_SM_9?>">
				<button type="submit" class="btn btn-primary"><?=lang( 'action_continue' )?></button>
			</div>
		</div>
		<?=form_close()?>
	</div>
</div>