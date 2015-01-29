<?php

	echo '<div class="group-accounts create">';
	echo form_open();

	echo '<p>' . lang( 'accounts_create_intro' ) .'</p>';

	$this->load->view( 'accounts/create/inc-basic' );

	echo '<p>' . form_submit( 'submit', lang( 'accounts_create_submit' ), 'class="awesome"' ) . '</p>';

	echo form_close();
	echo '</div>';

?>
<script type="text/javascript">
	$(function()
	{
		$( 'select[name=group_id]' ).on( 'change', function()
		{
			$( '#user-group-descriptions li' ).hide();
			$( '#user-group-' + $(this).val() ).show();
		});
	});
</script>