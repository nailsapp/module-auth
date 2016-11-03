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

class Base extends \App\Controller\Base
{
    protected $auth_model;

    // --------------------------------------------------------------------------

    public function __construct()
    {
        parent::__construct();

        $oConfig = Factory::service('Config');
        $oConfig->load('auth/auth');

        $this->lang->load('auth/auth');

        $oAsset = Factory::service('Asset');
        $oAsset->load('styles.css', 'nailsapp/module-auth');

        $this->auth_model = Factory::model('Auth', 'nailsapp/module-auth');
    }
}
