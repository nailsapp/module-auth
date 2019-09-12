<?php

use Nails\Auth\Constants;
use Nails\Factory;

/**
 * This file provides language user helper functions
 *
 * @package     Nails
 * @subpackage  common
 * @category    Helper
 * @author      Nails Dev Team
 * @link
 */

if (!function_exists('activeUser')) {

    /**
     * Alias to UserModel->activeUser(); method
     *
     * @param bool|string $mKeys      The key to look up in activeUser
     * @param string         $sDelimiter If multiple fields are requested they'll be joined by this string
     *
     * @return mixed
     */
    function activeUser($mKeys = false, $sDelimiter = ' ')
    {
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        return $oUserModel ? $oUserModel->activeUser($mKeys, $sDelimiter) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('userHasPermission')) {

    /**
     * Alias to UserModel->hasPermission(); method
     *
     * @param string $sPermission The permission to check for
     * @param mixed  $mUser       The user to check for; if null uses activeUser, if numeric, fetches user, if object uses that object
     *
     * @return  bool
     */
    function userHasPermission($sPermission, $mUser = null)
    {
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        return $oUserModel ? $oUserModel->hasPermission($sPermission, $mUser) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('groupHasPermission')) {

    /**
     * Alias to user_model->groupHasPermission(); method
     *
     * @param string $sPermission The permission to check for
     * @param mixed  $mGroup      The group to check for; if numeric, fetches group, if object uses that object
     *
     * @return  bool
     */
    function groupHasPermission($sPermission, $mGroup)
    {
        $oUserGroupModel = Factory::model('UserGroup', Constants::MODULE_SLUG);
        return $oUserGroupModel ? $oUserGroupModel->hasPermission($sPermission, $mGroup) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isLoggedIn')) {

    /**
     * Alias to UserModel->isLoggedIn()
     *
     * @return bool
     */
    function isLoggedIn()
    {
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        return $oUserModel->isLoggedIn();
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isAdmin')) {

    /**
     * Alias to UserModel->isAdmin()
     *
     * @param mixed $mUser The user to check, uses activeUser if null
     *
     * @return bool
     */
    function isAdmin($mUser = null)
    {
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        return $oUserModel ? $oUserModel->isAdmin($mUser) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('wasAdmin')) {

    /**
     * Alias to UserModel->wasAdmin()
     *
     * @return bool
     */
    function wasAdmin()
    {
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        return $oUserModel->wasAdmin();
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isSuperuser')) {

    /**
     * Alias to UserModel->isSuperuser()
     *
     * @param mixed $mUser The user to check, uses activeUser if null
     *
     * @return bool
     */
    function isSuperuser($mUser = null)
    {
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        return $oUserModel ? $oUserModel->isSuperuser($mUser) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getAdminRecoveryData')) {

    /**
     * Alias to UserModel->getAdminRecoveryData()
     *
     * @return bool
     */
    function getAdminRecoveryData()
    {
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        return $oUserModel->getAdminRecoveryData();
    }
}

// --------------------------------------------------------------------------

if (!function_exists('unsetAdminRecoveryData')) {

    /**
     * Alias to UserModel->unsetAdminRecoveryData()
     *
     * @return bool
     */
    function unsetAdminRecoveryData()
    {
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        return $oUserModel ? $oUserModel->unsetAdminRecoveryData() : false;
    }
}
