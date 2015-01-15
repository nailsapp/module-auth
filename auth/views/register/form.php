<div class="row">
	<div class="well well-lg col-sm-6 col-sm-offset-3">
		<!--	SOCIAL NETWORK BUTTONS	-->
		<?php

			if ( $social_signon_enabled ) :

				echo '<p class="text-center" style="margin:1em 0 2em 0;">';
					echo 'Register using your preferred social network.';
				echo '</p>';

				echo '<div class="row text-center" style="margin-top:1em;">';

					$_buttons = array();

					foreach ( $social_signon_providers as $provider ) :

						$_buttons[] = array( 'auth/login/' . $provider['slug'] . '/register', $provider['label'] );

					endforeach;

					// --------------------------------------------------------------------------

					//	Render the buttons
					$_min_cols	= 12 / 3;
					$_cols_each = floor( 12 / count( $_buttons ) );
					$_cols_each = $_cols_each < $_min_cols ? $_min_cols : $_cols_each;

					foreach ( $_buttons as $btn ) :

						echo '<div class="col-md-' . $_cols_each . ' text-center" style="margin-bottom:1em;">';
							echo anchor( $btn[0], $btn[1], 'class="btn btn-primary btn-lg btn-block"' );
						echo '</div>';

					endforeach;

				echo '</div>';

				echo '<hr />';

				echo '<p class="text-center" style="margin:1em 0 2em 0;">';
					switch ( APP_NATIVE_LOGIN_USING ) :

						case 'EMAIL' :

							echo 'Or sign in using your email address and password.';

						break;

						case 'USERNAME' :

							echo 'Or sign in using your username and password.';

						break;

						default :

							echo 'Or sign in using your email address or username and password.';

						break;

					endswitch;
				echo '</p>';

			endif;

			// --------------------------------------------------------------------------

			echo form_open( site_url( 'auth/register' ), 'class="form form-horizontal"' );
			echo form_hidden( 'registerme', TRUE );

			// --------------------------------------------------------------------------

			if ( APP_NATIVE_LOGIN_USING == 'EMAIL' || APP_NATIVE_LOGIN_USING != 'USERNAME' ) :

				$_field			= 'email';
				$_label			= lang( 'form_label_email' );
				$_placeholder	= lang( 'auth_register_email_placeholder' );

				?>
				<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
					<label class="col-sm-3 control-label" for="input-<?=$_field?>"><?=$_label?></label>
					<div class="col-sm-9">
						<?=form_email( $_field, set_value( $_field ), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "' )?>
						<?=form_error( $_field, '<p class="help-block">', '</p>' )?>
					</div>
				</div>
				<?php

			endif;

			if ( APP_NATIVE_LOGIN_USING == 'USERNAME' || APP_NATIVE_LOGIN_USING != 'EMAIL' ) :

				$_field			= 'username';
				$_label			= lang( 'form_label_username' );
				$_placeholder	= lang( 'auth_register_username_placeholder' );

				?>
				<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
					<label class="col-sm-3 control-label" for="input-<?=$_field?>"><?=$_label?></label>
					<div class="col-sm-9">
						<?=form_input( $_field, set_value( $_field ), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "' )?>
						<p class="help-block">
							<small>
								Usernames can only contain alpha numeric characters, underscores, periods and dashes (no spaces).
							</small>
						</p>
						<?=form_error( $_field, '<p class="help-block">', '</p>' )?>
					</div>
				</div>
				<?php

			endif;

		// --------------------------------------------------------------------------


		$_field			= 'password';
		$_label			= lang( 'form_label_password' );
		$_placeholder	= lang( 'auth_register_password_placeholder' );

		?>
		<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
			<label class="col-sm-3 control-label" for="input-<?=$_field?>"><?=$_label?></label>
			<div class="col-sm-9">
			<?php

				echo form_password($_field, set_value($_field), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "');

                if (!empty($passwordRulesAsString)) {

                    echo '<p class="help-block">';
                    	echo '<small>' . $passwordRulesAsString . '</small>';
                    echo '</p>';

                }

                echo form_error($_field, '<p class="help-block">', '</p>');

			?>
			</div>
		</div>
		<?php


		$_field			= 'first_name';
		$_label			= lang( 'form_label_first_name' );
		$_placeholder	= lang( 'auth_register_first_name_placeholder' );

		?>
		<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
			<label class="col-sm-3 control-label" for="input-<?=$_field?>"><?=$_label?></label>
			<div class="col-sm-9">
				<?=form_input( $_field, set_value( $_field ), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "' )?>
				<?=form_error( $_field, '<p class="help-block">', '</p>' )?>
			</div>
		</div>
		<?php


		$_field			= 'last_name';
		$_label			= lang( 'form_label_last_name' );
		$_placeholder	= lang( 'auth_register_last_name_placeholder' );

		?>
		<div class="form-group <?=form_error( $_field ) ? 'has-error' : ''?>">
			<label class="col-sm-3 control-label" for="input-<?=$_field?>"><?=$_label?></label>
			<div class="col-sm-9">
				<?=form_input( $_field, set_value( $_field ), 'id="input-<?=$_field?>" placeholder="' . $_placeholder . '" class="form-control "' )?>
				<?=form_error( $_field, '<p class="help-block">', '</p>' )?>
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-3 col-sm-9">
				<button type="submit" class="btn btn-primary"><?=lang( 'action_register' )?></button>
			</div>
		</div>
		<hr />
		<p class="text-center">
			Already got an account? <?=anchor( 'auth/login', 'Sign in now' )?>.
		</p>
	</div>
</div>
<?php

	// --------------------------------------------------------------------------

	//	Close the form
	echo form_close();