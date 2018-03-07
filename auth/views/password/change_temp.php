<div class="nailsapp-auth reset-password u-center-screen">
    <div class="panel">
        <h1 class="panel__header text-center">
            Reset Password
        </h1>
        <?php

        $aQuery = [];

        if ($return_to) {
            $aQuery['return_to'] = $return_to;
        }

        if ($remember) {
            $aQuery['remember'] = $remember;
        }

        $sQuery = $aQuery ? '?' . http_build_query($aQuery) : '';

        echo form_open(
            'auth/password/reset/' . $auth->id . '/' . $auth->hash . $sQuery,
            'class="form form-horizontal"'
        );

        ?>
        <div class="panel__body">
            <?php

            if (!empty($mfaQuestion)) {

                $sField            = 'mfaAnswer';
                $sFieldLabel       = 'Security Question';
                $sFieldPlaceholder = 'Type your answer';
                $sFieldAttr        = 'id="input-' . $sField . '" placeholder="' . $sFieldPlaceholder . '"';

                ?>
                <div class="form__group <?=form_error($sField) ? 'has-error' : ''?>">
                    <label for="input-<?=$sField?>">
                        <?=$sFieldLabel?>:
                    </label>
                    <?php

                    echo '<p><strong>' . $mfaQuestion->question . '</strong></p>';
                    echo form_password($sField, set_value($sField), $sFieldAttr);
                    echo form_error($sField, '<p class="help-block">', '</p>');

                    ?>
                </div>
                <?php
            }

            // --------------------------------------------------------------------------

            if (!empty($mfaDevice)) {

                $sField            = 'mfaCode';
                $sFieldLabel       = 'Security Code';
                $sFieldPlaceholder = 'Type your code';
                $sFieldAttr        = 'id="input-' . $sField . '" placeholder="' . $sFieldPlaceholder . '"';

                ?>
                <div class="form__group <?=form_error($sField) ? 'has-error' : ''?>">
                    <label for="input-<?=$sField?>">
                        <?=$sFieldLabel?>:
                    </label>
                    <?php

                    echo form_input($sField, set_value($sField), $sFieldAttr);
                    echo '<p class="help-block">';
                    echo '<small>';
                    echo 'Use your device to generate a single use code.';
                    echo '</small>';
                    echo '</p>';
                    echo form_error($sField, '<p class="help-block">', '</p>');

                    ?>
                </div>
                <?php
            }

            // --------------------------------------------------------------------------

            $sField            = 'new_password';
            $sFieldLabel       = lang('form_label_password');
            $sFieldPlaceholder = lang('auth_forgot_new_pass_placeholder');
            $sFieldAttr        = 'id="input-' . $sField . '" placeholder="' . $sFieldPlaceholder . '"';

            ?>
            <div class="form__group <?=form_error($sField) ? 'has-error' : ''?>">
                <label for="input-<?=$sField?>">
                    <?=$sFieldLabel?>:
                </label>
                <?php

                echo form_password($sField, set_value($sField), $sFieldAttr);
                if (!empty($passwordRules)) {

                    echo '<p class="help-block">';
                    echo '<small>';
                    echo $passwordRules;
                    echo '</small>';
                    echo '</p>';
                }
                echo form_error($sField, '<p class="help-block">', '</p>');

                ?>
            </div>
            <?php

            $sField            = 'confirm_pass';
            $sFieldLabel       = lang('form_label_password_confirm');
            $sFieldPlaceholder = lang('auth_forgot_new_pass_confirm_placeholder');
            $sFieldAttr        = 'id="input-' . $sField . '" placeholder="' . $sFieldPlaceholder . '"';

            ?>
            <div class="form__group <?=form_error($sField) ? 'has-error' : ''?>">
                <label for="input-<?=$sField?>"><?=$sFieldLabel?>:</label>
                <?php

                echo form_password($sField, set_value($sField), $sFieldAttr);
                echo form_error($sField, '<p class="help-block">', '</p>');

                ?>
            </div>
            <p>
                <button type="submit" class="btn btn--block">
                    <?=lang('auth_forgot_action_reset_continue')?>
                </button>
            </p>
        </div>
        <?=form_close()?>
    </div>
</div>
