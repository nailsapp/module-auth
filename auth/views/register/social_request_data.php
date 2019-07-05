<div class="nails-auth login social u-center-screen">
    <div class="panel">
        <h1 class="panel__header text-center">
            Welcome
        </h1>
        <?=form_open($form_url)?>
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
            <p class="text-center">
                <?=lang('auth_register_extra_message')?>
            </p>
            <?php
            if (APP_NATIVE_LOGIN_USING == 'EMAIL' || APP_NATIVE_LOGIN_USING != 'USERNAME') {
                if (isset($required_data['email'])) {

                    $sFieldKey         = 'email';
                    $FieldType         = 'form_email';
                    $sFieldLabel       = lang('form_label_email');
                    $sFieldPlaceholder = lang('auth_register_email_placeholder');
                    $sDefault          = $required_data['email'];

                    ?>
                    <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                        <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                        <?=$FieldType($sFieldKey, set_value($sFieldKey), 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"')?>
                        <?=form_error($sFieldKey, '<p class="help-block">', '</p>')?>
                    </div>
                    <?php
                }
            }

            if (APP_NATIVE_LOGIN_USING == 'USERNAME' || APP_NATIVE_LOGIN_USING != 'EMAIL') {

                if (isset($required_data['username'])) {

                    $sFieldKey         = 'username';
                    $FieldType         = 'form_input';
                    $sFieldLabel       = lang('form_label_username');
                    $sFieldPlaceholder = lang('auth_register_username_placeholder');
                    $sDefault          = $required_data['username'];

                    ?>
                    <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                        <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                        <?=$FieldType($sFieldKey, set_value($sFieldKey), 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"')?>
                        <?=form_error($sFieldKey, '<p class="help-block">', '</p>')?>
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

                ?>
                <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                    <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                    <?=$FieldType($sFieldKey, set_value($sFieldKey), 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"')?>
                    <?=form_error($sFieldKey, '<p class="help-block">', '</p>')?>
                </div>
                <?php

                // --------------------------------------------------------------------------

                $sFieldKey         = 'last_name';
                $FieldType         = 'form_input';
                $sFieldLabel       = lang('form_label_last_name');
                $sFieldPlaceholder = lang('auth_register_last_name_placeholder');
                $sDefault          = !empty($required_data['last_name']) ? $required_data['last_name'] : '';

                ?>
                <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                    <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                    <?=$FieldType($sFieldKey, set_value($sFieldKey), 'id="input-' . $sFieldKey . '" placeholder="' . $sFieldPlaceholder . '"')?>
                    <?=form_error($sFieldKey, '<p class="help-block">', '</p>')?>
                </div>
                <?php

            }
            ?>
            <p>
                <button type="submit" class="btn btn--block"><?=lang('action_continue')?></button>
            </p>
        </div>
        <?=form_close()?>
    </div>
</div>
