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

use Nails\Auth\Controller\Base;
use Nails\Factory;

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
        $oSession = Factory::service('Session', 'nails/module-auth');

        //  If you're logged in you shouldn't be accessing this method
        if (isLoggedIn()) {
            $oSession->setFlashData(
                'error',
                lang('auth_no_access_already_logged_in', activeUser('email'))
            );
            redirect('/');
        }

        // --------------------------------------------------------------------------

        $oUserGroupModel = Factory::model('UserGroup', 'nails/module-auth');
        $iDefaultGroupId = $oUserGroupModel->getDefaultGroupId();

        // --------------------------------------------------------------------------

        //  If there's POST data attempt to log user in
        $oInput = Factory::service('Input');
        if ($oInput->post()) {

            //  Validate input
            $oFormValidation = Factory::service('FormValidation');
            $oFormValidation->set_rules('first_name', '', 'required');
            $oFormValidation->set_rules('last_name', '', 'required');
            $oFormValidation->set_rules('password', '', 'required');

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {

                $oFormValidation->set_rules(
                    'email',
                    '',
                    'required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                );

            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {

                $oFormValidation->set_rules('username', '', 'required');

                if ($oInput->post('email')) {

                    $oFormValidation->set_rules(
                        'email',
                        '',
                        'valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                    );
                }

            } else {

                $oFormValidation->set_rules(
                    'email',
                    '',
                    'required|valid_email|is_unique[' . NAILS_DB_PREFIX . 'user_email.email]'
                );
                $oFormValidation->set_rules(
                    'username',
                    '',
                    'required'
                );
            }

            // --------------------------------------------------------------------------

            //  Change default messages
            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('valid_email', lang('fv_valid_email'));

            if (APP_NATIVE_LOGIN_USING == 'EMAIL') {
                $sMessage = lang('auth_register_email_is_unique', site_url('auth/password/forgotten'));
            } elseif (APP_NATIVE_LOGIN_USING == 'USERNAME') {
                $sMessage = lang('auth_register_username_is_unique', site_url('auth/password/forgotten'));
            } else {
                $sMessage = lang('auth_register_identity_is_unique', site_url('auth/password/forgotten'));
            }

            $oFormValidation->set_message('is_unique', $sMessage);

            // --------------------------------------------------------------------------

            //  Run validation
            if ($oFormValidation->run()) {

                //  Attempt the registration
                $aInsertData = [
                    'email'      => $oInput->post('email', true),
                    'username'   => $oInput->post('username', true),
                    'group_id'   => $iDefaultGroupId,
                    'password'   => $oInput->post('password', true),
                    'first_name' => $oInput->post('first_name', true),
                    'last_name'  => $oInput->post('last_name', true),
                ];

                // --------------------------------------------------------------------------

                //  Handle referrals
                if ($oSession->getUserData('referred_by')) {
                    $aInsertData['referred_by'] = $oSession->getUserData('referred_by');
                }

                // --------------------------------------------------------------------------

                //  Create new user
                $oUserModel = Factory::model('User', 'nails/module-auth');
                $oNewUser   = $oUserModel->create($aInsertData);

                if ($oNewUser) {

                    //  Create an event for this event
                    create_event('did_register', ['method' => 'native'], $oNewUser->id);

                    //  Log the user in
                    if (!$oUserModel->setLoginData($oNewUser->id)) {
                        //  Login failed for some reason, send them to the login page to try again
                        redirect('auth/login');
                    } else {
                        $oSession->setFlashData(
                            'success',
                            lang('auth_register_flashdata_welcome', $oNewUser->first_name)
                        );
                    }

                    // --------------------------------------------------------------------------

                    //  Redirect to the group homepage
                    //  @todo (Pablo - 2017-07-11) - Setting for forced email activation
                    //  @todo (Pablo - 2017-07-11) - Handle setting MFA questions and/or devices

                    $oGroup = $oUserGroupModel->getById($aInsertData['group_id']);

                    if ($oGroup->registration_redirect) {
                        $sRedirectUrl = $oGroup->registration_redirect;
                    } else {
                        $sRedirectUrl = $oGroup->default_homepage;
                    }

                    redirect($sRedirectUrl);

                } else {
                    $this->data['error'] = 'Could not create new user account. ' . $oUserModel->lastError();
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        // --------------------------------------------------------------------------

        $oSocial            = Factory::service('SocialSignOn', 'nails/module-auth');
        $oUserPasswordModel = Factory::model('UserPassword', 'nails/module-auth');

        $this->data['social_signon_enabled']   = $oSocial->isEnabled();
        $this->data['social_signon_providers'] = $oSocial->getProviders('ENABLED');
        $this->data['passwordRulesAsString']   = $oUserPasswordModel->getRulesAsString($iDefaultGroupId);

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
        $oSession = Factory::service('Session', 'nails/module-auth');

        $iId   = (int) $oUri->segment(4);
        $sHash = $oUri->segment(5);

        // --------------------------------------------------------------------------

        //  We got details?
        if (empty($iId) || empty($sHash)) {
            $oSession->setFlashData('error', lang('auth_register_resend_invalid'));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  Valid user?
        $oUserModel = Factory::model('User', 'nails/module-auth');
        $oUser      = $oUserModel->getById($iId);

        if (!$oUser) {
            $oSession->setFlashData('error', lang('auth_register_resend_invalid'));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  Account active?
        if ($oUser->email_is_verified) {
            $oSession->setFlashData(
                'message',
                lang('auth_register_resend_already_active', site_url('auth/login'))
            );
            redirect('auth/login');
        }

        // --------------------------------------------------------------------------

        //  Hash match?
        if (md5($oUser->activation_code) != $sHash) {
            $oSession->setFlashData('error', lang('auth_register_resend_invalid'));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        //  All good, resend now
        $oEmail = (object) [
            'to'   => $oUser->email,
            'type' => 'register_activate_resend',
            'data' => (object) [
                'first_name'      => $oUser->first_name,
                'user_id'         => $oUser->id,
                'activation_code' => $oUser->activation_code,
            ],
        ];

        // --------------------------------------------------------------------------

        //  Send it off now
        $oEmailer = Factory::service('Emailer', 'nails/module-email');
        $oEmailer->send_now($oEmail);

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
