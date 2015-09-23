<?php

/**
 * Auth config
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Config
 * @author      Nails Dev Team
 * @link
 */


/**
 * Disable errors when submitting the forgotten password form
 */
$config['authForgottenPassAlwaysSucceed'] = false;

/**
 * Toggle the "remember me" functionality
 */
$config['authEnableRememberMe'] = true;

/**
 * Toggle logins via hashes functionality
 */
$config['authEnableHashedLogin'] = true;

/**
 * On login show the last seen time as a human friendly string
 */
$config['authShowNicetimeOnLogin'] = true;

/**
 * On login show the last known IP of the user
 */
$config['authShowLastIpOnLogin'] = false;

// --------------------------------------------------------------------------

/**
 * Auth sub config files
 * Load both versions, app version overrides Nails version
 */
$appPath   = FCPATH . APPPATH . 'modules/auth/config/';
$nailsPath = NAILS_PATH . 'module-auth/auth/config/';

$files = array(
    'auth.social.php',
    'auth.twofactor.php',
    'auth.accesstoken.php'
);

foreach ($files as $file) {

    if (file_exists($nailsPath . $file)) {

        include $nailsPath . $file;
    }

    if (file_exists($appPath . $file)) {

        include $appPath . $file;
    }
}
