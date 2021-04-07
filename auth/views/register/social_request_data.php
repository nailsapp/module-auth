<?php

use Nails\Common\Service\View;
use Nails\Factory;

/** @var View $oView */
$oView = Factory::service('View');

?>
<div class="nails-auth login social u-center-screen">
    <div class="panel">
        <h1 class="panel__header text-center">
            Welcome
        </h1>
        <?=form_open($form_url)?>
        <div class="panel__body">
            <?php

            $oView->load('auth/_components/alerts');

            ?>
            <p class="text-center">
                <?=lang('auth_register_extra_message')?>
            </p>
            <?php
            if (\Nails\Config::get('APP_NATIVE_LOGIN_USING') == 'EMAIL' || \Nails\Config::get('APP_NATIVE_LOGIN_USING') != 'USERNAME') {
                if (isset($required_data['email'])) {

                    $sFieldKey         = 'email';
                    $FieldType         = 'form_email';
                    $sFieldLabel       = lang('form_label_email');
                    $sFieldPlaceholder = lang('auth_register_email_placeholder');
                    $sDefault          = $required_data['email'];
                    $sFieldAttr        = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

                    ?>
                    <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                        <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                        <?=$FieldType($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                        <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                    </div>
                    <?php
                }
            }

            if (\Nails\Config::get('APP_NATIVE_LOGIN_USING') == 'USERNAME' || \Nails\Config::get('APP_NATIVE_LOGIN_USING') != 'EMAIL') {

                if (isset($required_data['username'])) {

                    $sFieldKey         = 'username';
                    $FieldType         = 'form_input';
                    $sFieldLabel       = lang('form_label_username');
                    $sFieldPlaceholder = lang('auth_register_username_placeholder');
                    $sDefault          = $required_data['username'];
                    $sFieldAttr        = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

                    ?>
                    <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                        <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                        <?=$FieldType($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                        <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                    </div>
                    <?php

                }

            }

            if (!$required_data['first_name'] || !$required_data['last_name']) {

                $sFieldKey         = 'first_name';
                $FieldType         = 'form_input';
                $sFieldLabel       = lang('form_label_first_name');
                $sFieldPlaceholder = lang('auth_register_first_name_placeholder');
                $sDefault          = !empty($required_data['first_name']) ? $required_data['first_name'] : '';
                $sFieldAttr        = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

                ?>
                <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                    <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                    <?=$FieldType($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                    <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                </div>
                <?php

                // --------------------------------------------------------------------------

                $sFieldKey         = 'last_name';
                $FieldType         = 'form_input';
                $sFieldLabel       = lang('form_label_last_name');
                $sFieldPlaceholder = lang('auth_register_last_name_placeholder');
                $sDefault          = !empty($required_data['last_name']) ? $required_data['last_name'] : '';
                $sFieldAttr        = 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"';

                ?>
                <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                    <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                    <?=$FieldType($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                    <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                </div>
                <?php

            }
            ?>
            <p>
                <button type="submit" class="btn btn--block btn--primary">
                    <?=lang('action_continue')?>
                </button>
            </p>
        </div>
        <?=form_close()?>
    </div>
</div>
