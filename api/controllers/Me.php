<?php

namespace Nails\Api\Auth;

/**
 * Returns information about the currently logged in user
 *
 * @package     Nails
 * @subpackage  module-api
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

class Me extends \ApiController
{
    public static $requiresAuthentication = true;

    // --------------------------------------------------------------------------

    /**
     * Returns basic details about the currently logged in user
     * @return array
     */
    public function anyIndex()
    {
        return array(
            'user' => array(
                'id'        => activeUser('id'),
                'firstName' => activeUser('first_name'),
                'lastName'  => activeUser('last_name'),
                'email'     => activeUser('email'),
                'username'  => activeUser('username'),
                'avatar'    => cdn_avatar(),
                'gender'    => activeUser('gender')
            )
        );
    }

    // --------------------------------------------------------------------------

    /**
     * Get the current user's avatar
     * @param  int   $width  The width of the avatar
     * @param  int   $height The height of the avatar
     * @return array
     */
    public function anyAvatar($width = null, $height = null)
    {
        $width  = $width ? $width : 100;
        $height = $height ? $height : 100;

        return array(
            'url' => cdn_avatar(null, $width, $height)
        );
    }
}