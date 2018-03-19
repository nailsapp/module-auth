<?php

/**
 * USer logout facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Auth\Controller\Base;
use Nails\Factory;

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

        //  Log social media out
        $oSocial = Factory::service('SocialSignOn', 'nailsapp/module-auth');
        $oSocial->logout();

        // --------------------------------------------------------------------------

        //  Log user out
        $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
        $oAuthModel->logout();

        // --------------------------------------------------------------------------

        redirect();
    }
}
