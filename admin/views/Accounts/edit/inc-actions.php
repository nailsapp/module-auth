<?php

$oInput       = \Nails\Factory::service('Input');
$buttons      = [];
$returnString = '?return_to=' . urlencode(uri_string() . '?' . $oInput->server('QUERY_STRING'));

//  Login as
if ($user_edit->id != activeUser('id') && userHasPermission('admin:auth:accounts:loginAs')) {

    //  Generate the return string
    $url = uri_string();

    if ($oInput->get()) {

        //  Remove common problematic GET vars (for instance, we don't want isModal when we return)
        $get = $oInput->get();
        unset($get['isModal']);

        if ($get) {

            $url .= '?' . http_build_query($get);
        }
    }

    $returnString = '?return_to=' . urlencode($url);

    // --------------------------------------------------------------------------

    $url = site_url('auth/override/login_as/' . md5($user_edit->id) . '/' . md5($user_edit->password) . $returnString);

    $buttons[] = anchor($url, lang('admin_login_as') . ' ' . $user_edit->first_name, 'class="btn btn-primary" target="_parent"');
}

// --------------------------------------------------------------------------

//  Edit
if ($user_edit->id != activeUser('id') && userHasPermission('admin:auth:accounts:delete')) {

    $title = lang('admin_confirm_delete_title');
    $body  = lang('admin_confirm_delete_body');

    $buttons[] = anchor(
        'admin/auth/accounts/delete/' . $user_edit->id . '?return_to=' . urlencode('admin/auth/accounts'),
        lang('action_delete'),
        'class="btn btn-danger confirm" data-title="' . $title . '" data-body="' . $body . '"'
    );
}

// --------------------------------------------------------------------------

//  Suspend
if ($user_edit->is_suspended) {

    if (activeUser('id') != $user_edit->id && userHasPermission('admin:auth:accounts:unsuspend')) {

        $buttons[] = anchor(
            'admin/auth/accounts/unsuspend/' . $user_edit->id . $returnString,
            lang('action_unsuspend'),
            'class="btn btn-primary"'
        );
    }

} else {

    if (activeUser('id') != $user_edit->id && userHasPermission('admin:auth:accounts:suspend')) {

        $buttons[] = anchor(
            'admin/auth/accounts/suspend/' . $user_edit->id . $returnString,
            lang('action_suspend'),
            'class="btn btn-danger"'
        );
    }
}

if ($buttons) {

    ?>
    <fieldset id="edit-user-actions">
        <legend>
            <?=lang('accounts_edit_actions_legend')?>
        </legend>
        <p>
            <?php

            foreach ($buttons as $button) {

                echo $button;
            }

            ?>
        </p>
        <div class="clear"></div>
    </fieldset>
    <?php
}
