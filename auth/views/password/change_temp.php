<?php
$oView = \Nails\Factory::service('View');
?>
<div class="container nails-module-auth password password-change-temp">
    <?php

    $oView->load('components/header');

    ?>
    <div class="row">
        <div class="col-sm-6 col-sm-offset-3">
            <div class="well well-lg">
                <?php

                $query = [];

                if ($return_to) {
                    $query['return_to'] = $return_to;
                }

                if ($remember) {
                    $query['remember'] = $remember;
                }

                $query = $query ? '?' . http_build_query($query) : '';

                echo form_open(
                    'auth/password/reset/' . $auth->id . '/' . $auth->hash . $query,
                    'class="form form-horizontal"'
                );

                // --------------------------------------------------------------------------

                if (!empty($mfaQuestion)) {

                    $field       = 'mfaAnswer';
                    $label       = 'Security Question';
                    $placeholder = 'Type your answer';
                    $fieldAttr   = 'id="input-' . $field . '" placeholder="' . $placeholder . '" class="form-control"';

                    ?>
                    <div class="form-group <?=form_error($field) ? 'has-error' : ''?>">
                        <label class="col-sm-3 control-label" for="input-<?=$field?>">
                            <?=$label?>:
                        </label>
                        <div class="col-sm-9">
                            <?php

                            echo '<p><strong>' . $mfaQuestion->question . '</strong></p>';
                            echo form_password($field, set_value($field), $fieldAttr);
                            echo form_error($field, '<p class="help-block">', '</p>');

                            ?>
                        </div>
                    </div>
                    <hr/>
                    <?php
                }

                // --------------------------------------------------------------------------

                if (!empty($mfaDevice)) {

                    $field       = 'mfaCode';
                    $label       = 'Security Code';
                    $placeholder = 'Type your code';
                    $fieldAttr   = 'id="input-' . $field . '" placeholder="' . $placeholder . '" class="form-control"';

                    ?>
                    <div class="form-group <?=form_error($field) ? 'has-error' : ''?>">
                        <label class="col-sm-3 control-label" for="input-<?=$field?>">
                            <?=$label?>:
                        </label>
                        <div class="col-sm-9">
                            <?php

                            echo form_input($field, set_value($field), $fieldAttr);
                            echo '<p class="help-block">';
                            echo '<small>';
                            echo 'Use your device to generate a single use code.';
                            echo '</small>';
                            echo '</p>';
                            echo form_error($field, '<p class="help-block">', '</p>');

                            ?>
                        </div>
                    </div>
                    <hr/>
                    <?php
                }

                // --------------------------------------------------------------------------

                $field       = 'new_password';
                $label       = lang('form_label_password');
                $placeholder = lang('auth_forgot_new_pass_placeholder');
                $fieldAttr   = 'id="input-' . $field . '" placeholder="' . $placeholder . '" class="form-control"';

                ?>
                <div class="form-group <?=form_error($field) ? 'has-error' : ''?>">
                    <label class="col-sm-3 control-label" for="input-<?=$field?>">
                        <?=$label?>:
                    </label>
                    <div class="col-sm-9">
                        <?php

                        echo form_password($field, set_value($field), $fieldAttr);
                        if (!empty($passwordRules)) {

                            echo '<p class="help-block">';
                            echo '<small>';
                            echo $passwordRules;
                            echo '</small>';
                            echo '</p>';
                        }
                        echo form_error($field, '<p class="help-block">', '</p>');

                        ?>
                    </div>
                </div>
                <?php

                $field       = 'confirm_pass';
                $label       = lang('form_label_password_confirm');
                $placeholder = lang('auth_forgot_new_pass_confirm_placeholder');
                $fieldAttr   = 'id="input-' . $field . '" placeholder="' . $placeholder . '" class="form-control"';

                ?>
                <div class="form-group <?=form_error($field) ? 'has-error' : ''?>">
                    <label class="col-sm-3 control-label" for="input-<?=$field?>"><?=$label?>:</label>
                    <div class="col-sm-9">
                        <?php

                        echo form_password($field, set_value($field), $fieldAttr);
                        echo form_error($field, '<p class="help-block">', '</p>');

                        ?>
                    </div>
                </div>
                <div class="form-group">
                    <div class="col-sm-offset-3 col-sm-9">
                        <button type="submit" class="btn btn-primary">
                            <?=lang('auth_forgot_action_reset_continue')?>
                        </button>
                    </div>
                </div>
                <?=form_close()?>
            </div>
        </div>
    </div>
    <?php

    $oView->load('components/footer');

    ?>
</div>
