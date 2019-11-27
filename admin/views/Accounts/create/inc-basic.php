<?php

use Nails\Auth\Constants;
use Nails\Auth\Model\User\Group;
use Nails\Factory;

/** @var Group $oUserGroupModel */
$oUserGroupModel = Factory::model('UserGroup', Constants::MODULE_SLUG);

?>
<fieldset>
    <legend><?=lang('accounts_create_basic_legend')?></legend>
    <div class="box-container">
        <?php

        //  Group ID
        $aField = [
            'key'      => 'group_id',
            'label'    => lang('accounts_create_field_group_label'),
            'required' => true,
            'default'  => $oUserGroupModel->getDefaultGroupId(),
            'class'    => 'select2',
        ];

        //  Prepare ID's
        $aGroupsById = [];
        foreach ($groups as $oGroup) {

            //  If the group is a superuser group and the active user is not a superuser then remove it
            if (is_array($oGroup->acl) && in_array('admin:superuser', $oGroup->acl) && !isSuperuser()) {
                continue;
            }

            $aGroupsById[$oGroup->id] = $oGroup->label;
        }

        //  Render the group descriptions
        $iDefaultGroupId = $oUserGroupModel->getDefaultGroupId();
        $aField['info']  = '<ul id="user-group-descriptions">';
        foreach ($groups as $oGroup) {

            if (is_array($oGroup->acl) && in_array('admin:superuser', $oGroup->acl) && !isSuperuser()) {
                continue;
            }

            $sDisplay       = $oGroup->id == $iDefaultGroupId ? 'block' : 'none';
            $aField['info'] .= '<li class="alert alert-info" id="user-group-' . $oGroup->id . '" style="display:' . $sDisplay . ';">';
            $aField['info'] .= $oGroup->description;
            $aField['info'] .= '</li>';
        }
        $aField['info'] .= '</ul>';

        echo form_field_dropdown($aField, $aGroupsById, lang('accounts_create_field_group_tip'));

        // --------------------------------------------------------------------------

        //  Password
        $aField = [
            'key'         => 'password',
            'label'       => lang('form_label_password'),
            'placeholder' => lang('accounts_create_field_password_placeholder'),
        ];

        //  Render password rules
        $aField['info'] = '<ul id="user-group-pwrules">';
        foreach ($passwordRules as $iGroupId => $sRules) {
            if (!empty($sRules)) {
                $sDisplay       = $iGroupId == $iDefaultGroupId ? 'block' : 'none';
                $aField['info'] .= '<li class="alert alert-info" id="user-group-pw-' . $iGroupId . '" style="display:' . $sDisplay . ';">';
                $aField['info'] .= $sRules;
                $aField['info'] .= '</li>';
            }
        }
        $aField['info'] .= '</ul>';

        echo form_field($aField, lang('accounts_create_field_password_tip'));

        // --------------------------------------------------------------------------

        echo form_field_radio([
            'key'      => 'send_activation',
            'label'    => lang('accounts_create_field_send_welcome_label'),
            'default'  => false,
            'required' => false,
            'options'  => [
                [
                    'value'    => 'true',
                    'label'    => lang('accounts_create_field_send_welcome_yes'),
                    'selected' => true,
                ],
                [
                    'value'    => 'false',
                    'label'    => lang('accounts_create_field_send_welcome_no'),
                    'selected' => false,
                ],
            ],
        ]);

        echo form_field_radio([
            'key'      => 'temp_pw',
            'label'    => lang('accounts_create_field_temp_pw_label'),
            'default'  => false,
            'required' => false,
            'options'  => [
                [
                    'value'    => 'true',
                    'label'    => lang('accounts_create_field_temp_pw_yes'),
                    'selected' => true,
                ],
                [
                    'value'    => 'false',
                    'label'    => lang('accounts_create_field_temp_pw_no'),
                    'selected' => false,
                ],
            ],
        ]);

        echo form_field([
            'key'         => 'first_name',
            'label'       => lang('form_label_first_name'),
            'required'    => true,
            'placeholder' => lang('accounts_create_field_first_placeholder'),
            'max_length'  => 150,
        ]);

        echo form_field([
            'key'         => 'last_name',
            'label'       => lang('form_label_last_name'),
            'required'    => true,
            'placeholder' => lang('accounts_create_field_last_placeholder'),
            'max_length'  => 150,
        ]);

        echo form_field([
            'key'         => 'email',
            'label'       => lang('form_label_email'),
            'required'    => APP_NATIVE_LOGIN_USING == 'EMAIL' || APP_NATIVE_LOGIN_USING != 'USERNAME',
            'placeholder' => lang('accounts_create_field_email_placeholder'),
            'max_length'  => 255,
        ]);

        if (in_array(APP_NATIVE_LOGIN_USING, ['BOTH', 'USERNAME'])) {
            echo form_field([
                'key'         => 'username',
                'label'       => lang('form_label_username'),
                'required'    => true,
                'placeholder' => lang('accounts_create_field_username_placeholder'),
                'info'        => '<div class="alert alert-info">Username can only contain alpha numeric characters, underscores, periods and dashes (no spaces).</div>',
                'max_length'  => 150,
            ]);
        }

        ?>
    </div>
</fieldset>
