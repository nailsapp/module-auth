<?php

use Nails\Factory;

$oUserGroupModel = Factory::model('UserGroup', 'nails/module-auth');

?>
<fieldset>
    <legend><?=lang('accounts_create_basic_legend')?></legend>
    <div class="box-container">
        <?php

        //  Group ID
        $field             = [];
        $field['key']      = 'group_id';
        $field['label']    = lang('accounts_create_field_group_label');
        $field['required'] = true;
        $field['default']  = $oUserGroupModel->getDefaultGroupId();
        $field['class']    = 'select2';

        //  Prepare ID's
        $groupsById = [];
        foreach ($groups as $group) {

            //  If the group is a superuser group and the active user is not a superuser then remove it
            if (is_array($group->acl) && in_array('admin:superuser', $group->acl) && !isSuperuser()) {

                continue;
            }

            $groupsById[$group->id] = $group->label;
        }

        //  Render the group descriptions
        $iDefaultGroupId = $oUserGroupModel->getDefaultGroupId();
        $field['info']   = '<ul id="user-group-descriptions">';
        foreach ($groups as $group) {

            if (is_array($group->acl) && in_array('admin:superuser', $group->acl) && !isSuperuser()) {

                continue;
            }

            // --------------------------------------------------------------------------

            $display       = $group->id == $iDefaultGroupId ? 'block' : 'none';
            $field['info'] .= '<li class="alert alert-info" id="user-group-' . $group->id . '" style="display:' . $display . ';">';
            $field['info'] .= $group->description;
            $field['info'] .= '</li>';
        }
        $field['info'] .= '</ul>';

        echo form_field_dropdown($field, $groupsById, lang('accounts_create_field_group_tip'));

        // --------------------------------------------------------------------------

        //  Password
        $field                = [];
        $field['key']         = 'password';
        $field['label']       = lang('form_label_password');
        $field['placeholder'] = lang('accounts_create_field_password_placeholder');

        //  Render password rules
        $field['info'] = '<ul id="user-group-pwrules">';
        foreach ($passwordRules as $iGroupId => $sRules) {

            if (!empty($sRules)) {
                $display       = $iGroupId == $iDefaultGroupId ? 'block' : 'none';
                $field['info'] .= '<li class="alert alert-info" id="user-group-pw-' . $iGroupId . '" style="display:' . $display . ';">';
                $field['info'] .= $sRules;
                $field['info'] .= '</li>';
            }
        }
        $field['info'] .= '</ul>';

        echo form_field($field, lang('accounts_create_field_password_tip'));

        // --------------------------------------------------------------------------

        //  Send welcome/activation email
        $field             = [];
        $field['key']      = 'send_activation';
        $field['label']    = lang('accounts_create_field_send_welcome_label');
        $field['default']  = false;
        $field['required'] = false;

        $options   = [];
        $options[] = [
            'value'    => 'true',
            'label'    => lang('accounts_create_field_send_welcome_yes'),
            'selected' => true,
        ];
        $options[] = [
            'value'    => 'false',
            'label'    => lang('accounts_create_field_send_welcome_no'),
            'selected' => false,
        ];

        echo form_field_radio($field, $options);

        // --------------------------------------------------------------------------

        //  Require password update on log in
        $field             = [];
        $field['key']      = 'temp_pw';
        $field['label']    = lang('accounts_create_field_temp_pw_label');
        $field['default']  = false;
        $field['required'] = false;

        $options   = [];
        $options[] = [
            'value'    => 'true',
            'label'    => lang('accounts_create_field_temp_pw_yes'),
            'selected' => true,
        ];
        $options[] = [
            'value'    => 'false',
            'label'    => lang('accounts_create_field_temp_pw_no'),
            'selected' => false,
        ];

        echo form_field_radio($field, $options);

        // --------------------------------------------------------------------------

        //  First Name
        $field                = [];
        $field['key']         = 'first_name';
        $field['label']       = lang('form_label_first_name');
        $field['required']    = true;
        $field['placeholder'] = lang('accounts_create_field_first_placeholder');

        echo form_field($field);

        // --------------------------------------------------------------------------

        //  Last name
        $field                = [];
        $field['key']         = 'last_name';
        $field['label']       = lang('form_label_last_name');
        $field['required']    = true;
        $field['placeholder'] = lang('accounts_create_field_last_placeholder');

        echo form_field($field);

        // --------------------------------------------------------------------------

        //  Email address
        $field                = [];
        $field['key']         = 'email';
        $field['label']       = lang('form_label_email');
        $field['required']    = APP_NATIVE_LOGIN_USING == 'EMAIL' || APP_NATIVE_LOGIN_USING != 'USERNAME';
        $field['placeholder'] = lang('accounts_create_field_email_placeholder');

        echo form_field($field);

        // --------------------------------------------------------------------------

        //  Username
        $field                = [];
        $field['key']         = 'username';
        $field['label']       = lang('form_label_username');
        $field['required']    = APP_NATIVE_LOGIN_USING == 'USERNAME' || APP_NATIVE_LOGIN_USING != 'EMAIL';
        $field['placeholder'] = lang('accounts_create_field_username_placeholder');
        $field['info']        = '<div class="alert alert-info">Username can only contain alpha numeric characters, underscores, periods and dashes (no spaces).</div>';

        echo form_field($field);

        ?>
    </div>
</fieldset>
