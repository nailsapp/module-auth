<?php

echo form_field([
    'key'         => 'password',
    'label'       => lang('accounts_edit_password_field_password_label'),
    'placeholder' => lang('accounts_edit_password_field_password_placeholder'),
    'info'        => implode('', [
        '<div class="alert alert-info" style="margin:0;">',
        lang('accounts_edit_password_field_password_tip'),
        '<br />' . $aPasswordRules,
        '</div>',
    ]),
]);

echo form_field_radio([
    'key'     => 'temp_pw',
    'label'   => lang('accounts_edit_password_field_temp_pw_label'),
    'options' => [
        [
            'value'    => true,
            'label'    => lang('accounts_edit_password_field_temp_pw_yes'),
            'selected' => $oUser->temp_pw,
        ],
        [
            'value'    => false,
            'label'    => lang('accounts_edit_password_field_temp_pw_no'),
            'selected' => !$oUser->temp_pw,
        ],
    ],
]);


