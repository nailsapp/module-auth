<?php

/**
 * This class provides some common Auth controller functionality
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Controller;

use Nails\Factory;

class Base extends \NAILS_Controller
{
    protected $auth_model;

    // --------------------------------------------------------------------------

    public function __construct()
    {
        parent::__construct();
        $this->config->load('auth/auth');
        $this->lang->load('auth/auth');
        $this->asset->load('nails.auth.login.css', 'NAILS');
        $this->auth_model = Factory::model('Auth', 'nailsapp/module-auth');
    }
}
