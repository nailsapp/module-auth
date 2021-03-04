<?php

/**
 * This class provides the "UserName" default value
 *
 * @package    Nails
 * @subpackage module-auth
 * @author     Nails Dev Team
 */

namespace Nails\Auth\FormBuilder\DefaultValue\User;

use Nails\FormBuilder\DefaultValue\Base;

/**
 * Class Name
 *
 * @package Nails\Auth\FormBuilder\DefaultValue\User
 */
class Name extends Base
{
    const LABEL = 'User\'s Name';

    // --------------------------------------------------------------------------

    /**
     * Return the calculated default value
     *
     * @return mixed
     */
    public function defaultValue()
    {
        return activeUser('name');
    }
}
