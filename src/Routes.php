<?php

/**
 * Generates Auth routes
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth;

use Nails\Common\Interfaces\RouteGenerator;

class Routes implements RouteGenerator
{
    /**
     * Returns an array of routes for this module
     * @return array
     */
    public static function generate()
    {
        return [
            'auth/password/forgotten(/:any)?'                => 'auth/PasswordForgotten/$1',
            'auth/password/reset/(:num)/(:any)'              => 'auth/PasswordReset/$1/$2',
            'auth/mfa/device/(:num)/(:any)/(:any)(/:any)?'   => 'auth/MfaDevice',
            'auth/mfa/question/(:num)/(:any)/(:any)(/:any)?' => 'auth/MfaQuestion',
        ];
    }
}
