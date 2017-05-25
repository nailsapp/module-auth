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

use Nails\Factory;
use Nails\Auth\Controller\Base;

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
        if (!appSetting('user_registration_enabled', 'auth')) {

            show_404();
        }

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
        $oSession = Factory::service('Session', 'nailsapp/module-auth');

        //  If you're logged in you shouldn't be accessing this method
        if (isLoggedIn()) {
            $oSession->set_flashdata(
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
            $oFormValidation = Factory::service('FormValidation');
            $oFormValidation->set_rules('first_name', '', 'required|xss_clean');
            $oFormValidation->set_rules('last_name', '', 'required|xss_clean');
            $oFormValidation->set_rules('password', '', 'required|xss_clean');

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $oFormValidation->set_rules(
                    'email',
                    '',
                    'xss_clean|required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                );

                if ($this->input->post('username')) {

                    $oFormValidation->set_rules('email', '', 'xss_clean');
                }

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $oFormValidation->set_rules('username', '', 'xss_clean|required');

                if ($this->input->post('email')) {

                    $oFormValidation->set_rules(
                        'email',
                        '',
                        'xss_clean|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                    );
                }

            } else {

                $oFormValidation->set_rules(
                    'email',
                    '',
                    'xss_clean|required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                );
                $oFormValidation->set_rules(
                    'username',
                    '',
                    'xss_clean|required'
                );
            }

            // --------------------------------------------------------------------------

            //  Change default messages
            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('valid_email', lang('fv_valid_email'));

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $oFormValidation->set_message(
                    'is_unique',
                    lang('auth_register_email_is_unique', site_url('auth/forgotten_password'))
                );

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $oFormValidation->set_message(
                    'is_unique',
                    lang('auth_register_username_is_unique', site_url('auth/forgotten_password'))
                );

            } else {

                $oFormValidation->set_message(
                    'is_unique',
                    lang('auth_register_identity_is_unique', site_url('auth/forgotten_password'))
                );
            }

            // --------------------------------------------------------------------------

            //  Run validation
            if ($oFormValidation->run()) {

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
                if ($oSession->userdata('referred_by')) {
                    $aInsertData['referred_by'] = $oSession->userdata('referred_by');
                }

                // --------------------------------------------------------------------------

                //  Create new user
                $oUserModel = Factory::model('User', 'nailsapp/module-auth');
                $oNewUser   = $oUserModel->create($aInsertData);

                if ($oNewUser) {

                    //  Fetch user and group data
                    $oGroup = $this->user_group_model->getById($aInsertData['group_id']);

                    // --------------------------------------------------------------------------

                    //  Log the user in
                    $oUserModel->setLoginData($oNewUser->id);

                    // --------------------------------------------------------------------------

                    //  Create an event for this event
                    create_event('did_register', array('method' => 'native'), $oNewUser->id);

                    // --------------------------------------------------------------------------

                    //  Redirect to the group homepage
                    //  @todo: There should be the option to enable/disable forced activation

                    $oSession->set_flashdata('success', lang('auth_register_flashdata_welcome', $oNewUser->first_name));

                    $sRedirect = $oGroup->registration_redirect ? $oGroup->registration_redirect : $oGroup->default_homepage;

                    redirect($sRedirect);

                } else {

                    $this->data['error'] = 'Could not create new user account. ' . $oUserModel->lastError();
                }

            } else {

                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        $oSocial = Factory::service('SocialSignOn', 'nailsapp/module-auth');

        $this->data['social_signon_enabled']   = $oSocial->isEnabled();
        $this->data['social_signon_providers'] = $oSocial->getProviders('ENABLED');
        $this->data['passwordRulesAsString']   = $this->user_password_model->getRulesAsString($iDefaultGroupId);

        // --------------------------------------------------------------------------

        //  Load the views
        $oView = Factory::service('View');
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/register/form', $this->data);
        $oView->load('structure/footer/blank', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Allows a user to resend their activation email
     * @return void
     */
    public function resend()
    {
        $oUri     = Factory::service('Uri');
        $oSession = Factory::service('Session', 'nailsapp/module-auth');

        $iId    = (int) $oUri->segment(4);
        $sHash  = $oUri->segment(5);

        // --------------------------------------------------------------------------

        //  We got details?
        if (empty($iId) || empty($sHash)) {
            $oSession->set_flashdata('error', lang('auth_register_resend_invalid'));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  Valid user?
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $oUser      = $oUserModel->getById($iId);

        if (!$oUser) {
            $oSession->set_flashdata('error', lang('auth_register_resend_invalid'));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  Account active?
        if ($oUser->email_is_verified) {
            $oSession->set_flashdata(
                'message',
                lang('auth_register_resend_already_active', site_url('auth/login'))
            );
            redirect('auth/login');
        }

        // --------------------------------------------------------------------------

        //  Hash match?
        if (md5($oUser->activation_code) != $sHash) {
            $oSession->set_flashdata('error', lang('auth_register_resend_invalid'));
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
        $oView = Factory::service('View');
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/register/resend', $this->data);
        $oView->load('structure/footer/blank', $this->data);
    }
}
