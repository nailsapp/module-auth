<div class="group-accounts groups edit">
    <div class="system-alert message">
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
            <legend><?=lang('accounts_groups_edit_basic_legend')?></legend>
            <?php

                //  Display Name
                $field                = array();
                $field['key']         = 'label';
                $field['label']       = lang('accounts_groups_edit_basic_field_label_label');
                $field['default']     = $group->label;
                $field['required']    = true;
                $field['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_label');

                echo form_field($field);

                // --------------------------------------------------------------------------

                //  Name
                $field                = array();
                $field['key']         = 'slug';
                $field['label']       = lang('accounts_groups_edit_basic_field_label_slug');
                $field['default']     = $group->slug;
                $field['required']    = true;
                $field['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_slug');

                echo form_field($field);

                // --------------------------------------------------------------------------

                //  Description
                $field                = array();
                $field['key']         = 'description';
                $field['type']        = 'textarea';
                $field['label']       = lang('accounts_groups_edit_basic_field_label_description');
                $field['default']     = $group->description;
                $field['required']    = true;
                $field['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_description');

                echo form_field($field);

                // --------------------------------------------------------------------------

                //  Default Homepage
                $field                = array();
                $field['key']         = 'default_homepage';
                $field['label']       = lang('accounts_groups_edit_basic_field_label_homepage');
                $field['default']     = $group->default_homepage;
                $field['required']    = true;
                $field['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_homepage');

                echo form_field($field);

                // --------------------------------------------------------------------------

                //  Registration Redirect
                $field                = array();
                $field['key']         = 'registration_redirect';
                $field['label']       = lang('accounts_groups_edit_basic_field_label_registration');
                $field['default']     = $group->registration_redirect;
                $field['required']    = false;
                $field['placeholder'] = lang('accounts_groups_edit_basic_field_placeholder_registration');

                echo form_field($field, lang('accounts_groups_edit_basic_field_tip_registration'));

            ?>

        </fieldset>
        <!--    PERMISSIONS -->
        <fieldset id="permissions">
            <legend><?=lang('accounts_groups_edit_permission_legend')?></legend>
            <p class="system-alert message">
                <?=lang('accounts_groups_edit_permission_warn')?>
            </p>
            <p>
                <?=lang('accounts_groups_edit_permission_intro')?>
            </p>
            <hr />
            <?php

                //  Enable Super User status for this user group
                $field             = array();
                $field['key']      = 'acl[superuser]';
                $field['label']    = lang('accounts_groups_edit_permissions_field_label_superuser');
                $field['default']  = !empty($group->acl['superuser']);
                $field['required'] = false;
                $field['id']       = 'super-user';

                echo form_field_boolean($field);

                // --------------------------------------------------------------------------

                $_visible = $field['default'] ? 'none' : 'block';
                echo '<div id="toggle-superuser" class="permissionGroups" style="display:' . $_visible . ';">';

                    $numPermissions = count($permissions);
                    $rowOpen = false;
                    $perRow = 3;

                    for ($i=0; $i < $numPermissions; $i++) {

                        $permissionSlug = $permissions[$i]->slug;

                        if (!$rowOpen) {

                            echo '<div class="row">';
                            $rowOpen = true;
                        }

                        echo '<div class="col-md-4">';
                            echo '<fieldset class="permissionGroup">';
                                echo '<legend>' . $permissions[$i]->label . '</legend>';

                                echo '<div class="tableScroller">';
                                echo '<table>';
                                    echo '<thead>';
                                        echo '<tr>';
                                            echo '<th class="permission">Permission</th>';
                                            echo '<th class="enabled text-center">Enabled</th>';
                                        echo '</tr>';
                                    echo '</thead>';
                                    echo '<tbody>';

                                        foreach ($permissions[$i]->permissions as $permission => $label) {

                                            $contextColor = 1 == 0 ? 'success' : 'error';

                                            echo '<tr>';
                                                echo '<td class="permission">' . $label . '</td>';
                                                echo '<td class="enabled text-center ' . $contextColor . '">';
                                                    echo '<label>';
                                                        $key = 'acl[admin][' . $permissionSlug . '][' . $permission . ']';
                                                        echo form_checkbox($key, true, set_checkbox($key, true, true));
                                                    echo '</label>';
                                                echo '</td>';
                                            echo '</tr>';
                                        }

                                    echo '<tbody>';
                                echo '</table>';
                                echo '</div>';
                            echo '</fieldset>';
                        echo '</div>';

                        if ($i % $perRow == $perRow-1) {

                            echo '</div>';
                            $rowOpen = false;
                        }
                    }

                    if ($rowOpen) {

                        echo '</div>';
                    }

                echo '</div>';

            ?>

        </fieldset>

        <p>
            <?=form_submit('submit', lang('action_save_changes'), 'class="awesome"')?>
        </p>

    <?=form_close()?>
</div>