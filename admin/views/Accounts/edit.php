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

    ?>
    <div class="admin-floating-controls">
        <button type="submit" class="btn btn-primary">
            Save Changes
        </button>
        <?php
        echo $oView->load('Accounts/edit/inc-actions', [], true);
        if (!empty($oUser) && $CONFIG['ENABLE_NOTES']) {
            ?>
            <button type="button"
                    class="btn btn-default pull-right js-admin-notes"
                    data-model-name="<?=$CONFIG['MODEL_NAME']?>"
                    data-model-provider="<?=$CONFIG['MODEL_PROVIDER']?>"
                    data-id="<?=$oUser->id?>">
                Notes
            </button>
            <?php
        }
        ?>
    </div>
    <?=form_close()?>
    <?php
    foreach ($aTabs as $oTab) {
        echo $oTab->getAdditionalMarkup($oUser);
    }
    ?>
</div>
