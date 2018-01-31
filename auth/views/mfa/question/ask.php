<?php

$query              = [];
$query['return_to'] = isset($return_to) ? $return_to : '';
$query['remember']  = isset($remember) ? $remember : '';

$query = array_filter($query);

if ($query) {

    $query = '?' . http_build_query($query);

} else {

    $query = '';
}

if (!isset($login_method) || !isset($user_id) || !isset($token)) {

    $formUrl = null;

} else {

    $login_method = $login_method && $login_method != 'native' ? '/' . $login_method : '';
    $formUrl      = 'auth/mfa_question/' . $user_id . '/' . $token['salt'] . '/' . $token['token'] . $login_method . $query;
    $formUrl      = site_url($formUrl);
}

?>
<div class="container nails-module-auth mfa mfa-question mfa-question-ask">
    <div class="row">
        <div class="col-sm-6 col-sm-offset-3">
            <div class="well well-lg text-center">
                <p>
                    <?=lang('auth_twofactor_answer_body')?>
                </p>
                <hr/>
                <h4 style="margin-bottom:1.25em;">
                    <strong><?=$question->question?></strong>
                </h4>
                <p>
                    <?php

                    echo form_open($formUrl);

                    ?>
                </p>
                <p>
                    <?=form_password('answer', null, 'class="form-control" placeholder="Type your answer here"')?>
                </p>
                <hr/>
                <button class="btn btn-lg btn-primary" type="submit">Login</button>
                <?=form_close()?>
            </div>
        </div>
    </div>
</div>
