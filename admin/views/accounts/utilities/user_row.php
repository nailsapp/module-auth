<tr>
	<td class="id"><?=number_format( $member->id )?></td>
	<td class="details">
		<?php

			if ( $member->profile_img ) :

				echo anchor( cdn_serve( $member->profile_img ), img( array( 'src' => cdn_thumb( $member->profile_img, 65, 65 ), 'class' => 'profile-img' ) ), 'class="fancybox"' );

			else :

				switch ( $member->gender ) :

					case 'female' :	echo img( array( 'src' => cdn_blank_avatar( 65, 65, 'female' ), 'class' => 'profile-img' ) );	break;
					default	: 		echo img( array( 'src' => cdn_blank_avatar( 65, 65, 'male' ), 'class' => 'profile-img' ) );		break;

				endswitch;

			endif;

			echo '<div>';

			switch ( $this->input->get( 'sort' ) ) :

				case 'u.last_name' :	echo '<strong>' . $member->last_name . ', ' . $member->first_name . '</strong>';	break;
				default :				echo '<strong>' . $member->first_name . ' ' . $member->last_name . '</strong>';		break;

			endswitch;

			echo '<small>';

				echo $member->email;

				if ( $member->email_is_verified ) :

					echo '<span class="verified" rel="tipsy" title="' . lang( 'accounts_index_verified' ) . '">';
						echo '&nbsp;<span class="fa fa-check-circle"></span>';
					echo '<span>';

				endif;

			echo '</small>';

			if ( $member->last_login ) :

				echo '<small>' . lang( 'accounts_index_last_login', array( user_datetime( $member->last_login, 'Y-m-d', 'H:i:s' ), $member->login_count ) ) . '</small>';

			else :

				echo '<small>' . lang( 'accounts_index_last_nologins' ) . '</small>';

			endif;
			echo '</div>';

		?>
	</td>
	<td class="group"><?=$member->group_name?></td>

	<!--	EXTRA COLUMNS	-->
	<?php

		foreach ( $columns as $col ) :

			$this->load->view( 'admin/accounts/utilities/user_row_column_' . $col['view'] );

		endforeach;

	?>

	<!--	ACTIONS	-->
	<td class="actions">
		<?php

			//	Actions, only super users can do anything to other superusers
			if ( ! $user->is_superuser() && user_has_permission( 'superuser', $member ) ) :

				//	Member is a superuser and the admin is not a super user, no editing facility
				echo '<span class="not-editable">' . lang( 'accounts_index_noteditable' ) . '</span>';

			else :

				$_return	= $_SERVER['QUERY_STRING'] ? uri_string() . '?' . $_SERVER['QUERY_STRING'] : uri_string();
				$_return	= '?return_to=' . urlencode( $_return );
				$_buttons	= array();

				// --------------------------------------------------------------------------

				//	Login as?
				if ( $member->id != active_user( 'id' ) && user_has_permission( 'admin.accounts:0.can_login_as' ) ) :

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

					$_url = site_url( 'auth/override/login_as/' . md5( $member->id ) . '/' . md5( $member->password ) . $_return_string );

					$_buttons[] = anchor( $_url, lang( 'admin_login_as' ), 'class="awesome small"' );

				endif;

				// --------------------------------------------------------------------------

				//	Edit
				if ( user_has_permission('admin.accounts:0.can_edit_others') ) :

					if ( $member->id == active_user( 'id' ) || user_has_permission( 'admin.accounts:0.can_edit_others' ) ) :

						$_buttons[] = anchor( 'admin/accounts/edit/' . $member->id . $_return, lang( 'action_edit' ), 'data-fancybox-type="iframe" class="edit fancybox-max awesome small"' );

					endif;

				endif;

				// --------------------------------------------------------------------------

				//	Suspend user
				if ( $member->is_suspended ) :

					if ( user_has_permission( 'admin.accounts:0.can_suspend_user' ) ) :

						$_buttons[] = anchor( 'admin/accounts/unsuspend/' . $member->id . $_return, lang( 'action_unsuspend' ), 'class="awesome small green"' );

					endif;

				else :

					if ( user_has_permission( 'admin.accounts:0.can_suspend_user' ) ) :

						$_buttons[] = anchor( 'admin/accounts/suspend/' . $member->id . $_return, lang( 'action_suspend' ), 'class="awesome small red"' );

					endif;

				endif;

				// --------------------------------------------------------------------------

				//	Delete user
				if ( user_has_permission( 'admin.accounts:0.can_delete_others' ) && $member->id != active_user( 'id' ) && ! $this->user_model->is_superuser( $member->id ) ) :

					$_buttons[] = anchor( 'admin/accounts/delete/' . $member->id . $_return, lang( 'action_delete' ), 'class="confirm awesome small red" data-title="Delete user &quot;' . $member->first_name . ' ' . $member->last_name . '&quot?" data-body="Are you sure you want to delete this user? This action is not undoable."' );

				endif;

				// --------------------------------------------------------------------------

				//	Update user's group
				if (user_has_permission('admin.accounts:0.can_change_user_group')) {

					//	If this user us a super user and the current user is not a super user then don't allow this option
					if ($this->user_model->is_superuser($member->id) && !$this->user_model->is_superuser()) {

						//	Nothing

					} else {

						$_buttons[] = anchor( 'admin/accounts/change_group?users=' . $member->id, 'Edit Group', 'class="awesome small"' );

					}
				}

				// --------------------------------------------------------------------------

				//	These buttons are variable between views
				foreach ( $actions as $button ) :

					$_buttons[] = anchor( $button['url'] . $_return, $button['label'], 'class="awesome small ' . $button['class'] . '"' );

				endforeach;

				// --------------------------------------------------------------------------

				//	Render all the buttons, if any
				if ( $_buttons ) :

					foreach ( $_buttons as $button ) :

						echo $button;

					endforeach;

				else :

					echo '<span class="not-editable">' . lang( 'accounts_index_noactions' ) . '</span>';

				endif;

			endif;

		?>
	</td>
</tr>