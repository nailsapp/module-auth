<div class="group-accounts edit">
    <?php

    $oView = \Nails\Factory::service('View');

    echo form_open_multipart('admin/auth/accounts/edit/' . $user_edit->id . '?' . $this->input->server('QUERY_STRING'));
    echo form_hidden('id', $user_edit->id);
    echo form_hidden('email_orig', $user_edit->email);
    echo form_hidden('username_orig', $user_edit->username);

    if (!empty($isModal)) {
        $oView->load('Accounts/edit/inc-actions');
    }

    $oView->load([
        'Accounts/edit/inc-basic',
        'Accounts/edit/inc-emails',
        'Accounts/edit/inc-password',
    ]);

    $oConfig = \Nails\Factory::service('Config');

    $oConfig->load('auth/auth');

    if ($oConfig->item('authTwoFactorMode') == 'QUESTION') {
        $oView->load('Accounts/edit/inc-mfa-question');
    }

    if ($oConfig->item('authTwoFactorMode') == 'DEVICE') {
        $oView->load('Accounts/edit/inc-mfa-device');
    }

    $oView->load([
        'Accounts/edit/inc-meta',
        'Accounts/edit/inc-profile-img',
        'Accounts/edit/inc-uploads',
    ]);

    ?>
    <p>
        <?=form_submit('submit', lang('action_save_changes'), 'class="btn btn-primary"')?>
    </p>
    <?=form_close()?>
</div>
<?php

echo form_open('admin/auth/accounts/email', 'id="emailForm"');
echo form_hidden('id', $user_edit->id);
echo form_hidden('return', uri_string() . '?' . $this->input->server('QUERY_STRING'));
echo form_hidden('email');
echo form_hidden('action');
echo form_hidden('isPrimary');
echo form_hidden('isVerified');
echo form_close();
