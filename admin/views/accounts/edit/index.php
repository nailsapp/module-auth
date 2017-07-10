<div class="group-accounts edit">
    <?php

    echo form_open_multipart('admin/auth/accounts/edit/' . $user_edit->id . '?' . $this->input->server('QUERY_STRING'));
    echo form_hidden('id', $user_edit->id);
    echo form_hidden('email_orig', $user_edit->email);
    echo form_hidden('username_orig', $user_edit->username);

    if (!empty($isModal)) {
        $this->load->view('accounts/edit/inc-actions');
    }

    $this->load->view('accounts/edit/inc-basic');
    $this->load->view('accounts/edit/inc-emails');
    $this->load->view('accounts/edit/inc-password');

    $oConfig = \Nails\Factory::service('Config');

    $oConfig->load('auth/auth');

    if ($oConfig->item('authTwoFactorMode') == 'QUESTION') {
        $this->load->view('accounts/edit/inc-mfa-question');
    }

    if ($oConfig->item('authTwoFactorMode') == 'DEVICE') {
        $this->load->view('accounts/edit/inc-mfa-device');
    }

    $this->load->view('accounts/edit/inc-meta');
    $this->load->view('accounts/edit/inc-profile-img');
    $this->load->view('accounts/edit/inc-uploads');

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
