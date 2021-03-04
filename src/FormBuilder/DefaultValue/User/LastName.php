<?php

/**
 * This class provides the "User\LastName" default value to the form builder
 *
 * @package    Nails
 * @subpackage module-auth
 * @author     Nails Dev Team
 */

namespace Nails\Auth\FormBuilder\DefaultValue\User;

use Nails\FormBuilder\DefaultValue\Base;

/**
 * Class LastName
 *
 * @package Nails\Auth\FormBuilder\DefaultValue\User
 */
class LastName extends Base
{
    const LABEL = 'User\'s Surname';

    // --------------------------------------------------------------------------

    /**
     * Return the calculated default value
     *
     * @return mixed
     */
    public function defaultValue()
    {
        return activeUser('last_name');
    }
}
