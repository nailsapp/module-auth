<?php

$buttons      = array();
$returnString = '?return_to=' . urlencode(uri_string() . '?' . $SERVER['QUERY_STRING']);

//  Login as
if ($user_edit->id != activeUser('id') && userHasPermission('admin:auth:accounts:loginAs')) {

    //  Generate the return string
    $url = uri_string();

    if ($this->input->get()) {

        //  Remove common problematic GET vars (for instance, we don't want isModal when we return)
        $get = $this->input->get();
        unset($get['isModal']);
        unset($get['inline']);

        if ($get) {

            $url .= '?' . http_build_query($get);
        }
    }

    $returnString = '?return_to=' . urlencode($url);

    // --------------------------------------------------------------------------

    $url = site_url('auth/override/login_as/' . md5($user_edit->id) . '/' . md5($user_edit->password) . $returnString);

    $buttons[] = anchor($url, lang('admin_login_as') . ' ' . $user_edit->first_name, 'class="awesome" target="_parent"');
}

// --------------------------------------------------------------------------

//  Edit
if ($user_edit->id != activeUser('id') && userHasPermission('admin:auth:accounts:delete')) {

    $title = lang('admin_confirm_delete_title');
    $body  = lang('admin_confirm_delete_body');

    $buttons[] = anchor(
        'admin/auth/accounts/delete/' . $user_edit->id . '?return_to=' . urlencode('admin/auth/accounts'),
        lang('action_delete'),
        'class="awesome red confirm" data-title="' . $title . '" data-body="' . $body . '"'
    );
}

// --------------------------------------------------------------------------

//  Suspend
if ($user_edit->is_suspended) {

    if (activeUser('id') != $user_edit->id && userHasPermission('admin:auth:accounts:unsuspend')) {

        $buttons[] = anchor(
            'admin/auth/accounts/unsuspend/' . $user_edit->id . $returnString,
            lang('action_unsuspend'),
            'class="awesome"'
        );
    }

} else {

    if (activeUser('id') != $user_edit->id && userHasPermission('admin:auth:accounts:suspend')) {

        $buttons[] = anchor(
            'admin/auth/accounts/suspend/' . $user_edit->id . $returnString,
            lang('action_suspend'),
            'class="awesome red"'
        );
    }
}


if ($buttons) {

    echo '<fieldset id="edit-user-actions">';
        echo '<legend>';
            echo lang('accounts_edit_actions_legend');
        echo '</legend>';
        echo '<p>';

        foreach ($buttons as $button) {

            echo $button;
        }

        echo '</p>';
        echo '<div class="clear"></div>';
    echo '</fieldset>';
}
