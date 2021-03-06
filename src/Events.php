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

use Nails\Auth\Event\Listener;
use Nails\Common\Events\Base;
use Nails\Common\Events\Subscription;

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
     * @param int                       $iId      The ID of the user who was modified
     * @param \Nails\Auth\Resource\User $oOldUser The user object before it was updated
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
     * @param \Nails\Auth\Resource\User $oUser           The user who logged in
     * @param bool                      $bSetSessionData Whether session data was set
     */
    const USER_LOG_IN = 'AUTH:USER:LOGGED_IN';

    /**
     * Fired when a user logs out
     *
     * @param int $iId The ID of the user who logged out
     */
    const USER_LOG_OUT = 'AUTH:USER:LOGGED_OUT';
}
