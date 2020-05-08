<?php

use Nails\Auth\Resource\User;
use Nails\Common\Factory\Model\Field;

/**
 * @var User    $oUser
 * @var Field[] $aFields
 */

foreach ($aFields as $oField) {

    $oField->default = property_exists($oUser, $oField->key)
        ? $oUser->{$oField->key}
        : $oField->default;

    if (is_callable('\Nails\Common\Helper\Form\Field::' . $oField->type)) {
        echo call_user_func('\Nails\Common\Helper\Form\Field::' . $oField->type, (array) $oField);
    } else {
        echo  Nails\Common\Helper\Form\Field::text((array) $oField);
    }
}
