<?php

$query              = [];
$query['return_to'] = $return_to;
$query['remember']  = $remember;

$query = array_filter($query);

if (!empty($query)) {

    $query = '?' . http_build_query($query);

} else {

    $query = '';
}

?>
<div class="container nails-module-auth mfa mfa-device mfa-device-setup">
    <div class="row">
        <div class="col-sm-6 col-sm-offset-3">
            <div class="well well-lg">
                <?php

                echo form_open('auth/mfa/device/' . $user_id . '/' . $token['salt'] . '/' . $token['token'] . $query);

                echo form_hidden('mfaSecret', $secret['secret']);

                ?>
                <div class="panel panel-defaul">
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-xs-5">
                                <?php

                                echo img(
                                    [
                                        'src'   => $secret['url'],
                                        'class' => 'img-responsive img-thumbnail',
                                    ]
                                );

                                ?>
                            </div>
                            <div class="col-xs-7">
                                <p>
                                    This site requires that you use Multi Factor Authentication when logging in.
                                </p>
                                <p>
                                    Scan the QR code to the left with your MFA Device, then add two
                                    sequential codes in the boxes below.
                                </p>
                                <?php

                                $hasError = form_error('mfaCode1') ? 'has-error' : '';
                                echo '<div class="form-group ' . $hasError . '">';
                                echo form_input('mfaCode1', '', 'class="form-control" placeholder="Code 1"');
                                echo form_error('mfaCode1', '<p class="help-block">', '</p>');
                                echo '</div>';

                                $hasError = form_error('mfaCode2') ? 'has-error' : '';
                                echo '<div class="form-group ' . $hasError . '">';
                                echo form_input('mfaCode2', '', 'class="form-control" placeholder="Code 2"');
                                echo form_error('mfaCode2', '<p class="help-block">', '</p>');
                                echo '</div>';

                                ?>
                                <p>
                                    <button type="submit" class="btn btn-primary btn-block">
                                        Verify Codes &amp; Sign in
                                    </button>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
                <?=form_close()?>
                <hr/>
                <p>
                    <small>
                        MFA stands for Multi Factor Authentication. Once set up you will require your
                        MFA device to generate a single use code every time you log in. This two step
                        process greatly improves the security of your account.
                    </small>
                </p>
                <p>
                    <small>
                        You can use any MFA device you wish, however we recommend using Google
                        Authenticator, available for
                        <a href="https://itunes.apple.com/gb/app/google-authenticator/id388497605?mt=8">iOS</a>,
                        <a href="https://play.google.com/store/apps/details?id=com.google.android.apps.authenticator2">Android</a>
                        and
                        <a href="http://m.google.com/authenticator">Blackberry</a>.
                    </small>
                </p>
            </div>
        </div>
    </div>
</div>
