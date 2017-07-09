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
     * @param  boolean|string $sKey       The key to look up in activeUser
     * @param  string         $sDelimiter If multiple fields are requested they'll be joined by this string
     *
     * @return mixed
     */
    function activeUser($sKey = false, $sDelimiter = ' ')
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel->activeUser($sKey, $sDelimiter);
    }
}

// --------------------------------------------------------------------------

if (!function_exists('userHasPermission')) {

    /**
     * Alias to UserModel->hasPermission(); method
     *
     * @param   string $sPermission The permission to check for
     * @param   mixed  $mUser       The user to check for; if null uses activeUser,
     *                              if numeric, fetches user, if object uses that object
     *
     * @return  boolean
     */
    function userHasPermission($sPermission, $mUser = null)
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel->hasPermission($sPermission, $mUser);
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
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
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
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel->isAdmin($mUser);
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
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
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
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel->isSuperuser($mUser);
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
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
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
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel->unsetAdminRecoveryData();
    }
}
