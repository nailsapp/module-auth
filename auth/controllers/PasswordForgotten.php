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

use Nails\Auth\Constants;
use Nails\Auth\Controller\Base;
use Nails\Auth\Model\User;
use Nails\Auth\Model\User\Password;
use Nails\Auth\Service\Authentication;
use Nails\Auth\Service\Session;
use Nails\Common\Exception\NailsException;
use Nails\Common\Exception\ValidationException;
use Nails\Common\Service\Config;
use Nails\Common\Service\FormValidation;
use Nails\Common\Service\Input;
use Nails\Common\Service\Uri;
use Nails\Email;
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
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var FormValidation $oFormValidation */
        $oFormValidation = Factory::service('FormValidation');
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');
        /** @var Session $oSession */
        $oSession = Factory::service('Session', Constants::MODULE_SLUG);
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);
        /** @var User $oUserModel */
        $oUserModel = Factory::model('User', Constants::MODULE_SLUG);
        /** @var Emailer $oEmailer */
        $oEmailer = Factory::service('Emailer', Email\Constants::MODULE_SLUG);

        // --------------------------------------------------------------------------

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

                $oFormValidation
                    ->buildValidator([
                        'identifier' => array_filter([
                            APP_NATIVE_LOGIN_USING === 'EMAIL' ? ['required', 'valid_email'] : null,
                            APP_NATIVE_LOGIN_USING === 'USERNAME' ? ['required'] : null,
                            APP_NATIVE_LOGIN_USING === 'BOTH' ? ['required'] : null,
                        ])[0],
                    ])
                    ->run();

                // --------------------------------------------------------------------------

                /** @var \Nails\Auth\Resource\User $oUser */
                $oUser          = $oUserModel->getByIdentifier($sIdentifier);
                $bAlwaysSucceed = $oConfig->item('authForgottenPassAlwaysSucceed');

                //  Attempt to reset password
                if ($oUserPasswordModel->setToken($oUser)) {

                    //  Refresh the User object
                    $oUser = $oUserModel->getById($oUser->id);

                    if (!$bAlwaysSucceed && empty($oUser->email)) {

                        throw new NailsException(
                            lang('auth_forgot_email_fail_no_email')
                        );

                    } elseif (!$bAlwaysSucceed && empty($oUser)) {

                        throw new NailsException(
                            lang('auth_forgot_email_fail_no_id')
                        );

                    } elseif ($bAlwaysSucceed) {

                        //  Failed, but configured to always succeed - nothing to do

                    } else {

                        //  We've got something, go go go
                        $oEmail = (object) [
                            'type'  => 'forgotten_password',
                            'to_id' => $oUser->id,
                        ];

                        // --------------------------------------------------------------------------

                        //  Add data for the email view
                        [$sTTL, $sKey] = array_pad(explode(':', $oUser->forgotten_password_code), 2, null);

                        $oEmail->data = (object) [
                            'resetUrl'   => siteUrl('auth/password/forgotten/' . $sKey),
                            'identifier' => $oUser->email,
                        ];

                        // --------------------------------------------------------------------------

                        if (!$oEmailer->send($oEmail, true)) {
                            if (!$bAlwaysSucceed) {
                                throw new NailsException(lang('auth_forgot_email_fail'));
                            }
                        }
                    }

                } else {

                    switch (APP_NATIVE_LOGIN_USING) {

                        case 'EMAIL':
                            $sError = lang('auth_forgot_code_not_set_email', $oUser->email);
                            break;

                        case 'USERNAME':
                            $sError = lang('auth_forgot_code_not_set_username', $oUser->username);
                            break;

                        default:
                            $sError = lang('auth_forgot_code_not_set');
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
     * @param string $sCode The code to validate
     *
     * @return  void
     */
    public function _validate($sCode)
    {
        /** @var Input $oInput */
        $oInput = Factory::service('Input');
        /** @var Session $oSession */
        $oSession = Factory::service('Session', Constants::MODULE_SLUG);
        /** @var Config $oConfig */
        $oConfig = Factory::service('Config');
        /** @var Authentication $oAuthService */
        $oAuthService = Factory::service('Authentication', Constants::MODULE_SLUG);
        /** @var Password $oUserPasswordModel */
        $oUserPasswordModel = Factory::model('UserPassword', Constants::MODULE_SLUG);

        /**
         * Attempt to verify code, if two factor auth is enabled then don't generate a
         * new password, we'll need the user to jump through some hoops first.
         */
        $bGenerateNewPw = !$oConfig->item('authTwoFactorMode');
        $mNewPassword   = $oUserPasswordModel->validateToken($sCode, $bGenerateNewPw);

        // --------------------------------------------------------------------------

        //  Determine outcome of validation
        if ($mNewPassword === 'EXPIRED') {

            //  Code has expired
            $this->data['error'] = lang('auth_forgot_expired_code');

        } elseif ($mNewPassword === false) {

            //  Code was invalid
            $this->data['error'] = lang('auth_forgot_invalid_code');

        } else {

            if ($oConfig->item('authTwoFactorMode') == 'QUESTION') {

                //  Show them a security question
                $this->data['question'] = $oAuthService->mfaQuestionGet($mNewPassword['user_id']);

                if ($this->data['question']) {

                    if ($oInput->post()) {

                        $bIsValid = $oAuthService->mfaQuestionValidate(
                            $this->data['question']->id,
                            $mNewPassword['user_id'],
                            $oInput->post('answer')
                        );

                        if ($bIsValid) {

                            //  Correct answer, reset password and render views
                            $mNewPassword = $oUserPasswordModel->validateToken($sCode, true);

                            //  @todo (Pablo - 2019-07-17) - Do failures need handled here?

                            // --------------------------------------------------------------------------

                            //  Set some flashdata for the login page when they go to it; just a little reminder
                            $sStatus  = 'notice';
                            $sMessage = lang('auth_forgot_reminder', htmlentities($mNewPassword['password']));
                            $oSession->setFlashData($sStatus, $sMessage);

                            // --------------------------------------------------------------------------

                            //  Load the views
                            $this->loadStyles(
                                NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php'
                            );

                            Factory::service('View')
                                ->setData([
                                    'new_password' => $mNewPassword['password'],
                                    'user'         => (object) [
                                        'id'       => $mNewPassword['user_id'],
                                        'identity' => $mNewPassword['user_identity'],
                                    ],
                                ])
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
                    $mNewPassword = $oUserPasswordModel->validateToken($sCode, true);

                    //  @todo (Pablo - 2019-07-17) - Do failures need handled here?

                    // --------------------------------------------------------------------------

                    //  Set some flashdata for the login page when they go to it; just a little reminder
                    $sStatus  = 'notice';
                    $sMessage = lang('auth_forgot_reminder', htmlentities($mNewPassword['password']));

                    $oSession->setFlashData($sStatus, $sMessage);

                    // --------------------------------------------------------------------------

                    //  Load the views
                    $this->loadStyles(
                        NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php'
                    );

                    Factory::service('View')
                        ->setData([
                            'new_password' => $mNewPassword['password'],
                            'user'         => (object) [
                                'id'       => $mNewPassword['user_id'],
                                'identity' => $mNewPassword['user_identity'],
                            ],
                        ])
                        ->load([
                            'structure/header/blank',
                            'auth/password/forgotten_reset',
                            'structure/footer/blank',
                        ]);
                }

            } elseif ($oConfig->item('authTwoFactorMode') == 'DEVICE') {

                $mSecret = $oAuthService->mfaDeviceSecretGet($mNewPassword['user_id']);

                if ($mSecret) {

                    if ($oInput->post()) {

                        $sMfaCode = $oInput->post('mfaCode');

                        //  Verify the inout
                        if ($oAuthService->mfaDeviceCodeValidate($mNewPassword['user_id'], $sMfaCode)) {

                            //  Correct answer, reset password and render views
                            $mNewPassword = $oUserPasswordModel->validateToken($sCode, true);

                            //  @todo (Pablo - 2019-07-17) - Do failures need handled here?

                            // --------------------------------------------------------------------------

                            //  Set some flashdata for the login page when they go to it; just a little reminder
                            $sStatus  = 'notice';
                            $sMessage = lang('auth_forgot_reminder', htmlentities($mNewPassword['password']));

                            $oSession->setFlashData($sStatus, $sMessage);

                            // --------------------------------------------------------------------------

                            //  Load the views
                            $this->loadStyles(
                                NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php'
                            );

                            Factory::service('View')
                                ->setData([
                                    'new_password' => $mNewPassword['password'],
                                    'user'         => (object) [
                                        'id'       => $mNewPassword['user_id'],
                                        'identity' => $mNewPassword['user_identity'],
                                    ],
                                ])
                                ->load([
                                    'structure/header/blank',
                                    'auth/password/forgotten_reset',
                                    'structure/footer/blank',
                                ]);
                            return;

                        } else {
                            $this->data['error'] = 'Sorry, that code failed to validate. Please try again. ';
                            $this->data['error'] .= $oAuthService->lastError();
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
                    $mNewPassword = $oUserPasswordModel->validateToken($sCode, true);

                    //  @todo (Pablo - 2019-07-17) - Do failures need handled here?

                    // --------------------------------------------------------------------------

                    //  Set some flashdata for the login page when they go to it; just a little reminder
                    $sStatus  = 'notice';
                    $sMessage = lang('auth_forgot_reminder', htmlentities($mNewPassword['password']));

                    $oSession->setFlashData($sStatus, $sMessage);

                    // --------------------------------------------------------------------------

                    //  Load the views
                    $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php');
                    Factory::service('View')
                        ->setData([
                            'new_password' => $mNewPassword['password'],
                            'user'         => (object) [
                                'id'       => $mNewPassword['user_id'],
                                'identity' => $mNewPassword['user_identity'],
                            ],
                        ])
                        ->load([
                            'structure/header/blank',
                            'auth/password/forgotten_reset',
                            'structure/footer/blank',
                        ]);
                }

            } else {

                //  Everything worked!
                //  Set some flashdata for the login page when they go to it; just a little reminder
                $sStatus  = 'notice';
                $sMessage = lang('auth_forgot_reminder', htmlentities($mNewPassword['password']));

                $oSession->setFlashData($sStatus, $sMessage);

                // --------------------------------------------------------------------------

                //  Load the views
                $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_reset.php');
                Factory::service('View')
                    ->setData([
                        'new_password' => $mNewPassword['password'],
                        'user'         => (object) [
                            'id'       => $mNewPassword['user_id'],
                            'identity' => $mNewPassword['user_identity'],
                        ],
                    ])
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
            $oSession = Factory::service('Session', Constants::MODULE_SLUG);
            $oSession->setFlashData('error', lang('auth_no_access_already_logged_in', activeUser('email')));
            redirect('/');
        }

        // --------------------------------------------------------------------------

        /** @var Uri $oUri */
        $oUri = Factory::service('Uri');

        if ($sMethod == 'index') {
            $this->index();
        } elseif ($oUri->segment(5) !== 'process') {

            //  @todo (Pablo - 2019-12-10) - Remove this once https://github.com/nails/module-auth/issues/36 is resolved
            $this->loadStyles(NAILS_APP_PATH . 'application/modules/auth/views/password/forgotten_interstitial.php');
            Factory::service('View')
                ->load([
                    'structure/header/blank',
                    'auth/password/forgotten_interstitial',
                    'structure/footer/blank',
                ]);
            return;
        } else {
            $this->_validate($sMethod);
        }
    }
}
