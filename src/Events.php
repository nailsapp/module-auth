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

    /**
     * Fired when a user is deleted
     *
     * @param int $iId The ID of the user who was deleted
     */
    const USER_DELETED = 'AUTH:USER:DELETED';

    /**
     * Fired when a user is destroyed
     *
     * @param int $iId The ID of the user who was destroyed
     */
    const USER_DESTROYED = 'AUTH:USER:DESTROYED';

    /**
     * Fired when a user logs in
     *
     * @param int $iId The ID of the user who logged in
     */
    const USER_LOG_IN = 'AUTH:USER:LOGGED_IN';

    /**
     * Fired when a user logs out
     *
     * @param int $iId The ID of the user who logged out
     */
    const USER_LOG_OUT = 'AUTH:USER:LOGGED_OUT';
}
