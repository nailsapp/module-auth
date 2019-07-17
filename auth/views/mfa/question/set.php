<?php

$aQuery = array_filter([
    'return_to' => $return_to,
    'remember'  => $remember,
]);

$sQuery = !empty($aQuery) ? '?' . http_build_query($aQuery) : '';

?>
<div class="nails-auth mfa mfa--question mfa--question--setup u-center-screen">
    <div class="panel">
        <h1 class="panel__header text-center">
            Set up Two Factor Authentication
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
            <?=form_open('auth/mfa/question/' . $user_id . '/' . $token['salt'] . '/' . $token['token'] . $sQuery)?>
            <?php
            if ($num_questions) {
                ?>
                <p>
                    <?=lang('auth_twofactor_question_set_system_body')?>
                </p>
                <?php
                if ($num_custom_questions) {
                    ?>
                    <h3>
                        <?=lang('auth_twofactor_question_set_system_legend')?>
                    </h3>
                    <?php
                }

                for ($i = 0; $i < $num_questions; $i++) {

                    $sFieldKey     = 'question[' . $i . '][question]';
                    $sFieldLabel   = 'Question ' . ($i + 1);
                    $aFieldOptions = array_merge(['Please Choose...'], $questions);

                    ?>
                    <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                        <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                        <?=form_dropdown($sFieldKey, $aFieldOptions, set_value($sFieldKey), 'id="input-' . $sFieldKey . '"')?>
                        <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                    </div>
                    <?php

                    $sFieldKey         = 'question[' . $i . '][answer]';
                    $sFieldLabel       = 'Answer ' . ($i + 1);
                    $sFieldPlaceholder = 'Type your answer here';
                    $sFieldAttr        = 'id="input-' . $sFieldKey . '" autocomplete="off" placeholder="' . $sFieldPlaceholder . '"';

                    ?>
                    <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                        <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                        <?=form_text($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                        <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                    </div>
                    <?php
                }
            }

            if ($num_custom_questions) {
                ?>
                <p>
                    <?=lang('auth_twofactor_question_set_custom_body')?>
                </p>
                <?php

                if ($num_questions) {
                    ?>
                    <h3>
                        <?=lang('auth_twofactor_question_set_custom_legend')?>
                    </h3>
                    <?php
                }

                for ($i = 0; $i < $num_custom_questions; $i++) {

                    $sFieldKey         = 'custom_question[' . $i . '][question]';
                    $sFieldLabel       = 'Question ' . ($i + 1);
                    $sFieldPlaceholder = 'Type your question here';
                    $sFieldAttr        = 'id="input-' . $sFieldKey . '" autocomplete="off" placeholder="' . $sFieldPlaceholder . '"';

                    ?>
                    <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                        <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                        <?=form_text($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                        <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                    </div>
                    <?php

                    $sFieldKey         = 'custom_question[' . $i . '][answer]';
                    $sFieldLabel       = 'Answer ' . ($i + 1);
                    $sFieldPlaceholder = 'Type your answer here';
                    $sFieldAttr        = 'id="input-' . $sFieldKey . '" autocomplete="off" placeholder="' . $sFieldPlaceholder . '"';

                    ?>
                    <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                        <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                        <?=form_text($sFieldKey, set_value($sFieldKey), $sFieldAttr)?>
                        <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
                    </div>
                    <?php
                }
            }
            ?>
            <p>
                <button type="submit" class="btn btn--block btn--primary">
                    Save questions &amp; Sign in
                </button>
            </p>
            <?=form_close()?>
        </div>
    </div>
</div>
