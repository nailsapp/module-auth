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
use Nails\Auth\Controller\Base;

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
        $config              = $oConfig->item('authTwoFactor');
        $this->authMfaConfig = $config[$this->authMfaMode];
    }

    // --------------------------------------------------------------------------

    protected function validateToken()
    {
        $this->returnTo = $this->input->get('return_to', true);
        $this->remember = $this->input->get('remember', true);
        $userId         = $this->uri->segment(3);
        $this->mfaUser  = $this->user_model->getById($userId);

        if (!$this->mfaUser) {

            $this->session->set_flashdata('error', lang('auth_twofactor_token_unverified'));

            if ($this->returnTo) {

                redirect('auth/login?return_to=' . $this->returnTo);

            } else {

                redirect('auth/login');
            }
        }

        $salt              = $this->uri->segment(4);
        $token             = $this->uri->segment(5);
        $ipAddress         = $this->input->ipAddress();
        $this->loginMethod = $this->uri->segment(6) ? $this->uri->segment(6) : 'native';

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

        if (!$this->auth_model->mfaTokenValidate($this->mfaUser->id, $salt, $token, $ipAddress)) {

            $this->session->set_flashdata('error', lang('auth_twofactor_token_unverified'));

            $query              = array();
            $query['return_to'] = $this->returnTo;
            $query['remember']  = $this->remember;

            $query = array_filter($query);

            if ($query) {

                $query = '?' . http_build_query($query);

            } else {

                $query = '';
            }

            redirect('auth/login' . $query);

        } else {

            //  Token is valid, generate a new one for the next request
            $this->data['token'] = $this->auth_model->mfaTokenGenerate($this->mfaUser->id);

            //  Set other data for the views
            $this->data['user_id']      = $this->mfaUser->id;
            $this->data['login_method'] = $this->loginMethod;
            $this->data['return_to']    = $this->returnTo;
            $this->data['remember']     = $this->remember;
        }
    }

    // --------------------------------------------------------------------------

    protected function loginUser()
    {
        //  Set login data for this user
        $this->user_model->setLoginData($this->mfaUser->id);

        //  If we're remembering this user set a cookie
        if ($this->remember) {

            $this->user_model->setRememberCookie(
                $this->mfaUser->id,
                $this->mfaUser->password,
                $this->mfaUser->email
            );
        }

        //  Update their last login and increment their login count
        $this->user_model->updateLastLogin($this->mfaUser->id);

        // --------------------------------------------------------------------------

        //  Generate an event for this log in
        create_event('did_log_in', array('method' => $this->loginMethod), $this->mfaUser->id);

        // --------------------------------------------------------------------------

        //  Say hello
        if ($this->mfaUser->last_login) {

            $oConfig = Factory::service('Config');

            if ($oConfig->item('authShowNicetimeOnLogin')) {

                $lastLogin = niceTime(strtotime($this->mfaUser->last_login));

            } else {

                $lastLogin = toUserDatetime($this->mfaUser->last_login);
            }

            if ($oConfig->item('authShowLastIpOnLogin')) {

                $status  = 'positive';
                $message = lang(
                    'auth_login_ok_welcome_with_ip',
                    array(
                        $this->mfaUser->first_name,
                        $lastLogin,
                        $this->mfaUser->last_ip
                    )
                );

            } else {

                $status  = 'positive';
                $message = lang(
                    'auth_login_ok_welcome',
                    array(
                        $this->mfaUser->first_name,
                        $lastLogin
                    )
                );
            }

        } else {

            $status  = 'positive';
            $message = lang(
                'auth_login_ok_welcome_notime',
                array(
                    $this->mfaUser->first_name
                )
            );
        }

        if (function_exists('cdnAvatar')) {

            $sAvatarUrl   = cdnAvatar($this->mfaUser->id, 100, 100);
            $sloginAvatar = '<img src="' . $sAvatarUrl . '" class="login-avatar">';

        } else {

            $sloginAvatar = '';
        }

        $this->session->set_flashdata($status, $sloginAvatar . $message);

        // --------------------------------------------------------------------------

        //  Delete the token we generated, its no needed, eh!
        $this->auth_model->mfaTokenDelete($this->data['token']['id']);

        // --------------------------------------------------------------------------

        $redirect = $this->returnTo != site_url() ? $this->returnTo : $this->mfaUser->group_homepage;

        redirect($redirect);
    }
}
