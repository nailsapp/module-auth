<?php

/**
 * This class provides the "User\Id" default value to the form builder
 *
 * @package    Nails
 * @subpackage module-auth
 * @author     Nails Dev Team
 */

namespace Nails\Auth\FormBuilder\DefaultValue\User;

use Nails\FormBuilder\DefaultValue\Base;

/**
 * Class Id
 *
 * @package Nails\Auth\FormBuilder\DefaultValue\User
 */
class Id extends Base
{
    const LABEL = 'User\'s ID';

    // --------------------------------------------------------------------------

    /**
     * Return the calculated default value
     *
     * @return mixed
     */
    public function defaultValue()
    {
        return activeUser('id');
    }
}
