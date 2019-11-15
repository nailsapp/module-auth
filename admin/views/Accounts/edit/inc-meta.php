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

        if (is_callable('form_field_' . $oField->type)) {
            echo call_user_func('form_field_' . $oField->type, (array) $oField);
        } else {
            echo form_field((array) $oField);
        }
    }
} else {
    ?>
    <p>
        <?=lang('accounts_edit_meta_noeditable')?>
    </p>
    <?php
}
