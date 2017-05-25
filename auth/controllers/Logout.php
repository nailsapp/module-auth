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

use Nails\Factory;
use Nails\Auth\Controller\Base;

class Logout extends Base
{
    /**
     * Log user out and forward to homepage (or via helper method if needed).
     *
     * @access  public
     * @param   none
     * @return  void
     **/
    public function index()
    {
        //  If already logged out just send them silently on their way
        if (!$this->user_model->isLoggedIn()) {
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  Handle flashdata, if there's anything there pass it along as GET variables.
        //  We're about to destroy the session so they'll go bye-bye unless we do
        //  something with 'em.

        $aFlash             = array();
        $aFlash['name']     = activeUser('first_name');
        $aFlash['success']  = $this->session->flashdata('success');
        $aFlash['error']    = $this->session->flashdata('error');
        $aFlash['notice']   = $this->session->flashdata('notice');
        $aFlash['message']  = $this->session->flashdata('message');

        // --------------------------------------------------------------------------

        //  Generate an event for this log in
        create_event('did_log_out');

        // --------------------------------------------------------------------------

        //  Log user out
        $this->auth_model->logout();

        //  Log social media out, too
        $oSocial = Factory::service('SocialSignOn', 'nailsapp/module-auth');
        $oSocial->logout();

        // --------------------------------------------------------------------------

        //  Redirect via helper method
        redirect('auth/logout/bye?' . http_build_query($aFlash));
    }


    // --------------------------------------------------------------------------


    /**
     * Helper function to recreate a session (seeing as we destroyed it
     * during logout); allows us to pass a message along if needed.
     *
     * @access  public
     * @param   none
     * @return  void
     **/
    public function bye()
    {
        //  If there's no 'success' GET set our default log out message
        //  otherwise keep any which might be coming our way.

        $aGet = $this->input->get();

        // --------------------------------------------------------------------------

        if (!empty($aGet['success'])) {

            $this->session->set_flashdata('success', $aGet['success']);

        } else {

            $this->session->set_flashdata('success', lang('auth_logout_successful', $aGet['name']));
        }

        // --------------------------------------------------------------------------

        //  Set any other flashdata which might be needed
        if (is_array($aGet)) {

            foreach ($aGet as $key => $value) {
                if ($value) {
                    $this->session->set_flashdata($key, $value);
                }
            }
        }

        redirect('/');
    }
}
