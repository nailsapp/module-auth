<div class="group-settings site">
    <p>
        Configure how the site handles authentication.
    </p>
    <hr />
    <?php

        echo form_open();
        echo '<input type="hidden" name="activeTab" value="' . set_value('activeTab') . '" id="activeTab" />';

    ?>
    <ul class="tabs">
        <?php

        if (userHasPermission('admin:auth:settings:update:registration')) {

            $active = $this->input->post('activeTab') == 'tab-registration' || !$this->input->post('activeTab') ? 'active' : '';

            ?>
            <li class="tab <?=$active?>">
                <a href="#" data-tab="tab-registration">Registration</a>
            </li>
            <?php
        }

        if (userHasPermission('admin:auth:groups:edit')) {

            $active = $this->input->post('activeTab') == 'tab-password' ? 'active' : '';

            ?>
            <li class="tab <?=$active?>">
                <a href="#" data-tab="tab-password">Password</a>
            </li>
            <?php
        }

        if (userHasPermission('admin:auth:settings:update:social')) {

            if (!empty($providers)) {

                $active = $this->input->post('activeTab') == 'tab-social' ? 'active' : '';

                ?>
                <li class="tab <?=$active?>">
                    <a href="#" data-tab="tab-social">Social Integration</a>
                </li>
                <?php
            }
        }

        ?>
    </ul>
    <section class="tabs">
        <?php

        if (userHasPermission('admin:auth:settings:update:registration')) {

            $display = $this->input->post('activeTab') == 'tab-registration' || !$this->input->post('activeTab') ? 'active' : '';

            ?>
            <div class="tab-page tab-registration <?=$display?>">
                <div class="fieldset">
                <?php

                    $field            = array();
                    $field['key']     = 'user_registration_enabled';
                    $field['label']   = 'Registration Enabled';
                    $field['default'] = appSetting($field['key'], 'auth') ? true : false;

                    echo form_field_boolean($field, 'Admin will always be able to create users.');

                ?>
                </div>
            </div>
            <?php
        }

        if (userHasPermission('admin:auth:groups:edit')) {

            $display = $this->input->post('activeTab') == 'tab-password' ? 'active' : '';

            ?>
            <div class="tab-page tab-password <?=$display?>">
                Configure user password rules and properties from within the
                <?=anchor('admin/auth/groups/index', 'Groups')?> section of admin.
            </div>
            <?php
        }

        if (userHasPermission('admin:auth:settings:update:social')) {

            if (!empty($providers)) {

                $display = $this->input->post('activeTab') == 'tab-social' ? 'active' : '';

                ?>
                <div class="tab-page tab-social <?=$display?>">
                    <div class="fieldset" id="site-settings-socialsignin">
                        <p>
                            With the exception of OpenID providers, each social network requires that you
                            create an external application which links your website to theirs. These external
                            applications ensure that users are logging into the proper website and allows the
                            network to send the user back to the correct website after successfully authenticating
                            their account.
                        </p>
                        <p>
                            You can refer to <?=anchor('http://hybridauth.sourceforge.net/userguide.html', 'HybridAuth\'s Documentation', 'target="_blank"')?> for
                            instructions on how to create these applications.
                        </p>
                        <hr />
                        <?php

                        foreach ($providers as $provider) {

                            $field            = array();
                            $field['key']     = 'auth_social_signon_' . $provider['slug'] . '_enabled';
                            $field['label']   = $provider['label'];
                            $field['default'] = appSetting($field['key'], 'auth') ? true : false;

                            ?>
                            <div class="field checkbox boolean configure-provider">
                                <span class="label">
                                    <?=$field['label']?>
                                </span>
                                <span class="input">
                                    <?php

                                    $selected = set_value($field['key'], (bool) $field['default']);

                                    echo '<div class="toggle toggle-modern"></div>';
                                    echo form_checkbox($field['key'], true, $selected);
                                    echo $provider['fields'] ? '<a href="#configure-provider-' . $provider['slug'] . '" class="btn btn-warning fancybox">Configure</a>' : '';
                                    echo form_error($field['key'], '<span class="error">', '</span>');

                                    ?>
                                </span>
                                <div id="configure-provider-<?=$provider['slug']?>" class="configure-provider-fancybox" style="min-width:500px;display:none;">
                                    <p style="text-align:center;">
                                        Please provide the following information. Fields marked with a * are required.
                                    </p>
                                    <?php

                                    foreach ($provider['fields'] as $key => $label) {

                                        /**
                                         * Secondary conditional detects an actual array fo fields rather than
                                         * just the label/required array. Design could probably be improved...
                                         **/

                                        if (is_array($label) && !isset($label['label'])) {

                                            foreach ($label as $key1 => $label1) {

                                                $field             = array();
                                                $field['key']      = 'auth_social_signon_' . $provider['slug'] . '_' . $key . '_' . $key1;
                                                $field['label']    = $label1['label'];
                                                $field['required'] = $label1['required'];
                                                $field['default']  = appSetting($field['key'], 'auth');

                                                echo form_field($field);
                                            }

                                        } else {

                                            $field             = array();
                                            $field['key']      = 'auth_social_signon_' . $provider['slug'] . '_' . $key;
                                            $field['label']    = $label['label'];
                                            $field['required'] = $label['required'];
                                            $field['default']  = appSetting($field['key'], 'auth');

                                            echo form_field($field);
                                        }
                                    }

                                    ?>
                                </div>
                            </div>
                            <?php
                        }

                        ?>
                    </div>
                </div>
                <?php
            }
        }

    ?>
    </section>
    <p>
        <?=form_submit('submit', lang('action_save_changes'), 'class="btn btn-primary"')?>
    </p>
    <?=form_close()?>
</div>
