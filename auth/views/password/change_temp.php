<div class="nails-auth reset-password u-center-screen">
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
            $resetUrl . $sQuery,
            'class="form form-horizontal"'
        );

        ?>
        <div class="panel__body">
            <?php

            if (!empty($mfaQuestion)) {

                $sFieldKey         = 'mfaAnswer';
                $sFieldLabel       = 'Security Question';
                $sFieldPlaceholder = 'Type your answer';
                $sFieldAttr        = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

                ?>
                <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                    <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                    <p>
                        <strong>
                            <?=$mfaQuestion->question?>
                        </strong>
                    </p>
                    <?=form_password($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                    <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                </div>
                <?php
            }

            // --------------------------------------------------------------------------

            if (!empty($mfaDevice)) {

                $sFieldKey         = 'mfaCode';
                $sFieldLabel       = 'Security Code';
                $sFieldPlaceholder = 'Type your code';
                $sFieldAttr        = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

                ?>
                <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                    <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                    <?=form_input($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                    <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                    <p class="form__help">
                        <small>
                            Use your device to generate a single use code.
                        </small>
                    </p>
                </div>
                <?php
            }

            // --------------------------------------------------------------------------

            $sFieldKey         = 'new_password';
            $sFieldLabel       = lang('form_label_password');
            $sFieldPlaceholder = lang('auth_forgot_new_pass_placeholder');
            $sFieldAttr        = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

            ?>
            <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                <?=form_password($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                <?php
                if (!empty($passwordRules)) {
                    ?>
                    <p class="form__help">
                        <small>
                            <?=$passwordRules?>
                        </small>
                    </p>
                    <?php
                }
                ?>
            </div>
            <?php

            $sFieldKey         = 'confirm_pass';
            $sFieldLabel       = lang('form_label_password_confirm');
            $sFieldPlaceholder = lang('auth_forgot_new_pass_confirm_placeholder');
            $sFieldAttr        = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

            ?>
            <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                <?=form_password($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
            </div>
            <p>
                <button type="submit" class="btn btn--block btn--primary">
                    <?=lang('auth_forgot_action_reset_continue')?>
                </button>
            </p>
        </div>
        <?=form_close()?>
    </div>
</div>
