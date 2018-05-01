<?php

/**
 * Returns information about the currently logged in user
 *
 * @package     Nails
 * @subpackage  module-api
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Api\Auth;

class Me extends \Nails\Api\Controller\Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * Returns basic details about the currently logged in user
     * @return array
     */
    public function anyIndex()
    {
        return [
            'user' => [
                'id'         => activeUser('id'),
                'first_name' => activeUser('first_name'),
                'last_name'  => activeUser('last_name'),
                'email'      => activeUser('email'),
                'username'   => activeUser('username'),
                'avatar'     => cdnAvatar(),
                'gender'     => activeUser('gender')
            ]
        ];
    }

    // --------------------------------------------------------------------------

    /**
     * Get the current user's avatar
     * @param  int   $iWidth  The width of the avatar
     * @param  int   $iHeight The height of the avatar
     * @return array
     */
    public function anyAvatar($iWidth = null, $iHeight = null)
    {
        $iWidth  = $iWidth ? $iWidth : 100;
        $iHeight = $iHeight ? $iHeight : 100;

        return [
            'url' => cdnAvatar(null, $iWidth, $iHeight)
        ];
    }
}
