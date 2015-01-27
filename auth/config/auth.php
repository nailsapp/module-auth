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
$config['auth_forgotten_pass_always_succeed'] = false;

/**
 * Toggle the "remember me" functionality
 */
$config['auth_enable_remember_me'] = true;

/**
 * Toggle logins via hashes functionality
 */
$config['auth_enable_hashed_login'] = true;

/**
 * On login show the last seen time as a human friendly string
 */
$config['auth_show_nicetime_on_login'] = true;

/**
 * On login show the last known IP of the user
 */
$config['auth_show_last_ip_on_login'] = false;

// --------------------------------------------------------------------------

/**
 * Auth sub config files
 * Look for an app version, fallback to Nails version
 */
$appPath   = FCPATH . APPPATH . 'modules/auth/config/';
$nailsPath = NAILS_PATH . 'module-auth/auth/config/';

$files = array(
    'auth.password.php',
    'auth.social.php',
    'auth.twofactor.php'
);

foreach ($files as $file) {

    if (file_exists($appPath . $file)) {

        include $appPath . $file;

    } else {

        include $nailsPath . $file;
    }
}
