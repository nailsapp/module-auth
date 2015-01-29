<?php

	$_buttons		= array();
	$return_string	= '?return_to=' . urlencode( uri_string() . '?' . $_SERVER['QUERY_STRING'] );

	// --------------------------------------------------------------------------

	//	Login as
	if ( $user_edit->id != active_user( 'id' ) && user_has_permission( 'admin.accounts:0.can_login_as' ) ) :

		//	Generate the return string
		$_url = uri_string();

		if ( $_GET ) :

			//	Remove common problematic GET vars (for instance, we don't want is_fancybox when we return)
			$_get = $_GET;
			unset( $_get['is_fancybox'] );
			unset( $_get['inline'] );

			if ( $_get ) :

				$_url .= '?' . http_build_query( $_get );

			endif;

		endif;

		$_return_string = '?return_to=' . urlencode( $_url );

		// --------------------------------------------------------------------------

		$_url = site_url( 'auth/override/login_as/' . md5( $user_edit->id ) . '/' . md5( $user_edit->password ) . $_return_string );

		$_buttons[] = anchor( $_url, lang( 'admin_login_as' ) . ' ' . $user_edit->first_name, 'class="awesome" target="_parent"' );

	endif;

	// --------------------------------------------------------------------------

	//	Edit
	if ( $user_edit->id != active_user( 'id' ) && user_has_permission( 'admin.accounts:0.delete' ) ) :

		$_buttons[] = anchor( 'admin/accounts/delete/' . $user_edit->id . '?return_to=' . urlencode( 'admin/accounts' ), lang( 'action_delete' ), 'class="awesome red confirm" data-title="' . lang( 'admin_confirm_delete_title' ) . '" data-body="' . lang( 'admin_confirm_delete_body' ) . '"' );

	endif;

	// --------------------------------------------------------------------------

	//	Suspend
	if ( $user_edit->is_suspended ) :

		if ( active_user( 'id') != $user_edit->id && user_has_permission( 'admin.accounts:0.unsuspend' ) ) :

			$_buttons[] = anchor( 'admin/accounts/unsuspend/' . $user_edit->id . $return_string, lang( 'action_unsuspend' ), 'class="awesome"' );

		endif;

	else :

		if ( active_user( 'id') != $user_edit->id && user_has_permission( 'admin.accounts:0.suspend' ) ) :

			$_buttons[] = anchor( 'admin/accounts/suspend/' . $user_edit->id . $return_string, lang( 'action_suspend' ), 'class="awesome red"' );

		endif;

	endif;

?>

<?php if ( $_buttons ) : ?>
<fieldset id="edit-user-actions">
	<legend><?=lang( 'accounts_edit_actions_legend' )?></legend>
	<p>
	<?php

		foreach ( $_buttons as $button ) :

			echo $button;

		endforeach;

	?>
	</p>
	<div class="clear"></div>
</fieldset>
<?php endif; ?>