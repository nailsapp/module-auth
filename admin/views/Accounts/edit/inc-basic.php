<?php

use Nails\Auth\Resource\User;
use Nails\Common\Factory\Model\Field;

/**
 * @var User    $oUser
 * @var Field[] $aFields
 */

foreach ($aFields as $oField) {

    if (!property_exists($oField, 'default') || is_null($oField->default)) {
        $oField->default = property_exists($oUser, $oField->key) ? $oUser->{$oField->key} : null;
    }

    if (is_callable('form_field_' . $oField->type)) {
        echo call_user_func('form_field_' . $oField->type, (array) $oField);
    } else {
        echo form_field((array) $oField);
    }
}
