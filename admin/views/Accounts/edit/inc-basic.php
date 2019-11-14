<?php

use Nails\Auth\Resource\User;

/**
 * @var User       $oUser
 * @var string     $sDefaultTimezone
 * @var string[]   $aTimezones
 * @var stdClass[] $aDateFormats
 * @var stdClass[] $aTimeFormats
 * @var stdClass[] $aLanguages
 */

?>
<fieldset id="edit-user-basic">
    <legend>
        <?=lang('accounts_edit_basic_legend')?>
    </legend>
    <div class="box-container">
        <?php

        echo form_field([
            'key'         => 'first_name',
            'label'       => lang('form_label_first_name'),
            'default'     => $oUser->first_name,
            'required'    => true,
            'placeholder' => lang('accounts_edit_basic_field_first_placeholder'),
        ]);

        echo form_field([
            'key'         => 'last_name',
            'label'       => lang('form_label_last_name'),
            'default'     => $oUser->last_name,
            'required'    => true,
            'placeholder' => lang('accounts_edit_basic_field_last_placeholder'),
        ]);

        echo form_field([
            'key'         => 'username',
            'label'       => lang('accounts_edit_basic_field_username_label'),
            'default'     => $oUser->username,
            'required'    => false,
            'placeholder' => lang('accounts_edit_basic_field_username_placeholder'),
            'info'        => 'Username can only contain alpha numeric characters, underscores, periods and dashes (no spaces).',
        ]);

        echo form_field_dropdown([
            'key'     => 'gender',
            'label'   => lang('accounts_edit_basic_field_gender_label'),
            'default' => $oUser->gender,
            'class'   => 'select2',
            'options' => [
                'UNDISCLOSED' => 'Undisclosed',
                'MALE'        => 'Male',
                'FEMALE'      => 'Female',
                'TRANSGENDER' => 'Transgender',
                'OTHER'       => 'Other',
            ],
        ]);

        echo form_field_date([
            'key'     => 'dob',
            'label'   => lang('accounts_edit_basic_field_dob_label'),
            'default' => $oUser->dob,
            'class'   => 'select2',
        ]);

        echo form_field_dropdown([
            'key'     => 'timezone',
            'label'   => lang('accounts_edit_basic_field_timezone_label'),
            'default' => $oUser->timezone ? $oUser->timezone : $sDefaultTimezone,
            'class'   => 'select2',
            'options' => $aTimezones,
            'tip'     => lang('accounts_edit_basic_field_timezone_tip'),
        ]);

        echo form_field_dropdown([
            'key'      => 'datetime_format_date',
            'label'    => lang('accounts_edit_basic_field_date_format_label'),
            'default'  => $oUser->datetime_format_date ? $oUser->datetime_format_date : APP_DEFAULT_DATETIME_FORMAT_DATE_SLUG,
            'required' => false,
            'class'    => 'select2',
            'tip'      => lang('accounts_edit_basic_field_date_format_tip'),
            'options'  => array_combine(
                array_map(function (stdClass $oFormat) {
                    return $oFormat->slug;
                }, $aDateFormats),
                array_map(function (stdClass $oFormat) {
                    return $oFormat->label . ' (' . $oFormat->example . ')';
                }, $aDateFormats)
            ),
        ]);

        echo form_field_dropdown([
            'key'      => 'datetime_format_time',
            'label'    => lang('accounts_edit_basic_field_time_format_label'),
            'default'  => $oUser->datetime_format_time ? $oUser->datetime_format_time : APP_DEFAULT_DATETIME_FORMAT_TIME_SLUG,
            'required' => false,
            'class'    => 'select2',
            'tip'      => lang('accounts_edit_basic_field_time_format_tip'),
            'options'  => array_combine(
                array_map(function (stdClass $oFormat) {
                    return $oFormat->slug;
                }, $aTimeFormats),
                array_map(function (stdClass $oFormat) {
                    return $oFormat->label . ' (' . $oFormat->example . ')';
                }, $aTimeFormats)
            ),
        ]);

        echo form_field([
            'key'      => 'ip_address',
            'label'    => lang('accounts_edit_basic_field_register_ip_label'),
            'default'  => $oUser->ip_address,
            'readonly' => true,
        ]);

        echo form_field([
            'key'      => 'last_ip',
            'label'    => lang('accounts_edit_basic_field_last_ip_label'),
            'default'  => $oUser->last_ip,
            'readonly' => true,
        ]);

        echo form_field([
            'key'      => 'created',
            'label'    => lang('accounts_edit_basic_field_created_label'),
            'default'  => toUserDatetime($oUser->created),
            'readonly' => true,
        ]);

        echo form_field([
            'key'      => 'last_update',
            'label'    => lang('accounts_edit_basic_field_modified_label'),
            'default'  => toUserDatetime($oUser->last_update),
            'readonly' => true,
        ]);

        echo form_field([
            'key'      => 'login_count',
            'label'    => lang('accounts_edit_basic_field_logincount_label'),
            'default'  => $oUser->login_count ? $oUser->login_count : lang('accounts_edit_basic_field_not_logged_in'),
            'readonly' => true,
        ]);

        echo form_field([
            'key'      => 'last_login',
            'label'    => lang('accounts_edit_basic_field_last_login_label'),
            'default'  => $oUser->last_login ? toUserDatetime($oUser->last_login) : lang('accounts_edit_basic_field_not_logged_in'),
            'readonly' => true,
        ]);

        echo form_field([
            'key'      => 'referral',
            'label'    => lang('accounts_edit_basic_field_referral_label'),
            'default'  => $oUser->referral,
            'readonly' => true,
        ]);

        echo form_field([
            'key'         => 'referred_by',
            'label'       => lang('accounts_edit_basic_field_referred_by_label'),
            'default'     => $oUser->referred_by ? 'User ID: ' . $oUser->referred_by : 'Not referred',
            'placeholder' => lang('accounts_edit_basic_field_referred_by_placeholder'),
            'readonly'    => true,
        ]);

        ?>
    </div>
</fieldset>
