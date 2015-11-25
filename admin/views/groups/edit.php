<div class="group-accounts groups edit">
    <div class="alert alert-warning">
        <div class="padder">
            <p>
                <?=lang('accounts_groups_edit_warning')?>
            </p>
        </div>
    </div>
    <hr />
    <?=form_open()?>
        <!--    BASICS  -->
        <fieldset>
            <legend>
                <?=lang('accounts_groups_edit_basic_legend')?>
            </legend>
            <?php

            //  Display Name
            $aField                = array();
            $aField['key']         = 'label';
            $aField['label']       = lang('accounts_groups_edit_basic_field_label_label');
            $aField['default']     = $group->label;
            $aField['required']    = true;
            $aField['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_label');

            echo form_field($aField);

            // --------------------------------------------------------------------------

            //  Name
            $aField                = array();
            $aField['key']         = 'slug';
            $aField['label']       = lang('accounts_groups_edit_basic_field_label_slug');
            $aField['default']     = $group->slug;
            $aField['required']    = true;
            $aField['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_slug');

            echo form_field($aField);

            // --------------------------------------------------------------------------

            //  Description
            $aField                = array();
            $aField['key']         = 'description';
            $aField['label']       = lang('accounts_groups_edit_basic_field_label_description');
            $aField['default']     = $group->description;
            $aField['required']    = true;
            $aField['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_description');

            echo form_field($aField);

            // --------------------------------------------------------------------------

            //  Default Homepage
            $aField                = array();
            $aField['key']         = 'default_homepage';
            $aField['label']       = lang('accounts_groups_edit_basic_field_label_homepage');
            $aField['default']     = $group->default_homepage;
            $aField['required']    = true;
            $aField['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_homepage');

            echo form_field($aField, lang('accounts_groups_edit_basic_field_tip_homepage'));

            // --------------------------------------------------------------------------

            //  Registration Redirect
            $aField                = array();
            $aField['key']         = 'registration_redirect';
            $aField['label']       = lang('accounts_groups_edit_basic_field_label_registration');
            $aField['default']     = $group->registration_redirect;
            $aField['required']    = false;
            $aField['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_registration');

            echo form_field($aField, lang('accounts_groups_edit_basic_field_tip_registration'));

            ?>
        </fieldset>
        <!--    PASSWORD RULES  -->
        <fieldset>
            <legend>
                <?=lang('accounts_groups_edit_password_legend')?>
            </legend>
            <?php

            $aField                = array();
            $aField['key']         = 'pw[min]';
            $aField['label']       = lang('accounts_groups_edit_password_field_label_min_length');
            $aField['default']     = isset($group->password_rules->min) ? $group->password_rules->min : '';
            $aField['required']    = false;
            $aField['placeholder'] = lang('accounts_groups_edit_password_field_placeholder_min_length');
            $aField['tip']         = lang('accounts_groups_edit_password_field_tip_min_length');

            echo form_field($aField);

            // --------------------------------------------------------------------------

            $aField                = array();
            $aField['key']         = 'pw[max]';
            $aField['label']       = lang('accounts_groups_edit_password_field_label_max_length');
            $aField['default']     = isset($group->password_rules->max) ? $group->password_rules->max : '';
            $aField['required']    = false;
            $aField['placeholder'] = lang('accounts_groups_edit_password_field_placeholder_max_length');
            $aField['tip']         = lang('accounts_groups_edit_password_field_tip_max_length');

            echo form_field($aField);

            // --------------------------------------------------------------------------

            $aField                = array();
            $aField['key']         = 'pw[expires_after]';
            $aField['label']       = lang('accounts_groups_edit_password_field_label_expires_after');
            $aField['default']     = isset($group->password_rules->expiresAfter) ? $group->password_rules->expiresAfter : '';
            $aField['required']    = false;
            $aField['placeholder'] = lang('accounts_groups_edit_password_field_placeholder_expires_after');
            $aField['tip']         = lang('accounts_groups_edit_password_field_tip_expires_after');

            echo form_field_number($aField);

            // --------------------------------------------------------------------------

            $aField                = array();
            $aField['key']         = 'pw[requirements][]';
            $aField['label']       = lang('accounts_groups_edit_password_field_label_requirements');
            $aField['default']     = isset($group->password_rules->requirements) ? $group->password_rules->requirements : array('symbol' => true);
            $aField['required']    = false;

            $aOptions = array(
                array(
                    'label' => lang('accounts_groups_edit_password_field_label_requirements_symbol'),
                    'value' => 'symbol',
                    'selected' => !empty($group->password_rules->requirements->symbol)
                ),
                array(
                    'label' => lang('accounts_groups_edit_password_field_label_requirements_number'),
                    'value' => 'number',
                    'selected' => !empty($group->password_rules->requirements->number)
                ),
                array(
                    'label' => lang('accounts_groups_edit_password_field_label_requirements_lower'),
                    'value' => 'lower_alpha',
                    'selected' => !empty($group->password_rules->requirements->lower)
                ),
                array(
                    'label' => lang('accounts_groups_edit_password_field_label_requirements_upper'),
                    'value' => 'upper_alpha',
                    'selected' => !empty($group->password_rules->requirements->upper)
                ),
                array(
                    'label' => lang('accounts_groups_edit_password_field_label_requirements_not_username'),
                    'value' => 'not_username',
                    'selected' => !empty($group->password_rules->requirements->not_username),
                    'disabled' => true
                ),
                array(
                    'label' => lang('accounts_groups_edit_password_field_label_requirements_not_name'),
                    'value' => 'not_name',
                    'selected' => !empty($group->password_rules->requirements->not_name),
                    'disabled' => true
                ),
                array(
                    'label' => lang('accounts_groups_edit_password_field_label_requirements_not_dob'),
                    'value' => 'not_dob',
                    'selected' => !empty($group->password_rules->requirements->not_dob),
                    'disabled' => true
                )
            );

            echo form_field_checkbox($aField, $aOptions);

            // --------------------------------------------------------------------------

            $aField['key']         = 'pw[banned]';
            $aField['label']       = lang('accounts_groups_edit_password_field_label_banned');
            $aField['default']     = isset($group->password_rules->banned) ? implode(',', $group->password_rules->banned) : '';
            $aField['required']    = false;
            $aField['placeholder'] = lang('accounts_groups_edit_password_field_placeholder_banned');

            echo form_field($aField);

            ?>
        </fieldset>
        <!--    PERMISSIONS -->
        <fieldset id="permissions">
            <legend>
                <?=lang('accounts_groups_edit_permission_legend')?>
            </legend>
            <p class="alert alert-warning">
                <?=lang('accounts_groups_edit_permission_warn')?>
            </p>
            <p>
                <?=lang('accounts_groups_edit_permission_intro')?>
            </p>
            <?php

            //  Enable Super User status for this user group
            $aField          = array();
            $aField['key']   = 'acl[admin][superuser]';
            $aField['label'] = lang('accounts_groups_edit_permissions_field_label_superuser');
            if (!empty($group->acl)) {

                $sCheckKey = 'admin:superuser';
                $aField['default'] = in_array($sCheckKey, $group->acl);

            } else {

                $aField['default'] = false;
            }
            $aField['required'] = false;
            $aField['id']       = 'toggleSuperuser';

            echo form_field_boolean($aField);

            // --------------------------------------------------------------------------

            $sDisplay = $aField['default'] ? 'none' : 'block';

            ?>
            <div id="adminPermissions" class="permission-groups" style="display:<?=$sDisplay?>;">
                <div class="search" id="permissionSearch">
                    <div class="search-text">
                        <?=form_input('', '', 'autocomplete="off" placeholder="Type to filter permissions"')?>
                    </div>
                </div>
                <?php

                $iNumPermissions = count($permissions);

                for ($i=0; $i < $iNumPermissions; $i++) {

                    $sPermissionSlug = $permissions[$i]->slug;

                    ?>
                    <fieldset class="permission-group">
                        <legend><?=$permissions[$i]->label?></legend>
                        <table>
                            <thead>
                                <tr>
                                    <th class="permission">Permission</th>
                                    <th class="enabled text-center">
                                        <input type="checkbox" class="toggleAll">
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php

                            foreach ($permissions[$i]->permissions as $permission => $label) {

                                $key      = 'acl[admin][' . $sPermissionSlug . '][' . $permission . ']';
                                $sCheckKey = 'admin:' . $sPermissionSlug . ':' . $permission;

                                if (!empty($_POST)) {

                                    $bIsChecked = !empty($_POST['acl']['admin'][$sPermissionSlug][$permission]);

                                } elseif (!empty($group->acl)) {

                                    $bIsChecked = in_array($sCheckKey, $group->acl);

                                } else {

                                    $bIsChecked = false;
                                }

                                $contextColor = $bIsChecked ? 'success' : 'error';

                                ?>
                                <tr>
                                    <td class="permission"><?=$label?></td>
                                    <td class="enabled text-center <?=$contextColor?>">
                                        <label>
                                            <?=form_checkbox($key, true, $bIsChecked)?>
                                        </label>
                                    </td>
                                </tr>
                                <?php

                            }

                            ?>
                            <tbody>
                        </table>
                    </fieldset>
                    <?php

                }

                ?>
                </div>
        </fieldset>
        <p>
            <?=form_submit('submit', lang('action_save_changes'), 'class="btn btn-primary"')?>
        </p>
    <?=form_close()?>
</div>
