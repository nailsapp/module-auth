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

namespace Nails\Auth\Api\Controller;

use Nails\Api\Controller\Base;
use Nails\Factory;

class Me extends Base
{
    /**
     * Require the user be authenticated to use any endpoint
     */
    const REQUIRE_AUTH = true;

    // --------------------------------------------------------------------------

    /**
     * Returns basic details about the currently logged in user
     * @return ApiResponse
     */
    public function anyIndex()
    {
        return Factory::factory('ApiResponse', 'nails/module-api')
                      ->setData([
                          'id'         => (int) activeUser('id'),
                          'first_name' => activeUser('first_name') ?: null,
                          'last_name'  => activeUser('last_name') ?: null,
                          'email'      => activeUser('email') ?: null,
                          'username'   => activeUser('username') ?: null,
                          'avatar'     => cdnAvatar() ?: null,
                          'gender'     => activeUser('gender') ?: null,
                      ]);
    }
}
