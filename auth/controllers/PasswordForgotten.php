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
use Nails\Auth\Model\Auth;
use Nails\Auth\Model\User\Password;
use Nails\Auth\Service\Session;
use Nails\Common\Exception\NailsException;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Service\Config;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\Input;
use Nails\Email\Service\Emailer;
use Nails\Factory;

/**
 * Class PasswordForgotten
 */
class PasswordForgotten extends Base
{
    /**
     * Constructor
     **/
    public function __construct()
    {
        parent::__construct();
        $this->data['page']->title = lang('auth_title_forgotten_password');
    }

    // --------------------------------------------------------------------------

    /**
     * Reset password form
     *
     * @return  void
     **/
    public function index()
    {
        /** @var Session $oSession */
        $oSession = Factory::service('Session', 'nails/module-auth');
        if (isLoggedIn()) {
            $oSession->setFlashData('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        if ($oInput->post() || $oInput->get('identifier')) {

            try {

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

                /** @var FormValidation $oFormValidation */
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
                if (!$oFormValidation->run()) {
                    throw new ValidationException(lang('fv_there_were_errors'));
                }

                /** @var Config $oConfig */
                $oConfig        = Factory::service('Config');
                $bAlwaysSucceed = $oConfig->item('authForgottenPassAlwaysSucceed');

                //  Attempt to reset password
                /** @var Password $oUserPasswordModel */
                $oUserPasswordModel = Factory::model('UserPassword', 'nails/module-auth');
                if ($oUserPasswordModel->setToken($sIdentifier)) {

                    //  Send email to user
                    /** @var \Nails\Auth\Model\User $oUserModel */
                    $oUserModel = Factory::model('User', 'nails/module-auth');
                    switch (APP_NATIVE_LOGIN_USING) {

                        case 'EMAIL':
                            $this->data['reset_user'] = $oUserModel->getByEmail($sIdentifier);
                            $sSendToEmail             = $sIdentifier;
                            break;

                        case 'USERNAME':
                            $this->data['reset_user'] = $oUserModel->getByUsername($sIdentifier);
                            $sSendToId                = $this->data['reset_user']->id;
                            break;

                        default:
                            if (valid_email($sIdentifier)) {
                                $this->data['reset_user'] = $oUserModel->getByEmail($sIdentifier);
                                $sSendToEmail             = $sIdentifier;
                            } else {
                                $this->data['reset_user'] = $oUserModel->getByUsername($sIdentifier);
                                $sSendToId                = $this->data['reset_user']->id;
                            }
                            break;
                    }

                    // --------------------------------------------------------------------------

                    if (!$bAlwaysSucceed && isset($sSendToEmail) && !$sSendToEmail) {

                        //  If we're expecting an email, and none is available then we're kinda stuck
                        throw new NailsException(lang('auth_forgot_email_fail_no_email'));

                    } elseif (!$bAlwaysSucceed && isset($sSendToId) && !$sSendToId) {

                        //  If we're expecting an ID and it's empty then we're stuck again
                        throw new NailsException(lang('auth_forgot_email_fail_no_id'));

                    } elseif ($bAlwaysSucceed) {

                        //  Failed, but configured to always succeed - nothing to do

                    } else {

                        //  We've got something, go go go
                        $oEmail = (object) [
                            'type' => 'forgotten_password',
                        ];

                        if (isset($sSendToEmail) && $sSendToEmail) {
                            $oEmail->to_email = $sSendToEmail;
                        } elseif (isset($sSendToId) && $sSendToId) {
                            $oEmail->to_id = $sSendToId;
                        }

                        // --------------------------------------------------------------------------

                        //  Add data for the email view
                        $aCode        = explode(':', $this->data['reset_user']->forgotten_password_code);
                        $oEmail->data = (object) [
                            'resetUrl'   => siteUrl('auth/password/forgotten/' . $aCode[1]),
                            'identifier' => $sIdentifier,
                        ];

                        // --------------------------------------------------------------------------

                        /** @var Emailer $oEmailer */
                        $oEmailer = Factory::service('Emailer', 'nails/module-email');
                        if (!$oEmailer->send($oEmail, true)) {
                            if (!$bAlwaysSucceed) {
                                throw new NailsException(lang('auth_forgot_email_fail'));
                            }
                        }
                    }

                } elseif ($bAlwaysSucceed) {

                    $this->data['success'] = lang('auth_forgot_success');

                } else {

                    switch (APP_NATIVE_LOGIN_USING) {

                        case 'EMAIL':
                            $sError = lang('auth_forgot_code_not_set_email', $sIdentifier);
                            break;

                        case 'USERNAME':
                            $sError = lang('auth_forgot_code_not_set_username', $sIdentifier);
                            break;

                        default:
                            $sError = lang('auth_forgot_code_not_set', $sIdentifier);
                            break;
                    }

                    throw new NailsException($sError);
                }

                $oSession->setFlashData('success', lang('auth_forgot_success'));
                redirect('auth/login');

            } catch (Exception $e) {
                $this->data['error'] = $e->getMessage();
            }
        }

        //  Load the views
        $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten.php');

        Factory::service('View')
            ->load([
                'structure/header/blank',
                'auth/password/forgotten',
                'structure/footer/blank',
            ]);
    }

    // --------------------------------------------------------------------------

    /**
     * Validate a code
     *
     * @param string $code The code to validate
     *
     * @return  void
     */
    public function _validate($code)
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Session $oSession */
        $oSession = Factory::service('Session', 'nails/module-auth');
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');
        /** @var Auth $oAuthModel */
        $oAuthModel = Factory::model('Auth', 'nails/module-auth');
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', 'nails/module-auth');

        /**
         * Attempt to verify code, if two factor auth is enabled then don't generate a
         * new password, we'll need the user to jump through some hoops first.
         */
        $generateNewPw = !$oConfig->item('authTwoFactorMode');
        $newPw         = $oUserPasswordModel->validateToken($code, $generateNewPw);

        // --------------------------------------------------------------------------

        //  Determine outcome of validation
        if ($newPw === 'EXPIRED') {

            //  Code has expired
            $this->data['error'] = lang('auth_forgot_expired_code');

        } elseif ($newPw === false) {

            //  Code was invalid
            $this->data['error'] = lang('auth_forgot_invalid_code');

        } else {

            if ($oConfig->item('authTwoFactorMode') == 'QUESTION') {

                //  Show them a security question
                $this->data['question'] = $oAuthModel->mfaQuestionGet($newPw['user_id']);

                if ($this->data['question']) {

                    if ($oInput->post()) {

                        $isValid = $oAuthModel->mfaQuestionValidate(
                            $this->data['question']->id,
                            $newPw['user_id'],
                            $oInput->post('answer')
                        );

                        if ($isValid) {

                            //  Correct answer, reset password and render views
                            $newPw = $oUserPasswordModel->validateToken($code, true);

                            $this->data['new_password'] = $newPw['password'];

                            // --------------------------------------------------------------------------

                            //  Set some flashdata for the login page when they go to it; just a little reminder
                            $status  = 'notice';
                            $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                            $oSession->setFlashData($status, $message);

                            // --------------------------------------------------------------------------

                            //  Load the views
                            $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php');
                            Factory::service('View')
                                ->load([
                                    'structure/header/blank',
                                    'auth/password/forgotten_reset',
                                    'structure/footer/blank',
                                ]);
                            return;

                        } else {
                            $this->data['error'] = lang('auth_twofactor_answer_incorrect');
                        }
                    }

                    $this->data['page']->title = lang('auth_title_forgotten_password_security_question');

                    $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/mfa/question/ask.php');
                    Factory::service('View')
                        ->load([
                            'structure/header/blank',
                            'auth/mfa/question/ask',
                            'structure/footer/blank',
                        ]);

                } else {

                    //  No questions, reset and load views
                    $newPw = $oUserPasswordModel->validateToken($code, true);

                    $this->data['new_password'] = $newPw['password'];

                    // --------------------------------------------------------------------------

                    //  Set some flashdata for the login page when they go to it; just a little reminder
                    $status  = 'notice';
                    $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                    $oSession->setFlashData($status, $message);

                    // --------------------------------------------------------------------------

                    //  Load the views
                    $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php');
                    Factory::service('View')
                        ->load([
                            'structure/header/blank',
                            'auth/password/forgotten_reset',
                            'structure/footer/blank',
                        ]);
                }

            } elseif ($oConfig->item('authTwoFactorMode') == 'DEVICE') {

                $secret = $oAuthModel->mfaDeviceSecretGet($newPw['user_id']);

                if ($secret) {

                    if ($oInput->post()) {

                        $mfaCode = $oInput->post('mfaCode');

                        //  Verify the inout
                        if ($oAuthModel->mfaDeviceCodeValidate($newPw['user_id'], $mfaCode)) {

                            //  Correct answer, reset password and render views
                            $newPw = $oUserPasswordModel->validateToken($code, true);

                            $this->data['new_password'] = $newPw['password'];

                            // --------------------------------------------------------------------------

                            //  Set some flashdata for the login page when they go to it; just a little reminder
                            $status  = 'notice';
                            $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                            $oSession->setFlashData($status, $message);

                            // --------------------------------------------------------------------------

                            //  Load the views
                            $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php');
                            Factory::service('View')
                                ->load([
                                    'structure/header/blank',
                                    'auth/password/forgotten_reset',
                                    'structure/footer/blank',
                                ]);
                            return;

                        } else {

                            $this->data['error'] = 'Sorry, that code failed to validate. Please try again. ';
                            $this->data['error'] .= $oAuthModel->lastError();
                        }
                    }

                    $this->data['page']->title = 'Please enter the code from your device';

                    $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/mfa/device/ask.php');
                    Factory::service('View')
                        ->load([
                            'structure/header/blank',
                            'auth/mfa/device/ask',
                            'structure/footer/blank',
                        ]);

                } else {

                    //  No devices, reset and load views
                    $newPw = $oUserPasswordModel->validateToken($code, true);

                    $this->data['new_password'] = $newPw['password'];

                    // --------------------------------------------------------------------------

                    //  Set some flashdata for the login page when they go to it; just a little reminder
                    $status  = 'notice';
                    $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                    $oSession->setFlashData($status, $message);

                    // --------------------------------------------------------------------------

                    //  Load the views
                    $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php');
                    Factory::service('View')
                        ->load([
                            'structure/header/blank',
                            'auth/password/forgotten_reset',
                            'structure/footer/blank',
                        ]);
                }

            } else {

                //  Everything worked!
                $this->data['new_password'] = $newPw['password'];

                // --------------------------------------------------------------------------

                //  Set some flashdata for the login page when they go to it; just a little reminder
                $status  = 'notice';
                $message = lang('auth_forgot_reminder', htmlentities($newPw['password']));

                $oSession->setFlashData($status, $message);

                // --------------------------------------------------------------------------

                //  Load the views
                $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php');
                Factory::service('View')
                    ->load([
                        'structure/header/blank',
                        'auth/password/forgotten_reset',
                        'structure/footer/blank',
                    ]);
            }

            return;
        }

        // --------------------------------------------------------------------------

        //  Load the views
        $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten.php');
        Factory::service('View')
            ->load([
                'structure/header/blank',
                'auth/password/forgotten',
                'structure/footer/blank',
            ]);
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
            /** @var Session $oSession */
            $oSession = Factory::service('Session', 'nails/module-auth');
            $oSession->setFlashData('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        if ($sMethod == 'index') {
            $this->index();
        } else {
            $this->_validate($sMethod);
        }
    }
}
