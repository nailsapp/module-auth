<?php

/**
 * This class provides some common functionality to the MFA controllers
 *
 * @package     Nails
 * @subpackage  module-auth
 * @category    Controller
 * @author      Nails Dev Team
 * @link
 */

namespace Nails\Auth\Controller;

use Nails\Factory;

class BaseMfa extends Base
{
    protected $authMfaMode;
    protected $authMfaConfig;
    protected $returnTo;
    protected $remember;
    protected $mfaUser;
    protected $loginMethod;

    // --------------------------------------------------------------------------

    /**
     * Construct the controller and set the Mfa Configs
     */
    public function __construct()
    {
        parent::__construct();

        $oConfig = Factory::service('Config');

        $this->authMfaMode   = $oConfig->item('authTwoFactorMode');
        $aConfig             = $oConfig->item('authTwoFactor');
        $this->authMfaConfig = $aConfig[$this->authMfaMode];
    }

    // --------------------------------------------------------------------------

    protected function validateToken()
    {
        $oSession   = Factory::service('Session', 'nailsapp/module-auth');
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $oInput     = Factory::service('Input');
        $oUri       = Factory::service('Uri');

        $this->returnTo = $oInput->get('return_to', true);
        $this->remember = $oInput->get('remember', true);
        $iUserId        = (int) $oUri->segment(4);
        $this->mfaUser  = $oUserModel->getById($iUserId);

        if (!$this->mfaUser) {
            $oSession->set_flashdata('error', lang('auth_twofactor_token_unverified'));
            if ($this->returnTo) {
                redirect('auth/login?return_to=' . $this->returnTo);
            } else {
                redirect('auth/login');
            }
        }

        $sSalt             = $oUri->segment(5);
        $sToken            = $oUri->segment(6);
        $sIpAddress        = $oInput->ipAddress();
        $this->loginMethod = $oUri->segment(7) ? $oUri->segment(7) : 'native';

        //  Safety first
        switch (strtolower($this->loginMethod)) {

            case 'facebook':
            case 'twitter':
            case 'linkedin':
            case 'native':
                //  All good, homies.
                break;

            default:
                $this->loginMethod = 'native';
                break;
        }

        $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
        if (!$oAuthModel->mfaTokenValidate($this->mfaUser->id, $sSalt, $sToken, $sIpAddress)) {

            $oSession->set_flashdata('error', lang('auth_twofactor_token_unverified'));

            $aQuery = [
                'return_to' => $this->returnTo,
                'remember'  => $this->remember,
            ];

            $aQuery = array_filter($aQuery);

            if ($aQuery) {
                $sQuery = '?' . http_build_query($aQuery);
            } else {
                $sQuery = '';
            }

            redirect('auth/login' . $sQuery);

        } else {

            //  Token is valid, generate a new one for the next request
            $this->data['token'] = $oAuthModel->mfaTokenGenerate($this->mfaUser->id);

            //  Set other data for the views
            $this->data['user_id']      = $this->mfaUser->id;
            $this->data['login_method'] = $this->loginMethod;
            $this->data['return_to']    = $this->returnTo;
            $this->data['remember']     = $this->remember;
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Logs a user In
     */
    protected function loginUser()
    {
        //  Set login data for this user
        $oUserModel = Factory::model('User', 'nailsapp/module-auth');
        $oUserModel->setLoginData($this->mfaUser->id);

        //  If we're remembering this user set a cookie
        if ($this->remember) {
            $oUserModel->setRememberCookie(
                $this->mfaUser->id,
                $this->mfaUser->password,
                $this->mfaUser->email
            );
        }

        //  Update their last login and increment their login count
        $oUserModel->updateLastLogin($this->mfaUser->id);

        // --------------------------------------------------------------------------

        //  Generate an event for this log in
        create_event('did_log_in', ['method' => $this->loginMethod], $this->mfaUser->id);

        // --------------------------------------------------------------------------

        //  Say hello
        if ($this->mfaUser->last_login) {

            $oConfig = Factory::service('Config');

            if ($oConfig->item('authShowNicetimeOnLogin')) {
                $sLastLogin = niceTime(strtotime($this->mfaUser->last_login));
            } else {
                $sLastLogin = toUserDatetime($this->mfaUser->last_login);
            }

            if ($oConfig->item('authShowLastIpOnLogin')) {

                $status  = 'positive';
                $message = lang(
                    'auth_login_ok_welcome_with_ip',
                    [
                        $this->mfaUser->first_name,
                        $sLastLogin,
                        $this->mfaUser->last_ip,
                    ]
                );

            } else {

                $status  = 'positive';
                $message = lang(
                    'auth_login_ok_welcome',
                    [
                        $this->mfaUser->first_name,
                        $sLastLogin,
                    ]
                );
            }

        } else {

            $status  = 'positive';
            $message = lang(
                'auth_login_ok_welcome_notime',
                [
                    $this->mfaUser->first_name,
                ]
            );
        }

        if (function_exists('cdnAvatar')) {
            $sAvatarUrl   = cdnAvatar($this->mfaUser->id, 100, 100);
            $sLoginAvatar = '<img src="' . $sAvatarUrl . '" class="login-avatar">';
        } else {
            $sLoginAvatar = '';
        }

        $oSession = Factory::service('Session', 'nailsapp/module-auth');
        $oSession->set_flashdata($status, $sLoginAvatar . $message);

        // --------------------------------------------------------------------------

        //  Delete the token we generated, it's no needed, eh!
        $oAuthModel = Factory::model('Auth', 'nailsapp/module-auth');
        $oAuthModel->mfaTokenDelete($this->data['token']['id']);

        // --------------------------------------------------------------------------

        $sRedirectUrl = $this->returnTo != site_url() ? $this->returnTo : $this->mfaUser->group_homepage;
        redirect($sRedirectUrl);
    }
}
