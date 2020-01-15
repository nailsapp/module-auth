<?php

use Nails\Auth\Resource\User;
use Nails\Common\Factory\Model\Field;

/**
 * @var User    $oUser
 * @var Field[] $aMetaCols
 */

if (!empty($aMetaCols)) {
    foreach ($aMetaCols as $oField) {

        $oField->default = property_exists($oUser, $oField->key) ? $oUser->{$oField->key} : null;

        if (is_callable('\Nails\Common\Helper\Form\Field::' . $oField->type)) {
            echo call_user_func('\Nails\Common\Helper\Form\Field::' . $oField->type, (array) $oField);
        } else {
            echo  Nails\Common\Helper\Form\Field::text((array) $oField);
        }
    }
} else {
    ?>
    <p>
        <?=lang('accounts_edit_meta_noeditable')?>
    </p>
    <?php
}
