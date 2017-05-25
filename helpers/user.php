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
     * Alias to user_model->activeUser(); method
     *
     * @param  boolean|string $keys      The key to look up in activeUser
     * @param  string         $delimiter If multiple fields are requested they'll be joined by this string
     *
     * @return mixed
     */
    function activeUser($keys = false, $delimiter = ' ')
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel ? $oUserModel->activeUser($keys, $delimiter) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('userHasPermission')) {

    /**
     * Alias to user_model->hasPermission(); method
     *
     * @param   string $permission The permission to check for
     * @param   mixed  $user       The user to check for; if null uses activeUser, if numeric, fetches user, if object uses that object
     *
     * @return  boolean
     */
    function userHasPermission($permission, $user = null)
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel ? $oUserModel->hasPermission($permission, $user) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isLoggedIn')) {

    /**
     * Alias to user_model->isLoggedIn()
     * @return boolean
     */
    function isLoggedIn()
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel ? $oUserModel->isLoggedIn() : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isAdmin')) {

    /**
     * Alias to user_model->isAdmin()
     *
     * @param  mixed $user The user to check, uses activeUser if null
     *
     * @return boolean
     */
    function isAdmin($user = null)
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel ? $oUserModel->isAdmin($user) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('wasAdmin')) {

    /**
     * Alias to user_model->wasAdmin()
     * @return boolean
     */
    function wasAdmin()
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel ? $oUserModel->wasAdmin() : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('isSuperuser')) {

    /**
     * Alias to user_model->isSuperuser()
     *
     * @param  mixed $user The user to check, uses activeUser if null
     *
     * @return boolean
     */
    function isSuperuser($user = null)
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel ? $oUserModel->isSuperuser($user) : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('getAdminRecoveryData')) {

    /**
     * Alias to user_model->getAdminRecoveryData()
     *
     * @return boolean
     */
    function getAdminRecoveryData()
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel ? $oUserModel->getAdminRecoveryData() : false;
    }
}

// --------------------------------------------------------------------------

if (!function_exists('unsetAdminRecoveryData')) {

    /**
     * Alias to user_model->unsetAdminRecoveryData()
     *
     * @return boolean
     */
    function unsetAdminRecoveryData()
    {
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        return $oUserModel ? $oUserModel->unsetAdminRecoveryData() : false;
    }
}
