<?php

/**
 * This class provides the "User\Email" default value to the form builder
 *
 * @package    Nails
 * @subpackage module-auth
 * @author     Nails Dev Team
 */

namespace Nails\Auth\FormBuilder\DefaultValue\User;

use Nails\FormBuilder\DefaultValue\Base;

/**
 * Class Email
 *
 * @package Nails\Auth\FormBuilder\DefaultValue\User
 */
class Email extends Base
{
    const LABEL = 'User\'s Email';

    // --------------------------------------------------------------------------

    /**
     * Return the calculated default value
     *
     * @return mixed
     */
    public function defaultValue()
    {
        return activeUser('email');
    }
}
