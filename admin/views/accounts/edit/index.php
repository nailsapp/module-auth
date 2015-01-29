<?php

    echo '<div class="group-accounts edit">';
    echo form_open_multipart('admin/accounts/edit/' . $user_edit->id . '?' . $this->input->server('QUERY_STRING'));
    echo form_hidden('id', $user_edit->id);
    echo form_hidden('email_orig', $user_edit->email);
    echo form_hidden('username_orig', $user_edit->username);

    if (!$this->input->get('inline') || $this->input->get('is_fancybox')) {

        $this->load->view('accounts/edit/inc-actions');
    }

    $this->load->view('accounts/edit/inc-basic');
    $this->load->view('accounts/edit/inc-emails');
    $this->load->view('accounts/edit/inc-password');

    $this->config->load('auth/auth');

    if ($this->config->item('authTwoFactorMode') == 'QUESTION') {

        $this->load->view('accounts/edit/inc-mfa-question');
    }


    if ($this->config->item('authTwoFactorMode') == 'DEVICE') {

        $this->load->view('accounts/edit/inc-mfa-device');
    }

    $this->load->view('accounts/edit/inc-meta');
    $this->load->view('accounts/edit/inc-profile-img');
    $this->load->view('accounts/edit/inc-uploads');


    echo '<p>' . form_submit('submit', lang('action_save_changes'), 'class="awesome"') . '</p>';

    echo form_close();
    echo '</div>';


    // --------------------------------------------------------------------------

    //  email forms
    echo form_open('admin/accounts/email', 'id="emailForm"');
        echo form_hidden('id', $user_edit->id);
        echo form_hidden('return', uri_string() . '?' . $this->input->server('QUERY_STRING'));
        echo form_hidden('email');
        echo form_hidden('action');
        echo form_hidden('isPrimary');
        echo form_hidden('isVerified');
    echo form_close();

