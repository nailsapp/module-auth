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
                <?=lang('auth_forgot_reset_ok')?>
            </p>
            <div>
                <p class="alert alert--info new-password">
                    <?=$new_password?>
                </p>
            </div>
            <p>
                <?=anchor('auth/login?identity=' . urlencode($user->identity), lang('auth_forgot_action_proceed'), 'class="btn btn--block btn--primary"')?>
            </p>
        </div>
    </div>
</div>
