<?php

/**
 * Auth config (Access Tokens)
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Config
 * @author      Nails Dev Team
 * @link
 */

/**
 * Define how long the default expiration should be for access tokens; this should
 * be a value accepted by the DateInterval constructor.
 */
$config['authAccessTokenDefaultExpiration'] = 'P6M';

/**
 * Token template, defines the structure of the token, X's will be replaced with
 * the appropriate character. More of a vanity thing than anything else.
 */
$config['authAccessTokenTemplate'] = 'XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX-XXXX/XXX-XXXX-XXX';

/**
 * The characters which will make up the token; replace the X's in authAccessTokenTemplate.
 */
$config['authAccessTokenCharacters'] = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
