<?php

/**
 * Forgotten password facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 * @todo        Refactor this class so that not so much code is being duplicated, especially re: MFA
 */

use Nails\Auth\Controller\Base;
use Nails\Factory;

class PasswordForgotten extends Base
{
    /**
     * PasswordForgotten constructor.
     */
    public function __construct()
    {
        parent::__construct();

        // --------------------------------------------------------------------------

        //  Specify a default title for this page
        $this->data['page']->title = lang('auth_title_forgotten_password');
    }

    // --------------------------------------------------------------------------

    /**
     * Reset password form
     */
    protected function index()
    {
        //  If user is logged in they shouldn't be accessing this method
        if (isLoggedIn()) {
            $oSession = Factory::service('Session', 'nailsapp/module-auth');
            $oSession->set_flashdata('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }

        //  If there's POST data attempt to validate the user
        $oInput = Factory::service('Input');
        if ($oInput->post() || $oInput->get('identifier')) {

            //  Define vars
            $sIdentifier = $oInput->post('identifier');

            /**
             * Override with the $_GET variable if POST failed to return anything. Populate
             * the $_POST var with some data so form validation continues as normal, feels
             * hacky but works.
             */

            if (!$sIdentifier && $oInput->get('identifier')) {
                $_POST['identifier'] = $oInput->get('identifier');
                $sIdentifier         = $oInput->get('identifier');
            }

            // --------------------------------------------------------------------------

            /**
             * Set rules.
             * The rules vary depending on what login method is enabled.
             */

            $oFormValidation = Factory::service('FormValidation');

            switch (APP_NATIVE_LOGIN_USING) {
                case 'EMAIL':
                    $oFormValidation->set_rules('identifier', '', 'required|trim|valid_email');
                    break;

                case 'USERNAME':
                    $oFormValidation->set_rules('identifier', '', 'required|trim');
                    break;

                default:
                    $oFormValidation->set_rules('identifier', '', 'trim');
                    break;
            }

            // --------------------------------------------------------------------------

            //  Override default messages
            $oFormValidation->set_message('required', lang('fv_required'));
            $oFormValidation->set_message('valid_email', lang('fv_valid_email'));

            // --------------------------------------------------------------------------

            //  Run validation
            if ($oFormValidation->run()) {

                /**
                 * Some apps may want the forgotten password tool to always return as successful,
                 * even if it wasn't. Bad UX, if you ask me, but I'm not the client.
                 */

                $oConfig        = Factory::service('Config');
                $bAlwaysSucceed = $oConfig->item('authForgottenPassAlwaysSucceed');

                //  Attempt to reset password
                $oUserPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
                if ($oUserPasswordModel->setToken($sIdentifier)) {

                    //  Send email to user
                    $oUserModel = Factory::model('User', 'nailsapp/module-auth');
                    switch (APP_NATIVE_LOGIN_USING) {

                        case 'EMAIL':
                            //  User provided an email, send to that email
                            $this->data['reset_user'] = $oUserModel->getByEmail($sIdentifier);
                            $sSendToEmail             = $sIdentifier;
                            break;

                        case 'USERNAME':
                            /**
                             * Can't email a username, send to their ID and let the email library
                             * handle the routing
                             */

                            $this->data['reset_user'] = $oUserModel->getByUsername($sIdentifier);
                            $iSendToId                = $this->data['reset_user']->id;
                            break;

                        default:
                            if (valid_email($sIdentifier)) {

                                //  User provided an email, send to that email
                                $this->data['reset_user'] = $oUserModel->getByEmail($sIdentifier);
                                $sSendToEmail             = $sIdentifier;

                            } else {

                                /**
                                 * Can't email a username, send to their ID and let the email library handle
                                 * the routing
                                 */

                                $this->data['reset_user'] = $oUserModel->getByUsername($sIdentifier);
                                $iSendToId                = $this->data['reset_user']->id;
                            }
                            break;
                    }

                    // --------------------------------------------------------------------------

                    if (!$bAlwaysSucceed && isset($sSendToEmail) && !$sSendToEmail) {

                        //  If we're expecting an email, and none is available then we're kinda stuck
                        $this->data['error'] = lang('auth_forgot_email_fail_no_email');

                    } elseif (!$bAlwaysSucceed && isset($iSendToId) && !$iSendToId) {

                        //  If we're expecting an ID and it's empty then we're stuck again
                        $this->data['error'] = lang('auth_forgot_email_fail_no_id');

                    } elseif ($bAlwaysSucceed) {

                        //  Failed, but we always succeed so, yeah, succeed
                        $this->data['success'] = lang('auth_forgot_success');

                    } else {

                        //  We've got something, go go go
                        $oEmail       = new stdClass();
                        $oEmail->type = 'forgotten_password';

                        if (!empty($sSendToEmail)) {
                            $oEmail->to_email = $sSendToEmail;
                        } elseif (!empty($iSendToId)) {
                            $oEmail->to_id = $iSendToId;
                        }

                        // --------------------------------------------------------------------------

                        //  Add data for the email view
                        $aCode = explode(':', $this->data['reset_user']->forgotten_password_code);

                        $oEmail->data             = new \stdClass();
                        $oEmail->data->resetUrl   = site_url('auth/password/forgotten/' . $aCode[1]);
                        $oEmail->data->identifier = $sIdentifier;

                        // --------------------------------------------------------------------------

                        //  Send user the password reset email
                        $oEmailer = Factory::service('Emailer', 'nailsapp/module-email');
                        if ($oEmailer->send($oEmail)) {
                            $this->data['success'] = lang('auth_forgot_success');
                        } elseif ($bAlwaysSucceed) {
                            $this->data['success'] = lang('auth_forgot_success');
                        } else {
                            $this->data['error'] = lang('auth_forgot_email_fail');
                        }
                    }

                } elseif ($bAlwaysSucceed) {

                    $this->data['success'] = lang('auth_forgot_success');

                } else {

                    switch (APP_NATIVE_LOGIN_USING) {
                        case 'EMAIL':
                            $this->data['error'] = lang('auth_forgot_code_not_set_email', $sIdentifier);
                            break;

                        case 'USERNAME':
                            $this->data['error'] = lang('auth_forgot_code_not_set_username', $sIdentifier);
                            break;

                        default:
                            $this->data['error'] = lang('auth_forgot_code_not_set');
                            break;
                    }
                }

            } else {
                $this->data['error'] = lang('fv_there_were_errors');
            }
        }

        //  Load the views
        $oView = Factory::service('View');
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/password/forgotten', $this->data);
        $oView->load('structure/footer/blank', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a code
     *
     * @param   string $sCode The code to validate
     *
     * @return  void
     */
    protected function validate($sCode)
    {
        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oConfig  = Factory::service('Config');
        $oView    = Factory::service('View');

        /**
         * Attempt to verify code, if two factor auth is enabled then don't generate a
         * new password, we'll need the user to jump through some hoops first.
         */
        $bGenerateNewPw     = !$oConfig->item('authTwoFactorMode');
        $oUserPasswordModel = Factory::model('UserPassword', 'nailsapp/module-auth');
        $aNewPw             = $oUserPasswordModel->validateToken($sCode, $bGenerateNewPw);

        // --------------------------------------------------------------------------

        //  Determine outcome of validation
        if ($aNewPw === 'EXPIRED') {

            //  Code has expired
            $this->data['error'] = lang('auth_forgot_expired_code');

        } elseif ($aNewPw === false) {

            //  Code was invalid
            $this->data['error'] = lang('auth_forgot_invalid_code');

        } else {

            $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
            $oInput     = Factory::service('Input');

            if ($oConfig->item('authTwoFactorMode') == 'QUESTION') {

                //  Show them a security question
                $this->data['question'] = $oAuthModel->mfaQuestionGet($aNewPw['user_id']);

                if ($this->data['question']) {

                    if ($oInput->post()) {

                        $bIsValid = $oAuthModel->mfaQuestionValidate(
                            $this->data['question']->id,
                            $aNewPw['user_id'],
                            $oInput->post('answer')
                        );

                        if ($bIsValid) {

                            //  Correct answer, reset password and render views
                            $aNewPw = $oUserPasswordModel->validateToken($sCode, true);

                            $this->data['new_password'] = $aNewPw['password'];

                            // --------------------------------------------------------------------------

                            //  Set some flashdata for the login page when they go to it; just a little reminder
                            $sStatus  = 'notice';
                            $sMessage = lang('auth_forgot_reminder', htmlentities($aNewPw['password']));

                            $oSession->set_flashdata($sStatus, $sMessage);

                            // --------------------------------------------------------------------------

                            //  Load the views
                            $oView->load('structure/header/blank', $this->data);
                            $oView->load('auth/password/forgotten_reset', $this->data);
                            $oView->load('structure/footer/blank', $this->data);
                            return;

                        } else {
                            $this->data['error'] = lang('auth_twofactor_answer_incorrect');
                        }
                    }

                    $this->data['page']->title = lang('auth_title_forgotten_password_security_question');

                    $oView->load('structure/header/blank', $this->data);
                    $oView->load('auth/mfa/question/ask', $this->data);
                    $oView->load('structure/footer/blank', $this->data);

                } else {

                    //  No questions, reset and load views
                    $aNewPw = $oUserPasswordModel->validateToken($sCode, true);

                    $this->data['new_password'] = $aNewPw['password'];

                    // --------------------------------------------------------------------------

                    //  Set some flashdata for the login page when they go to it; just a little reminder
                    $sStatus  = 'notice';
                    $sMessage = lang('auth_forgot_reminder', htmlentities($aNewPw['password']));

                    $oSession->set_flashdata($sStatus, $sMessage);

                    // --------------------------------------------------------------------------

                    //  Load the views
                    $oView->load('structure/header/blank', $this->data);
                    $oView->load('auth/password/forgotten_reset', $this->data);
                    $oView->load('structure/footer/blank', $this->data);
                }

            } elseif ($oConfig->item('authTwoFactorMode') == 'DEVICE') {

                $oSecret = $oAuthModel->mfaDeviceSecretGet($aNewPw['user_id']);

                if ($oSecret) {

                    if ($oInput->post()) {

                        $sMfaCode = $oInput->post('mfaCode');

                        //  Verify the inout
                        if ($oAuthModel->mfaDeviceCodeValidate($aNewPw['user_id'], $sMfaCode)) {

                            //  Correct answer, reset password and render views
                            $aNewPw = $oUserPasswordModel->validateToken($sCode, true);

                            $this->data['new_password'] = $aNewPw['password'];

                            // --------------------------------------------------------------------------

                            //  Set some flashdata for the login page when they go to it; just a little reminder
                            $sStatus  = 'notice';
                            $sMessage = lang('auth_forgot_reminder', htmlentities($aNewPw['password']));

                            $oSession->set_flashdata($sStatus, $sMessage);

                            // --------------------------------------------------------------------------

                            //  Load the views
                            $oView->load('structure/header/blank', $this->data);
                            $oView->load('auth/password/forgotten_reset', $this->data);
                            $oView->load('structure/footer', $this->data);
                            return;

                        } else {

                            $this->data['error'] = 'Sorry, that code failed to validate. Please try again. ';
                            $this->data['error'] .= $oAuthModel->lastError();
                        }
                    }

                    $this->data['page']->title = 'Please enter the code from your device';

                    $oView->load('structure/header/blank', $this->data);
                    $oView->load('auth/mfa/device/ask', $this->data);
                    $oView->load('structure/footer', $this->data);

                } else {

                    //  No devices, reset and load views
                    $aNewPw = $oUserPasswordModel->validateToken($sCode, true);

                    $this->data['new_password'] = $aNewPw['password'];

                    // --------------------------------------------------------------------------

                    //  Set some flashdata for the login page when they go to it; just a little reminder
                    $sStatus  = 'notice';
                    $sMessage = lang('auth_forgot_reminder', htmlentities($aNewPw['password']));

                    $oSession->set_flashdata($sStatus, $sMessage);

                    // --------------------------------------------------------------------------

                    //  Load the views
                    $oView->load('structure/header/blank', $this->data);
                    $oView->load('auth/password/forgotten_reset', $this->data);
                    $oView->load('structure/footer/blank', $this->data);
                }

            } else {

                //  Everything worked!
                $this->data['new_password'] = $aNewPw['password'];

                // --------------------------------------------------------------------------

                //  Set some flashdata for the login page when they go to it; just a little reminder
                $sStatus  = 'notice';
                $sMessage = lang('auth_forgot_reminder', htmlentities($aNewPw['password']));

                $oSession->set_flashdata($sStatus, $sMessage);

                // --------------------------------------------------------------------------

                //  Load the views
                $oView->load('structure/header/blank', $this->data);
                $oView->load('auth/password/forgotten_reset', $this->data);
                $oView->load('structure/footer/blank', $this->data);
            }

            return;
        }

        // --------------------------------------------------------------------------

        //  Load the views
        $oView->load('structure/header/blank', $this->data);
        $oView->load('auth/password/forgotten', $this->data);
        $oView->load('structure/footer/blank', $this->data);
    }

    // --------------------------------------------------------------------------

    /**
     * Route requests to the right method
     *
     * @param string $sMethod The method being called
     */
    public function _remap($sMethod)
    {
        //  If you're logged in you shouldn't be accessing this method
        if (isLoggedIn()) {
            $oSession = Factory::service('Session', 'nailsapp/module-auth');
            $oSession->set_flashdata('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        $oUri  = Factory::service('Uri');
        $sCode = $oUri->segment(4);
        if (empty($sCode)) {
            $this->index();
        } else {
            $this->validate($sCode);
        }
    }
}
