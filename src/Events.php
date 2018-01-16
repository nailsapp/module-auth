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
     * Fired when a user is modified
     *
     * @param \stdClass $iId The ID of the user who was updated
     */
    const USER_MODIFIED = 'AUTH:USER:MODIFIED';

    /**
     * Fired when a user is created
     *
     * @param \stdClass $iId The ID of the user who was created
     */
    const USER_CREATED = 'AUTH:USER:CREATED';
}
