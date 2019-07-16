<?php

$aQuery = array_filter([
    'return_to' => $return_to,
    'remember'  => $remember,
]);

$sQuery   = !empty($aQuery) ? '?' . http_build_query($aQuery) : '';
$sFormUrl = null;

if (isset($login_method) && isset($user_id) && isset($token)) {
    $login_method = $login_method && $login_method != 'native' ? '/' . $login_method : '';
    $sFormUrl     = 'auth/mfa/question/' . $user_id . '/' . $token['salt'] . '/' . $token['token'] . $login_method . $sQuery;
    $sFormUrl     = site_url($sFormUrl);
}

?>
<div class="nails-auth mfa mfa--question mfa--question--ask u-center-screen">
    <div class="panel">
        <h1 class="panel__header text-center">
            Two Factor Authentication
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
            <p>
                <?=lang('auth_twofactor_answer_body')?>
            </p>
            <h4>
                <strong><?=$question->question?></strong>
            </h4>
            <?=form_open($sFormUrl)?>
            <?php

            $sFieldKey         = 'answer';
            $sFieldLabel       = 'Answer';
            $sFieldPlaceholder = 'Type your answer here';
            ?>
            <div class="form__group <?=form_error($sFieldKey) ? 'has-error' : ''?>">
                <label for="input-<?=$sFieldKey?>"><?=$sFieldLabel?></label>
                <?=form_text($sFieldKey, set_value($sFieldKey), 'id="input-' . $sFieldKey . '" autocomplete="off" placeholder="' . $sFieldPlaceholder . '"')?>
                <?=form_error($sFieldKey, '<p class="form__error">', '</p>')?>
            </div>
            <p>
                <button type="submit" class="btn btn--block btn--primary">
                    Verify answer &amp; Sign in
                </button>
            </p>
            <?=form_close()?>
        </div>
    </div>
</div>
