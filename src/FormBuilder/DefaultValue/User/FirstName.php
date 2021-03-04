<?php

/**
 * This class provides the "User\FirstName" default value to the form builder
 *
 * @package    Nails
 * @subpackage module-auth
 * @author     Nails Dev Team
 */

namespace Nails\Auth\FormBuilder\DefaultValue\User;

use Nails\FormBuilder\DefaultValue\Base;

/**
 * Class FirstName
 *
 * @package Nails\Auth\FormBuilder\DefaultValue\User
 */
class FirstName extends Base
{
    const LABEL = 'User\'s First Name';

    // --------------------------------------------------------------------------

    /**
     * Return the calculated default value
     *
     * @return mixed
     */
    public function defaultValue()
    {
        return activeUser('first_name');
    }
}
