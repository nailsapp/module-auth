<?php
echo form_field_radio([
    'key'     => 'reset_mfa_device',
    'label'   => lang('accounts_edit_mfa_device_field_reset_label'),
    'options' => [
        [
            'value'    => true,
            'label'    => lang('accounts_edit_mfa_device_field_reset_yes'),
            'selected' => set_radio('reset_mfa_device') ? true : false,
        ],
        [
            'value'    => false,
            'label'    => lang('accounts_edit_mfa_device_field_reset_no'),
            'selected' => !set_radio('reset_mfa_device') ? true : false,
        ],
    ],
]);
