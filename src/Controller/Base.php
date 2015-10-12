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

        // --------------------------------------------------------------------------

        //  Load config
        $this->config->load('auth/auth');

        // --------------------------------------------------------------------------

        //  Load language file
        $this->lang->load('auth/auth');

        // --------------------------------------------------------------------------

        //  Load model
        $this->auth_model = Factory::model('Auth', 'nailsapp/module-auth');
    }
}
