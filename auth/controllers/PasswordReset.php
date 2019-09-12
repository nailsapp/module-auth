<?php

/**
 * Reset password facility
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

use Nails\Auth\Constants;
use Nails\Auth\Controller\Base;
use Nails\Auth\Model\Auth;
use Nails\Auth\Model\User\Password;
use Nails\Auth\Service\Session;
use Nails\Common\Service\Config;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\Input;
use Nails\Common\Service\Uri;
use Nails\Factory;

class PasswordReset extends Base
{
    /**
     * PasswordReset constructor.
     */
    public function __construct()
    {
        parent::__construct();

        //  If user is logged in they shouldn't be accessing this method
        if (isLoggedIn()) {
            /** @var Session $oSession */
            $oSession = Factory::service('Session', Constants::MODULE_SLUG);
            $oSession->setFlashData('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Validate the supplied assets and if valid present the user with a reset form
     *
     * @param int    $iUserId The ID of the user to reset
     * @param string $sHash   The hash to validate against
     *
     * @return  void
     **/
    protected function validate($iUserId, $sHash)
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');
        /** @var \Nails\Auth\Model\User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        /** @var Auth $oAuthModel */
        $oAuthModel = Factory::model('Auth', Constants::MODULE_SLUG);
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);

        //  Check auth credentials
        $oUser = $oUserModel->getById($iUserId);

        // --------------------------------------------------------------------------

        if ($oUser && isset($oUser->salt) && $sHash == md5($oUser->salt)) {

            //  Valid combination, is there MFA on the account?
            if ($oConfig->item('authTwoFactorMode')) {

                /**
                 * This variable will stop the password resetting until we're confident
                 * that MFA has been passed
                 */

                $bMfaValid = false;

                /**
                 * Check the user's account to see if they have MFA enabled, if so
                 * require that they pass that before allowing the password to be reset
                 */

                switch ($oConfig->item('authTwoFactorMode')) {

                    case 'QUESTION':
                        $this->data['mfaQuestion'] = $oAuthModel->mfaQuestionGet($oUser->id);

                        if ($this->data['mfaQuestion']) {

                            if ($oInput->post()) {

                                //  Validate answer
                                $isValid = $oAuthModel->mfaQuestionValidate(
                                    $this->data['mfaQuestion']->id,
                                    $oUser->id,
                                    $oInput->post('mfaAnswer')
                                );

                                if ($isValid) {

                                    $bMfaValid = true;

                                } else {

                                    $this->data['error'] = 'Sorry, the answer to your security ';
                                    $this->data['error'] .= 'question was incorrect.';
                                }
                            }

                        } else {

                            //  No questions set up, allow for now
                            $bMfaValid = true;
                        }

                        break;

                    case 'DEVICE':
                        $this->data['mfaDevice'] = $oAuthModel->mfaDeviceSecretGet($oUser->id);

                        if ($this->data['mfaDevice']) {

                            if ($oInput->post()) {

                                //  Validate answer
                                $isValid = $oAuthModel->mfaDeviceCodeValidate(
                                    $oUser->id,
                                    $oInput->post('mfaCode')
                                );

                                if ($isValid) {

                                    $bMfaValid = true;

                                } else {

                                    $this->data['error'] = 'Sorry, that code could not be validated. ';
                                    $this->data['error'] .= $oAuthModel->lastError();
                                }
                            }

                        } else {

                            //  No devices set up, allow for now
                            $bMfaValid = true;
                        }
                        break;
                }

            } else {

                //  No MFA so just set this to true
                $bMfaValid = true;
            }

            // --------------------------------------------------------------------------

            // Only run if MFA has been passed and there's POST data
            if ($bMfaValid && $oInput->post()) {

                // Validate data
                /** @var FormValidation $oFormValidation */
                $oFormValidation = Factory::service('FormValidation');

                /**
                 * Define rules - I know it's not usual to give fields names, but in this case
                 * it allows the matches message to have more context (a name, rather than a
                 * field name)
                 */
                $oFormValidation->set_rules('new_password', 'Password', 'required|matches[confirm_pass]');
                $oFormValidation->set_rules('confirm_pass', 'Confirm Password', 'required');

                // --------------------------------------------------------------------------

                //  Set custom messages
                $oFormValidation->set_message('required', lang('fv_required'));
                $oFormValidation->set_message('matches', lang('fv_matches'));

                // --------------------------------------------------------------------------

                //  Run validation
                if ($oFormValidation->run()) {

                    //  Validated, update user and login.
                    $aData     = [
                        'forgotten_password_code' => null,
                        'temp_pw'                 => false,
                        'password'                => $oInput->post('new_password'),
                    ];
                    $bRemember = (bool) $oInput->get('remember');

                    //  Reset the password
                    if ($oUserModel->update($oUser->id, $aData)) {

                        //  Log the user in
                        switch (APP_NATIVE_LOGIN_USING) {

                            case 'EMAIL':
                                $oLoginUser = $oAuthModel->login(
                                    $oUser->email,
                                    $oInput->post('new_password'),
                                    $bRemember
                                );
                                break;

                            case 'USERNAME':
                                $oLoginUser = $oAuthModel->login(
                                    $oUser->username,
                                    $oInput->post('new_password'),
                                    $bRemember
                                );
                                break;

                            default:
                                $oLoginUser = $oAuthModel->login(
                                    $oUser->email,
                                    $oInput->post('new_password'),
                                    $bRemember
                                );
                                break;
                        }

                        if ($oLoginUser) {

                            //  Say hello
                            if ($oLoginUser->last_login) {

                                if ($oConfig->item('authShowNicetimeOnLogin')) {
                                    $sLastLogin = niceTime(strtotime($oLoginUser->last_login));
                                } else {
                                    $sLastLogin = toUserDatetime($oLoginUser->last_login);
                                }

                                if ($oConfig->item('authShowLastIpOnLogin')) {

                                    $sStatus  = 'positive';
                                    $sMessage = lang(
                                        'auth_login_ok_welcome_with_ip',
                                        [
                                            $oLoginUser->first_name,
                                            $sLastLogin,
                                            $oLoginUser->last_ip,
                                        ]
                                    );

                                } else {

                                    $sStatus  = 'positive';
                                    $sMessage = lang(
                                        'auth_login_ok_welcome',
                                        [
                                            $oLoginUser->first_name,
                                            $sLastLogin,
                                        ]
                                    );
                                }

                            } else {

                                $sStatus  = 'positive';
                                $sMessage = lang(
                                    'auth_login_ok_welcome_notime',
                                    [
                                        $oLoginUser->first_name,
                                    ]
                                );
                            }

                            /** @var Session $oSession */
                            $oSession = Factory::service('Session', Constants::MODULE_SLUG);
                            $oSession->setFlashData($sStatus, $sMessage);

                            //  If MFA is setup then we'll need to set the user's session data
                            if ($oConfig->item('authTwoFactorMode')) {
                                $oUserModel->setLoginData($oUser->id);
                            }

                            //  Log user in and forward to wherever they need to go
                            if ($oInput->get('return_to')) {
                                redirect($oInput->get('return_to'));
                            } elseif ($oUser->group_homepage) {
                                redirect($oUser->group_homepage);
                            } else {
                                redirect('/');
                            }

                        } else {
                            $this->data['error'] = lang('auth_forgot_reset_badlogin', siteUrl('auth/login'));
                        }

                    } else {
                        $this->data['error'] = lang('auth_forgot_reset_badupdate', $oUserModel->lastError());
                    }

                } else {
                    $this->data['error'] = lang('fv_there_were_errors');
                }
            }

            // --------------------------------------------------------------------------

            //  Set data
            $this->data['page']->title   = lang('auth_title_reset');
            $this->data['auth']          = (object) [
                'id'   => $oUser->id,
                'hash' => $sHash,
            ];
            $this->data['passwordRules'] = $oUserPasswordModel->getRulesAsString($oUser->group_id);
            $this->data['return_to']     = $oInput->get('return_to');
            $this->data['remember']      = $oInput->get('remember');

            if (empty($this->data['message'])) {

                switch ($oInput->get('reason')) {

                    case 'EXPIRED':
                        $this->data['message'] = lang(
                            'auth_login_pw_expired',
                            $oUserPasswordModel->expiresAfter($oUser->group_id)
                        );
                        break;

                    case 'TEMP':
                    default:
                        $this->data['message'] = lang('auth_login_pw_temp');
                        break;
                }
            }

            // --------------------------------------------------------------------------

            //  Load the views
            $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/change_temp.php');
            Factory::service('View')
                ->load([
                    'structure/header/blank',
                    'auth/password/change_temp',
                    'structure/footer/blank',
                ]);

            return;
        }

        // --------------------------------------------------------------------------

        show404();
    }

    // --------------------------------------------------------------------------

    /**
     * Route requests to the right method
     *
     * @param string $iUserId The ID of the user to reset, as per the URL
     *
     * @return  void
     **/
    public function _remap($iUserId)
    {
        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');
        $this->validate($iUserId, $oUri->rsegment(3));
    }
}
