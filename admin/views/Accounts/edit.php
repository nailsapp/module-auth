<?php

use Nails\Admin\Helper;
use Nails\Common\Service\Config;
use Nails\Common\Service\Input;
use Nails\Common\Service\View;
use Nails\Factory;

/** @var View $oView */
$oView = Factory::service('View');
/** @var Config $oConfig */
$oConfig = Factory::service('Config');
/** @var Input $oView */
$oInput = Factory::service('Input');

echo form_open('admin/auth/accounts/email', 'id="email-form"');
echo form_hidden('id', $user_edit->id);
echo form_hidden('return', uri_string() . '?' . $oInput->server('QUERY_STRING'));
echo form_hidden('email');
echo form_hidden('action');
echo form_hidden('isPrimary');
echo form_hidden('isVerified');
echo form_close();

?>
<div class="group-accounts edit">
    <?php

    echo form_open();

    echo form_hidden('id', $user_edit->id);
    echo form_hidden('email_orig', $user_edit->email);
    echo form_hidden('username_orig', $user_edit->username);

    if (!empty($isModal)) {
        echo $oView->load('Accounts/edit/inc-actions', [], true);
    }

    $aUserTabs = [
        //        [
        //            'label'   => 'Meta',
        //            'content' => function () use ($oView) {
        //                return $oView->load('Accounts/edit/inc-meta', [], true);
        //            },
        //        ],
        //        [
        //            'label'   => 'Emails',
        //            'content' => function () use ($oView) {
        //                return $oView->load('Accounts/edit/inc-emails', [], true);
        //            },
        //        ],
        //        [
        //            'label'   => 'Security',
        //            'content' => function () use ($oView, $oConfig) {
        //                $oConfig->load('auth/auth');
        //                return $oView->load(
        //                    array_filter([
        //                        'Accounts/edit/inc-password',
        //                        $oConfig->item('authTwoFactorMode') == 'QUESTION' ? 'Accounts/edit/inc-mfa-question' : null,
        //                        $oConfig->item('authTwoFactorMode') == 'DEVICE' ? 'Accounts/edit/inc-mfa-device' : null,
        //                    ]),
        //                    [],
        //                    true
        //                );
        //            },
        //        ],
    ];

    /** @var \Nails\Auth\Interfaces\Admin\User\Tab $oTab */
    foreach ($aTabs as $oTab) {
        $aUserTabs[] = [
            'order'   => $oTab->getOrder(),
            'label'   => $oTab->getLabel(),
            'content' => $oTab->getBody($user_edit),
        ];
    }

//    arraySortMulti($aUserTabs, 'order');

    echo Helper::tabs($aUserTabs);

    ?>
    <div class="admin-floating-controls">
        <button type="submit" class="btn btn-primary">
            Save Changes
        </button>
        <?php
        if (!empty($user_edit) && $CONFIG['ENABLE_NOTES']) {
            ?>
            <button type="button"
                    class="btn btn-default pull-right js-admin-notes"
                    data-model-name="<?=$CONFIG['MODEL_NAME']?>"
                    data-model-provider="<?=$CONFIG['MODEL_PROVIDER']?>"
                    data-id="<?=$user_edit->id?>">
                Notes
            </button>
            <?php
        }
        ?>
    </div>
    <?=form_close()?>
</div>
