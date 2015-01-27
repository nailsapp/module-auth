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
$config['auth_password_rules'] = array();

//  Minimum password length
$config['auth_password_rules']['min_length'] = 6;

//  Maximum password length, 0 means unlimited length
$config['auth_password_rules']['max_length'] = 0;

/**
 * Define sets of characters which a password must contain
 * symbol, lower_alpha, upper_alpha and number are special
 * strings and will render the charset for you. Any other
 * string will be treated as a charset itself.
 */

$config['auth_password_rules']['contains']   = array();
$config['auth_password_rules']['contains'][] = 'symbol';
$config['auth_password_rules']['contains'][] = 'lower_alpha';
$config['auth_password_rules']['contains'][] = 'upper_alpha';
$config['auth_password_rules']['contains'][] = 'number';

//  Define strings which should not be used as a password
$config['auth_password_rules']['is_not']   = array();
$config['auth_password_rules']['is_not'][] = 'password';
$config['auth_password_rules']['is_not'][] = '123456789';