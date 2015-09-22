<?php

/**
 * Auth config (Passwords)
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Config
 * @author      Nails Dev Team
 * @link
 */

//  Define password strength rules
$config['authPasswordRules'] = array();

//  Minimum password length
$config['authPasswordRules']['minLength'] = 6;

//  Maximum password length, 0 means unlimited length
$config['authPasswordRules']['maxLength'] = 0;

/**
 * Define sets of characters which a password must contain
 * symbol, lower_alpha, upper_alpha and number are special
 * strings and will render the charset for you. Any other
 * string will be treated as a charset itself.
 */

$config['authPasswordRules']['contains']   = array();
$config['authPasswordRules']['contains'][] = 'symbol';
$config['authPasswordRules']['contains'][] = 'lower_alpha';
$config['authPasswordRules']['contains'][] = 'upper_alpha';
$config['authPasswordRules']['contains'][] = 'number';

//  Define strings which should not be used as a password
$config['authPasswordRules']['isNot']   = array();
$config['authPasswordRules']['isNot'][] = 'password';
$config['authPasswordRules']['isNot'][] = '123456789';

/**
 * Define how long passwords are valid for, in days. Leave
 * empty to disable this functionality.
 */

$config['authPasswordExpireAfter'] = 0;
