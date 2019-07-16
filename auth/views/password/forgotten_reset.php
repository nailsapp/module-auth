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
            <p class="alert alert--info new-password">
                <?=$new_password?>
            </p>
            <p>
                <?=anchor('auth/login', lang('auth_forgot_action_proceed'), 'class="btn btn--block btn--primary"')?>
            </p>
        </div>
    </div>
</div>

<script type="text/javascript">

    var textBox = document.getElementById('temp-password');
    textBox.onfocus = function() {
        textBox.select();
        // Work around Chrome's little problem
        textBox.onmouseup = function() {
            // Prevent further mouseup intervention
            textBox.onmouseup = null;
            return false;
        };
    };
</script>
