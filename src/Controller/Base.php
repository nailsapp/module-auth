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
    public function __construct()
    {
        parent::__construct();
        $oConfig = Factory::service('Config');
        $oConfig->load('auth/auth');
        $this->lang->load('auth/auth');
    }

    // --------------------------------------------------------------------------

    /**
     * Loads Auth styles if supplied view does not exist
     *
     * @param string $sView The view to test
     */
    protected function loadStyles($sView)
    {
        //  Test if a view has been provided by the app
        if (!is_file($sView)) {
            $oAsset = Factory::service('Asset');
            $oAsset->clear();
            $oAsset->load('nails.min.css', 'nails/common');
            $oAsset->load('styles.css', 'nails/module-auth');
        }
    }
}
