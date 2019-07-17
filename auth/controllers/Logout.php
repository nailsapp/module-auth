<?php

/**
 * Logs a user out
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Auth\Controller\Base;
use Nails\Auth\Model\Auth;
use Nails\Auth\Service\SocialSignOn;
use Nails\Factory;

/**
 * Class Logout
 */
class Logout extends Base
{
    /**
     * Log user out and forward to homepage (or via helper method if needed).
     */
    public function index()
    {
        //  If already logged out just send them silently on their way
        if (!isLoggedIn()) {
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  Generate an event for this log in
        create_event('did_log_out');

        // --------------------------------------------------------------------------

        /** @var SocialSignOn $oSocial */
        $oSocial = Factory::service('SocialSignOn', 'nails/module-auth');
        /** @var Auth $oAuthModel */
        $oAuthModel = Factory::model('Auth', 'nails/module-auth');

        $oSocial->logout();
        $oAuthModel->logout();

        // --------------------------------------------------------------------------

        redirect();
    }
}
