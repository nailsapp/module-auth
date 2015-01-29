<fieldset id="edit-user-mfa-device">
    <legend><?=lang('accounts_edit_mfa_device_legend')?></legend>
    <div class="box-container">
    <?php

        //  Require new MFA Device on log in
        $field             = array();
        $field['key']      = 'reset_mfa_device';
        $field['label']    = lang('accounts_edit_mfa_device_field_reset_label');
        $field['default']  = false;
        $field['required'] = false;

        $options   = array();

        $options[] = array(
            'value'    => 'true',
            'label'    => lang('accounts_edit_mfa_device_field_reset_yes'),
            'selected' => set_radio($field['key']) ? true : false
       );

        $options[] = array(
            'value'    => 'false',
            'label'    => lang('accounts_edit_mfa_device_field_reset_no'),
            'selected' => !set_radio($field['key']) ? true : false
       );

        echo form_field_radio($field, $options);

    ?>
    </div>
</fieldset>