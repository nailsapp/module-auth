<?php

$sReturnTo = $return_to ? '?return_to=' . urlencode($return_to) : '';

?>
<div class="nails-auth login u-center-screen">
    <div class="panel">
        <h1 class="panel__header text-center">
            Welcome
        </h1>
        <div class="panel__body">
            <p class="alert alert--danger <?=empty($error) ? 'hidden' : ''?>">
                <?=$error?>
            </p>
            <p class="alert alert--success <?=empty($success) ? 'hidden' : ''?>">
                <?=$success?>
            </p>
            <p class="alert alert--warning <?=empty($message) ? 'hidden' : ''?>">
                <?=$message?>
            </p>
            <p class="alert alert--info <?=empty($info) ? 'hidden' : ''?>">
                <?=$info?>
            </p>
            <?php
            if ($social_signon_enabled) {
                ?>
                <p class="text-center">
                    Sign in using your preferred social network.
                </p>
                <?php

                foreach ($social_signon_providers as $aProvider) {
                    echo anchor(
                        'auth/login/' . $aProvider['slug'] . $sReturnTo,
                        $aProvider['label'],
                        'class="btn btn--block btn--primary"'
                    );
                }

                ?>
                <hr/>
                <p class="text-center">
                    <?php
                    switch (APP_NATIVE_LOGIN_USING) {
                        case 'EMAIL':
                            echo 'Or sign in using your email address and password.';
                            break;

                        case 'USERNAME':
                            echo 'Or sign in using your username and password.';
                            break;

                        default:
                            echo 'Or sign in using your email address or username and password.';
                            break;
                    }
                    ?>
                </p>
                <?php
            }

            echo form_open(site_url('auth/login' . $sReturnTo));

            switch (APP_NATIVE_LOGIN_USING) {

                case 'EMAIL':
                    $sFieldLabel       = lang('form_label_email');
                    $sFieldPlaceholder = lang('auth_login_email_placeholder');
                    $FieldType         = 'form_email';
                    break;

                case 'USERNAME':
                    $sFieldLabel       = lang('form_label_username');
                    $sFieldPlaceholder = lang('auth_login_username_placeholder');
                    $FieldType         = 'form_input';
                    break;

                default:
                    $sFieldLabel       = lang('auth_login_both');
                    $sFieldPlaceholder = lang('auth_login_both_placeholder');
                    $FieldType         = 'form_input';
                    break;
            }

            $sFieldKey = 'identifier';

            ?>
            <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                <?=$FieldType($sFieldKey, set_value($sFieldKey), 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"')?>
                <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
            </div>
            <?php

            $sFieldKey         = 'password';
            $sFieldLabel       = lang('form_label_password');
            $sFieldPlaceholder = lang('auth_login_password_placeholder');

            ?>
            <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                <?=form_password($sFieldKey, set_value($sFieldKey), 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"')?>
                <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
            </div>
            <div class="form__group form__group--checkbox">
                <div class="col-sm-offset-3 col-sm-9">
                    <label>
                        <input type="checkbox" name="remember" <?=set_checkbox('remember')?>>
                        Remember me
                    </label>
                </div>
            </div>
            <p>
                <button type="submit" class="btn btn--block btn--primary">
                    Sign in
                </button>
                <?=anchor('auth/password/forgotten', 'Forgotten Your Password?', 'class="btn btn--block btn--link"')?>
            </p>
            <?=form_close()?>
            <?php
            if (appSetting('user_registration_enabled', 'auth')) {
                ?>
                <hr/>
                <p class="text-center">
                    Not got an account?
                </p>
                <p class="text-center">
                    <?=anchor('auth/register', 'Register now', 'class="btn btn--block"')?>
                </p>
                <?php
            }
            ?>
        </div>
    </div>
</div>
