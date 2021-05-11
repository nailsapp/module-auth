<?php

/**
 * This model handles interactions with the app's "nails_user_event" table.
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Model
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Model\User;

use Nails\Auth\Constants;
use Nails\Common\Model\Base;

/**
 * Class Event
 *
 * @package Nails\Auth\Model\User
 */
class Event extends Base
{
    /**
     * The table this model represents
     *
     * @var string
     */
    const TABLE = NAILS_DB_PREFIX . 'user_event';

    /**
     * The name of the resource to use (as passed to \Nails\Factory::resource())
     *
     * @var string
     */
    const RESOURCE_NAME = 'UserEvent';

    /**
     * The provider of the resource to use (as passed to \Nails\Factory::resource())
     *
     * @var string
     */
    const RESOURCE_PROVIDER = Constants::MODULE_SLUG;

    // --------------------------------------------------------------------------

    /**
     * Returns the searchable columns for this module
     *
     * @return string[]
     */
    public function getSearchableColumns(): array
    {
        return [
            'type',
            'data'
        ];
    }
}
