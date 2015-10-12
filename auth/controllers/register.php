<?php

/**
 * User registration facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use \Nails\Auth\Controller\Base;

class Register extends Base
{
    /**
     * Construct the controller
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Is registration enabled
        if (!app_setting('user_registration_enabled', 'auth')) {

            show_404();
        }

        // --------------------------------------------------------------------------

        //  Load libraries
        $this->load->library('form_validation');
        $this->load->library('auth/social_signon');

        // --------------------------------------------------------------------------

        //  Specify a default title for this page
        $this->data['page']->title = lang('auth_title_register');
    }

    // --------------------------------------------------------------------------

    /**
     * Display registration form, validate data and create user
     * @return void
     */
    public function index()
    {
        //  If you're logged in you shouldn't be accessing this method
        if ($this->user_model->isLoggedIn()) {

            $this->session->set_flashdata(
                'error',
                lang('auth_no_access_already_logged_in', activeUser('email'))
            );
            redirect('/');
        }

        // --------------------------------------------------------------------------

        $iDefaultGroupId = $this->user_group_model->getDefaultGroupId();

        // --------------------------------------------------------------------------

        //  If there's POST data attempt to log user in
        if ($this->input->post()) {

            //  Validate input
            $this->form_validation->set_rules('first_name', '', 'required|xss_clean');
            $this->form_validation->set_rules('last_name', '', 'required|xss_clean');
            $this->form_validation->set_rules('password', '', 'required|xss_clean');

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $this->form_validation->set_rules(
                    'email',
                    '',
                    'xss_clean|required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                );

                if ($this->input->post('username')) {

                    $this->form_validation->set_rules('email', '', 'xss_clean');
                }

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $this->form_validation->set_rules('username', '', 'xss_clean|required');

                if ($this->input->post('email')) {

                    $this->form_validation->set_rules(
                        'email',
                        '',
                        'xss_clean|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                    );
                }

            } else {

                $this->form_validation->set_rules(
                    'email',
                    '',
                    'xss_clean|required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                );
                $this->form_validation->set_rules(
                    'username',
                    '',
                    'xss_clean|required'
                );
            }

            // --------------------------------------------------------------------------

            //  Change default messages
            $this->form_validation->set_message('required', lang('fv_required'));
            $this->form_validation->set_message('valid_email', lang('fv_valid_email'));

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $this->form_validation->set_message(
                    'is_unique',
                    lang('auth_register_email_is_unique', site_url('auth/forgotten_password'))
                );

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $this->form_validation->set_message(
                    'is_unique',
                    lang('auth_register_username_is_unique', site_url('auth/forgotten_password'))
                );

            } else {

                $this->form_validation->set_message(
                    'is_unique',
                    lang('auth_register_identity_is_unique', site_url('auth/forgotten_password'))
                );
            }

            // --------------------------------------------------------------------------

            //  Run validation
            if ($this->form_validation->run()) {

                //  Attempt the registration
                $aInsertData               = array();
                $aInsertData['email']      = $this->input->post('email');
                $aInsertData['username']   = $this->input->post('username');
                $aInsertData['group_id']   = $iDefaultGroupId;
                $aInsertData['password']   = $this->input->post('password');
                $aInsertData['first_name'] = $this->input->post('first_name');
                $aInsertData['last_name']  = $this->input->post('last_name');

                // --------------------------------------------------------------------------

                //  Handle referrals
                if ($this->session->userdata('referred_by')) {

                    $aInsertData['referred_by'] = $this->session->userdata('referred_by');
                }

                // --------------------------------------------------------------------------

                //  Create new user
                $oNewUser = $this->user_model->create($aInsertData);

                if ($oNewUser) {

                    //  Fetch user and group data
                    $oGroup = $this->user_group_model->get_by_id($aInsertData['group_id']);

                    // --------------------------------------------------------------------------

                    //  Log the user in
                    $this->user_model->setLoginData($oNewUser->id);

                    // --------------------------------------------------------------------------

                    //  Create an event for this event
                    create_event('did_register', array('method' => 'native'), $oNewUser->id);

                    // --------------------------------------------------------------------------

                    //  Redirect to the group homepage
                    //  @todo: There should be the option to enable/disable forced activation

                    $this->session->set_flashdata('success', lang('auth_register_flashdata_welcome', $oNewUser->first_name));

                    $sRedirect = $oGroup->registration_redirect ? $oGroup->registration_redirect : $oGroup->default_homepage;

                    redirect($sRedirect);

                } else {

                    $this->data['error'] = 'Could not create new user account. ' . $this->user_model->last_error();
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        $this->data['social_signon_enabled']   = $this->social_signon->is_enabled();
        $this->data['social_signon_providers'] = $this->social_signon->get_providers('ENABLED');
        $this->data['passwordRulesAsString']   = $this->user_password_model->getRulesAsString($iDefaultGroupId);

        // --------------------------------------------------------------------------

        //  Load the views
        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/register/form', $this->data);
        $this->load->view('structure/footer', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Allows a user to resend their activation email
     * @return void
     */
    public function resend()
    {
        $iId    = (int) $this->uri->segment(4);
        $sHash  = $this->uri->segment(5);

        // --------------------------------------------------------------------------

        //  We got details?
        if (empty($iId) || empty($sHash)) {

            $this->session->set_flashdata('error', lang('auth_register_resend_invalid'));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  Valid user?
        $oUser = $this->user_model->get_by_id($iId);

        if (!$oUser) {

            $this->session->set_flashdata('error', lang('auth_register_resend_invalid'));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  Account active?
        if ($oUser->email_is_verified) {

            $this->session->set_flashdata(
                'message',
                lang('auth_register_resend_already_active', site_url('auth/login'))
            );
            redirect('auth/login');
        }

        // --------------------------------------------------------------------------

        //  Hash match?
        if (md5($oUser->activation_code) != $sHash) {

            $this->session->set_flashdata('error', lang('auth_register_resend_invalid'));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  All good, resend now
        $oEmail                          = new StdClass();
        $oEmail->to                      = $oUser->email;
        $oEmail->type                    = 'register_activate_resend';
        $oEmail->data                    = array();
        $oEmail->data['first_name']      = $oUser->first_name;
        $oEmail->data['user_id']         = $oUser->id;
        $oEmail->data['activation_code'] = $oUser->activation_code;

        // --------------------------------------------------------------------------

        //  Send it off now
        $this->emailer->send_now($oEmail);

        // --------------------------------------------------------------------------

        //  Set some data for the view
        $this->data['email'] = $oUser->email;

        // --------------------------------------------------------------------------

        //  Load the views
        $this->load->view('structure/header', $this->data);
        $this->load->view('auth/register/resend', $this->data);
        $this->load->view('structure/footer', $this->data);
    }
}
