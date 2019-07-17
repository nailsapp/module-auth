<?php
$oInput = \Nails\Factory::service('Input');
?>
<div class="nails-auth forgotten-password u-center-screen">
    <div class="panel">
        <h1 class="panel__header text-center">
            Password Reset
        </h1>
        <?=form_open('auth/password/forgotten')?>
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
            <p>
                <?=lang('auth_forgot_message')?>
            </p>
            <?php

            switch (APP_NATIVE_LOGIN_USING) {

                case 'EMAIL':

                    $sFieldLabel       = lang('form_label_email');
                    $sFieldPlaceholder = lang('auth_forgot_email_placeholder');
                    $sFieldType        = 'form_email';
                    break;

                case 'USERNAME':

                    $sFieldLabel       = lang('form_label_username');
                    $sFieldPlaceholder = lang('auth_forgot_username_placeholder');
                    $sFieldType        = 'form_input';
                    break;

                default:

                    $sFieldLabel       = lang('auth_forgot_both');
                    $sFieldPlaceholder = lang('auth_forgot_both_placeholder');
                    $sFieldType        = 'form_input';
                    break;
            }

            $sFieldKey  = 'identifier';
            $sFieldAttr = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

            ?>
            <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                <?=$sFieldType($sFieldKey, set_value($sFieldKey, $oInput->get('email')), $sFieldAttr)?>
                <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
            </div>
            <p>
                <button type="submit" class="btn btn--block btn--primary">
                    <?=lang('auth_forgot_action_reset')?>
                </button>
                <?=anchor('auth/login', 'Log In', 'class="btn btn--block btn--link"')?>
            </p>
        </div>
        <?=form_close()?>
    </div>
</div>
