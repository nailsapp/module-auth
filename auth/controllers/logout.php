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
        if ( ! $this->user_model->isLoggedIn() ) :

            redirect( '/' );

        endif;

        // --------------------------------------------------------------------------

        //  Handle flashdata, if there's anything there pass it along as GET variables.
        //  We're about to destroy the session so they'll go bye-bye unless we do
        //  something with 'em.

        $_flash             = array();
        $_flash['name']     = activeUser( 'first_name' );
        $_flash['success']  = $this->session->flashdata( 'success' );
        $_flash['error']    = $this->session->flashdata( 'error' );
        $_flash['notice']   = $this->session->flashdata( 'notice' );
        $_flash['message']  = $this->session->flashdata( 'message' );

        // --------------------------------------------------------------------------

        //  Generate an event for this log in
        create_event('did_log_out');

        // --------------------------------------------------------------------------

        //  Log user out
        $this->auth_model->logout();

        //  Log social media out, too
        $this->load->library( 'auth/social_signon' );
        $this->social_signon->logout();

        // --------------------------------------------------------------------------

        //  Redirect via helper method
        redirect( 'auth/logout/bye?' . http_build_query( $_flash ) );
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

        $_get = $this->input->get();

        // --------------------------------------------------------------------------

        if ( ! empty( $_get['success'] ) ) :

            $this->session->set_flashdata( 'success', $_get['success'] );

        else :

            $this->session->set_flashdata( 'success', lang( 'auth_logout_successful', $_get['name'] ) );

        endif;

        // --------------------------------------------------------------------------

        //  Set any other flashdata which might be needed
        if ( is_array( $_get ) ) :

            foreach ( $_get as $key => $value ) :

                if ( $value ) :

                    $this->session->set_flashdata( $key, $value );

                endif;

            endforeach;

        endif;

        // --------------------------------------------------------------------------

        redirect( '/' );
    }
}
