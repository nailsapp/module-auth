<?php

use Nails\Common\Service\Input;
use Nails\Factory;
use Nails\Auth\Resource\User;

/**
 * @var User $oUser
 */

/** @var Input $oInput */
$oInput        = Factory::service('Input');
$aButtons      = [];
$sReturnString = '?return_to=' . urlencode(uri_string() . '?' . $oInput->server('QUERY_STRING'));

//  Login as
if ($oUser->id != activeUser('id') && userHasPermission('admin:auth:accounts:loginAs')) {

    //  Generate the return string
    $sUrl = uri_string();

    if ($oInput->get()) {

        //  Remove common problematic GET vars (for instance, we don't want isModal when we return)
        $get = $oInput->get();
        unset($get['isModal']);

        if ($get) {
            $sUrl .= '?' . http_build_query($get);
        }
    }

    $sReturnString = '?return_to=' . urlencode($sUrl);

    // --------------------------------------------------------------------------

    $sUrl = siteUrl('auth/override/login_as/' . md5($oUser->id) . '/' . md5($oUser->password) . $sReturnString);

    $aButtons[] = anchor($sUrl, lang('admin_login_as') . ' ' . $oUser->first_name, 'class="btn btn-primary" target="_parent"');
}

// --------------------------------------------------------------------------

//  Edit
if ($oUser->id != activeUser('id') && userHasPermission('admin:auth:accounts:delete')) {

    $sTitle = lang('admin_confirm_delete_title');
    $sBody  = lang('admin_confirm_delete_body');

    $aButtons[] = anchor(
        'admin/auth/accounts/delete/' . $oUser->id . '?return_to=' . urlencode('admin/auth/accounts'),
        lang('action_delete'),
        'class="btn btn-danger confirm" data-title="' . $sTitle . '" data-body="' . $sBody . '"'
    );
}

// --------------------------------------------------------------------------

//  Suspend
if ($oUser->is_suspended) {

    if (activeUser('id') != $oUser->id && userHasPermission('admin:auth:accounts:unsuspend')) {
        $aButtons[] = anchor(
            'admin/auth/accounts/unsuspend/' . $oUser->id . $sReturnString,
            lang('action_unsuspend'),
            'class="btn btn-primary"'
        );
    }

} else {

    if (activeUser('id') != $oUser->id && userHasPermission('admin:auth:accounts:suspend')) {
        $aButtons[] = anchor(
            'admin/auth/accounts/suspend/' . $oUser->id . $sReturnString,
            lang('action_suspend'),
            'class="btn btn-danger"'
        );
    }
}

if ($aButtons) {

    ?>
    <fieldset id="edit-user-actions">
        <legend>
            <?=lang('accounts_edit_actions_legend')?>
        </legend>
        <p>
            <?php

            foreach ($aButtons as $sButton) {
                echo $sButton;
            }

            ?>
        </p>
        <div class="clear"></div>
    </fieldset>
    <?php
}
