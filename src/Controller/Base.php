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

abstract class Base extends \App\Controller\Base
{
    /**
     * Base constructor.
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    public function __construct()
    {
        parent::__construct();
        $oConfig = Factory::service('Config');
        $oConfig->load('auth/auth');
        get_instance()->lang->load('auth/auth');
    }

    // --------------------------------------------------------------------------

    /**
     * Loads Auth styles if supplied view does not exist
     *
     * @param string $sView The view to test
     *
     * @throws \Nails\Common\Exception\FactoryException
     */
    protected function loadStyles($sView)
    {
        //  Test if a view has been provided by the app
        if (!is_file($sView)) {
            $oAsset = Factory::service('Asset');
            $oAsset->clear();
            $oAsset->load('nails.min.css', 'nails/common');
            $oAsset->load('styles.min.css', 'nails/module-auth');
        }
    }
}
