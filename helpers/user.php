<?php

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
     * @param  boolean|string $mKeys      The key to look up in activeUser
     * @param  string         $sDelimiter If multiple fields are requested they'll be joined by this string
     *
     * @return mixed
     */
    function activeUser($mKeys = false, $sDelimiter = ' ')
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        return $oUserModel ? $oUserModel->activeUser($mKeys, $sDelimiter) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('userHasPermission')) {

    /**
     * Alias to UserModel->hasPermission(); method
     *
     * @param   string $sPermission The permission to check for
     * @param   mixed  $mUser       The user to check for; if null uses activeUser, if numeric, fetches user, if object uses that object
     *
     * @return  boolean
     */
    function userHasPermission($sPermission, $mUser = null)
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        return $oUserModel ? $oUserModel->hasPermission($sPermission, $mUser) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('groupHasPermission')) {

    /**
     * Alias to user_model->groupHasPermission(); method
     *
     * @param   string $sPermission The permission to check for
     * @param   mixed  $mGroup      The group to check for; if numeric, fetches group, if object uses that object
     *
     * @return  boolean
     */
    function groupHasPermission($sPermission, $mGroup)
    {
        $oUserGroupModel = Factory::model('UserGroup', 'nails/module-auth');
        return $oUserGroupModel ? $oUserGroupModel->hasPermission($sPermission, $mGroup) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isLoggedIn')) {

    /**
     * Alias to UserModel->isLoggedIn()
     * @return boolean
     */
    function isLoggedIn()
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        return $oUserModel->isLoggedIn();
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isAdmin')) {

    /**
     * Alias to UserModel->isAdmin()
     *
     * @param  mixed $mUser The user to check, uses activeUser if null
     *
     * @return boolean
     */
    function isAdmin($mUser = null)
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        return $oUserModel ? $oUserModel->isAdmin($mUser) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('wasAdmin')) {

    /**
     * Alias to UserModel->wasAdmin()
     * @return boolean
     */
    function wasAdmin()
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        return $oUserModel->wasAdmin();
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isSuperuser')) {

    /**
     * Alias to UserModel->isSuperuser()
     *
     * @param  mixed $mUser The user to check, uses activeUser if null
     *
     * @return boolean
     */
    function isSuperuser($mUser = null)
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        return $oUserModel ? $oUserModel->isSuperuser($mUser) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getAdminRecoveryData')) {

    /**
     * Alias to UserModel->getAdminRecoveryData()
     *
     * @return boolean
     */
    function getAdminRecoveryData()
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        return $oUserModel->getAdminRecoveryData();
    }
}

// --------------------------------------------------------------------------

if (!function_exists('unsetAdminRecoveryData')) {

    /**
     * Alias to UserModel->unsetAdminRecoveryData()
     *
     * @return boolean
     */
    function unsetAdminRecoveryData()
    {
        $oUserModel = Factory::model('User', 'nails/module-auth');
        return $oUserModel ? $oUserModel->unsetAdminRecoveryData() : false;
    }
}
