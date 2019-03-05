<?php

/**
 * The class provides a summary of the events fired by this module
 *
 * @package     Nails
 * @subpackage  module-common
 * @category    Events
 * @author      Nails Dev Team
 */

namespace Nails\Auth;

use Nails\Common\Events\Base;

class Events extends Base
{
    /**
     * Fired when a user is created
     *
     * @param int $iId The ID of the user who was created
     */
    const USER_CREATED = 'AUTH:USER:CREATED';

    /**
     * Fired when a user is modified
     *
     * @param int $iId The ID of the user who was modified
     */
    const USER_MODIFIED = 'AUTH:USER:MODIFIED';
}
