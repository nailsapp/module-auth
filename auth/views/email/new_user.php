<?php if ( ! empty( $admin->id ) && $admin->id != $sent_to->id ) : ?>
<p>
	<?=$admin->first_name . ' ' . $admin->last_name?>, an administrator for <?=APP_NAME?>, has just created a new <em><?=$admin->group->name?></em> account for you.
</p>
<?php else : ?>
<p>
	Thank you for registering at the <?=APP_NAME?> website.
</p>
<?php endif; ?>
<?php if ( ! empty( $password ) ) : ?>
<p>
	Your password is shown below<?=! empty( $temp_pw ) ? ', you will be asked to set this to something more memorable when you log in' : ''?>.
</p>
<p class="heads-up" style="font-weight:bold;font-size:1.5em;text-align:center;font-family:LucidaConsole, Monaco, monospace;">
	<?=$password?>
</p>
<?php endif; ?>
<p>
	<?=anchor( 'auth/login', 'Click here to log in', 'class="button large"' )?>
</p>
<?php if ( ! empty( $verification_code ) ) : ?>
<hr />
<p>
	Additionally, we would appreciate it if you could verify your email address by clicking the button below, we do this to maintain the integrity of our database.
</p>
<p>
	<?=anchor( 'email/verify/' . $sent_to->id . '/' . $verification_code, 'Verify Email', 'class="button small"' )?>
</p>
<?php endif; ?>