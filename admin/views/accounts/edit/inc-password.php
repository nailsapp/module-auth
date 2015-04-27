<fieldset id="edit-user-password">
    <legend>
        <?=lang('accounts_edit_password_legend')?>
    </legend>
    <div class="box-container">
    <?php
        //  Reset Password
        $field                = array();
        $field['key']         = 'password';
        $field['label']       = lang('accounts_edit_password_field_password_label');
        $field['default']     = '';
        $field['required']    = false;
        $field['placeholder'] = lang('accounts_edit_password_field_password_placeholder');
        $field['info']         = lang('accounts_edit_password_field_password_tip');

        echo form_field($field);

        // --------------------------------------------------------------------------

        //  Require password update on log in
        $field             = array();
        $field['key']      = 'temp_pw';
        $field['label']    = lang('accounts_edit_password_field_temp_pw_label');
        $field['default']  = false;
        $field['required'] = false;

        $options   = array();

        $options[] = array(
            'value'    => 'TRUE',
            'label'    => lang('accounts_edit_password_field_temp_pw_yes'),
            'selected' => $user_edit->temp_pw ? true : false
        );

        $options[] = array(
            'value'    => 'FALSE',
            'label'    => lang('accounts_edit_password_field_temp_pw_no'),
            'selected' =>  ! $user_edit->temp_pw ? true : false
        );

        echo form_field_radio($field, $options);

    ?>
    </div>
</fieldset>