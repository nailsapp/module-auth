<?php

use Nails\Admin\Helper;
use Nails\Common\Service\View;
use Nails\Factory;

/** @var View $oView */
$oView = Factory::service('View');

?>
<div class="group-accounts edit">
    <?php

    echo !empty($isModal)
        ? form_open(uri_string() . '?isModal=true')
        : form_open();

    /** @var array[] $aUserTabs */
    $aUserTabs = [];
    /** @var \Nails\Auth\Interfaces\Admin\User\Tab $oTab */
    foreach ($aTabs as $oTab) {
        $aUserTabs[] = [
            'order'   => $oTab->getOrder(),
            'label'   => $oTab->getLabel(),
            'content' => $oTab->getBody($oUser),
        ];
    }

    arraySortMulti($aUserTabs, 'order');

    echo Helper::tabs($aUserTabs);
    echo \Nails\Admin\Helper::floatingControls(array_merge(
        $CONFIG['FLOATING_CONFIG'],
        [
            'html' => [
                'center' => $oView->load('Accounts/edit/inc-actions', [], true),
            ],
        ]
    ));
    echo form_close();

    foreach ($aTabs as $oTab) {
        echo $oTab->getAdditionalMarkup($oUser);
    }

    ?>
</div>
